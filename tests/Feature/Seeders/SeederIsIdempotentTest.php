<?php // tests/Feature/Seeders/SeederIsIdempotentTest.php

use App\Domain\Discount\Models\DiscountCode;
use App\Domain\Shipping\Models\ShippingRate;
use App\Domain\Shipping\Models\ShippingZone;
use Database\Seeders\DemoShopSeeder;

// The container seeds on every boot, and the database lives on a volume that
// survives the redeploy — so the second run always finds the first run's rows.
// The first deploy died here: `create()` on a unique column threw, the
// entrypoint exited 1, and the machine restarted until Fly gave up. Each of
// those attempts inserted three more shipping zones and six more rates before
// it reached the throw, because those have no unique constraint to stop them.
it('can run twice without duplicating anything', function () {
    (new DemoShopSeeder)->run();

    $zones = ShippingZone::count();
    $rates = ShippingRate::count();
    $codes = DiscountCode::count();

    expect($zones)->toBe(3)
        ->and($rates)->toBe(6)
        ->and($codes)->toBe(1);

    (new DemoShopSeeder)->run();

    expect(ShippingZone::count())->toBe($zones)
        ->and(ShippingRate::count())->toBe($rates)
        ->and(DiscountCode::count())->toBe($codes);
});

// Guards the invariant rather than the keying: an unlisted country is quoted
// whatever the fallback zone says, so a second one makes the quote depend on
// which row the query returns first. Keying the zones on name is what keeps
// this true today; this test is what notices if that stops being enough.
it('keeps exactly one fallback zone across runs', function () {
    (new DemoShopSeeder)->run();
    (new DemoShopSeeder)->run();

    expect(ShippingZone::where('is_fallback', true)->count())->toBe(1);
});
