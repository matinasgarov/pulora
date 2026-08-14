<?php // tests/Concurrency/ConcurrentOversellTest.php

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Variant;
use App\Domain\Order\Models\Order;
use App\Domain\Shipping\Models\ShippingRate;
use App\Domain\Shipping\Models\ShippingZone;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;

/*
 * OversellTest places two orders one after the other in a single process. Two
 * sequential transactions never contend, so lockForUpdate() never actually
 * blocks there — it verifies that the stock check sits inside the transaction,
 * not that the lock works.
 *
 * This test spawns real OS processes that race for the same row, which is the
 * only way to observe the lock doing its job.
 *
 * Note the deliberate absence of RefreshDatabase: it wraps each test in a
 * transaction that is never committed, so the fixtures below would be invisible
 * to every other process. This file lives outside Feature/ and Unit/ precisely
 * so Pest does not apply that trait to it (see tests/Pest.php), and it commits
 * its fixtures for the workers to see.
 */
uses(Tests\TestCase::class);

const WORKERS = 4;

beforeEach(function () {
    if (config('database.default') !== 'mysql_test') {
        $this->markTestSkipped('Row locking is only observable on MySQL; SQLite treats lockForUpdate() as a no-op.');
    }

    // No wrapping transaction here, so state persists between runs — start clean.
    Artisan::call('migrate:fresh');
});

it('lets exactly one of several racing processes take the last unit', function () {
    $product = Product::factory()->create(['base_price_minor' => 8900]);
    $variant = Variant::factory()->for($product)->create([
        'stock_quantity' => 1,
        'weight_grams' => 120,
    ]);

    $zone = ShippingZone::create([
        'name' => 'Azerbaijan', 'country_codes' => ['AZ'], 'is_fallback' => true,
    ]);
    $rate = ShippingRate::create([
        'shipping_zone_id' => $zone->id, 'name' => 'Standard',
        'min_weight_grams' => 0, 'max_weight_grams' => 3000, 'price_minor' => 500,
    ]);

    // Enough lead time for every worker to boot the framework and be spinning
    // on the clock before the start instant arrives.
    $startAt = microtime(true) + 5.0;

    $processes = collect(range(1, WORKERS))->map(function () use ($variant, $rate, $startAt) {
        $process = new Process(
            [PHP_BINARY, __DIR__.'/attempt-order.php', $variant->id, $rate->id, $startAt],
            env: ['DB_CONNECTION' => 'mysql_test'],
            timeout: 60,
        );

        $process->start();

        return $process;
    });

    $results = $processes->map(function (Process $process) {
        $process->wait();

        return trim($process->getOutput());
    });

    // Surface a worker that died for an unrelated reason (a boot failure, a
    // deadlock) instead of letting it masquerade as a lost race.
    $errors = $results->filter(fn (string $r) => ! in_array($r, ['OK', 'REFUSED'], true));

    expect($errors)->toBeEmpty("A worker failed unexpectedly:\n".$errors->implode("\n"));

    expect($results->filter(fn (string $r) => $r === 'OK')->count())->toBe(1)
        ->and($results->filter(fn (string $r) => $r === 'REFUSED')->count())->toBe(WORKERS - 1);

    // The row itself must agree with the workers: one unit sold, one order.
    expect($variant->fresh()->stock_quantity)->toBe(0)
        ->and(Order::count())->toBe(1);
});
