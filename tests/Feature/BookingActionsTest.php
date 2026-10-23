<?php

use App\Enums\BookingStatus;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\PromoCode;
use App\Models\RatePlan;
use App\Models\Room;
use App\Models\RoomType;
use App\Support\Bookings\AvailabilityService;
use App\Support\Bookings\BookingActions;
use App\Support\Bookings\BookingException;
use App\Support\Bookings\BookingService;
use App\Support\HotelSettings;
use App\Support\Pricing\PricingException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;

// 1 March 2027 is a Monday, so 5 and 6 March are the Friday and Saturday nights.

function actType(array $overrides = []): RoomType
{
    return RoomType::create(array_merge([
        'name' => 'Deluxe',
        'base_price' => 2500000,
        'weekend_price' => 3000000,
        'max_adults' => 3,
        'max_children' => 2,
        'bed_type' => 'King',
        'is_active' => true,
    ], $overrides));
}

function actRoom(RoomType $type, string $number): Room
{
    return Room::create(['room_number' => $number, 'floor' => 1, 'room_type_id' => $type->id]);
}

function actPlan(): RatePlan
{
    return RatePlan::firstOrCreate(
        ['name' => 'Best Available'],
        ['adjustment_bps' => 0, 'extra_per_night' => 0, 'is_refundable' => true, 'is_active' => true],
    );
}

function actBook(RoomType $type, string $in, string $out, array $args = []): Booking
{
    return app(BookingService::class)->create(...array_merge([
        'roomType' => $type,
        'ratePlan' => actPlan(),
        'guest' => Guest::create(['full_name' => 'Test Guest']),
        'checkIn' => $in,
        'checkOut' => $out,
        'adults' => 2,
    ], $args));
}

function actActions(): BookingActions
{
    return app(BookingActions::class);
}

function actFree(RoomType $type, string $in, string $out): array
{
    return app(AvailabilityService::class)->freeRooms($type, $in, $out)->pluck('room_number')->all();
}

beforeEach(function () {
    HotelSettings::set('tax_rate', '7.5');
    HotelSettings::set('tax_included', '0');
    HotelSettings::set('service_charge_rate', '0');
    HotelSettings::set('service_charge_included', '0');
    HotelSettings::set('deposit_percent', '30');
    HotelSettings::set('pending_expiry_hours', '24');
});

// ---------------------------------------------------------------- confirm

it('confirms a pending booking', function () {
    $type = actType();
    actRoom($type, '101');
    $booking = actBook($type, '2027-03-01', '2027-03-03');

    $confirmed = actActions()->confirm($booking);

    expect($confirmed->status)->toBe(BookingStatus::Confirmed)
        ->and($confirmed->confirmed_at)->not->toBeNull()
        ->and($confirmed->expires_at)->toBeNull()
        ->and(ActivityLog::where('action', 'booking.confirmed')->count())->toBe(1);
});

it('refuses to confirm a booking that is not pending', function () {
    $type = actType();
    actRoom($type, '101');
    $booking = actBook($type, '2027-03-01', '2027-03-03', ['status' => BookingStatus::Confirmed]);

    expect(fn () => actActions()->confirm($booking))
        ->toThrow(BookingException::class, 'Only a pending booking');
});

it('moves a confirmed hold to another room when its room was taken after the hold ran out', function () {
    $type = actType();
    actRoom($type, '101');
    actRoom($type, '102');

    $held = actBook($type, '2027-03-01', '2027-03-03');
    $held->update(['expires_at' => now()->subHour()]);

    $taker = actBook($type, '2027-03-01', '2027-03-03');
    expect($taker->room->room_number)->toBe('101');

    $confirmed = actActions()->confirm($held);

    expect($confirmed->status)->toBe(BookingStatus::Confirmed)
        ->and($confirmed->room->room_number)->toBe('102');
});

it('refuses to confirm an expired hold when no room is left', function () {
    $type = actType();
    actRoom($type, '101');

    $held = actBook($type, '2027-03-01', '2027-03-03');
    $held->update(['expires_at' => now()->subHour()]);
    actBook($type, '2027-03-01', '2027-03-03');

    expect(fn () => actActions()->confirm($held))
        ->toThrow(BookingException::class, 'released');

    expect($held->refresh()->status)->toBe(BookingStatus::Pending);
});

// ---------------------------------------------------------------- cancel

it('cancels a booking, frees the room and records the reason', function () {
    $type = actType();
    actRoom($type, '101');
    $booking = actBook($type, '2027-03-01', '2027-03-03', ['status' => BookingStatus::Confirmed]);

    expect(actFree($type, '2027-03-01', '2027-03-03'))->toBe([]);

    $cancelled = actActions()->cancel($booking, 'Guest changed plans');

    expect($cancelled->status)->toBe(BookingStatus::Cancelled)
        ->and($cancelled->cancelled_at)->not->toBeNull()
        ->and($cancelled->cancellation_reason)->toBe('Guest changed plans')
        ->and(actFree($type, '2027-03-01', '2027-03-03'))->toBe(['101'])
        ->and(ActivityLog::where('action', 'booking.cancelled')->count())->toBe(1);
});

it('needs a reason to cancel and refuses bookings that are already finished', function () {
    $type = actType();
    actRoom($type, '101');
    $booking = actBook($type, '2027-03-01', '2027-03-03');

    expect(fn () => actActions()->cancel($booking, '   '))
        ->toThrow(BookingException::class, 'reason is required');

    $booking->update(['status' => BookingStatus::CheckedOut]);

    expect(fn () => actActions()->cancel($booking, 'Too late'))
        ->toThrow(BookingException::class, 'Only pending or confirmed');
});

it('gives the promo code use back when a booking is cancelled', function () {
    $type = actType();
    actRoom($type, '101');
    $promo = PromoCode::create(['code' => 'WELCOME10', 'type' => 'percent', 'value' => 1000, 'is_active' => true]);

    $booking = actBook($type, '2027-03-01', '2027-03-03', ['promoCode' => 'WELCOME10']);
    expect($promo->refresh()->used_count)->toBe(1);

    actActions()->cancel($booking, 'Guest changed plans');

    expect($promo->refresh()->used_count)->toBe(0);
});

it('says what the rate plan allows when cancelling', function () {
    $type = actType();
    actRoom($type, '101');
    actRoom($type, '102');

    $far = actBook($type, now()->addDays(30)->toDateString(), now()->addDays(32)->toDateString());
    $terms = actActions()->cancellationTerms($far);

    expect($terms['refundable'])->toBeTrue()
        ->and($terms['within_free_window'])->toBeTrue()
        ->and($terms['free_until'])->not->toBeNull();

    $strict = RatePlan::create([
        'name' => 'Strict', 'adjustment_bps' => 0, 'extra_per_night' => 0,
        'is_refundable' => true, 'free_cancellation_hours' => 72, 'is_active' => true,
    ]);
    $soon = actBook($type, now()->addDay()->toDateString(), now()->addDays(3)->toDateString(), ['ratePlan' => $strict]);

    expect(actActions()->cancellationTerms($soon)['within_free_window'])->toBeFalse();

    $nonRefundable = RatePlan::create([
        'name' => 'Non-refundable', 'adjustment_bps' => 0, 'extra_per_night' => 0,
        'is_refundable' => false, 'is_active' => true,
    ]);
    $locked = actBook($type, now()->addDays(40)->toDateString(), now()->addDays(42)->toDateString(), ['ratePlan' => $nonRefundable]);
    $strictTerms = actActions()->cancellationTerms($locked);

    expect($strictTerms['refundable'])->toBeFalse()
        ->and($strictTerms['within_free_window'])->toBeFalse();
});

// ---------------------------------------------------------------- no-show

it('marks a confirmed booking as a no-show from its arrival date', function () {
    $type = actType();
    actRoom($type, '101');
    $yesterday = CarbonImmutable::now()->subDay()->toDateString();
    $today = CarbonImmutable::now()->toDateString();

    $booking = actBook($type, $yesterday, $today, ['status' => BookingStatus::Confirmed]);

    $result = actActions()->markNoShow($booking);

    expect($result->status)->toBe(BookingStatus::NoShow)
        ->and(ActivityLog::where('action', 'booking.no_show')->count())->toBe(1);
});

it('refuses a no-show before arrival or for a booking that is not confirmed', function () {
    $type = actType();
    actRoom($type, '101');
    actRoom($type, '102');

    $future = actBook($type, '2027-03-01', '2027-03-03', ['status' => BookingStatus::Confirmed]);
    expect(fn () => actActions()->markNoShow($future))
        ->toThrow(BookingException::class, 'from its arrival date');

    $pending = actBook($type, '2027-03-01', '2027-03-03');
    expect(fn () => actActions()->markNoShow($pending))
        ->toThrow(BookingException::class, 'Only a confirmed booking');
});

// ---------------------------------------------------------------- change

it('changes the dates and re-prices the booking', function () {
    $type = actType();
    actRoom($type, '101');
    $booking = actBook($type, '2027-03-01', '2027-03-03');
    expect($booking->total)->toBe(5375000);

    $changed = actActions()->modify($booking, '2027-03-05', '2027-03-07', 2, 0);

    expect($changed->check_in->toDateString())->toBe('2027-03-05')
        ->and($changed->subtotal)->toBe(6000000)
        ->and($changed->tax)->toBe(450000)
        ->and($changed->total)->toBe(6450000)
        ->and($changed->deposit_required)->toBe(1935000)
        ->and($changed->status)->toBe(BookingStatus::Pending)
        ->and(ActivityLog::where('action', 'booking.modified')->count())->toBe(1);
});

it('lets a booking be extended even though the new dates overlap its own', function () {
    $type = actType();
    actRoom($type, '101');
    $booking = actBook($type, '2027-03-01', '2027-03-03');

    $changed = actActions()->modify($booking, '2027-03-01', '2027-03-05', 2, 0);

    expect($changed->room->room_number)->toBe('101')
        ->and($changed->total)->toBe(10750000);
});

it('moves to another room when its own room is taken for the new dates', function () {
    $type = actType();
    $first = actRoom($type, '101');
    actRoom($type, '102');

    $booking = actBook($type, '2027-03-01', '2027-03-03', ['room' => $first]);
    actBook($type, '2027-03-05', '2027-03-07', ['room' => $first]);

    $changed = actActions()->modify($booking, '2027-03-04', '2027-03-08', 2, 0);

    expect($changed->room->room_number)->toBe('102');
});

it('refuses a change when no room is free for the new dates', function () {
    $type = actType();
    $room = actRoom($type, '101');

    $booking = actBook($type, '2027-03-01', '2027-03-03', ['room' => $room]);
    actBook($type, '2027-03-05', '2027-03-07', ['room' => $room]);

    expect(fn () => actActions()->modify($booking, '2027-03-04', '2027-03-06', 2, 0))
        ->toThrow(BookingException::class, 'No room of this type');

    expect($booking->refresh()->check_in->toDateString())->toBe('2027-03-01');
});

it('moves a booking to a chosen free room and refuses a taken one', function () {
    $type = actType();
    $first = actRoom($type, '101');
    $second = actRoom($type, '102');

    $booking = actBook($type, '2027-03-01', '2027-03-03', ['room' => $first]);
    actBook($type, '2027-03-01', '2027-03-03', ['room' => $second]);

    expect(fn () => actActions()->modify($booking, '2027-03-01', '2027-03-03', 2, 0, $second))
        ->toThrow(BookingException::class, 'is not free');

    $third = actRoom($type, '103');
    $moved = actActions()->modify($booking, '2027-03-01', '2027-03-03', 2, 0, $third);

    expect($moved->room->room_number)->toBe('103');
});

it('keeps the promo discount when a booking is changed, even if the code is used up', function () {
    $type = actType();
    actRoom($type, '101');
    $promo = PromoCode::create([
        'code' => 'WELCOME10', 'type' => 'percent', 'value' => 1000, 'usage_limit' => 1, 'is_active' => true,
    ]);

    $booking = actBook($type, '2027-03-01', '2027-03-03', ['promoCode' => 'WELCOME10']);
    expect($booking->discount)->toBe(500000);

    $changed = actActions()->modify($booking, '2027-03-01', '2027-03-04', 2, 0);

    expect($changed->subtotal)->toBe(7500000)
        ->and($changed->discount)->toBe(750000)
        ->and($changed->total)->toBe(7256250)
        ->and($promo->refresh()->used_count)->toBe(1);
});

it('only changes pending or confirmed bookings and still applies the stay rules', function () {
    $type = actType(['min_stay' => 2]);
    actRoom($type, '101');
    $booking = actBook($type, '2027-03-01', '2027-03-03');

    expect(fn () => actActions()->modify($booking, '2027-03-01', '2027-03-02', 2, 0))
        ->toThrow(PricingException::class, 'minimum stay');

    $booking->update(['status' => BookingStatus::CheckedIn]);

    expect(fn () => actActions()->modify($booking, '2027-03-01', '2027-03-04', 2, 0))
        ->toThrow(BookingException::class, 'Only pending or confirmed');
});

it('previews a change without saving it', function () {
    $type = actType();
    actRoom($type, '101');
    actRoom($type, '102');
    $booking = actBook($type, '2027-03-01', '2027-03-03');

    $preview = actActions()->previewChange($booking, '2027-03-05', '2027-03-07', 2, 0);

    expect($preview['quote']->total)->toBe(6450000)
        ->and($preview['rooms']->pluck('room_number')->all())->toBe(['101', '102'])
        ->and($booking->refresh()->check_in->toDateString())->toBe('2027-03-01');
});

// ---------------------------------------------------------------- expiry

it('cancels only the pending bookings whose hold has run out', function () {
    $type = actType();
    foreach (['101', '102', '103'] as $number) {
        actRoom($type, $number);
    }

    $promo = PromoCode::create(['code' => 'WELCOME10', 'type' => 'percent', 'value' => 1000, 'is_active' => true]);

    $overdue = actBook($type, '2027-03-01', '2027-03-03', ['promoCode' => 'WELCOME10']);
    $overdue->update(['expires_at' => now()->subMinute()]);

    $stillHeld = actBook($type, '2027-03-01', '2027-03-03');
    $confirmed = actBook($type, '2027-03-01', '2027-03-03', ['status' => BookingStatus::Confirmed]);

    expect($promo->refresh()->used_count)->toBe(1);

    expect(actActions()->expireOverdue())->toBe(1);

    expect($overdue->refresh()->status)->toBe(BookingStatus::Cancelled)
        ->and($overdue->cancellation_reason)->toBe('Deposit not received in time')
        ->and($stillHeld->refresh()->status)->toBe(BookingStatus::Pending)
        ->and($confirmed->refresh()->status)->toBe(BookingStatus::Confirmed)
        ->and($promo->refresh()->used_count)->toBe(0)
        ->and(ActivityLog::where('action', 'booking.expired')->count())->toBe(1);

    expect(actActions()->expireOverdue())->toBe(0);
});

it('runs the expiry job from the command line', function () {
    $type = actType();
    actRoom($type, '101');
    actBook($type, '2027-03-01', '2027-03-03')->update(['expires_at' => now()->subMinute()]);

    $exitCode = Artisan::call('bookings:expire-pending');

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Cancelled 1 expired pending booking');
});