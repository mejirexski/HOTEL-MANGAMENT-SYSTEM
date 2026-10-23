<?php

use Illuminate\Notifications\AnonymousNotifiable;
use App\Enums\WaitlistStatus;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\RatePlan;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\WaitlistEntry;
use App\Notifications\WaitlistRoomAvailable;
use App\Support\Bookings\BookingActions;
use App\Support\Bookings\BookingException;
use App\Support\Bookings\BookingService;
use App\Support\Bookings\WaitlistService;
use App\Support\HotelSettings;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;

function wlType(array $overrides = []): RoomType
{
    return RoomType::create(array_merge([
        'name' => 'Deluxe', 'base_price' => 2500000, 'max_adults' => 3,
        'max_children' => 2, 'bed_type' => 'King', 'is_active' => true,
    ], $overrides));
}

function wlRoom(RoomType $type, string $number): Room
{
    return Room::create(['room_number' => $number, 'floor' => 1, 'room_type_id' => $type->id]);
}

function wlGuest(?string $email = null, string $name = 'Test Guest'): Guest
{
    return Guest::create(['full_name' => $name, 'email' => $email]);
}

function wlDay(int $offset): string
{
    return now()->addDays($offset)->toDateString();
}

function wlBook(RoomType $type, string $in, string $out): Booking
{
    return app(BookingService::class)->create(
        roomType: $type,
        ratePlan: RatePlan::firstOrCreate(
            ['name' => 'Best Available'],
            ['adjustment_bps' => 0, 'extra_per_night' => 0, 'is_refundable' => true, 'is_active' => true],
        ),
        guest: wlGuest(),
        checkIn: $in,
        checkOut: $out,
        adults: 2,
    );
}

function wlService(): WaitlistService
{
    return app(WaitlistService::class);
}

function wlCancel(Booking $booking): void
{
    app(BookingActions::class)->cancel($booking, 'Guest changed plans');
}

beforeEach(function () {
    HotelSettings::set('waitlist_offer_hours', '24');
    HotelSettings::set('pending_expiry_hours', '24');
});

it('lets a guest join the waitlist only when no room is free', function () {
    $type = wlType();
    wlRoom($type, '101');
    $guest = wlGuest();

    expect(fn () => wlService()->join($type, $guest, wlDay(10), wlDay(12), 2))
        ->toThrow(BookingException::class, 'book it instead');

    wlBook($type, wlDay(10), wlDay(12));

    $entry = wlService()->join($type, $guest, wlDay(10), wlDay(12), 2, notes: 'Wants a quiet room');

    expect($entry->status)->toBe(WaitlistStatus::Waiting)
        ->and($entry->notes)->toBe('Wants a quiet room')
        ->and(ActivityLog::where('action', 'waitlist.joined')->count())->toBe(1);
});

it('refuses waitlist requests that cannot work', function () {
    $type = wlType();
    wlRoom($type, '101');
    wlBook($type, wlDay(10), wlDay(12));
    $guest = wlGuest();

    expect(fn () => wlService()->join($type, $guest, wlDay(-2), wlDay(-1), 2))
        ->toThrow(BookingException::class, 'in the past')
        ->and(fn () => wlService()->join($type, $guest, wlDay(12), wlDay(10), 2))
        ->toThrow(BookingException::class, 'Check-out must be after')
        ->and(fn () => wlService()->join($type, $guest, wlDay(10), wlDay(12), 5))
        ->toThrow(BookingException::class, 'at most 3 adults')
        ->and(fn () => wlService()->join(wlType(['name' => 'Closed', 'is_active' => false]), $guest, wlDay(10), wlDay(12), 2))
        ->toThrow(BookingException::class, 'not available');

    wlService()->join($type, $guest, wlDay(10), wlDay(12), 2);

    expect(fn () => wlService()->join($type, $guest, wlDay(11), wlDay(13), 2))
        ->toThrow(BookingException::class, 'already on the waitlist');
});

it('offers a freed room to the first guest in line and emails them', function () {
    Notification::fake();

    $type = wlType();
    wlRoom($type, '101');
    $booking = wlBook($type, wlDay(10), wlDay(12));

    $first = wlService()->join($type, wlGuest('first@example.com', 'First'), wlDay(10), wlDay(12), 2);
    $second = wlService()->join($type, wlGuest('second@example.com', 'Second'), wlDay(10), wlDay(12), 2);

    expect(wlService()->processRoomType($type))->toBe(0);

    wlCancel($booking);

    expect(wlService()->processRoomType($type))->toBe(1)
        ->and($first->refresh()->status)->toBe(WaitlistStatus::Notified)
        ->and($first->offer_expires_at->isFuture())->toBeTrue()
        ->and($second->refresh()->status)->toBe(WaitlistStatus::Waiting);

    Notification::assertSentOnDemandTimes(WaitlistRoomAvailable::class, 1);
});

it('moves on to the next guest when an offer lapses or is cancelled', function () {
    $type = wlType();
    wlRoom($type, '101');
    $booking = wlBook($type, wlDay(10), wlDay(12));

    $first = wlService()->join($type, wlGuest(null, 'First'), wlDay(10), wlDay(12), 2);
    $second = wlService()->join($type, wlGuest(null, 'Second'), wlDay(10), wlDay(12), 2);
    $third = wlService()->join($type, wlGuest(null, 'Third'), wlDay(10), wlDay(12), 2);

    wlCancel($booking);
    wlService()->processRoomType($type);

    expect($first->refresh()->status)->toBe(WaitlistStatus::Notified)
        ->and($second->refresh()->status)->toBe(WaitlistStatus::Waiting);

    $first->update(['offer_expires_at' => now()->subMinute()]);

    expect(wlService()->processAll())->toBe(['lapsed' => 1, 'notified' => 1])
        ->and($first->refresh()->status)->toBe(WaitlistStatus::Lapsed)
        ->and($second->refresh()->status)->toBe(WaitlistStatus::Notified)
        ->and($third->refresh()->status)->toBe(WaitlistStatus::Waiting);

    wlService()->cancel($second);

    expect($second->refresh()->status)->toBe(WaitlistStatus::Cancelled)
        ->and($third->refresh()->status)->toBe(WaitlistStatus::Notified);
});

it('never offers more rooms than are actually free', function () {
    $type = wlType();
    wlRoom($type, '101');
    wlRoom($type, '102');
    $first = wlBook($type, wlDay(10), wlDay(12));
    $second = wlBook($type, wlDay(10), wlDay(12));

    $entries = collect(['A', 'B', 'C'])->map(
        fn (string $name) => wlService()->join($type, wlGuest(null, $name), wlDay(10), wlDay(12), 2)
    );

    wlCancel($first);
    expect(wlService()->processRoomType($type))->toBe(1);

    wlCancel($second);
    expect(wlService()->processRoomType($type))->toBe(1)
        ->and($entries->map(fn (WaitlistEntry $e) => $e->refresh()->status->value)->all())
        ->toBe(['notified', 'notified', 'waiting']);
});

it('does not offer the same room twice to guests with overlapping dates', function () {
    $type = wlType();
    wlRoom($type, '101');
    $booking = wlBook($type, wlDay(10), wlDay(14));

    $first = wlService()->join($type, wlGuest(null, 'First'), wlDay(10), wlDay(12), 2);
    $second = wlService()->join($type, wlGuest(null, 'Second'), wlDay(11), wlDay(13), 2);

    wlCancel($booking);

    expect(wlService()->processRoomType($type))->toBe(1)
        ->and($first->refresh()->status)->toBe(WaitlistStatus::Notified)
        ->and($second->refresh()->status)->toBe(WaitlistStatus::Waiting);
});

it('notifies a guest without an email address on screen only', function () {
    Notification::fake();

    $type = wlType();
    wlRoom($type, '101');
    $booking = wlBook($type, wlDay(10), wlDay(12));
    $entry = wlService()->join($type, wlGuest(null, 'No Email'), wlDay(10), wlDay(12), 2);

    wlCancel($booking);

    expect(wlService()->processRoomType($type))->toBe(1)
        ->and($entry->refresh()->status)->toBe(WaitlistStatus::Notified);

    Notification::assertNothingSent();
});

it('lapses waitlist entries whose arrival date has passed', function () {
    $type = wlType();

    $entry = WaitlistEntry::create([
        'guest_id' => wlGuest()->id,
        'room_type_id' => $type->id,
        'check_in' => wlDay(-3),
        'check_out' => wlDay(-1),
        'adults' => 2,
        'children' => 0,
        'status' => 'waiting',
    ]);

    expect(wlService()->lapseOverdue())->toBe(1)
        ->and($entry->refresh()->status)->toBe(WaitlistStatus::Lapsed);
});

it('marks an entry as booked once and refuses to change it again', function () {
    $type = wlType();
    wlRoom($type, '101');
    wlBook($type, wlDay(10), wlDay(12));
    $entry = wlService()->join($type, wlGuest(), wlDay(10), wlDay(12), 2);

    expect(wlService()->markBooked($entry)->status)->toBe(WaitlistStatus::Booked);

    expect(fn () => wlService()->markBooked($entry))
        ->toThrow(BookingException::class, 'Only a waiting or notified entry');
});

it('runs the waitlist job from the command line', function () {
    expect(Artisan::call('waitlist:process'))->toBe(0)
        ->and(Artisan::output())->toContain('notified');
});

it('renders a waitlist email with the hotel name, the stay and the contact details', function () {
    HotelSettings::set('hotel_name', 'Blue Diamond Hotel');
    HotelSettings::set('hotel_phone', '08000000000');

    $type = wlType();
    wlRoom($type, '101');
    wlBook($type, wlDay(10), wlDay(12));
    $entry = wlService()->join($type, wlGuest('wendy@example.com', 'Waiting Wendy'), wlDay(10), wlDay(12), 2, 1);
    $entry->update(['status' => 'notified', 'offer_expires_at' => now()->addDay()]);

    $html = (string) (new WaitlistRoomAvailable($entry->refresh()))->toMail(new AnonymousNotifiable)->render();

    expect($html)->toContain('Blue Diamond Hotel')
        ->toContain('Waiting Wendy')
        ->toContain('Deluxe')
        ->toContain('08000000000')
        ->toContain('1 child');
});

it('does not send an offer email that is no longer open', function () {
    Notification::fake();

    $type = wlType();
    wlRoom($type, '101');
    wlBook($type, wlDay(10), wlDay(12));
    $entry = wlService()->join($type, wlGuest('wendy@example.com'), wlDay(10), wlDay(12), 2);

    // Still waiting, never offered.
    Notification::route('mail', 'wendy@example.com')->notify(new WaitlistRoomAvailable($entry));

    // Offered, but the hold has already run out.
    $entry->update(['status' => 'notified', 'offer_expires_at' => now()->subHour()]);
    Notification::route('mail', 'wendy@example.com')->notify(new WaitlistRoomAvailable($entry));

    Notification::assertNothingSent();

    // Offered and still open.
    $entry->update(['offer_expires_at' => now()->addHour()]);
    Notification::route('mail', 'wendy@example.com')->notify(new WaitlistRoomAvailable($entry));

    Notification::assertSentOnDemandTimes(WaitlistRoomAvailable::class, 1);
});