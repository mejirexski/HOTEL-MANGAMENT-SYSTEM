<?php

use App\Models\PromoCode;
use App\Models\RatePlan;
use App\Models\RoomType;
use App\Models\SeasonalRate;
use App\Models\StayRestriction;
use App\Support\HotelSettings;
use App\Support\Pricing\PriceCalculator;
use App\Support\Pricing\PriceQuote;
use App\Support\Pricing\PricingException;
use Carbon\CarbonImmutable;

// Calendar: 12 Oct 2026 is a Monday, so 16 and 17 Oct are the Friday and Saturday.

function pricingRoomType(array $overrides = []): RoomType
{
    return RoomType::create(array_merge([
        'name' => 'Deluxe',
        'base_price' => 2500000,        // N25,000
        'weekend_price' => 3000000,     // N30,000
        'extra_person_fee' => 500000,   // N5,000
        'base_occupancy' => 2,
        'max_adults' => 3,
        'max_children' => 2,
        'bed_type' => 'King',
        'min_stay' => 1,
        'is_active' => true,
    ], $overrides));
}

function pricingPlan(array $overrides = []): RatePlan
{
    return RatePlan::create(array_merge([
        'name' => 'Best Available',
        'adjustment_bps' => 0,
        'extra_per_night' => 0,
        'is_refundable' => true,
        'is_active' => true,
    ], $overrides));
}

function quoteFor(
    RoomType $roomType,
    RatePlan $plan,
    string $checkIn,
    string $checkOut,
    int $adults = 2,
    int $children = 0,
    ?PromoCode $promo = null,
    ?string $bookedOn = null,
): PriceQuote {
    return app(PriceCalculator::class)->quote(
        $roomType,
        $plan,
        CarbonImmutable::parse($checkIn),
        CarbonImmutable::parse($checkOut),
        $adults,
        $children,
        $promo,
        $bookedOn ? CarbonImmutable::parse($bookedOn) : null,
    );
}

beforeEach(function () {
    HotelSettings::set('tax_rate', '7.5');
    HotelSettings::set('tax_included', '0');
    HotelSettings::set('service_charge_rate', '0');
    HotelSettings::set('service_charge_included', '0');
});

it('prices a weekday stay with tax added', function () {
    $q = quoteFor(pricingRoomType(), pricingPlan(), '2026-10-12', '2026-10-14');

    expect($q->roomSubtotal)->toBe(5000000)
        ->and($q->discount)->toBe(0)
        ->and($q->tax)->toBe(375000)
        ->and($q->total)->toBe(5375000)
        ->and($q->total)->toBeInt();
});

it('uses the weekend price for Friday and Saturday nights only', function () {
    $q = quoteFor(pricingRoomType(), pricingPlan(), '2026-10-15', '2026-10-19'); // Thu to Mon

    expect(array_column($q->nights, 'source'))->toBe(['base', 'weekend', 'weekend', 'base'])
        ->and($q->roomSubtotal)->toBe(11000000)
        ->and($q->total)->toBe(11825000);
});

it('lets a seasonal rate override both weekend and base prices', function () {
    SeasonalRate::create([
        'room_type_id' => ($rt = pricingRoomType())->id,
        'name' => 'Festival',
        'start_date' => '2026-10-16',
        'end_date' => '2026-10-17',
        'price' => 4000000,
    ]);

    $q = quoteFor($rt, pricingPlan(), '2026-10-16', '2026-10-19'); // Fri, Sat, Sun

    expect(array_column($q->nights, 'source'))->toBe(['seasonal', 'seasonal', 'base'])
        ->and($q->roomSubtotal)->toBe(10500000);
});

it('applies the rate plan to the nightly price', function (int $bps, int $addOn, int $expectedSubtotal) {
    $plan = pricingPlan(['adjustment_bps' => $bps, 'extra_per_night' => $addOn]);

    $q = quoteFor(pricingRoomType(), $plan, '2026-10-12', '2026-10-14');

    expect($q->roomSubtotal)->toBe($expectedSubtotal);
})->with([
    'flexible +10%' => [1000, 0, 5500000],
    'non-refundable -10%' => [-1000, 0, 4500000],
    'package add-on' => [0, 500000, 6000000],
]);

it('charges extra guests above the included number for every night', function () {
    $q = quoteFor(pricingRoomType(), pricingPlan(), '2026-10-12', '2026-10-14', adults: 3, children: 1);

    expect($q->extraGuests)->toBe(2)
        ->and($q->extraPersonTotal)->toBe(2000000)
        ->and($q->total)->toBe(7525000); // (5,000,000 + 2,000,000) + 7.5% tax
});

it('takes a percentage promo off the room total only', function () {
    $promo = PromoCode::create(['code' => 'WELCOME10', 'type' => 'percent', 'value' => 1000, 'is_active' => true]);

    $q = quoteFor(pricingRoomType(), pricingPlan(), '2026-10-12', '2026-10-14', promo: $promo);

    expect($q->discount)->toBe(500000)
        ->and($q->tax)->toBe(337500)
        ->and($q->total)->toBe(4837500);

    $withExtras = quoteFor(pricingRoomType(['name' => 'Suite']), pricingPlan(['name' => 'Flexible']), '2026-10-12', '2026-10-14', 3, 1, $promo);

    expect($withExtras->discount)->toBe(500000)
        ->and($withExtras->extraPersonTotal)->toBe(2000000)
        ->and($withExtras->total)->toBe(6987500); // 6,500,000 + 7.5% tax
});

it('never discounts more than the room total', function () {
    $promo = PromoCode::create(['code' => 'HUGE', 'type' => 'fixed', 'value' => 999999999, 'is_active' => true]);

    $q = quoteFor(pricingRoomType(), pricingPlan(), '2026-10-12', '2026-10-14', promo: $promo);

    expect($q->discount)->toBe(5000000)
        ->and($q->total)->toBe(0);
});

it('adds service charge first, then tax on top of it', function () {
    HotelSettings::set('service_charge_rate', '10');

    $q = quoteFor(pricingRoomType(), pricingPlan(), '2026-10-12', '2026-10-14');

    expect($q->serviceCharge)->toBe(500000)
        ->and($q->tax)->toBe(412500)
        ->and($q->total)->toBe(5912500);
});

it('extracts tax that is already included in the listed price', function () {
    HotelSettings::set('tax_included', '1');

    $q = quoteFor(pricingRoomType(['base_price' => 2687500, 'weekend_price' => null]), pricingPlan(), '2026-10-12', '2026-10-14');

    expect($q->net)->toBe(5000000)
        ->and($q->tax)->toBe(375000)
        ->and($q->total)->toBe(5375000);
});

it('keeps the total equal to the listed price when tax and service charge are both included', function () {
    HotelSettings::set('service_charge_rate', '10');
    HotelSettings::set('service_charge_included', '1');
    HotelSettings::set('tax_included', '1');

    $q = quoteFor(pricingRoomType(['base_price' => 2956250, 'weekend_price' => null]), pricingPlan(), '2026-10-12', '2026-10-14');

    expect($q->net)->toBe(5000000)
        ->and($q->serviceCharge)->toBe(500000)
        ->and($q->tax)->toBe(412500)
        ->and($q->total)->toBe(5912500);
});

it('rejects a check-out that is not after check-in', function () {
    expect(fn () => quoteFor(pricingRoomType(), pricingPlan(), '2026-10-14', '2026-10-14'))
        ->toThrow(PricingException::class, 'Check-out must be after check-in');
});

it('rejects too many adults or children', function () {
    $rt = pricingRoomType();
    $plan = pricingPlan();

    expect(fn () => quoteFor($rt, $plan, '2026-10-12', '2026-10-14', adults: 4))
        ->toThrow(PricingException::class, 'at most 3 adults')
        ->and(fn () => quoteFor($rt, $plan, '2026-10-12', '2026-10-14', adults: 2, children: 3))
        ->toThrow(PricingException::class, 'at most 2 children');
});

it('rejects inactive room types and rate plans', function () {
    expect(fn () => quoteFor(pricingRoomType(['is_active' => false]), pricingPlan(), '2026-10-12', '2026-10-14'))
        ->toThrow(PricingException::class, 'not available');

    expect(fn () => quoteFor(pricingRoomType(['name' => 'Other']), pricingPlan(['is_active' => false]), '2026-10-12', '2026-10-14'))
        ->toThrow(PricingException::class, 'not available');
});

it('enforces closed-to-arrival and minimum stay restrictions on the arrival date', function () {
    $rt = pricingRoomType(['min_stay' => 2]);
    $plan = pricingPlan();

    StayRestriction::create([
        'room_type_id' => $rt->id, 'start_date' => '2026-12-24', 'end_date' => '2026-12-26',
        'min_stay' => 3,
    ]);
    StayRestriction::create([
        'room_type_id' => $rt->id, 'start_date' => '2026-12-27', 'end_date' => '2026-12-28',
        'closed_to_arrival' => true,
    ]);

    expect(fn () => quoteFor($rt, $plan, '2026-12-24', '2026-12-26'))
        ->toThrow(PricingException::class, 'is 3 nights');

    expect(quoteFor($rt, $plan, '2026-12-24', '2026-12-27'))->toBeInstanceOf(PriceQuote::class);

    expect(fn () => quoteFor($rt, $plan, '2026-12-27', '2026-12-30'))
        ->toThrow(PricingException::class, 'closed');

    expect(quoteFor($rt, $plan, '2026-12-23', '2026-12-25'))->toBeInstanceOf(PriceQuote::class);
});

it('enforces the maximum stay', function () {
    $rt = pricingRoomType(['max_stay' => 5]);

    expect(fn () => quoteFor($rt, pricingPlan(), '2026-10-12', '2026-10-18'))
        ->toThrow(PricingException::class, 'maximum stay');
});

it('rejects expired promo codes and promo codes for other rate plans', function () {
    $rt = pricingRoomType();
    $plan = pricingPlan();

    $expired = PromoCode::create(['code' => 'OLD10', 'type' => 'percent', 'value' => 1000, 'valid_to' => '2026-01-01', 'is_active' => true]);

    expect(fn () => quoteFor($rt, $plan, '2026-10-12', '2026-10-14', promo: $expired, bookedOn: '2026-10-13'))
        ->toThrow(PricingException::class, 'expired');

    $restricted = PromoCode::create(['code' => 'PKG10', 'type' => 'percent', 'value' => 1000, 'is_active' => true]);
    $restricted->ratePlans()->attach(pricingPlan(['name' => 'Package'])->id);

    expect(fn () => quoteFor($rt, $plan, '2026-10-12', '2026-10-14', promo: $restricted))
        ->toThrow(PricingException::class, 'does not apply');
});