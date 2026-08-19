#!/bin/sh
set -e

# Fail loudly and early on the one thing that is easy to forget. Without an
# APP_KEY Laravel throws on the first encrypted cookie, which reads as a broken
# deploy rather than a missing secret.
if [ -z "$APP_KEY" ]; then
    echo "APP_KEY is not set. Run: fly secrets set APP_KEY=\"\$(php artisan key:generate --show)\"" >&2
    exit 1
fi

# SQLite on the mounted volume, so the catalogue and anything corrected in the
# admin panel survive a redeploy. Everything else here is disposable.
DB=/data/database.sqlite
[ -f "$DB" ] || touch "$DB"

# The storefront serves photographs from storage/app/public through a symlink.
# Both live in the image rather than the volume — they are build artefacts, not
# state — so the link is remade on every boot.
php artisan storage:link --force >/dev/null 2>&1 || true

php artisan migrate --force --no-interaction

# Create-only, so a price corrected in the admin panel survives a redeploy.
php artisan db:seed --class=DemoCatalogueSeeder --force --no-interaction

php artisan config:cache
php artisan route:cache
php artisan view:cache

# Last, not first: every command above runs as root and leaves root-owned
# caches, the SQLite file and its -wal/-shm siblings behind. php-fpm runs as
# www-data, so without this the first request fails trying to write a session.
chown -R www-data:www-data /data storage bootstrap/cache

exec "$@"
