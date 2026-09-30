#!/usr/bin/env bash
set -euo pipefail
umask 022

SHA="${1:?Commit SHA required}"
ASSET_SHA="${2:?Asset checksum required}"
[[ "$SHA" =~ ^[0-9a-f]{40}$ ]]
[[ "$ASSET_SHA" =~ ^[0-9a-f]{64}$ ]]

PHP=/opt/cpanel/ea-php84/root/usr/bin/php
COMPOSER="$HOME/bin/composer.phar"
WEB="$HOME/public_html/staging.togetherkamera.com"
FOUNDATION="$HOME/together-rental-release-c077adb"
STATE="$HOME/together-rental-staging-automation"
RELEASE="$HOME/together-rental-release-${SHA:0:12}"
ASSETS="$HOME/tr-staging-incoming/$SHA.tar.gz"
LOCK="$STATE/deploy.lock"
INDEX_TMP="$WEB/.index-$SHA.tmp"
CUTOVER=0
BACKUP=''

mkdir -p "$STATE"
if ! mkdir "$LOCK" 2>/dev/null; then
    echo 'Another staging deployment is active (or deploy.lock needs inspection).' >&2
    exit 1
fi
cleanup() {
    result=$?
    if [ "$result" -ne 0 ] && [ "$CUTOVER" -eq 1 ]; then
        cp -p -- "$BACKUP" "$INDEX_TMP"
        mv -f -- "$INDEX_TMP" "$WEB/index.php"
        echo "WEBROOT ROLLBACK: $BACKUP" >&2
    fi
    rm -f -- "$INDEX_TMP"
    rmdir "$LOCK"
    exit "$result"
}
trap cleanup EXIT

test -x "$PHP"
test -s "$COMPOSER"
test -s "$FOUNDATION/.env"
test -d "$FOUNDATION/storage/app/public"
test -s "$WEB/index.php"
test -s "$WEB/.htaccess"
test -L "$WEB/storage"
test "$(readlink -f "$WEB/storage")" = "$(readlink -f "$FOUNDATION/storage/app/public")"
test -s "$ASSETS"
printf '%s  %s\n' "$ASSET_SHA" "$ASSETS" | sha256sum -c -
grep -qx 'APP_ENV=production' "$FOUNDATION/.env"
grep -qx 'APP_DEBUG=false' "$FOUNDATION/.env"
grep -qx 'APP_URL=https://staging.togetherkamera.com' "$FOUNDATION/.env"
grep -qx 'DB_DATABASE=togr7678_tr_stg' "$FOUNDATION/.env"
grep -qx 'DB_USERNAME=togr7678_tr_stg' "$FOUNDATION/.env"

CURRENT_SHA=c077adb3f1ea2778e72ac8e72edecdf32ed09012
if [ -s "$STATE/current_sha" ]; then
    CURRENT_SHA=$(cat "$STATE/current_sha")
    [[ "$CURRENT_SHA" =~ ^[0-9a-f]{40}$ ]]
    test "$(grep -F "together-rental-release-${CURRENT_SHA:0:12}" "$WEB/index.php" | wc -l)" -gt 0
else
    grep -Fq "$FOUNDATION" "$WEB/index.php"
fi

test ! -e "$RELEASE"
GIT_TERMINAL_PROMPT=0 git clone https://github.com/athallahnz/together-rental.git "$RELEASE"
git -C "$RELEASE" checkout --detach "$SHA"
test "$(git -C "$RELEASE" rev-parse HEAD)" = "$SHA"

# Source-only deployments are automatic. Schema changes get a separate,
# reviewed staging migration and database backup before this deploy resumes.
CHANGED_MIGRATIONS=$(git -C "$RELEASE" diff --name-only "$CURRENT_SHA" "$SHA" -- database/migrations)
if [ -n "$CHANGED_MIGRATIONS" ]; then
    printf 'PENDING MIGRATION; staging unchanged:\n%s\n' "$CHANGED_MIGRATIONS" >&2
    exit 1
fi

mv -- "$RELEASE/storage" "$RELEASE/storage.source-template"
ln -s "$FOUNDATION/storage" "$RELEASE/storage"
ln -s "$FOUNDATION/.env" "$RELEASE/.env"
cd "$RELEASE"
"$PHP" "$COMPOSER" install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress --no-scripts
"$PHP" artisan package:discover --ansi
"$PHP" "$COMPOSER" check-platform-reqs --no-dev

tar -xzf "$ASSETS" -C "$RELEASE/public"
test -s "$RELEASE/public/build/manifest.json"
"$PHP" -r '
    $p = $argv[1];
    $manifest = json_decode(file_get_contents($p."/manifest.json"), true, 512, JSON_THROW_ON_ERROR);
    foreach ($manifest as $entry) {
        $files = array_merge(isset($entry["file"]) ? [$entry["file"]] : [], $entry["css"] ?? [], $entry["assets"] ?? []);
        foreach ($files as $file) {
            if (!is_file($p."/".$file)) { throw new RuntimeException("Missing asset: ".$file); }
        }
    }
    echo "MANIFEST PASS: ".count($manifest)." entries\n";
' "$RELEASE/public/build"

"$PHP" artisan config:cache
"$PHP" -l "$RELEASE/public/index.php"
mkdir -p "$WEB/build"
cp -a "$RELEASE/public/build/." "$WEB/build/"

# Keep cPanel .htaccess and existing storage symlink. Retain older hashed assets
# so pages opened just before cutover can still load their JS and CSS.
for item in "$RELEASE"/public/* "$RELEASE"/public/.[!.]* "$RELEASE"/public/..?*; do
    [ -e "$item" ] || [ -L "$item" ] || continue
    name=${item##*/}
    case "$name" in
        index.php|.htaccess|build|storage|hot) continue ;;
    esac
    cp -a -- "$item" "$WEB/$name"
done

BACKUP="$STATE/index-before-${SHA:0:12}-$(date +%Y%m%d%H%M%S).php"
cp -p -- "$WEB/index.php" "$BACKUP"
cat > "$INDEX_TMP" <<'PHP_INDEX'
<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));
$root = STAGING_APP_ROOT;
if (file_exists($maintenance = $root.'/storage/framework/maintenance.php')) {
    require $maintenance;
}
require $root.'/vendor/autoload.php';
/** @var Application $app */
$app = require_once $root.'/bootstrap/app.php';
$app->handleRequest(Request::capture());
PHP_INDEX
"$PHP" -r '$p=$argv[1]; $s=file_get_contents($p); $s=str_replace("STAGING_APP_ROOT", var_export($argv[2], true), $s); file_put_contents($p,$s);' "$INDEX_TMP" "$RELEASE"
"$PHP" -l "$INDEX_TMP"
mv -f -- "$INDEX_TMP" "$WEB/index.php"
CUTOVER=1

status=$(curl -sS -L --max-time 30 -o /dev/null -w '%{http_code}' \
    "https://staging.togetherkamera.com/?staging_release=$SHA")
if [ "$status" != 200 ]; then
    echo "Staging HTTP check failed: $status" >&2
    exit 1
fi

printf '%s\n' "$SHA" > "$STATE/current_sha.tmp"
mv -f -- "$STATE/current_sha.tmp" "$STATE/current_sha"
CUTOVER=0
echo "STAGING DEPLOY PASS: $SHA; HTTP $status; BACKUP $BACKUP"
rm -f -- "$ASSETS"
