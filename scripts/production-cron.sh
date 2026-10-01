#!/usr/bin/env bash
set -euo pipefail

MODE=${1:?Use schedule or queue}
STATE="$HOME/together-rental-production/state"
PHP=/opt/cpanel/ea-php84/root/usr/bin/php
test -x "$PHP"
test -s "$STATE/current_sha"
SHA=$(cat "$STATE/current_sha")
[[ "$SHA" =~ ^[0-9a-f]{40}$ ]]
RELEASE="$HOME/together-rental-production/releases/${SHA:0:12}"
test "$(git -C "$RELEASE" rev-parse HEAD)" = "$SHA"

case "$MODE" in
    schedule)
        exec "$PHP" "$RELEASE/artisan" schedule:run --no-interaction
        ;;
    queue)
        command -v flock >/dev/null || { echo 'flock is required for queue cron' >&2; exit 1; }
        exec flock -n "$STATE/queue.lock" "$PHP" "$RELEASE/artisan" \
            queue:work database --stop-when-empty --tries=3 --timeout=180 --no-interaction
        ;;
    *) echo 'Use schedule or queue' >&2; exit 1 ;;
esac
