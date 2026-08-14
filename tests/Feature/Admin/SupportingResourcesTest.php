<?php // tests/Feature/Admin/SupportingResourcesTest.php

use App\Domain\Discount\Models\DiscountCode;
use App\Domain\Payment\Models\PaymentLog;
use App\Domain\Shipping\Models\ShippingZone;
use App\Filament\Resources\DiscountCodes\Pages\CreateDiscountCode;
use App\Filament\Resources\DiscountCodes\Pages\EditDiscountCode;
use App\Filament\Resources\PaymentLogs\Pages\ListPaymentLogs;
use App\Filament\Resources\PaymentLogs\PaymentLogResource;
use App\Filament\Resources\ShippingZones\Pages\CreateShippingZone;
use App\Filament\Resources\ShippingZones\Pages\EditShippingZone;
use App\Filament\Resources\ShippingZones\Pages\ListShippingZones;
use App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_operator' => true]));
});

it('lists shipping zones', function () {
    $zone = ShippingZone::create(['name' => 'Azerbaijan', 'country_codes' => ['AZ']]);

    livewire(ListShippingZones::class)->assertCanSeeTableRecords([$zone]);
});

it('creates a shipping zone', function () {
    // A fallback zone must already exist elsewhere, or this non-fallback
    // creation would leave the system with zero fallback zones — see the
    // fallback-zone validation tests below.
    ShippingZone::create(['name' => 'Azerbaijan', 'country_codes' => ['AZ'], 'is_fallback' => true]);

    livewire(CreateShippingZone::class)
        ->fillForm(['name' => 'Regional', 'country_codes' => ['GE', 'TR'], 'is_fallback' => false])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(ShippingZone::where('name', 'Regional')->sole()->country_codes)->toBe(['GE', 'TR']);
});

it('refuses to switch off the only fallback zone', function () {
    $zone = ShippingZone::create(['name' => 'Azerbaijan', 'country_codes' => ['AZ'], 'is_fallback' => true]);

    livewire(EditShippingZone::class, ['record' => $zone->getKey()])
        ->fillForm(['is_fallback' => false])
        ->call('save')
        ->assertHasFormErrors(['is_fallback']);

    expect($zone->fresh()->is_fallback)->toBeTrue();
});

it('allows switching off a fallback zone when another one remains', function () {
    ShippingZone::create(['name' => 'Azerbaijan', 'country_codes' => ['AZ'], 'is_fallback' => true]);
    $zone = ShippingZone::create(['name' => 'Regional', 'country_codes' => ['GE'], 'is_fallback' => true]);

    livewire(EditShippingZone::class, ['record' => $zone->getKey()])
        ->fillForm(['is_fallback' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($zone->fresh()->is_fallback)->toBeFalse();
});

it('refuses to create a non-fallback zone when no fallback zone exists yet', function () {
    livewire(CreateShippingZone::class)
        ->fillForm(['name' => 'Regional', 'country_codes' => ['GE'], 'is_fallback' => false])
        ->call('create')
        ->assertHasFormErrors(['is_fallback']);

    expect(ShippingZone::where('name', 'Regional')->exists())->toBeFalse();
});

it('creates a discount code', function () {
    livewire(CreateDiscountCode::class)
        ->fillForm([
            'code' => 'WELCOME10',
            'kind' => 'percent',
            'value' => 10,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(DiscountCode::where('code', 'WELCOME10')->exists())->toBeTrue();
});

it('rejects a duplicate discount code', function () {
    DiscountCode::create(['code' => 'WELCOME10', 'kind' => 'percent', 'value' => 10]);

    livewire(CreateDiscountCode::class)
        ->fillForm(['code' => 'WELCOME10', 'kind' => 'percent', 'value' => 10])
        ->call('create')
        ->assertHasFormErrors(['code']);
});

it('has no times_used field on the form', function () {
    $code = DiscountCode::create(['code' => 'EXISTING', 'kind' => 'percent', 'value' => 10, 'times_used' => 4]);

    livewire(EditDiscountCode::class, ['record' => $code->getKey()])
        ->assertFormFieldDoesNotExist('times_used');
});

it('ignores a times_used value pushed at the create form', function () {
    livewire(CreateDiscountCode::class)
        ->fillForm(['code' => 'SNEAKY', 'kind' => 'percent', 'value' => 10, 'times_used' => 99])
        ->call('create');

    // times_used belongs to DiscountService::consume()'s atomic increment.
    expect(DiscountCode::where('code', 'SNEAKY')->sole()->times_used)->toBe(0);
});

it('locks the kind field on a discount code that has been used', function () {
    $code = DiscountCode::create(['code' => 'USED10', 'kind' => 'percent', 'value' => 10, 'times_used' => 1]);

    livewire(EditDiscountCode::class, ['record' => $code->getKey()])
        ->assertFormFieldIsDisabled('kind');
});

it('leaves the kind field editable on a discount code nobody has used', function () {
    $code = DiscountCode::create(['code' => 'UNUSED10', 'kind' => 'percent', 'value' => 10]);

    livewire(EditDiscountCode::class, ['record' => $code->getKey()])
        ->assertFormFieldIsEnabled('kind');
});

it('rejects a fixed discount amount below the minimum', function () {
    livewire(CreateDiscountCode::class)
        ->fillForm(['code' => 'ZERO', 'kind' => 'fixed', 'value' => '0.00'])
        ->call('create')
        ->assertHasFormErrors(['value']);
});

it('accepts a small but positive fixed discount amount', function () {
    livewire(CreateDiscountCode::class)
        ->fillForm(['code' => 'SMALL', 'kind' => 'fixed', 'value' => '0.01'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(DiscountCode::where('code', 'SMALL')->sole()->value)->toBe(1);
});

it('hides the delete action on a discount code that has been used', function () {
    $code = DiscountCode::create(['code' => 'USEDDEL', 'kind' => 'percent', 'value' => 10, 'times_used' => 1]);

    livewire(EditDiscountCode::class, ['record' => $code->getKey()])
        ->assertActionHidden('delete');

    expect(DiscountCode::find($code->id))->not->toBeNull();
});

it('allows the delete action on a discount code nobody has used', function () {
    $code = DiscountCode::create(['code' => 'UNUSEDDEL', 'kind' => 'percent', 'value' => 10]);

    livewire(EditDiscountCode::class, ['record' => $code->getKey()])
        ->assertActionVisible('delete');
});

it('hides the delete action on the last remaining fallback zone', function () {
    $zone = ShippingZone::create(['name' => 'Azerbaijan', 'country_codes' => ['AZ'], 'is_fallback' => true]);

    livewire(EditShippingZone::class, ['record' => $zone->getKey()])
        ->assertActionHidden('delete');

    expect(ShippingZone::find($zone->id))->not->toBeNull();
});

it('allows deleting a fallback zone when another one remains', function () {
    ShippingZone::create(['name' => 'Azerbaijan', 'country_codes' => ['AZ'], 'is_fallback' => true]);
    $zone = ShippingZone::create(['name' => 'Regional', 'country_codes' => ['GE'], 'is_fallback' => true]);

    livewire(EditShippingZone::class, ['record' => $zone->getKey()])
        ->assertActionVisible('delete');
});

it('allows deleting a non-fallback zone', function () {
    ShippingZone::create(['name' => 'Azerbaijan', 'country_codes' => ['AZ'], 'is_fallback' => true]);
    $zone = ShippingZone::create(['name' => 'Regional', 'country_codes' => ['GE'], 'is_fallback' => false]);

    livewire(EditShippingZone::class, ['record' => $zone->getKey()])
        ->assertActionVisible('delete');
});

it('lists payment logs and refuses creation', function () {
    PaymentLog::create([
        'gateway' => 'MockGateway',
        'direction' => 'callback',
        'reference' => 'REF-1',
        'payload' => ['status' => 'paid'],
    ]);

    livewire(ListPaymentLogs::class)->assertSuccessful();

    expect(PaymentLogResource::canCreate())->toBeFalse()
        ->and(PaymentLogResource::canEdit(PaymentLog::first()))->toBeFalse();
});
