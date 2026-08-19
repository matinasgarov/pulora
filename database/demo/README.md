# Demo fixtures

The 64 product photographs the storefront serves, already normalized — square
canvas, product sized by area, sweep tinted to the tile colour.

They are committed because the 8.4MB of finished images is what a deployment
needs, and the 60MB of originals they are made from is deliberately not in git
(see /walletImages in .gitignore). Without these a fresh clone has 22 products
and no photographs.

Regenerate them by restoring `walletImages/` and running:

    php artisan db:seed --class=WalletImagesSeeder
    cp storage/app/public/card-holders/*.jpg database/demo/card-holders/

DemoCatalogueSeeder copies them into storage and builds the catalogue around
them, so a deploy needs neither the originals nor GD.
