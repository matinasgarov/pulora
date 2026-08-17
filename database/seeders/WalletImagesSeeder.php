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
    public function run(): void
    {
        $source = base_path('walletImages');
        $target = storage_path('app/public/card-holders');

        if (! File::isDirectory($source)) {
            return;
        }

        File::ensureDirectoryExists($target);

        collect(File::files($source))
            ->filter(fn ($file) => in_array(strtolower($file->getExtension()), ['jpg', 'jpeg', 'png', 'webp', 'jfif'], true))
            ->sortBy(fn ($file) => strtolower($file->getFilename()))
            ->groupBy(fn ($file) => strtolower(substr($file->getBasename('.'.$file->getExtension()), 0, 1)))
            ->each(function ($files, string $letter) use ($target) {
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

                $files->values()->each(function ($file, int $index) use ($product, $target) {
                    // Always .jpg: the normalizer re-encodes, so the source
                    // extension (.jfif, .png) says nothing about the output.
                    $filename = strtolower($file->getBasename('.'.$file->getExtension())).'.jpg';
                    $destination = $target.DIRECTORY_SEPARATOR.$filename;

                    // Straight copy would put 0.63-to-2.22 aspect ratios into a
                    // fixed 4/5 tile, which is what made the grid look uneven.
                    if (! app(ProductImageNormalizer::class)->normalize($file->getPathname(), $destination)) {
                        File::copy($file->getPathname(), $destination);
                    }

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
    }
}
