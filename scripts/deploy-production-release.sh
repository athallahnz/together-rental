#!/usr/bin/env bash
set -euo pipefail
umask 022

SHA=${1:?Full release SHA required}
MODE=${2:?Mode prepare, check, apply, or rollback required}
EXPECTED_DB=${3:?Production database name required}
BACKUP_ID=${4:-}
[[ "$SHA" =~ ^[0-9a-f]{40}$ ]]
[[ "$EXPECTED_DB" =~ ^[A-Za-z0-9_]+$ ]]
case "$MODE" in prepare|check|apply|rollback) ;; *) echo 'Invalid mode' >&2; exit 1 ;; esac

PHP=/opt/cpanel/ea-php84/root/usr/bin/php
COMPOSER="$HOME/bin/composer.phar"
STAGING_STATE="$HOME/together-rental-staging-automation"
STAGING_RELEASE="$HOME/together-rental-release-${SHA:0:12}"
STAGING_FOUNDATION="$HOME/together-rental-release-c077adb"
ROOT="$HOME/together-rental-production"
SHARED="$ROOT/shared"
STATE="$ROOT/state"
RELEASE="$ROOT/releases/${SHA:0:12}"
WEB="$HOME/public_html"
MARKER='# Together Rental production front controller'
LOCK="$STATE/deploy.lock"
CUTOVER=0
BACKUP=''

die() { echo "STOP: $*" >&2; exit 1; }

restore_webroot() {
    local saved=$1
    if [ -f "$saved/index.php" ]; then
        cp -p -- "$saved/index.php" "$WEB/index.php"
    else
        rm -f -- "$WEB/index.php"
    fi
    if [ -f "$saved/index.html" ]; then
        cp -p -- "$saved/index.html" "$WEB/index.html"
    else
        rm -f -- "$WEB/index.html"
    fi
    if [ -f "$saved/.htaccess" ]; then
        cp -p -- "$saved/.htaccess" "$WEB/.htaccess"
    else
        rm -f -- "$WEB/.htaccess"
    fi
    if [ -f "$saved/manifest.json" ]; then
        mkdir -p "$WEB/build"
        cp -p -- "$saved/manifest.json" "$WEB/build/manifest.json"
    else
        rm -f -- "$WEB/build/manifest.json"
    fi
    if [ -f "$saved/fonts-manifest.json" ]; then
        cp -p -- "$saved/fonts-manifest.json" "$WEB/build/fonts-manifest.json"
    else
        rm -f -- "$WEB/build/fonts-manifest.json"
    fi
}

cleanup() {
    local result=$?
    trap - EXIT
    if [ "$result" -ne 0 ] && [ "$CUTOVER" -eq 1 ]; then
        restore_webroot "$BACKUP"
        echo "WEBROOT ROLLBACK: $BACKUP" >&2
    fi
    if [ -d "$LOCK" ]; then rmdir "$LOCK"; fi
    exit "$result"
}

preflight_common() {
    test -x "$PHP" || die 'PHP 8.4 CLI unavailable'
    test -s "$COMPOSER" || die 'Composer unavailable'
    test -d "$WEB/staging.togetherkamera.com" || die 'Staging document root missing'
    test -s "$WEB/staging.togetherkamera.com/index.php" || die 'Staging index missing'
    test ! -e "$WEB/.env" || die '.env must stay outside public_html'
    test ! -L "$WEB" || die 'Inspect symlinked public_html manually'
    test -s "$STAGING_STATE/current_sha" || die 'No successful staging release recorded'
    test "$(cat "$STAGING_STATE/current_sha")" = "$SHA" || die 'Requested SHA is not the current staging release'
    test "$(git -C "$STAGING_RELEASE" rev-parse HEAD)" = "$SHA" || die 'Staging source SHA mismatch'
    test -s "$STAGING_RELEASE/public/build/manifest.json" || die 'Staging assets missing'
    test -s "$SHARED/.env" || die 'Create a separate production .env first'
    test ! -L "$SHARED/.env" || die 'Production .env must be an independent file'
    test -d "$SHARED/storage" || die 'Create separate production shared storage first'
    test -d "$SHARED/storage/app/public" || die 'Production public storage missing'
    test ! -L "$SHARED/storage/app/public" || die 'Production public storage must not be a symlink'
    test "$(readlink -f "$SHARED/storage")" != "$(readlink -f "$STAGING_FOUNDATION/storage")" || die 'Storage points at staging'
    test "$(readlink -f "$SHARED/storage/app/public")" != "$(readlink -f "$STAGING_FOUNDATION/storage/app/public")" || die 'Public storage points at staging'
    grep -qx 'APP_ENV=production' "$SHARED/.env" || die 'APP_ENV must be production'
    grep -qx 'APP_DEBUG=false' "$SHARED/.env" || die 'Disable APP_DEBUG'
    grep -qx 'APP_URL=https://togetherkamera.com' "$SHARED/.env" || die 'Production APP_URL mismatch'
    grep -qx 'DB_CONNECTION=mysql' "$SHARED/.env" || die 'Production DB_CONNECTION must be mysql'
    grep -qx "DB_DATABASE=$EXPECTED_DB" "$SHARED/.env" || die 'Production DB name mismatch'
    grep -q '^APP_KEY=base64:' "$SHARED/.env" || die 'Generate an independent production APP_KEY'
    "$PHP" -r '
        $s=file_get_contents($argv[1]);
        if(!preg_match("/^APP_KEY=base64:([^\r\n]+)$/m",$s,$m) || strlen(base64_decode($m[1],true)?:"")!==32){
            throw new RuntimeException("Production APP_KEY must contain 32 random bytes");
        }
    ' "$SHARED/.env"
    test "$EXPECTED_DB" != togr7678_tr_stg || die 'Staging database name is forbidden'
    test "$(grep '^APP_KEY=' "$SHARED/.env")" != "$(grep '^APP_KEY=' "$STAGING_FOUNDATION/.env")" || die 'APP_KEY is shared with staging'
}

validate_release() {
    test "$(git -C "$RELEASE" rev-parse HEAD)" = "$SHA" || die 'Production source SHA mismatch'
    test "$(readlink -f "$RELEASE/.env")" = "$(readlink -f "$SHARED/.env")" || die 'Production .env link mismatch'
    test "$(readlink -f "$RELEASE/storage")" = "$(readlink -f "$SHARED/storage")" || die 'Production storage link mismatch'
    test -s "$RELEASE/vendor/autoload.php" || die 'Production vendor missing'
    test ! -e "$RELEASE/public/hot" || die 'Vite hot file forbidden'
    cmp -s -- "$STAGING_RELEASE/public/build/manifest.json" "$RELEASE/public/build/manifest.json" || die 'Production asset manifest differs from staging'
    "$PHP" -r '
        $p=$argv[1]; $m=json_decode(file_get_contents($p."/manifest.json"),true,512,JSON_THROW_ON_ERROR);
        foreach($m as $entry){foreach(array_merge(isset($entry["file"])?[$entry["file"]]:[],$entry["css"]??[],$entry["assets"]??[]) as $file){
            if(!is_file($p."/".$file)){throw new RuntimeException("Missing asset: ".$file);}
        }} echo "MANIFEST PASS: ".count($m)." entries\n";
    ' "$RELEASE/public/build"
}

check_database() {
    "$PHP" -r '
        require $argv[1]."/vendor/autoload.php";
        $app=require $argv[1]."/bootstrap/app.php";
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $db=Illuminate\Support\Facades\DB::connection();
        $actual=$db->selectOne("SELECT DATABASE() AS name")->name;
        if($actual!==$argv[2] || $actual==="togr7678_tr_stg") throw new RuntimeException("Wrong production DB");
        if(config("app.url")!=="https://togetherkamera.com" || config("app.debug")) throw new RuntimeException("Wrong production app config");
        $schema=Illuminate\Support\Facades\Schema::connection($db->getName());
        if(!$schema->hasTable("migrations") || !$schema->hasTable("users")) throw new RuntimeException("Production database is not migrated");
        $ran=$db->table("migrations")->pluck("migration")->all();
        $files=array_map(fn($p)=>basename($p,".php"),glob($argv[1]."/database/migrations/*.php"));
        $pending=array_diff($files,$ran);
        if($pending) throw new RuntimeException("Pending production migrations: ".implode(", ",$pending));
        $admin=$db->table("users")->join("role_user","role_user.user_id","=","users.id")
            ->join("roles","roles.id","=","role_user.role_id")
            ->where("roles.slug","super-admin")->where("users.status","active")->exists();
        if(!$admin) throw new RuntimeException("No active production super-admin");
        echo "PRODUCTION DB PASS: ".$actual."; migrations and admin ready\n";
    ' "$RELEASE" "$EXPECTED_DB"
}

check_webroot() {
    test -d "$WEB" || die 'Production document root missing'
    test ! -e "$WEB/index.htm" || die 'Unexpected index.htm in production webroot'
    if [ -s "$STATE/current_sha" ]; then
        local old
        old=$(cat "$STATE/current_sha")
        [[ "$old" =~ ^[0-9a-f]{40}$ ]] || die 'Invalid production state SHA'
        test -s "$WEB/index.php" || die 'Production index.php missing'
        grep -Fq "$ROOT/releases/${old:0:12}" "$WEB/index.php" || die 'Production index.php differs from recorded release'
        test ! -e "$WEB/index.html" || die 'Unexpected index.html in production webroot'
        grep -Fqx "$MARKER" "$WEB/.htaccess" || die 'Production .htaccess marker missing'
    else
        test -s "$WEB/index.html" || die 'Placeholder index.html missing'
        test ! -e "$WEB/index.php" || die 'Unexpected index.php in production webroot'
        if [ -f "$WEB/.htaccess" ]; then
            ! grep -Eiq '^[[:space:]]*(RewriteEngine|RewriteRule|Redirect|DirectoryIndex|AuthType)' "$WEB/.htaccess" || die 'Existing .htaccess needs manual review'
        fi
    fi
    if [ -e "$WEB/storage" ] || [ -L "$WEB/storage" ]; then
        test -L "$WEB/storage" || die 'Existing production storage is not a symlink'
        test "$(readlink -f "$WEB/storage")" = "$(readlink -f "$SHARED/storage/app/public")" || die 'Production storage link points elsewhere'
    fi
}

if [ "$MODE" = rollback ]; then
    test -d "$STATE" || die 'Production state missing'
    if ! mkdir "$LOCK" 2>/dev/null; then die 'Another production deployment is active'; fi
    trap cleanup EXIT
    [[ "$BACKUP_ID" =~ ^backup-[0-9a-f]{12}-[0-9]{14}$ ]] || die 'Invalid backup ID'
    BACKUP="$STATE/$BACKUP_ID"
    test -s "$BACKUP/target_sha" || die 'Backup not found'
    test "$(cat "$BACKUP/target_sha")" = "$SHA" || die 'Backup target SHA mismatch'
    test "$(cat "$STATE/current_sha")" = "$SHA" || die 'Production has moved on; refuse rollback'
    grep -Fq "$RELEASE" "$WEB/index.php" || die 'Index has changed since deployment'
    test "$(sha256sum "$WEB/index.php" | cut -d ' ' -f1)" = "$(cat "$BACKUP/new_index_sha")" || die 'Production index was edited'
    test "$(sha256sum "$WEB/.htaccess" | cut -d ' ' -f1)" = "$(cat "$BACKUP/new_htaccess_sha")" || die 'Production .htaccess was edited'
    test "$(sha256sum "$WEB/build/manifest.json" | cut -d ' ' -f1)" = "$(cat "$BACKUP/new_manifest_sha")" || die 'Production manifest was edited'
    restore_webroot "$BACKUP"
    if [ -s "$BACKUP/previous_sha" ]; then
        cp -p -- "$BACKUP/previous_sha" "$STATE/current_sha"
    else
        rm -f -- "$STATE/current_sha"
    fi
    echo "PRODUCTION WEBROOT ROLLBACK PASS: $BACKUP_ID (database not rolled back)"
    exit 0
fi

preflight_common

if [ "$MODE" = prepare ]; then
    mkdir -p "$STATE" "$ROOT/releases" "$ROOT/bin"
    if ! mkdir "$LOCK" 2>/dev/null; then die 'Another production deployment is active'; fi
    trap cleanup EXIT
    if [ ! -e "$RELEASE" ]; then
        GIT_TERMINAL_PROMPT=0 git clone https://github.com/athallahnz/together-rental.git "$RELEASE"
        git -C "$RELEASE" checkout --detach "$SHA"
        mv -- "$RELEASE/storage" "$RELEASE/storage.source-template"
        ln -s "$SHARED/storage" "$RELEASE/storage"
        ln -s "$SHARED/.env" "$RELEASE/.env"
        (cd "$RELEASE" && "$PHP" "$COMPOSER" install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress --no-scripts && "$PHP" artisan package:discover --ansi && "$PHP" "$COMPOSER" check-platform-reqs --no-dev)
        cp -a -- "$STAGING_RELEASE/public/build" "$RELEASE/public/build"
        (cd "$RELEASE" && "$PHP" artisan config:cache)
    fi
    validate_release
    "$PHP" -l "$RELEASE/public/index.php"
    test -s "$(dirname "$0")/production-cron.sh" || die 'Production cron wrapper missing'
    install -m 0755 "$(dirname "$0")/production-cron.sh" "$ROOT/bin/production-cron.sh"
    echo "PRODUCTION PREPARE PASS: $SHA; release $RELEASE"
    exit 0
fi

validate_release
check_database
check_webroot
echo "PRODUCTION PRECHECK PASS: $SHA; DB $EXPECTED_DB"
if [ "$MODE" = check ]; then exit 0; fi
if [ -s "$STATE/current_sha" ] && [ "$(cat "$STATE/current_sha")" = "$SHA" ]; then
    echo "PRODUCTION ALREADY DEPLOYED: $SHA"
    exit 0
fi

mkdir -p "$STATE"
if ! mkdir "$LOCK" 2>/dev/null; then die 'Another production deployment is active'; fi
trap cleanup EXIT
BACKUP="$STATE/backup-${SHA:0:12}-$(date +%Y%m%d%H%M%S)"
mkdir -m 700 -- "$BACKUP"
printf '%s\n' "$SHA" > "$BACKUP/target_sha"
if [ -s "$STATE/current_sha" ]; then cp -p -- "$STATE/current_sha" "$BACKUP/previous_sha"; fi
for file in index.php index.html .htaccess; do
    if [ -f "$WEB/$file" ]; then cp -p -- "$WEB/$file" "$BACKUP/$file"; fi
done
if [ -f "$WEB/build/manifest.json" ]; then cp -p -- "$WEB/build/manifest.json" "$BACKUP/manifest.json"; fi
if [ -f "$WEB/build/fonts-manifest.json" ]; then cp -p -- "$WEB/build/fonts-manifest.json" "$BACKUP/fonts-manifest.json"; fi

HTACCESS_TMP="$WEB/.together-rental-htaccess-${SHA:0:12}.tmp"
INDEX_TMP="$WEB/.together-rental-index-${SHA:0:12}.tmp"
if [ -f "$WEB/.htaccess" ]; then
    sed "/^$MARKER$/,/\$/{ /^$MARKER$/d; d; }" "$WEB/.htaccess" > "$HTACCESS_TMP"
else
    : > "$HTACCESS_TMP"
fi
printf '\n%s\n' "$MARKER" >> "$HTACCESS_TMP"
cat "$RELEASE/public/.htaccess" >> "$HTACCESS_TMP"
cat > "$INDEX_TMP" <<'PHP_INDEX'
<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

// Together Rental production release
define('LARAVEL_START', microtime(true));
$root = PRODUCTION_APP_ROOT;
if (file_exists($maintenance = $root.'/storage/framework/maintenance.php')) {
    require $maintenance;
}
require $root.'/vendor/autoload.php';
/** @var Application $app */
$app = require_once $root.'/bootstrap/app.php';
$app->handleRequest(Request::capture());
PHP_INDEX
"$PHP" -r '$p=$argv[1];$s=file_get_contents($p);file_put_contents($p,str_replace("PRODUCTION_APP_ROOT",var_export($argv[2],true),$s));' "$INDEX_TMP" "$RELEASE"
"$PHP" -l "$INDEX_TMP"

mkdir -p "$WEB/build"
cp -a -- "$RELEASE/public/build/." "$WEB/build/"
for item in "$RELEASE"/public/* "$RELEASE"/public/.[!.]* "$RELEASE"/public/..?*; do
    [ -e "$item" ] || [ -L "$item" ] || continue
    name=${item##*/}
    case "$name" in index.php|index.html|.htaccess|build|storage|hot) continue ;; esac
    cp -a -- "$item" "$WEB/$name"
done
if [ ! -e "$WEB/storage" ] && [ ! -L "$WEB/storage" ]; then
    ln -s "$SHARED/storage/app/public" "$WEB/storage"
fi

CUTOVER=1
mv -f -- "$HTACCESS_TMP" "$WEB/.htaccess"
if [ -f "$WEB/index.html" ]; then mv -f -- "$WEB/index.html" "$BACKUP/index.html.removed"; fi
mv -f -- "$INDEX_TMP" "$WEB/index.php"
sha256sum "$WEB/index.php" | cut -d ' ' -f1 > "$BACKUP/new_index_sha"
sha256sum "$WEB/.htaccess" | cut -d ' ' -f1 > "$BACKUP/new_htaccess_sha"
sha256sum "$WEB/build/manifest.json" | cut -d ' ' -f1 > "$BACKUP/new_manifest_sha"

for path in /up/ /login; do
    path=${path%/}
    status=$(curl -sS --max-time 30 -o /dev/null -w '%{http_code}' "https://togetherkamera.com$path?production_release=$SHA")
    test "$status" = 200 || die "Production HTTP $path returned $status"
done
printf '%s\n' "$SHA" > "$STATE/current_sha.tmp"
mv -f -- "$STATE/current_sha.tmp" "$STATE/current_sha"
CUTOVER=0
echo "PRODUCTION DEPLOY PASS: $SHA; /up and /login HTTP 200; BACKUP_ID ${BACKUP##*/}"
