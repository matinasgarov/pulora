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
     * Overridable so a test can seed from a fixture folder holding one
     * photograph. Against the real folder every run normalises 67 images, which
     * made a three-assertion test take two minutes.
     */
    public function __construct(
        private readonly ?string $source = null,
        private readonly ?string $target = null,
        /**
         * False when the source folder already holds finished images — the
         * committed demo fixtures. Re-normalising an already-normalised
         * photograph would crop it a second time and shrink the product, so
         * this is not merely an optimisation.
         */
        private readonly bool $normalize = true,
    ) {}

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
     * Whether this run should push the catalogue file back over products that
     * already exist, discarding anything edited in the admin panel since.
     */
    private function overwrites(): bool
    {
        return filter_var(env('PULORA_RESEED_OVERWRITE', false), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * The source photographs are gitignored — 60MB of originals that git would
     * carry forever. Restore the folder from wherever it is backed up before
     * running this; with no folder present it returns without touching
     * anything, so a fresh clone seeds the rest of the database fine.
     */
    public function run(): void
    {
        $source = $this->source ?? base_path('walletImages');
        $target = $this->target ?? storage_path('app/public/card-holders');

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
        foreach ($catalogue as $prefix => $definition) {
            $files = $groups->get($prefix);

            // A named product with no photographs yet is not an error — it just
            // has nothing to show, so it is left out rather than seeded blank.
            if ($files === null || $files->isEmpty()) {
                continue;
            }

            $slug = Str::slug($definition['name']['en']);
            $slugs[] = $slug;

            $product = Product::firstOrNew(['slug' => $slug]);

            // Create-only for everything the operator can edit in Filament.
            // The panel and this list are both sources of truth for a name or a
            // price, and updateOrCreate meant the panel quietly lost: an edit
            // made at /admin came back the next time anyone seeded. A new
            // product gets its copy from here; an existing one keeps whatever
            // it has been given since. Set PULORA_RESEED_OVERWRITE=true to push
            // this file's version back over the top on purpose.
            if (! $product->exists || $this->overwrites()) {
                $product->fill([
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'story' => [
                        'en' => 'Cut, stitched and edge-painted by hand at the bench.',
                        'az' => 'Dəzgahda əllə kəsilir, tikilir və kənarları boyanır.',
                    ],
                    'leather' => $definition['leather'],
                    'category' => $definition['category']->value,
                    'base_price_minor' => $definition['price'],
                    'lead_time_days' => 5,
                    'is_active' => true,
                    'specs' => [
                        'en' => [['label' => 'Made to order', 'value' => '5 business days']],
                        'az' => [['label' => 'Hazırlanma', 'value' => '5 iş günü']],
                    ],
                ])->save();
            }

            // Stock is a live number the operator manages, so it is only ever
            // set when the variant is first created.
            Variant::firstOrCreate(
                ['sku' => 'PUL-'.strtoupper(str_replace('_', 'X', $prefix))],
                [
                    'product_id' => $product->id,
                    'description' => 'Default',
                    'stock_quantity' => 10,
                    'weight_grams' => $definition['category'] === ProductCategory::Bag ? 480 : 120,
                    'is_active' => true,
                ],
            );

            $paths = [];

            $files->values()->each(function ($file, int $index) use ($product, $definition, $target, &$written, &$paths) {
                // Always .jpg: the normalizer re-encodes, so the source
                // extension (.jfif, .png) says nothing about the output.
                $filename = strtolower($file->getBasename('.'.$file->getExtension())).'.jpg';
                $destination = $target.DIRECTORY_SEPARATOR.$filename;

                // Straight copy would put mismatched aspect ratios and white
                // sweeps onto the page unmodified — see ProductImageNormalizer
                // for area-based sizing and why the sweep is tinted.
                if (! $this->normalize || ! app(ProductImageNormalizer::class)->normalize($file->getPathname(), $destination)) {
                    File::copy($file->getPathname(), $destination);
                }

                $written[] = $filename;
                $path = 'card-holders/'.$filename;
                $paths[] = $path;

                // The JPEG behind an existing row is rewritten every run, but
                // the row itself is kept — alt text is editable and deleting
                // the row to recreate it threw that away each time.
                ProductImage::firstOrCreate(
                    ['product_id' => $product->id, 'path' => $path],
                    [
                        'alt_text' => [
                            'en' => $definition['name']['en'].' angle '.($index + 1),
                            'az' => $definition['name']['az'].' görünüş '.($index + 1),
                        ],
                        'sort_order' => $index,
                    ],
                );
            });

            // Only rows whose photograph no longer exists.
            ProductImage::where('product_id', $product->id)->whereNotIn('path', $paths)->delete();
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
