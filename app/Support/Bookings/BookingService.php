<?php

namespace App\Support\Bookings;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\PromoCode;
use App\Models\RatePlan;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Support\AuditLog;
use App\Support\HotelSettings;
use App\Support\Pricing\PriceCalculator;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BookingService
{
    // No 0, 1, I or O, so a reference can be read out over the phone.
    private const REFERENCE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly PriceCalculator $calculator,
    ) {
    }

    /**
     * Creates a booking. Throws BookingException (no room, bad promo code, wrong room)
     * or PricingException (stay or promo rules) and saves nothing when it does.
     */
    public function create(
        RoomType $roomType,
        RatePlan $ratePlan,
        Guest $guest,
        CarbonInterface|string $checkIn,
        CarbonInterface|string $checkOut,
        int $adults,
        int $children = 0,
        ?string $promoCode = null,
        string $source = 'staff',
        ?string $notes = null,
        ?User $actor = null,
        ?Room $room = null,
        BookingStatus $status = BookingStatus::Pending,
    ): Booking {
        if (! in_array($status, [BookingStatus::Pending, BookingStatus::Confirmed], true)) {
            throw new InvalidArgumentException('A new booking must start as Pending or Confirmed.');
        }

        if (! $guest->exists) {
            throw new InvalidArgumentException('The guest must be saved before booking.');
        }

        $in = CarbonImmutable::parse($checkIn)->startOfDay();
        $out = CarbonImmutable::parse($checkOut)->startOfDay();

        return DB::transaction(function () use (
            $roomType, $ratePlan, $guest, $in, $out, $adults, $children,
            $promoCode, $source, $notes, $actor, $room, $status,
        ) {
            // 1. Lock the room type first. A second booking for the same type waits here until
            //    this one is saved, then reads the bookings again and sees it.
            $type = RoomType::query()->whereKey($roomType->id)->lockForUpdate()->first();

            if (! $type) {
                throw new BookingException('This room type no longer exists.');
            }

            $plan = RatePlan::query()->find($ratePlan->id);

            if (! $plan) {
                throw new BookingException('This rate plan no longer exists.');
            }

            // 2. Choose the room.
            if ($room !== null) {
                $chosen = Room::query()->find($room->id);

                if (! $chosen) {
                    throw new BookingException('That room no longer exists.');
                }
                if ($chosen->room_type_id !== $type->id) {
                    throw new BookingException('That room belongs to a different room type.');
                }
                if (! $this->availability->isRoomFree($chosen, $in, $out)) {
                    throw new BookingException("Room {$chosen->room_number} is not free for those dates.");
                }
            } else {
                $chosen = $this->availability->freeRooms($type, $in, $out)->first();

                if (! $chosen) {
                    throw new BookingException('No room of this type is available for those dates.');
                }
            }

            // 3. Promo code, looked up under a lock so its usage count is exact.
            $promo = null;

            if ($promoCode !== null && trim($promoCode) !== '') {
                $promo = PromoCode::query()
                    ->where('code', strtoupper(trim($promoCode)))
                    ->lockForUpdate()
                    ->first();

                if (! $promo) {
                    throw new BookingException('Promo code not found.');
                }
            }

            // 4. Price the stay. This also checks the stay rules and the promo code rules.
            $quote = $this->calculator->quote($type, $plan, $in, $out, $adults, $children, $promo);

            $depositBps = (int) round(((float) HotelSettings::get('deposit_percent')) * 100);
            $deposit = intdiv($quote->total * $depositBps + 5000, 10000);

            $expiresAt = $status === BookingStatus::Pending
                ? now()->addHours(max(1, (int) HotelSettings::get('pending_expiry_hours')))
                : null;

            // 5. Save.
            $booking = Booking::create([
                'reference' => $this->newReference(),
                'guest_id' => $guest->id,
                'room_id' => $chosen->id,
                'room_type_id' => $type->id,
                'rate_plan_id' => $plan->id,
                'promo_code_id' => $promo?->id,
                'check_in' => $in->toDateString(),
                'check_out' => $out->toDateString(),
                'adults' => $adults,
                'children' => $children,
                'status' => $status,
                'source' => $source,
                'subtotal' => $quote->roomSubtotal + $quote->extraPersonTotal,
                'discount' => $quote->discount,
                'service_charge' => $quote->serviceCharge,
                'tax' => $quote->tax,
                'total' => $quote->total,
                'deposit_required' => $deposit,
                'price_breakdown' => $quote->toArray(),
                'notes' => $notes,
                'expires_at' => $expiresAt,
                'confirmed_at' => $status === BookingStatus::Confirmed ? now() : null,
                'created_by' => ($actor ?? Auth::user())?->id,
            ]);

            $promo?->increment('used_count');

            AuditLog::record('booking.created', $booking, null, $this->snapshot($booking, $promo), $actor);

            return $booking;
        });
    }

    private function snapshot(Booking $booking, ?PromoCode $promo): array
    {
        return [
            'reference' => $booking->reference,
            'status' => $booking->status->value,
            'guest_id' => $booking->guest_id,
            'room_id' => $booking->room_id,
            'room_type_id' => $booking->room_type_id,
            'rate_plan_id' => $booking->rate_plan_id,
            'promo_code' => $promo?->code,
            'check_in' => $booking->check_in->toDateString(),
            'check_out' => $booking->check_out->toDateString(),
            'adults' => $booking->adults,
            'children' => $booking->children,
            'total' => $booking->total,
            'deposit_required' => $booking->deposit_required,
        ];
    }

    private function newReference(): string
    {
        $alphabet = self::REFERENCE_ALPHABET;

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $suffix = '';

            for ($i = 0; $i < 6; $i++) {
                $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }

            $reference = 'HMS-' . now()->format('ym') . '-' . $suffix;

            if (! Booking::withTrashed()->where('reference', $reference)->exists()) {
                return $reference;
            }
        }

        throw new BookingException('Could not create a booking reference. Please try again.');
    }
}