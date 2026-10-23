<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\RatePlan;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Support\Bookings\BookingException;
use App\Support\Bookings\BookingService;
use App\Support\GuestAccess;
use Illuminate\Support\Facades\DB;

function gstGuest(array $overrides = []): Guest
{
    return Guest::create(array_merge(['full_name' => 'Test Guest', 'phone' => '08061234567'], $overrides));
}

function gstDay(int $offset): string
{
    return now()->addDays($offset)->toDateString();
}

function gstBook(Guest $guest, string $in, string $out): Booking
{
    $type = RoomType::firstOrCreate(
        ['name' => 'Deluxe'],
        ['base_price' => 2500000, 'max_adults' => 3, 'max_children' => 2, 'bed_type' => 'King', 'is_active' => true],
    );
    $room = Room::create(['room_number' => 'G' . uniqid(), 'floor' => 1, 'room_type_id' => $type->id]);
    $plan = RatePlan::firstOrCreate(
        ['name' => 'Best Available'],
        ['adjustment_bps' => 0, 'extra_per_night' => 0, 'is_refundable' => true, 'is_active' => true],
    );

    return app(BookingService::class)->create(
        roomType: $type, ratePlan: $plan, guest: $guest,
        checkIn: $in, checkOut: $out, adults: 2, room: $room,
    );
}

it('stores the phone digits and a lower-case email so guests can be matched', function () {
    $ada = gstGuest(['phone' => '+234 (806) 123-4567', 'email' => '  Ada@Example.COM ']);
    $other = gstGuest(['full_name' => 'Other', 'phone' => '08061234567']);

    expect(Guest::digitsOf('+234 806 123 4567'))->toBe('8061234567')
        ->and($ada->phone_digits)->toBe('8061234567')
        ->and($other->phone_digits)->toBe('8061234567')
        ->and($ada->email)->toBe('ada@example.com');
});

it('keeps the id number encrypted and masks it by default', function () {
    $guest = gstGuest(['id_type' => 'National ID (NIN)', 'id_number' => '12345678901']);

    expect($guest->refresh()->id_number)->toBe('12345678901')
        ->and(DB::table('guests')->where('id', $guest->id)->value('id_number'))->not->toBe('12345678901')
        ->and($guest->maskedIdNumber())->toBe('••••8901')
        ->and(gstGuest(['id_number' => '123'])->maskedIdNumber())->toBe('•••')
        ->and(gstGuest(['id_number' => null])->maskedIdNumber())->toBeNull()
        ->and($guest->toArray())->not->toHaveKey('id_number');
});

it('finds guests by name, phone in any format, email and booking reference', function () {
    gstGuest(['full_name' => 'Ada Obi', 'phone' => '0803 111 2222', 'email' => 'ada@example.com']);
    $bola = gstGuest(['full_name' => 'Bola Ade', 'phone' => '08099998888']);
    $booking = gstBook($bola, gstDay(10), gstDay(12));

    $names = fn (string $term) => Guest::search($term)->orderBy('full_name')->pluck('full_name')->all();

    expect($names('ada@'))->toBe(['Ada Obi'])
        ->and($names('Obi'))->toBe(['Ada Obi'])
        ->and($names('803111'))->toBe(['Ada Obi'])
        ->and($names('+234 803 111 2222'))->toBe(['Ada Obi'])
        ->and($names($booking->reference))->toBe(['Bola Ade'])
        ->and($names('zzz'))->toBe([])
        ->and($names('   '))->toBe(['Ada Obi', 'Bola Ade']);
});

it('finds probable duplicates by phone or email without matching itself', function () {
    $ada = gstGuest(['full_name' => 'Ada Obi', 'phone' => '08061234567', 'email' => 'ada@example.com']);

    expect(Guest::findDuplicates('+234 806 123 4567', null)->pluck('id')->all())->toBe([$ada->id])
        ->and(Guest::findDuplicates('0700000000', 'ADA@Example.com')->pluck('id')->all())->toBe([$ada->id])
        ->and(Guest::findDuplicates('08061234567', 'ada@example.com', $ada->id))->toBeEmpty()
        ->and(Guest::findDuplicates('0700000000', 'other@example.com'))->toBeEmpty()
        ->and(Guest::findDuplicates(null, ''))->toBeEmpty();
});

it('refuses to delete a guest with a current or future booking and allows it afterwards', function () {
    $guest = gstGuest();
    $booking = gstBook($guest, gstDay(10), gstDay(12));

    expect(fn () => $guest->delete())->toThrow(BookingException::class, 'cannot be deleted');

    $booking->update(['status' => BookingStatus::Cancelled]);

    expect($guest->delete())->toBeTrue()
        ->and($booking->refresh()->guest->full_name)->toBe('Test Guest');
});

it('lets a guest with only finished stays be deleted', function () {
    $guest = gstGuest();
    gstBook($guest, gstDay(-5), gstDay(-3))->update(['status' => BookingStatus::CheckedOut]);

    expect($guest->delete())->toBeTrue();
});

it('refuses to delete a guest who is on the waitlist', function () {
    $guest = gstGuest();
    $type = RoomType::create([
        'name' => 'Suite', 'base_price' => 4000000, 'max_adults' => 2,
        'max_children' => 0, 'bed_type' => 'King', 'is_active' => true,
    ]);

    $entry = WaitlistEntry::create([
        'guest_id' => $guest->id, 'room_type_id' => $type->id,
        'check_in' => gstDay(10), 'check_out' => gstDay(12),
        'adults' => 2, 'children' => 0, 'status' => 'waiting',
    ]);

    expect(fn () => $guest->delete())->toThrow(BookingException::class);

    $entry->update(['status' => 'cancelled']);

    expect($guest->delete())->toBeTrue();
});

it('limits guest records and id details by role', function (string $role, bool $view, bool $manage, bool $id, bool $delete) {
    $user = User::factory()->create();
    $user->assignRole($role);

    expect(GuestAccess::canView($user))->toBe($view)
        ->and(GuestAccess::canManage($user))->toBe($manage)
        ->and(GuestAccess::canSeeIdDetails($user))->toBe($id)
        ->and(GuestAccess::canDelete($user))->toBe($delete);
})->with([
    'owner' => ['owner', true, true, true, true],
    'general manager' => ['general-manager', true, true, true, true],
    'receptionist' => ['receptionist', true, true, true, false],
    'cashier' => ['cashier', true, false, false, false],
    'housekeeping' => ['housekeeping', false, false, false, false],
    'guest' => ['guest', false, false, false, false],
]);