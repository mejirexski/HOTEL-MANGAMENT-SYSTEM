<?php

namespace App\Support\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\PromoCode;
use App\Models\RatePlan;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Support\AuditLog;
use App\Support\HotelSettings;
use App\Support\Pricing\PriceCalculator;
use App\Support\Pricing\PriceQuote;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BookingActions
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly PriceCalculator $calculator,
    ) {
    }

    // ------------------------------------------------------------------ confirm

    public function confirm(Booking $booking, ?User $actor = null): Booking
    {
        return DB::transaction(function () use ($booking, $actor) {
            [$type, $locked] = $this->lockBooking($booking);

            if ($locked->status !== BookingStatus::Pending) {
                throw new BookingException('Only a pending booking can be confirmed.');
            }

            $in = $locked->check_in->toDateString();
            $out = $locked->check_out->toDateString();

            $before = [
                'status' => $locked->status->value,
                'room_id' => $locked->room_id,
                'expires_at' => $locked->expires_at?->toDateTimeString(),
            ];

            $roomId = $locked->room_id;
            $room = Room::query()->find($locked->room_id);

            // If the hold ran out, someone else may have taken the room in the meantime.
            if (! $room || ! $this->availability->isRoomFree($room, $in, $out, $locked->id)) {
                $replacement = $this->availability->freeRooms($type, $in, $out, $locked->id)->first();

                if (! $replacement) {
                    throw new BookingException(
                        'The room was released when the hold ran out and no room of this type is free for these dates any more.'
                    );
                }

                $roomId = $replacement->id;
            }

            $locked->update([
                'status' => BookingStatus::Confirmed,
                'room_id' => $roomId,
                'confirmed_at' => now(),
                'expires_at' => null,
            ]);

            AuditLog::record('booking.confirmed', $locked, $before, [
                'status' => BookingStatus::Confirmed->value,
                'room_id' => $roomId,
                'expires_at' => null,
            ], $actor);

            return $locked;
        });
    }

    // ------------------------------------------------------------------ cancel

    public function cancel(Booking $booking, string $reason, ?User $actor = null): Booking
    {
        $reason = mb_substr(trim($reason), 0, 255);

        if ($reason === '') {
            throw new BookingException('A cancellation reason is required.');
        }

        return DB::transaction(function () use ($booking, $reason, $actor) {
            $locked = $this->lockBookingOnly($booking);

            if (! in_array($locked->status, [BookingStatus::Pending, BookingStatus::Confirmed], true)) {
                throw new BookingException('Only pending or confirmed bookings can be cancelled.');
            }

            $terms = $this->cancellationTerms($locked);
            $before = ['status' => $locked->status->value];

            $locked->update([
                'status' => BookingStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            $this->releasePromoUse($locked);

            AuditLog::record('booking.cancelled', $locked, $before, [
                'status' => BookingStatus::Cancelled->value,
                'reason' => $reason,
                'refundable' => $terms['refundable'],
                'within_free_window' => $terms['within_free_window'],
            ], $actor);

            return $locked;
        });
    }

    /**
     * What the rate plan's policy says about cancelling now.
     *
     * @return array{refundable: bool, free_until: ?CarbonImmutable, within_free_window: bool}
     */
    public function cancellationTerms(Booking $booking): array
    {
        $plan = $booking->ratePlan;

        if (! $plan || ! $plan->is_refundable) {
            return ['refundable' => false, 'free_until' => null, 'within_free_window' => false];
        }

        $hours = $plan->free_cancellation_hours ?? (int) HotelSettings::get('free_cancellation_hours');

        $arrival = CarbonImmutable::parse(
            $booking->check_in->toDateString() . ' ' . HotelSettings::get('check_in_time')
        );
        $freeUntil = $arrival->subHours($hours);

        return [
            'refundable' => true,
            'free_until' => $freeUntil,
            'within_free_window' => now()->lessThanOrEqualTo($freeUntil),
        ];
    }

    // ------------------------------------------------------------------ no-show

    public function markNoShow(Booking $booking, ?User $actor = null): Booking
    {
        return DB::transaction(function () use ($booking, $actor) {
            $locked = $this->lockBookingOnly($booking);

            if ($locked->status !== BookingStatus::Confirmed) {
                throw new BookingException('Only a confirmed booking can be marked as a no-show.');
            }

            if ($locked->check_in->toDateString() > now()->toDateString()) {
                throw new BookingException('A booking can only be marked as a no-show from its arrival date.');
            }

            $before = ['status' => $locked->status->value];

            $locked->update(['status' => BookingStatus::NoShow]);

            AuditLog::record('booking.no_show', $locked, $before, ['status' => BookingStatus::NoShow->value], $actor);

            return $locked;
        });
    }

    // ------------------------------------------------------------------ change dates, guests or room

    /**
     * Price and free rooms for a proposed change, without saving anything.
     *
     * @return array{quote: PriceQuote, rooms: Collection}
     */
    public function previewChange(
        Booking $booking,
        CarbonInterface|string $checkIn,
        CarbonInterface|string $checkOut,
        int $adults,
        int $children,
    ): array {
        $in = CarbonImmutable::parse($checkIn)->startOfDay();
        $out = CarbonImmutable::parse($checkOut)->startOfDay();

        if ($out->lessThanOrEqualTo($in)) {
            throw new BookingException('Check-out must be after check-in.');
        }

        $type = RoomType::withTrashed()->findOrFail($booking->room_type_id);

        return [
            'quote' => $this->priceFor($booking, $type, $in, $out, $adults, $children),
            'rooms' => $this->availability->freeRooms($type, $in, $out, $booking->id),
        ];
    }

    public function modify(
        Booking $booking,
        CarbonInterface|string $checkIn,
        CarbonInterface|string $checkOut,
        int $adults,
        int $children,
        ?Room $room = null,
        ?User $actor = null,
    ): Booking {
        $in = CarbonImmutable::parse($checkIn)->startOfDay();
        $out = CarbonImmutable::parse($checkOut)->startOfDay();

        if ($out->lessThanOrEqualTo($in)) {
            throw new BookingException('Check-out must be after check-in.');
        }

        return DB::transaction(function () use ($booking, $in, $out, $adults, $children, $room, $actor) {
            [$type, $locked] = $this->lockBooking($booking);

            if (! in_array($locked->status, [BookingStatus::Pending, BookingStatus::Confirmed], true)) {
                throw new BookingException('Only pending or confirmed bookings can be changed.');
            }

            if ($room !== null) {
                $target = Room::query()->find($room->id);

                if (! $target) {
                    throw new BookingException('That room no longer exists.');
                }
                if ($target->room_type_id !== $type->id) {
                    throw new BookingException('That room belongs to a different room type.');
                }
                if (! $this->availability->isRoomFree($target, $in, $out, $locked->id)) {
                    throw new BookingException("Room {$target->room_number} is not free for those dates.");
                }
            } else {
                $current = Room::query()->find($locked->room_id);

                $target = ($current && $this->availability->isRoomFree($current, $in, $out, $locked->id))
                    ? $current
                    : $this->availability->freeRooms($type, $in, $out, $locked->id)->first();

                if (! $target) {
                    throw new BookingException('No room of this type is free for the new dates.');
                }
            }

            $unchanged = $locked->check_in->toDateString() === $in->toDateString()
                && $locked->check_out->toDateString() === $out->toDateString()
                && $locked->adults === $adults
                && $locked->children === $children
                && $locked->room_id === $target->id;

            if ($unchanged) {
                return $locked;
            }

            $quote = $this->priceFor($locked, $type, $in, $out, $adults, $children);
            $before = $this->snapshot($locked);

            $locked->update([
                'room_id' => $target->id,
                'check_in' => $in->toDateString(),
                'check_out' => $out->toDateString(),
                'adults' => $adults,
                'children' => $children,
                'subtotal' => $quote->roomSubtotal + $quote->extraPersonTotal,
                'discount' => $quote->discount,
                'service_charge' => $quote->serviceCharge,
                'tax' => $quote->tax,
                'total' => $quote->total,
                'deposit_required' => $this->depositFor($quote->total),
                'price_breakdown' => $quote->toArray(),
            ]);

            AuditLog::record('booking.modified', $locked, $before, $this->snapshot($locked->refresh()), $actor);

            return $locked;
        });
    }

    // ------------------------------------------------------------------ expiry of unpaid pending bookings

    /** Cancels pending bookings whose hold has run out. Safe to run repeatedly. Returns how many it cancelled. */
    public function expireOverdue(): int
    {
        $cancelled = 0;

        $ids = Booking::query()
            ->where('status', BookingStatus::Pending->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->pluck('id');

        foreach ($ids as $id) {
            DB::transaction(function () use ($id, &$cancelled) {
                $locked = Booking::query()->whereKey($id)->lockForUpdate()->first();

                if (! $locked
                    || $locked->status !== BookingStatus::Pending
                    || $locked->expires_at === null
                    || $locked->expires_at->isFuture()) {
                    return;
                }

                $reason = 'Deposit not received in time';

                $locked->update([
                    'status' => BookingStatus::Cancelled,
                    'cancelled_at' => now(),
                    'cancellation_reason' => $reason,
                ]);

                $this->releasePromoUse($locked);

                AuditLog::record(
                    'booking.expired',
                    $locked,
                    ['status' => BookingStatus::Pending->value],
                    ['status' => BookingStatus::Cancelled->value, 'reason' => $reason],
                );

                $cancelled++;
            });
        }

        return $cancelled;
    }

    // ------------------------------------------------------------------ helpers

    /** @return array{0: RoomType, 1: Booking} */
    private function lockBooking(Booking $booking): array
    {
        // Same lock order as BookingService: the room type first, then the booking.
        $type = RoomType::withTrashed()->whereKey($booking->room_type_id)->lockForUpdate()->first();
        $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->first();

        if (! $type || ! $locked) {
            throw new BookingException('This booking no longer exists.');
        }

        return [$type, $locked];
    }

    private function lockBookingOnly(Booking $booking): Booking
    {
        $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->first();

        if (! $locked) {
            throw new BookingException('This booking no longer exists.');
        }

        return $locked;
    }

    private function releasePromoUse(Booking $booking): void
    {
        if (! $booking->promo_code_id) {
            return;
        }

        $promo = PromoCode::withTrashed()->whereKey($booking->promo_code_id)->lockForUpdate()->first();

        if ($promo && $promo->used_count > 0) {
            $promo->decrement('used_count');
        }
    }

    private function priceFor(
        Booking $booking,
        RoomType $type,
        CarbonInterface|string $in,
        CarbonInterface|string $out,
        int $adults,
        int $children,
    ): PriceQuote {
        $promo = $booking->promo_code_id ? PromoCode::withTrashed()->find($booking->promo_code_id) : null;

        return $this->calculator->quote(
            $type,
            RatePlan::withTrashed()->findOrFail($booking->rate_plan_id),
            CarbonImmutable::parse($in),
            CarbonImmutable::parse($out),
            $adults,
            $children,
            $promo,
            null,
            promoAlreadyApplied: true,
        );
    }

    private function depositFor(int $total): int
    {
        $bps = (int) round(((float) HotelSettings::get('deposit_percent')) * 100);

        return intdiv($total * $bps + 5000, 10000);
    }

    private function snapshot(Booking $booking): array
    {
        return [
            'room_id' => $booking->room_id,
            'check_in' => $booking->check_in->toDateString(),
            'check_out' => $booking->check_out->toDateString(),
            'adults' => $booking->adults,
            'children' => $booking->children,
            'total' => $booking->total,
            'deposit_required' => $booking->deposit_required,
        ];
    }
}