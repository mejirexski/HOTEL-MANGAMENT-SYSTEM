<?php

use App\Enums\BookingStatus;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\PromoCode;
use App\Models\RatePlan;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Support\Bookings\BookingException;
use App\Support\Bookings\BookingService;
use App\Support\HotelSettings;
use App\Support\Pricing\PricingException;

// 12 Oct 2026 is a Monday: two weekday nights at N25,000 each.

function svcType(array $overrides = []): RoomType
{
    return RoomType::create(array_merge([
        'name' => 'Deluxe',
        'base_price' => 2500000,
        'max_adults' => 3,
        'max_children' => 2,
        'bed_type' => 'King',
        'is_active' => true,
    ], $overrides));
}

function svcRoom(RoomType $type, string $number): Room
{
    return Room::create(['room_number' => $number, 'floor' => 1, 'room_type_id' => $type->id]);
}

function svcPlan(): RatePlan
{
    return RatePlan::firstOrCreate(
        ['name' => 'Best Available'],
        ['adjustment_bps' => 0, 'extra_per_night' => 0, 'is_refundable' => true, 'is_active' => true],
    );
}

function svcBook(RoomType $type, string $in, string $out, array $args = []): Booking
{
    return app(BookingService::class)->create(...array_merge([
        'roomType' => $type,
        'ratePlan' => svcPlan(),
        'guest' => Guest::create(['full_name' => 'Test Guest']),
        'checkIn' => $in,
        'checkOut' => $out,
        'adults' => 2,
    ], $args));
}

function svcPromo(array $overrides = []): PromoCode
{
    return PromoCode::create(array_merge([
        'code' => 'WELCOME10', 'type' => 'percent', 'value' => 1000, 'is_active' => true,
    ], $overrides));
}

beforeEach(function () {
    HotelSettings::set('tax_rate', '7.5');
    HotelSettings::set('tax_included', '0');
    HotelSettings::set('service_charge_rate', '0');
    HotelSettings::set('service_charge_included', '0');
    HotelSettings::set('deposit_percent', '30');
    HotelSettings::set('pending_expiry_hours', '24');
});

it('creates a priced pending booking in the lowest free room', function () {
    $type = svcType();
    svcRoom($type, '102');
    svcRoom($type, '101');

    $booking = svcBook($type, '2026-10-12', '2026-10-14');

    expect($booking->status)->toBe(BookingStatus::Pending)
        ->and($booking->room->room_number)->toBe('101')
        ->and($booking->room_type_id)->toBe($type->id)
        ->and($booking->nights())->toBe(2)
        ->and($booking->subtotal)->toBe(5000000)
        ->and($booking->tax)->toBe(375000)
        ->and($booking->total)->toBe(5375000)
        ->and($booking->deposit_required)->toBe(1612500)
        ->and($booking->reference)->toMatch('/^HMS-\d{4}-[A-HJ-NP-Z2-9]{6}$/')
        ->and($booking->expires_at->isFuture())->toBeTrue()
        ->and($booking->price_breakdown['roomSubtotal'])->toBe(5000000)
        ->and(ActivityLog::where('action', 'booking.created')->count())->toBe(1);
});

it('records who created the booking', function () {
    $type = svcType();
    svcRoom($type, '101');
    $user = User::factory()->create();

    $booking = svcBook($type, '2026-10-12', '2026-10-14', ['actor' => $user]);

    expect($booking->created_by)->toBe($user->id)
        ->and(ActivityLog::where('action', 'booking.created')->value('user_id'))->toBe($user->id);
});

it('gives every booking its own reference', function () {
    $type = svcType();
    foreach (['101', '102', '103'] as $number) {
        svcRoom($type, $number);
    }

    $references = collect(range(1, 3))->map(fn () => svcBook($type, '2026-10-12', '2026-10-14')->reference);

    expect($references->unique()->count())->toBe(3);
});

it('refuses the last room once it is taken and frees it again when that booking is cancelled', function () {
    $type = svcType();
    svcRoom($type, '101');
    svcRoom($type, '102');

    $first = svcBook($type, '2026-10-12', '2026-10-14');
    $second = svcBook($type, '2026-10-12', '2026-10-14');

    expect([$first->room->room_number, $second->room->room_number])->toBe(['101', '102']);

    expect(fn () => svcBook($type, '2026-10-13', '2026-10-15'))
        ->toThrow(BookingException::class, 'No room of this type is available');

    expect(Booking::count())->toBe(2);

    $first->update(['status' => BookingStatus::Cancelled]);

    expect(svcBook($type, '2026-10-13', '2026-10-15')->room_id)->toBe($first->room_id);
});

it('allows back-to-back stays in the same room', function () {
    $type = svcType();
    svcRoom($type, '101');

    $first = svcBook($type, '2026-10-12', '2026-10-14');
    $second = svcBook($type, '2026-10-14', '2026-10-16');

    expect($second->room_id)->toBe($first->room_id);
});

it('books a specific room only when it is free and of the right type', function () {
    $type = svcType();
    $suite = svcType(['name' => 'Suite']);
    svcRoom($type, '101');
    $second = svcRoom($type, '102');
    $suiteRoom = svcRoom($suite, '201');

    $booking = svcBook($type, '2026-10-12', '2026-10-14', ['room' => $second]);
    expect($booking->room_id)->toBe($second->id);

    expect(fn () => svcBook($type, '2026-10-13', '2026-10-15', ['room' => $second]))
        ->toThrow(BookingException::class, 'is not free');

    expect(fn () => svcBook($type, '2026-10-12', '2026-10-14', ['room' => $suiteRoom]))
        ->toThrow(BookingException::class, 'different room type');
});

it('can create a confirmed booking but refuses other starting statuses', function () {
    $type = svcType();
    svcRoom($type, '101');

    $booking = svcBook($type, '2026-10-12', '2026-10-14', ['status' => BookingStatus::Confirmed]);

    expect($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->confirmed_at)->not->toBeNull()
        ->and($booking->expires_at)->toBeNull();

    expect(fn () => svcBook($type, '2026-10-20', '2026-10-22', ['status' => BookingStatus::CheckedIn]))
        ->toThrow(InvalidArgumentException::class, 'Pending or Confirmed');
});

it('takes the promo discount and counts the use, then refuses the code once its limit is reached', function () {
    $type = svcType();
    svcRoom($type, '101');
    svcRoom($type, '102');
    $promo = svcPromo(['usage_limit' => 1]);

    $booking = svcBook($type, '2026-10-12', '2026-10-14', ['promoCode' => 'welcome10']);

    expect($booking->discount)->toBe(500000)
        ->and($booking->promo_code_id)->toBe($promo->id)
        ->and($booking->total)->toBe(4837500)
        ->and($promo->refresh()->used_count)->toBe(1);

    expect(fn () => svcBook($type, '2026-10-12', '2026-10-14', ['promoCode' => 'WELCOME10']))
        ->toThrow(PricingException::class, 'usage limit');

    expect(Booking::count())->toBe(1)
        ->and($promo->refresh()->used_count)->toBe(1);
});

it('refuses an unknown promo code and saves nothing', function () {
    $type = svcType();
    svcRoom($type, '101');

    expect(fn () => svcBook($type, '2026-10-12', '2026-10-14', ['promoCode' => 'NOPE']))
        ->toThrow(BookingException::class, 'Promo code not found');

    expect(Booking::count())->toBe(0);
});

it('saves nothing when no room is free, even with a valid promo code', function () {
    $type = svcType();
    $promo = svcPromo();

    expect(fn () => svcBook($type, '2026-10-12', '2026-10-14', ['promoCode' => 'WELCOME10']))
        ->toThrow(BookingException::class, 'No room of this type');

    expect(Booking::count())->toBe(0)
        ->and($promo->refresh()->used_count)->toBe(0)
        ->and(ActivityLog::where('action', 'booking.created')->count())->toBe(0);
});

it('passes pricing rule errors through and saves nothing', function () {
    $type = svcType(['min_stay' => 2]);
    svcRoom($type, '101');

    expect(fn () => svcBook($type, '2026-10-12', '2026-10-13'))
        ->toThrow(PricingException::class, 'minimum stay');

    $inactive = svcType(['name' => 'Closed', 'is_active' => false]);
    svcRoom($inactive, '201');

    expect(fn () => svcBook($inactive, '2026-10-12', '2026-10-14'))
        ->toThrow(PricingException::class, 'not available');

    expect(Booking::count())->toBe(0);
});