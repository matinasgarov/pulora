#!/bin/sh
set -e

# Fail loudly and early on the one thing that is easy to forget. Without an
# APP_KEY Laravel throws on the first encrypted cookie, which reads as a broken
# deploy rather than a missing secret.
#
# --no-ansi and the `tr` matter: `key:generate --show` colours its output, and
# on Windows the CLI ends the line with CR. Both ride along inside "$(...)",
# and the key then fails Encrypter's length check on the first request as a
# 500 with no mention of the key. Hence the shape check below as well.
if [ -z "$APP_KEY" ]; then
    echo "APP_KEY is not set. Run:" >&2
    echo "  fly secrets set APP_KEY=\"\$(php artisan key:generate --show --no-ansi | tr -d '\r')\"" >&2
    exit 1
fi

# A key that is present but malformed is worse than a missing one: every page
# 500s and the log blames the cipher. Laravel accepts a 32-byte key, raw or
# base64-encoded; anything else is a copy-paste accident.
case "$APP_KEY" in
    base64:*) KEY_BYTES=$(printf '%s' "${APP_KEY#base64:}" | base64 -d 2>/dev/null | wc -c) ;;
    *)        KEY_BYTES=$(printf '%s' "$APP_KEY" | wc -c) ;;
esac

if [ "$KEY_BYTES" -ne 32 ]; then
    echo "APP_KEY decodes to $KEY_BYTES bytes, not 32 — it is malformed." >&2
    echo "Colour codes or a stray carriage return are the usual cause. Regenerate with:" >&2
    echo "  fly secrets set APP_KEY=\"\$(php artisan key:generate --show --no-ansi | tr -d '\r')\"" >&2
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
