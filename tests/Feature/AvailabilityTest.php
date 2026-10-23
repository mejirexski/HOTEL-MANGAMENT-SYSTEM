<?php

use App\Models\Booking;
use App\Models\Guest;
use App\Models\RatePlan;
use App\Models\Room;
use App\Models\RoomType;
use App\Support\Bookings\AvailabilityService;

function availRoomType(array $overrides = []): RoomType
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

function availRoom(RoomType $roomType, string $number): Room
{
    return Room::create(['room_number' => $number, 'floor' => 1, 'room_type_id' => $roomType->id]);
}

function availBooking(Room $room, string $in, string $out, string $status = 'confirmed', array $overrides = []): Booking
{
    static $counter = 0;
    $counter++;

    $guest = Guest::create(['full_name' => 'Guest ' . $counter]);
    $plan = RatePlan::firstOrCreate(
        ['name' => 'Best Available'],
        ['adjustment_bps' => 0, 'extra_per_night' => 0, 'is_refundable' => true, 'is_active' => true],
    );

    return Booking::create(array_merge([
        'reference' => 'TEST-' . $counter,
        'guest_id' => $guest->id,
        'room_id' => $room->id,
        'room_type_id' => $room->room_type_id,
        'rate_plan_id' => $plan->id,
        'check_in' => $in,
        'check_out' => $out,
        'adults' => 2,
        'children' => 0,
        'status' => $status,
    ], $overrides));
}

function freeNumbers(RoomType $roomType, string $in, string $out, ?int $ignoreBookingId = null): array
{
    return app(AvailabilityService::class)
        ->freeRooms($roomType, $in, $out, $ignoreBookingId)
        ->pluck('room_number')
        ->all();
}

it('lists the free rooms of a type in natural room-number order', function () {
    $type = availRoomType();
    availRoom($type, '101');
    availRoom($type, '9');
    availRoom($type, '10');

    expect(freeNumbers($type, '2026-11-10', '2026-11-12'))->toBe(['9', '10', '101']);
});

it('applies the overlap rule to a booking from 10 to 15 November', function (string $in, string $out, bool $roomIsFree) {
    $type = availRoomType();
    $room = availRoom($type, '101');
    availBooking($room, '2026-11-10', '2026-11-15');

    expect(freeNumbers($type, $in, $out))->toBe($roomIsFree ? ['101'] : []);
})->with([
    'inside the booking' => ['2026-11-11', '2026-11-13', false],
    'overlaps the start' => ['2026-11-08', '2026-11-11', false],
    'overlaps the end' => ['2026-11-14', '2026-11-17', false],
    'covers the whole booking' => ['2026-11-08', '2026-11-20', false],
    'identical dates' => ['2026-11-10', '2026-11-15', false],
    'one night on the last booked night' => ['2026-11-14', '2026-11-15', false],
    'ends on the check-in day' => ['2026-11-07', '2026-11-10', true],
    'starts on the check-out day' => ['2026-11-15', '2026-11-18', true],
    'well before' => ['2026-11-01', '2026-11-03', true],
    'well after' => ['2026-12-01', '2026-12-03', true],
]);

it('only blocks a room for the statuses that hold it', function (string $status, bool $blocks) {
    $type = availRoomType();
    $room = availRoom($type, '101');
    availBooking($room, '2026-11-10', '2026-11-15', $status);

    expect(freeNumbers($type, '2026-11-11', '2026-11-12'))->toBe($blocks ? [] : ['101']);
})->with([
    'confirmed' => ['confirmed', true],
    'checked in' => ['checked_in', true],
    'pending without an expiry' => ['pending', true],
    'checked out' => ['checked_out', false],
    'cancelled' => ['cancelled', false],
    'no-show' => ['no_show', false],
]);

it('stops an expired pending booking from blocking but not a pending one that is still valid', function () {
    $type = availRoomType();
    $expiredRoom = availRoom($type, '101');
    $validRoom = availRoom($type, '102');

    availBooking($expiredRoom, '2026-11-10', '2026-11-15', 'pending', ['expires_at' => now()->subHour()]);
    availBooking($validRoom, '2026-11-10', '2026-11-15', 'pending', ['expires_at' => now()->addHour()]);

    expect(freeNumbers($type, '2026-11-11', '2026-11-12'))->toBe(['101']);
});

it('ignores deleted bookings and does not offer deleted rooms', function () {
    $type = availRoomType();
    $bookedRoom = availRoom($type, '101');
    $deletedRoom = availRoom($type, '102');

    availBooking($bookedRoom, '2026-11-10', '2026-11-15')->delete();
    $deletedRoom->delete();

    expect(freeNumbers($type, '2026-11-11', '2026-11-12'))->toBe(['101']);
});

it('only looks at rooms of the requested type', function () {
    $deluxe = availRoomType();
    $suite = availRoomType(['name' => 'Suite']);
    availRoom($deluxe, '101');
    availRoom($suite, '201');

    expect(freeNumbers($deluxe, '2026-11-10', '2026-11-12'))->toBe(['101'])
        ->and(freeNumbers($suite, '2026-11-10', '2026-11-12'))->toBe(['201']);
});

it('lets a booking be ignored so it does not block its own change', function () {
    $type = availRoomType();
    $room = availRoom($type, '101');
    $booking = availBooking($room, '2026-11-10', '2026-11-15');

    expect(freeNumbers($type, '2026-11-12', '2026-11-17'))->toBe([])
        ->and(freeNumbers($type, '2026-11-12', '2026-11-17', $booking->id))->toBe(['101']);
});

it('answers whether one room is free', function () {
    $type = availRoomType();
    $room = availRoom($type, '101');
    $booking = availBooking($room, '2026-11-10', '2026-11-15');
    $service = app(AvailabilityService::class);

    expect($service->isRoomFree($room, '2026-11-11', '2026-11-12'))->toBeFalse()
        ->and($service->isRoomFree($room, '2026-11-15', '2026-11-16'))->toBeTrue()
        ->and($service->isRoomFree($room, '2026-11-11', '2026-11-12', $booking->id))->toBeTrue();
});

it('lists only the room types that fit the party and still have a free room', function () {
    $deluxe = availRoomType(['name' => 'Deluxe']);
    $suite = availRoomType(['name' => 'Suite']);
    $single = availRoomType(['name' => 'Single', 'max_adults' => 1, 'max_children' => 0]);
    $closed = availRoomType(['name' => 'Closed', 'is_active' => false]);
    $full = availRoomType(['name' => 'Full']);

    $deluxeRoom = availRoom($deluxe, '101');
    availRoom($deluxe, '102');
    availRoom($suite, '201');
    availRoom($single, '301');
    availRoom($closed, '401');
    $fullRoom = availRoom($full, '501');

    availBooking($fullRoom, '2026-11-10', '2026-11-15');
    availBooking($deluxeRoom, '2026-11-10', '2026-11-15');

    $result = app(AvailabilityService::class)->availableRoomTypes('2026-11-11', '2026-11-13', adults: 2);

    expect($result->mapWithKeys(fn (array $row) => [$row['room_type']->name => $row['free']])->all())
        ->toBe(['Deluxe' => 1, 'Suite' => 1]);
});

it('rejects a stay that does not end after it starts', function () {
    $type = availRoomType();

    expect(fn () => app(AvailabilityService::class)->freeRooms($type, '2026-11-12', '2026-11-12'))
        ->toThrow(InvalidArgumentException::class, 'Check-out must be after check-in');
});