# Deploying

The production host is Azerbaijani shared hosting: local disk persists, cron is
available, there are no long-running queue workers, and Node is not installed.

## Before every deploy commit

`/public/build` is **tracked**, because the host cannot run a build step. Compiled
assets must be regenerated and committed whenever CSS, JS, or Filament changes:

```bash
npm run build
git add public/build
git commit -m "chore: rebuild assets"
```

Forgetting this ships an unstyled panel.

## On the server

```bash
git pull
php artisan migrate --force
php artisan filament:assets
php artisan storage:link      # first deploy only
php artisan config:cache
php artisan route:cache
```

## Cron

There is no queue worker. Add one entry:

```
* * * * * cd /path/to/leather-shop && php artisan schedule:run >> /dev/null 2>&1
```

This runs `ReleaseExpiredReservations` and processes queued mail.

## Environment

`APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, the host's
MySQL credentials, and `SHOP_OPERATOR_EMAIL` set to a real inbox.

## First run

```bash
php artisan shop:make-admin
```

Then sign in at `/admin`.
