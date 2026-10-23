<?php

namespace App\Support\Pricing;

use App\Models\PromoCode;
use App\Models\RatePlan;
use App\Models\RoomType;
use App\Models\SeasonalRate;
use App\Models\StayRestriction;
use App\Support\HotelSettings;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class PriceCalculator
{
    private const SCALE = 10000; // basis points

    public function quote(
        RoomType $roomType,
        RatePlan $ratePlan,
        CarbonInterface $checkIn,
        CarbonInterface $checkOut,
        int $adults,
        int $children = 0,
        ?PromoCode $promo = null,
        ?CarbonInterface $bookedOn = null,
        bool $promoAlreadyApplied = false,
    ): PriceQuote {
        $start = CarbonImmutable::parse($checkIn->toDateString());
        $end = CarbonImmutable::parse($checkOut->toDateString());

        if ($end->lessThanOrEqualTo($start)) {
            throw new PricingException('Check-out must be after check-in.');
        }

        $nights = (int) round($start->diffInDays($end));

        $this->assertBookable($roomType, $ratePlan, $adults, $children);
        $this->assertStayAllowed($roomType, $start, $nights);

        $lines = $this->nightlyLines($roomType, $ratePlan, $start, $nights);
        $roomSubtotal = (int) array_sum(array_column($lines, 'rate'));

        $extraGuests = max(0, $adults + $children - $roomType->base_occupancy);
        $extraPersonTotal = $extraGuests * $roomType->extra_person_fee * $nights;

        $discount = 0;
        if ($promo !== null) {
            if (! $promoAlreadyApplied) {
                $problem = $promo->problemFor($ratePlan->id, $bookedOn ?? CarbonImmutable::now());

                if ($problem !== null) {
                    throw new PricingException($problem);
                }
            }

            $discount = $promo->discountFor($roomSubtotal);
        }

        $listed = $roomSubtotal - $discount + $extraPersonTotal;

        $taxIncluded = HotelSettings::get('tax_included') === '1';
        $serviceIncluded = HotelSettings::get('service_charge_included') === '1';

        [$net, $service, $tax, $total] = $this->split(
            $listed,
            $this->bps(HotelSettings::get('service_charge_rate')),
            $this->bps(HotelSettings::get('tax_rate')),
            $serviceIncluded,
            $taxIncluded,
        );

        return new PriceQuote(
            nights: $lines,
            roomSubtotal: $roomSubtotal,
            extraGuests: $extraGuests,
            extraPersonTotal: $extraPersonTotal,
            promoCode: $promo?->code,
            discount: $discount,
            net: $net,
            serviceCharge: $service,
            tax: $tax,
            total: $total,
            taxIncluded: $taxIncluded,
            serviceChargeIncluded: $serviceIncluded,
        );
    }

    private function assertBookable(RoomType $roomType, RatePlan $ratePlan, int $adults, int $children): void
    {
        if (! $roomType->is_active) {
            throw new PricingException('This room type is not available for booking.');
        }
        if (! $ratePlan->is_active) {
            throw new PricingException('This rate plan is not available.');
        }
        if ($adults < 1) {
            throw new PricingException('At least one adult is required.');
        }
        if ($adults > $roomType->max_adults) {
            throw new PricingException("This room type allows at most {$roomType->max_adults} adults.");
        }
        if ($children < 0 || $children > $roomType->max_children) {
            throw new PricingException("This room type allows at most {$roomType->max_children} children.");
        }
    }

    private function assertStayAllowed(RoomType $roomType, CarbonImmutable $start, int $nights): void
    {
        $day = $start->toDateString();

        $restriction = StayRestriction::where('room_type_id', $roomType->id)
            ->whereDate('start_date', '<=', $day)
            ->whereDate('end_date', '>=', $day)
            ->first();

        if ($restriction?->closed_to_arrival) {
            throw new PricingException('Arrivals are closed on ' . $start->format('d M Y') . ' for this room type.');
        }

        $minStay = $restriction?->min_stay ?? $roomType->min_stay;

        if ($nights < $minStay) {
            throw new PricingException("The minimum stay for this arrival date is {$minStay} nights.");
        }

        if ($roomType->max_stay !== null && $nights > $roomType->max_stay) {
            throw new PricingException("The maximum stay for this room type is {$roomType->max_stay} nights.");
        }
    }

    private function nightlyLines(RoomType $roomType, RatePlan $ratePlan, CarbonImmutable $start, int $nights): array
    {
        $lastNight = $start->addDays($nights - 1);

        $seasons = SeasonalRate::where('room_type_id', $roomType->id)
            ->whereDate('start_date', '<=', $lastNight->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->get();

        $lines = [];

        for ($i = 0; $i < $nights; $i++) {
            $night = $start->addDays($i);
            $day = $night->toDateString();

            $season = $seasons->first(
                fn (SeasonalRate $s) => $day >= $s->start_date->toDateString() && $day <= $s->end_date->toDateString()
            );

            if ($season) {
                $source = 'seasonal';
                $base = $season->price;
            } elseif ($roomType->weekend_price !== null && ($night->isFriday() || $night->isSaturday())) {
                $source = 'weekend';
                $base = $roomType->weekend_price;
            } else {
                $source = 'base';
                $base = $roomType->base_price;
            }

            $lines[] = [
                'date' => $day,
                'source' => $source,
                'base' => $base,
                'rate' => $ratePlan->adjustNightlyRate($base),
            ];
        }

        return $lines;
    }

    /**
     * Splits the listed amount into net, service charge and tax (all kobo).
     * Service charge is worked out on the net amount, and tax on net + service charge.
     * A charge marked "included" is already inside the listed amount; one marked "added" goes on top.
     *
     * @return array{0:int,1:int,2:int,3:int} [net, service, tax, total]
     */
    private function split(int $listed, int $serviceBps, int $taxBps, bool $serviceIncluded, bool $taxIncluded): array
    {
        $scale = self::SCALE;

        // listed = net x (1 + [service included] x s + [tax included] x (1 + s) x t)
        $factor = $scale * $scale
            + ($serviceIncluded ? $serviceBps * $scale : 0)
            + ($taxIncluded ? ($scale + $serviceBps) * $taxBps : 0);

        $net = intdiv($listed * $scale * $scale * 2 + $factor, $factor * 2);

        $service = intdiv($net * $serviceBps + intdiv($scale, 2), $scale);
        $tax = intdiv(($net + $service) * $taxBps + intdiv($scale, 2), $scale);

        if ($serviceIncluded && $taxIncluded) {
            $tax = $listed - $net - $service; // absorb rounding so the total equals the listed price
        }

        return [$net, $service, $tax, $net + $service + $tax];
    }

    private function bps(?string $percent): int
    {
        return (int) round(((float) $percent) * 100);
    }
}