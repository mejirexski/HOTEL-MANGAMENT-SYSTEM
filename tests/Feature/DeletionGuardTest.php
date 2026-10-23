<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\RatePlan;
use App\Models\Room;
use App\Models\RoomType;
use App\Support\Bookings\BookingException;
use App\Support\Bookings\BookingService;
use Carbon\CarbonImmutable;

function guardType(): RoomType
{
    return RoomType::create([
        'name' => 'Deluxe', 
        'base_price' => 2500000, 
        'max_adults' => 3,
        'max_children' => 2, 
        'bed_type' => 'King', 
        'is_active' => true,
    ]);
}

function guardRoom(RoomType $type, string $number = '101'): Room
{
    return Room::create(['room_number' => $number, 'floor' => 1, 'room_type_id' => $type->id]);
}

function guardPlan(string $name = 'Best Available'): RatePlan
{
    return RatePlan::create([
        'name' => $name, 
        'adjustment_bps' => 0, 
        'extra_per_night' => 0,
        'is_refundable' => true, 
        'is_active' => true,
    ]);
}

function guardDay(int $offset): string
{
    return CarbonImmutable::now()->addDays($offset)->toDateString();
}

function guardBook(Room $room, RatePlan $plan, string $in, string $out): Booking
{
    return app(BookingService::class)->create(
        roomType: $room->roomType,
        ratePlan: $plan,
        guest: Guest::create(['full_name' => 'Test Guest']),
        checkIn: $in,
        checkOut: $out,
        adults: 2,
        room: $room,
    );
}

it('refuses to delete a room with a current or future booking and allows it once that is cancelled', function () {
    $room = guardRoom(guardType());
    $booking = guardBook($room, guardPlan(), guardDay(10), guardDay(12));
    $booking->update(['status' => BookingStatus::Confirmed]);

    expect(fn () => $room->delete())->toThrow(BookingException::class, 'current or future bookings');
    expect(Room::find($room->id))->not->toBeNull();

    $booking->update(['status' => BookingStatus::Cancelled]);

    expect($room->delete())->toBeTrue()
        ->and(Room::find($room->id))->toBeNull()
        ->and(Room::withTrashed()->find($room->id))->not->toBeNull();
});

it('only lets bookings that still matter block the deletion of a room', function (string $status, bool $blocks) {
    $room = guardRoom(guardType());
    $booking = guardBook($room, guardPlan(), guardDay(10), guardDay(12));
    $booking->update(['status' => BookingStatus::from($status)]);

    if ($blocks) {
        expect(fn () => $room->delete())->toThrow(BookingException::class);
    } else {
        expect($room->delete())->toBeTrue();
    }
})->with([
    'pending hold' => ['pending', true],
    'confirmed' => ['confirmed', true],
    'checked in' => ['checked_in', true],
    'checked out' => ['checked_out', false],
    'cancelled' => ['cancelled', false],
    'no-show' => ['no_show', false],
]);

it('does not let a finished stay block the deletion of a room, and the old booking still shows the room', function () {
    $room = guardRoom(guardType());
    $booking = guardBook($room, guardPlan(), guardDay(-3), guardDay(-1));
    $booking->update(['status' => BookingStatus::Confirmed]);

    expect($room->delete())->toBeTrue()
        ->and($booking->refresh()->room->room_number)->toBe('101');
});

it('still blocks the deletion of a room while a guest is checked in, even past the booked check-out', function () {
    $room = guardRoom(guardType());
    $booking = guardBook($room, guardPlan(), guardDay(-3), guardDay(-1));
    $booking->update(['status' => BookingStatus::CheckedIn]);

    expect(fn () => $room->delete())->toThrow(BookingException::class);
});

it('does not let an expired hold block the deletion of a room', function () {
    $room = guardRoom(guardType());
    $booking = guardBook($room, guardPlan(), guardDay(10), guardDay(12));
    $booking->update(['expires_at' => now()->subHour()]);

    expect($room->delete())->toBeTrue();
});

it('blocks a permanent delete of a room that has a current booking too', function () {
    $room = guardRoom(guardType());
    guardBook($room, guardPlan(), guardDay(10), guardDay(12));

    expect(fn () => $room->forceDelete())->toThrow(BookingException::class);
});

it('refuses to delete a rate plan that current or future bookings use', function () {
    $room = guardRoom(guardType());
    $plan = guardPlan();
    $booking = guardBook($room, $plan, guardDay(10), guardDay(12));

    expect(fn () => $plan->delete())->toThrow(BookingException::class, 'Deactivate it instead');

    $booking->update(['status' => BookingStatus::Cancelled]);

    expect($plan->delete())->toBeTrue()
        ->and($booking->refresh()->ratePlan->name)->toBe('Best Available');
});

it('refuses to delete a room type that still has rooms', function () {
    $type = guardType();
    $room = guardRoom($type);

    expect(fn () => $type->delete())->toThrow(BookingException::class, 'still has rooms');

    $room->delete();

    expect($type->delete())->toBeTrue()
        ->and(RoomType::withTrashed()->find($type->id))->not->toBeNull();
});