<?php

namespace App\Support\Bookings;

use App\Enums\WaitlistStatus;
use App\Models\Guest;
use App\Models\RoomType;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Notifications\WaitlistRoomAvailable;
use App\Support\AuditLog;
use App\Support\HotelSettings;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class WaitlistService
{
    public function __construct(private readonly AvailabilityService $availability)
    {
    }

    /** Puts a guest on the waitlist. Only allowed when no room of the type is free for those dates. */
    public function join(
        RoomType $roomType,
        Guest $guest,
        CarbonInterface|string $checkIn,
        CarbonInterface|string $checkOut,
        int $adults,
        int $children = 0,
        ?string $notes = null,
        ?User $actor = null,
    ): WaitlistEntry {
        $in = CarbonImmutable::parse($checkIn)->startOfDay();
        $out = CarbonImmutable::parse($checkOut)->startOfDay();

        if ($out->lessThanOrEqualTo($in)) {
            throw new BookingException('Check-out must be after check-in.');
        }
        if ($in->toDateString() < now()->toDateString()) {
            throw new BookingException('The arrival date cannot be in the past.');
        }
        if (! $roomType->is_active) {
            throw new BookingException('This room type is not available for booking.');
        }
        if ($adults < 1 || $adults > $roomType->max_adults) {
            throw new BookingException("This room type allows at most {$roomType->max_adults} adults.");
        }
        if ($children < 0 || $children > $roomType->max_children) {
            throw new BookingException("This room type allows at most {$roomType->max_children} children.");
        }

        if ($this->availability->freeRooms($roomType, $in, $out)->isNotEmpty()) {
            throw new BookingException('A room is available for those dates, so book it instead of joining the waitlist.');
        }

        $duplicate = WaitlistEntry::query()->active()
            ->where('guest_id', $guest->id)
            ->where('room_type_id', $roomType->id)
            ->whereDate('check_in', '<', $out->toDateString())
            ->whereDate('check_out', '>', $in->toDateString())
            ->exists();

        if ($duplicate) {
            throw new BookingException('This guest is already on the waitlist for those dates.');
        }

        $entry = WaitlistEntry::create([
            'guest_id' => $guest->id,
            'room_type_id' => $roomType->id,
            'check_in' => $in->toDateString(),
            'check_out' => $out->toDateString(),
            'adults' => $adults,
            'children' => $children,
            'status' => WaitlistStatus::Waiting,
            'notes' => $notes,
            'created_by' => ($actor ?? Auth::user())?->id,
        ]);

        AuditLog::record('waitlist.joined', $entry, null, $this->snapshot($entry), $actor);

        return $entry;
    }

    public function cancel(WaitlistEntry $entry, ?User $actor = null): WaitlistEntry
    {
        $result = $this->close($entry, WaitlistStatus::Cancelled, 'waitlist.cancelled', $actor);

        // An offer that was never taken can now go to the next guest in line.
        $this->processRoomType(RoomType::withTrashed()->findOrFail($entry->room_type_id));

        return $result;
    }

    public function markBooked(WaitlistEntry $entry, ?User $actor = null): WaitlistEntry
    {
        return $this->close($entry, WaitlistStatus::Booked, 'waitlist.booked', $actor);
    }

    /**
     * Offers freed rooms to the guests waiting longest. Never offers more rooms than are free,
     * counting offers that are still being held. Returns how many offers it made.
     */
    public function processRoomType(RoomType $roomType): int
    {
        $hours = max(1, (int) HotelSettings::get('waitlist_offer_hours'));

        return DB::transaction(function () use ($roomType, $hours) {
            // The same lock the booking service takes, so offers and new bookings never fight over a room.
            RoomType::withTrashed()->whereKey($roomType->id)->lockForUpdate()->first();

            $offered = 0;

            $waiting = WaitlistEntry::query()
                ->where('room_type_id', $roomType->id)
                ->where('status', WaitlistStatus::Waiting->value)
                ->whereDate('check_in', '>=', now()->toDateString())
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();

            foreach ($waiting as $entry) {
                $in = $entry->check_in->toDateString();
                $out = $entry->check_out->toDateString();

                $free = $this->availability->freeRooms($roomType, $in, $out)->count();

                $heldOffers = WaitlistEntry::query()
                    ->where('room_type_id', $roomType->id)
                    ->where('status', WaitlistStatus::Notified->value)
                    ->where('offer_expires_at', '>', now())
                    ->whereDate('check_in', '<', $out)
                    ->whereDate('check_out', '>', $in)
                    ->count();

                if ($free > $heldOffers) {
                    $this->offer($entry, $hours);
                    $offered++;
                }
            }

            return $offered;
        });
    }

    /** Ends offers nobody took and entries whose arrival date has passed. Returns how many it ended. */
    public function lapseOverdue(): int
    {
        $entries = WaitlistEntry::query()
            ->active()
            ->where(function ($query) {
                $query->where(function ($offer) {
                    $offer->where('status', WaitlistStatus::Notified->value)
                        ->where('offer_expires_at', '<=', now());
                })->orWhereDate('check_in', '<', now()->toDateString());
            })
            ->get();

        foreach ($entries as $entry) {
            $before = ['status' => $entry->status->value];

            $entry->update(['status' => WaitlistStatus::Lapsed]);

            AuditLog::record('waitlist.lapsed', $entry, $before, ['status' => WaitlistStatus::Lapsed->value]);
        }

        return $entries->count();
    }

    /** @return array{lapsed: int, notified: int} */
    public function processAll(): array
    {
        $lapsed = $this->lapseOverdue();
        $notified = 0;

        $typeIds = WaitlistEntry::query()
            ->where('status', WaitlistStatus::Waiting->value)
            ->distinct()
            ->pluck('room_type_id');

        foreach (RoomType::withTrashed()->whereIn('id', $typeIds)->get() as $type) {
            $notified += $this->processRoomType($type);
        }

        return ['lapsed' => $lapsed, 'notified' => $notified];
    }

    // ------------------------------------------------------------------ helpers

    private function offer(WaitlistEntry $entry, int $hours): void
    {
        $entry->update([
            'status' => WaitlistStatus::Notified,
            'notified_at' => now(),
            'offer_expires_at' => now()->addHours($hours),
        ]);

        AuditLog::record(
            'waitlist.notified',
            $entry,
            ['status' => WaitlistStatus::Waiting->value],
            [
                'status' => WaitlistStatus::Notified->value,
                'offer_expires_at' => $entry->offer_expires_at->toDateTimeString(),
            ],
        );

        $email = $entry->guest?->email;

        if ($email) {
            Notification::route('mail', $email)->notify(new WaitlistRoomAvailable($entry));
        }
    }

    private function close(WaitlistEntry $entry, WaitlistStatus $to, string $action, ?User $actor): WaitlistEntry
    {
        return DB::transaction(function () use ($entry, $to, $action, $actor) {
            $locked = WaitlistEntry::query()->whereKey($entry->id)->lockForUpdate()->first();

            if (! $locked || ! in_array($locked->status, [WaitlistStatus::Waiting, WaitlistStatus::Notified], true)) {
                throw new BookingException('Only a waiting or notified entry can be changed.');
            }

            $before = ['status' => $locked->status->value];

            $locked->update(['status' => $to]);

            AuditLog::record($action, $locked, $before, ['status' => $to->value], $actor);

            return $locked;
        });
    }

    private function snapshot(WaitlistEntry $entry): array
    {
        return [
            'guest_id' => $entry->guest_id,
            'room_type_id' => $entry->room_type_id,
            'check_in' => $entry->check_in->toDateString(),
            'check_out' => $entry->check_out->toDateString(),
            'adults' => $entry->adults,
            'children' => $entry->children,
            'status' => $entry->status->value,
        ];
    }
}