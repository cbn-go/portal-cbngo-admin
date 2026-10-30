#!/usr/bin/env bash
set -euo pipefail

APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
PHP_BIN="${PHP_BIN:-/opt/alt/php83/usr/bin/php}"

cd "$APP_DIR"

mkdir -p \
    resources/views \
    storage/app/public \
    storage/app/private \
    storage/framework/sessions \
    storage/framework/cache/data \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chmod -R ug+rwx storage bootstrap/cache || true

"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan db:seed --class=StatuteSeeder --force
"$PHP_BIN" artisan db:seed --class=InstitutionalSettingSeeder --force
"$PHP_BIN" artisan storage:link --force || true
if [[ -d "$APP_DIR/public_html" ]]; then
    ln -sfn "$APP_DIR/storage/app/public" "$APP_DIR/public_html/storage"
fi
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan icons:cache

if "$PHP_BIN" artisan list --raw 2>/dev/null | grep -q '^filament:cache-components'; then
    "$PHP_BIN" artisan filament:cache-components
fi
