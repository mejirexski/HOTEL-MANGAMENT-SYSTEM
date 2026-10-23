<?php

use Illuminate\Support\Facades\Route;

// Welcome route
Route::get('/', function () {
    return view('welcome');
});

// Auth routes
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
});

// Stuff routes
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'role:owner|general-manager',
])->get('/staff', fn () => view('staff.index'))->name('staff.index');

// Audit log routes
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'role:owner|general-manager',
])->get('/audit-log', fn () => view('audit.index'))->name('audit.index');

// Settings routes
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'role:owner|general-manager',
])->get('/settings', fn () => view('settings.index'))->name('settings.index');

// Room type routes
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'role:owner|general-manager|receptionist|cashier|housekeeping|maintenance',
])->get('/room-types', fn () => view('room-types.index'))->name('room-types.index');

// Room routes
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'role:owner|general-manager|receptionist|cashier|housekeeping|maintenance',
])->get('/rooms', fn () => view('rooms.index'))->name('rooms.index');

// Rate plan routes
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'role:owner|general-manager|receptionist|cashier|housekeeping|maintenance',
])->get('/rate-plans', fn () => view('rate-plans.index'))->name('rate-plans.index');

// Room type pricing routes
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'role:owner|general-manager|receptionist|cashier|housekeeping|maintenance',
])->get('/room-types/{roomType}/pricing', fn (\App\Models\RoomType $roomType) => view('room-types.pricing', ['roomType' => $roomType]))
    ->name('room-types.pricing');

// Promo code routes
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'role:owner|general-manager',
])->get('/promo-codes', fn () => view('promo-codes.index'))->name('promo-codes.index');

// Booking routes
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'role:owner|general-manager|receptionist|cashier',
])->get('/price-quote', fn () => view('price-quote.index'))->name('price-quote.index');

// Booking routes
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'role:owner|general-manager|receptionist|cashier',
])->get('/bookings', fn () => view('bookings.index'))->name('bookings.index');
// Booking create route
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'role:owner|general-manager|receptionist',
])->get('/bookings/create', fn () => view('bookings.create'))->name('bookings.create');

// Booking show route
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'role:owner|general-manager|receptionist|cashier',
])->get('/bookings/{booking}', fn (\App\Models\Booking $booking) => view('bookings.show', ['booking' => $booking]))
    ->whereNumber('booking')
    ->name('bookings.show');

// Booking edit route
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'role:owner|general-manager|receptionist',
])->get('/waitlist', fn () => view('waitlist.index'))->name('waitlist.index');


if (app()->environment('local')) {
    Route::get('/dev/mail-preview/waitlist', function () {
        $entry = \App\Models\WaitlistEntry::with(['guest', 'roomType'])->latest('id')->firstOrFail();

        return (new \App\Notifications\WaitlistRoomAvailable($entry))
            ->toMail(new \Illuminate\Notifications\AnonymousNotifiable);
    })->middleware(['auth:sanctum', config('jetstream.auth_session'), 'role:owner'])
        ->name('dev.mail-preview.waitlist');
}

// Guest routes
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'role:owner|general-manager|receptionist|cashier',
])->get('/guests', fn () => view('guests.index'))->name('guests.index');

// Guest create route
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'role:owner|general-manager|receptionist',
])->get('/guests/create', fn () => view('guests.create'))->name('guests.create');

// Guest show route
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'role:owner|general-manager|receptionist|cashier',
])->get('/guests/{guest}', fn (\App\Models\Guest $guest) => view('guests.show', ['guest' => $guest]))
    ->whereNumber('guest')
    ->name('guests.show');

// Guest edit route
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'role:owner|general-manager|receptionist',
])->get('/guests/{guest}/edit', fn (\App\Models\Guest $guest) => view('guests.edit', ['guest' => $guest]))
    ->whereNumber('guest')
    ->name('guests.edit');