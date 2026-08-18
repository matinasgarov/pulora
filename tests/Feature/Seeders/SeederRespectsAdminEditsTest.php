<?php // tests/Feature/Seeders/SeederRespectsAdminEditsTest.php

use App\Domain\Catalog\Models\Product;
use Database\Seeders\WalletCatalogue;
use Database\Seeders\WalletImagesSeeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

beforeEach(function () {
    // A fixture folder with one photograph for one real prefix. Seeding from
    // the actual walletImages folder normalises 67 images per run, which is
    // both slow and a dependency on a gitignored directory being present.
    $this->source = sys_get_temp_dir().DIRECTORY_SEPARATOR.'seed-src-'.uniqid();
    $this->target = sys_get_temp_dir().DIRECTORY_SEPARATOR.'seed-out-'.uniqid();

    File::ensureDirectoryExists($this->source);

    $canvas = imagecreatetruecolor(600, 800);
    imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
    imagefilledrectangle($canvas, 200, 300, 400, 500, imagecolorallocate($canvas, 30, 25, 20));
    imagejpeg($canvas, $this->source.DIRECTORY_SEPARATOR.'x1.jpg', 90);

    $this->definition = WalletCatalogue::all()['x'];
    $this->slug = Str::slug($this->definition['name']['en']);

    $this->seed = fn () => (new WalletImagesSeeder($this->source, $this->target))->run();
});

afterEach(function () {
    File::deleteDirectory($this->source);
    File::deleteDirectory($this->target);
});

it('does not undo an edit made in the admin panel', function () {
    // Filament and WalletCatalogue are both sources of truth for a price.
    // updateOrCreate meant the panel quietly lost — an operator's correction
    // came back the next time anyone ran a seed.
    ($this->seed)();

    Product::where('slug', $this->slug)->update(['base_price_minor' => 6100]);

    ($this->seed)();

    expect(Product::where('slug', $this->slug)->value('base_price_minor'))->toBe(6100);
});

it('restores the catalogue file when asked to on purpose', function () {
    ($this->seed)();

    Product::where('slug', $this->slug)->update(['base_price_minor' => 6100]);

    putenv('PULORA_RESEED_OVERWRITE=true');
    $_ENV['PULORA_RESEED_OVERWRITE'] = 'true';

    try {
        ($this->seed)();
    } finally {
        putenv('PULORA_RESEED_OVERWRITE');
        unset($_ENV['PULORA_RESEED_OVERWRITE']);
    }

    expect(Product::where('slug', $this->slug)->value('base_price_minor'))
        ->toBe($this->definition['price']);
});

it('keeps alt text that has been edited', function () {
    // The JPEG is rewritten every run, but the row carrying the alt text is
    // not: deleting and recreating it discarded the edit each time.
    ($this->seed)();

    $image = Product::where('slug', $this->slug)->first()->images()->first();
    $image->update(['alt_text' => ['en' => 'Edited by hand', 'az' => 'Əllə redaktə edilib']]);

    ($this->seed)();

    expect($image->fresh()->getTranslations('alt_text')['en'])->toBe('Edited by hand');
});

it('drops the row when its photograph is gone, and keeps the others', function () {
    imagejpeg(imagecreatetruecolor(400, 400), $this->source.DIRECTORY_SEPARATOR.'x2.jpg', 90);
    ($this->seed)();

    expect(Product::where('slug', $this->slug)->first()->images)->toHaveCount(2);

    File::delete($this->source.DIRECTORY_SEPARATOR.'x2.jpg');
    ($this->seed)();

    expect(Product::where('slug', $this->slug)->first()->images)->toHaveCount(1);
});
