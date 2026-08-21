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

# Migrating and seeding are deploy work, not boot work, but the entrypoint runs
# on every wake-up as well as every deploy — and the machine sleeps when idle,
# so 'every wake-up' is 'every visitor who arrives after a quiet spell'. Doing
# them unconditionally cost about four seconds each time to discover there was
# nothing to do, on top of the three for Blade. Overrunning matters more than
# the seconds suggest: Fly's proxy waits roughly eight seconds for the app to
# answer, then gives up and retries with a backoff, which turned a ten-second
# boot into a twenty-four-second one for whoever was waiting.
#
# So they run once per image instead. FLY_IMAGE_REF changes with every deploy;
# the stamp lives on the volume, beside the database it describes. If the
# variable is missing — running this image anywhere that is not Fly — the
# guard opens and both run, because being slow is better than being unmigrated.
STAMP=/data/.provisioned
CURRENT="${FLY_IMAGE_REF:-}"

if [ -z "$CURRENT" ] || [ "$(cat "$STAMP" 2>/dev/null)" != "$CURRENT" ]; then
    php artisan migrate --force --no-interaction

    # Create-only, so a price corrected in the admin panel survives a redeploy.
    php artisan db:seed --class=DemoCatalogueSeeder --force --no-interaction

    # Only stamp when there is something to stamp: off Fly the guard above is
    # always open, so every boot migrates and seeds.
    if [ -n "$CURRENT" ]; then
        printf '%s' "$CURRENT" > "$STAMP"
    fi
else
    echo "Already provisioned for this image; skipping migrate and seed."
fi

# config:cache and route:cache are deliberately NOT run.
#
# They cannot be built into the image the way view:cache can, because both
# bake environment values in and the build environment is not the runtime one.
# That left them here, where they cost about four seconds of every wake-up —
# two artisan bootstraps on a shared CPU — which was the largest remaining
# item once migrate, seed, storage:link and the recursive chown were dealt
# with, and the difference between a four-second cold start and an
# eight-second one.
#
# Without them Laravel parses config/ and routes/ per request instead. That is
# real but small — tens of milliseconds, against seconds of boot — and this is
# a preview that sleeps when idle, so almost every visitor pays the boot and
# very few make enough requests for the per-request cost to add up. Revisit
# when the shop opens and the machine stops sleeping: at that point the trade
# reverses, and the caches want to move onto the volume behind the same
# FLY_IMAGE_REF stamp used above rather than simply coming back here.
#
# One incidental benefit: with no config cache, env() works everywhere rather
# than silently returning null outside config files.
# Last, not first: the commands above run as root and leave root-owned files
# behind — the config and route caches, the SQLite file and its -wal/-shm
# siblings. php-fpm runs as www-data, so without this the first request fails
# trying to write a session.
#
# storage/ is deliberately not in this list. It is owned correctly in the image
# and nothing at runtime writes to it, but recursing over it meant walking the
# product photographs on every wake-up, which cost five seconds. These two are
# a handful of files each.
chown -R www-data:www-data /data bootstrap/cache

exec "$@"
