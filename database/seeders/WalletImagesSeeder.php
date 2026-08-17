<?php

namespace Database\Seeders;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductImage;
use App\Domain\Catalog\Models\Variant;
use App\Domain\Catalog\ProductCategory;
use App\Support\ProductImageNormalizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class WalletImagesSeeder extends Seeder
{
    /**
     * A product photograph is named letter-then-number: a1, k3, h4.
     *
     * The letter is the grouping key, so anything else dropped in this folder
     * would be filed under its first character and silently become a product
     * angle. hero.png joined the H wallet that way, and a stray
     * "ChatGPT Image….png" joined C. Matching the scheme exactly is what makes
     * the folder safe to keep other files in — and it excludes duplicates like
     * "h4 (2)", which are the same shot twice.
     */
    public static function isProductPhoto(string $basename): bool
    {
        return preg_match('/^[a-z][0-9]+$/', strtolower($basename)) === 1;
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

        $written = [];

        collect(File::files($source))
            ->filter(fn ($file) => in_array(strtolower($file->getExtension()), ['jpg', 'jpeg', 'png', 'webp', 'jfif'], true))
            ->filter(fn ($file) => self::isProductPhoto($file->getBasename('.'.$file->getExtension())))
            ->sortBy(fn ($file) => strtolower($file->getFilename()))
            ->groupBy(fn ($file) => strtolower(substr($file->getBasename('.'.$file->getExtension()), 0, 1)))
            ->each(function ($files, string $letter) use ($target, &$written) {
                $name = 'Card holder '.strtoupper($letter);
                $slug = Str::slug($name);

                $product = Product::updateOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => ['en' => $name, 'az' => 'Kart qabı '.strtoupper($letter)],
                        'description' => [
                            'en' => 'Handmade leather card holder.',
                            'az' => 'Əl işi dəri kart qabı.',
                        ],
                        'story' => [
                            'en' => 'Grouped from the new product image set.',
                            'az' => 'Yeni məhsul şəkilləri dəstindən qruplaşdırılıb.',
                        ],
                        'leather' => ['en' => 'Leather', 'az' => 'Dəri'],
                        'category' => ProductCategory::Card->value,
                        'tag' => null,
                        'specs' => [
                            'en' => [
                                ['label' => 'Card slots', 'value' => '4'],
                                ['label' => 'Made to order', 'value' => '5 business days'],
                            ],
                            'az' => [
                                ['label' => 'Kart yuvası', 'value' => '4'],
                                ['label' => 'Hazırlanma', 'value' => '5 iş günü'],
                            ],
                        ],
                        'base_price_minor' => 4900,
                        'lead_time_days' => 5,
                        'is_active' => true,
                    ],
                );

                Variant::updateOrCreate(
                    ['sku' => 'CARD-HOLDER-'.strtoupper($letter)],
                    [
                        'product_id' => $product->id,
                        'description' => 'Default',
                        'stock_quantity' => 10,
                        'weight_grams' => 120,
                        'is_active' => true,
                    ],
                );

                ProductImage::where('product_id', $product->id)->delete();

                $files->values()->each(function ($file, int $index) use ($product, $target, &$written) {
                    // Always .jpg: the normalizer re-encodes, so the source
                    // extension (.jfif, .png) says nothing about the output.
                    $filename = strtolower($file->getBasename('.'.$file->getExtension())).'.jpg';
                    $destination = $target.DIRECTORY_SEPARATOR.$filename;

                    // Straight copy would put mismatched aspect ratios and
                    // white sweeps onto the page unmodified — see
                    // ProductImageNormalizer for area-based sizing and why the
                    // sweep is tinted to --color-ground rather than left white.
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
            });

        // Anything left in the target directory that this run did not write is
        // an image whose source was renamed, replaced or removed. Left alone it
        // sits there forever, and the last one that mattered was hero.png being
        // served as a product angle. Only this directory is touched, and only
        // files the seeder itself produces.
        collect(File::files($target))
            ->reject(fn ($file) => in_array($file->getFilename(), $written, true))
            ->each(fn ($file) => File::delete($file->getPathname()));
    }
}
