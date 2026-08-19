<?php // database/seeders/DemoCatalogueSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeds the catalogue on a machine that does not have the source photographs.
 *
 * A deployment gets `database/demo/card-holders` — the finished images, already
 * normalised and committed — rather than the 60MB of originals, which are
 * gitignored. So this is WalletImagesSeeder pointed at those, with normalising
 * switched off: running the normaliser over an already-normalised photograph
 * would crop it a second time and shrink the product in its frame.
 *
 * Everything else is identical, including create-only behaviour, so re-running
 * a deploy does not undo prices set in the admin panel.
 */
class DemoCatalogueSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DemoShopSeeder::class);

        (new WalletImagesSeeder(
            source: database_path('demo/card-holders'),
            normalize: false,
        ))->run();
    }
}
