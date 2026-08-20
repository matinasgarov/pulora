# Deploying the public preview

The preview is a shop window: the catalogue, search, filters, pagination and
product pages all work; the bag and checkout are closed. It runs on Fly.io as a
single container with a small volume for the SQLite file.

## What makes this deployable at all

Three things that are easy to get wrong, and are already handled:

- **The Vite bundle is committed** (`public/build`), so the image needs no Node.
- **The product photographs are committed** as normalised fixtures in
  `database/demo/card-holders` — 8.4MB. The 60MB of originals in `walletImages/`
  are gitignored and are *not* needed to deploy. `DemoCatalogueSeeder` builds
  the catalogue from the fixtures without re-normalising them.
- **`database/database.sqlite` is never shipped.** It holds your operator's
  password hash and test orders. The container makes a fresh one on its volume.

## First deploy

```sh
fly launch --no-deploy          # accept the existing fly.toml
fly volumes create pulora_data --size 1 --region fra
fly secrets set APP_KEY="$(php artisan key:generate --show --no-ansi | tr -d '\r')"
fly deploy
```

`APP_KEY` is the one secret that must be set. The container refuses to start
without it and says so, rather than failing later on the first encrypted cookie.

`--no-ansi` and the `tr` are not decoration. `key:generate --show` colours its
output, and the PHP CLI on Windows ends the line with a carriage return; both
ride along inside `"$(...)"`. The key is then the right length plus four
invisible bytes, and every page 500s with *"Unsupported cipher or incorrect key
length"* — which says nothing about the key having been pasted wrong. The
entrypoint now checks that `APP_KEY` decodes to 32 bytes and refuses to boot
otherwise, so this fails at the deploy with an explanation instead.

## What the preview does and does not do

`PULORA_ORDERING_ENABLED=false` in `fly.toml` closes ordering. The bag and
checkout return 404 — unreachable, not merely unlinked, because the URLs are
guessable and the checkout resolves the payment gateway, which refuses to run
outside local/testing. Product pages say ordering opens soon.

To open the shop later: set it to `true`, and configure a real payment driver
first. `PAYMENT_DRIVER=mock` will throw in production by design.

**No admin account exists on the preview.** The seeders create products and
shipping, never users, so `/admin/login` is reachable but nothing can pass it.
That is deliberate for a public URL. To make yourself one later:

```sh
fly ssh console -C "php artisan tinker"
```

## Redeploying

`fly deploy` reruns the entrypoint: migrate, then seed create-only. Prices or
copy you corrected in the admin panel are **not** overwritten. To push the
values from `WalletCatalogue.php` over the top on purpose, set
`PULORA_RESEED_OVERWRITE=true` for one deploy and then remove it.

## Before this becomes the real shop

- `APP_DEBUG` is `false` in `fly.toml`; keep it that way. `.env.example` has it
  `true` because that is right for local development — never copy that file to
  a server.
- HTTPS and the Secure session cookie are already forced when `APP_ENV` is
  `production` (see `AppServiceProvider`). Fly terminates TLS and `force_https`
  redirects, so that half is covered too.
- The admin panel has no two-factor. Worth adding before it holds real orders.
- SQLite on one volume is fine for a preview and for a shop this size. It is a
  single point of failure with no backup: `fly ssh console -C "cat
  /data/database.sqlite"` is the poor man's backup until it matters enough for
  Postgres.
