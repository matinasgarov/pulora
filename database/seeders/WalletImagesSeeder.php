<?php

namespace Database\Seeders;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductImage;
use App\Domain\Catalog\Models\Variant;
use App\Support\ProductImageNormalizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class WalletImagesSeeder extends Seeder
{
    /**
     * A product photograph is a prefix then a number: a1, k3, d_2.
     *
     * The prefix groups the angles of one product; the number orders them. An
     * underscore is part of the prefix rather than a separator, so `a1` (the
     * teal card case) and `a_1` (the walnut dopp kit) stay two products.
     *
     * Matching the scheme exactly is what makes the folder safe to keep other
     * files in — hero.png joined the H wallet once, and a stray
     * "ChatGPT Image….png" joined C, because grouping on the first letter
     * accepted anything. It also excludes duplicates like "h4 (2)", which are
     * the same shot twice.
     */
    public static function isProductPhoto(string $basename): bool
    {
        return preg_match('/^[a-z]_?[0-9]+$/', strtolower($basename)) === 1;
    }

    /** The grouping key: everything before the trailing number. */
    public static function prefix(string $basename): string
    {
        preg_match('/^([a-z]_?)[0-9]+$/', strtolower($basename), $m);

        return $m[1] ?? '';
    }

    /**
     * The source photographs are gitignored — 60MB of originals that git would
     * carry forever. Restore the folder from wherever it is backed up before
     * running this; with no folder present it returns without touching
     * anything, so a fresh clone seeds the rest of the database fine.
     */
    public function run(): void
    {
        $source = base_path('walletImages');
        $target = storage_path('app/public/card-holders');

        if (! File::isDirectory($source)) {
            return;
        }

        File::ensureDirectoryExists($target);

        $catalogue = WalletCatalogue::all();

        $groups = collect(File::files($source))
            ->filter(fn ($file) => in_array(strtolower($file->getExtension()), ['jpg', 'jpeg', 'png', 'webp', 'jfif'], true))
            ->filter(fn ($file) => self::isProductPhoto($file->getBasename('.'.$file->getExtension())))
            ->sortBy(fn ($file) => strtolower($file->getFilename()), SORT_NATURAL)
            ->groupBy(fn ($file) => self::prefix($file->getBasename('.'.$file->getExtension())));

        $written = [];
        $slugs = [];

        // Driven by the catalogue's order, not the folder's, so that the same
        // piece in three colours lands in three adjacent tiles. Products are
        // created in this order and the grid's default sort is insertion order.
        foreach ($catalogue as $prefix => [$nameEn, $nameAz, $category, $priceMinor, $leatherEn, $leatherAz]) {
            $files = $groups->get($prefix);

            // A named product with no photographs yet is not an error — it just
            // has nothing to show, so it is left out rather than seeded blank.
            if ($files === null || $files->isEmpty()) {
                continue;
            }

            $slug = Str::slug($nameEn);
            $slugs[] = $slug;

            $product = Product::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => ['en' => $nameEn, 'az' => $nameAz],
                    'description' => [
                        'en' => 'Handmade in Baku, cut and stitched to order.',
                        'az' => 'Bakıda əl işi, sifarişlə kəsilir və tikilir.',
                    ],
                    'story' => [
                        'en' => 'Cut, stitched and edge-painted by hand at the bench.',
                        'az' => 'Dəzgahda əllə kəsilir, tikilir və kənarları boyanır.',
                    ],
                    'leather' => ['en' => $leatherEn, 'az' => $leatherAz],
                    'category' => $category->value,
                    'tag' => null,
                    'specs' => [
                        'en' => [['label' => 'Made to order', 'value' => '5 business days']],
                        'az' => [['label' => 'Hazırlanma', 'value' => '5 iş günü']],
                    ],
                    'base_price_minor' => $priceMinor,
                    'lead_time_days' => 5,
                    'is_active' => true,
                ],
            );

            Variant::updateOrCreate(
                ['sku' => 'PUL-'.strtoupper(str_replace('_', 'X', $prefix))],
                [
                    'product_id' => $product->id,
                    'description' => 'Default',
                    'stock_quantity' => 10,
                    'weight_grams' => $category === \App\Domain\Catalog\ProductCategory::Bag ? 480 : 120,
                    'is_active' => true,
                ],
            );

            ProductImage::where('product_id', $product->id)->delete();

            $files->values()->each(function ($file, int $index) use ($product, $target, &$written) {
                // Always .jpg: the normalizer re-encodes, so the source
                // extension (.jfif, .png) says nothing about the output.
                $filename = strtolower($file->getBasename('.'.$file->getExtension())).'.jpg';
                $destination = $target.DIRECTORY_SEPARATOR.$filename;

                // Straight copy would put mismatched aspect ratios and white
                // sweeps onto the page unmodified — see ProductImageNormalizer
                // for area-based sizing and why the sweep is tinted.
                if (! app(ProductImageNormalizer::class)->normalize($file->getPathname(), $destination)) {
                    File::copy($file->getPathname(), $destination);
                }

                $written[] = $filename;

                ProductImage::create([
                    'product_id' => $product->id,
                    'path' => 'card-holders/'.$filename,
                    'alt_text' => [
                        'en' => $product->name.' angle '.($index + 1),
                        'az' => $product->name.' görünüş '.($index + 1),
                    ],
                    'sort_order' => $index,
                ]);
            });
        }

        // Products from an earlier naming scheme. Orders keep their own
        // snapshot of name, sku and price, so removing the product they were
        // bought from does not alter what a customer was charged or shown.
        Product::query()
            ->whereNotIn('slug', $slugs)
            ->where('slug', 'like', 'card-holder-%')
            ->delete();

        // Anything left in the target directory that this run did not write is
        // an image whose source was renamed, replaced or removed. Left alone it
        // sits there forever, and the last one that mattered was hero.png being
        // served as a product angle.
        collect(File::files($target))
            ->reject(fn ($file) => in_array($file->getFilename(), $written, true))
            ->each(fn ($file) => File::delete($file->getPathname()));
    }
}
