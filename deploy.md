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
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan filament:assets
php artisan storage:link      # first deploy only
php artisan config:cache
php artisan route:cache
```

## Cron

There are no long-running queue workers. Add one cron entry:

```
* * * * * cd /path/to/leather-shop && php artisan schedule:run >> /dev/null 2>&1
```

`schedule:run` triggers everything registered in `routes/console.php` on this
single cron tick: `ReleaseExpiredReservations` every five minutes, and
`queue:work --stop-when-empty --max-time=50` every minute to drain queued mail
(order confirmations, shipment notifications, payment anomaly alerts) — there
is no persistent worker process, so without this scheduled drain, queued mail
just accumulates in the `jobs` table and is never sent.

## Environment

`APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, the host's
MySQL credentials, and `SHOP_OPERATOR_EMAIL` set to a real inbox.

`QUEUE_CONNECTION=database` — matches `config/queue.php`'s default; documented
here because the cron-driven `queue:work` above only works if mail is actually
queued to the database, not to `sync` or elsewhere.

`PAYMENT_DRIVER` — read by `App\Providers\AppServiceProvider`'s `PaymentGateway`
binding. As of this branch only a `mock` driver exists
(`App\Domain\Payment\MockGateway`), and the binding explicitly refuses to use
it outside `local`/`testing` environments. **There is currently no real
payment gateway wired up**, so this is not yet a complete go-live runbook for
taking real payments — a production `PAYMENT_DRIVER` needs a real gateway
implementation added before this checklist covers a live launch.

## First run

```bash
php artisan shop:make-admin
```

Then sign in at `/admin`.
