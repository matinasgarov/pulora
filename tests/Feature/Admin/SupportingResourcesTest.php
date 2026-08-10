<?php // tests/Feature/Admin/SupportingResourcesTest.php

use App\Domain\Discount\Models\DiscountCode;
use App\Domain\Payment\Models\PaymentLog;
use App\Domain\Shipping\Models\ShippingZone;
use App\Filament\Resources\DiscountCodes\Pages\CreateDiscountCode;
use App\Filament\Resources\DiscountCodes\Pages\EditDiscountCode;
use App\Filament\Resources\DiscountCodes\Pages\ListDiscountCodes;
use App\Filament\Resources\PaymentLogs\PaymentLogResource;
use App\Filament\Resources\PaymentLogs\Pages\ListPaymentLogs;
use App\Filament\Resources\ShippingZones\Pages\CreateShippingZone;
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
    livewire(CreateShippingZone::class)
        ->fillForm(['name' => 'Regional', 'country_codes' => ['GE', 'TR'], 'is_fallback' => false])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(ShippingZone::where('name', 'Regional')->sole()->country_codes)->toBe(['GE', 'TR']);
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
