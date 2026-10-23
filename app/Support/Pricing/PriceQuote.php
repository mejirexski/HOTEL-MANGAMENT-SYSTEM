<?php

namespace App\Support\Pricing;

final readonly class PriceQuote
{
    public function __construct(
        public array $nights,            // each: date, source (base|weekend|seasonal), base, rate (kobo)
        public int $roomSubtotal,
        public int $extraGuests,
        public int $extraPersonTotal,
        public ?string $promoCode,
        public int $discount,
        public int $net,
        public int $serviceCharge,
        public int $tax,
        public int $total,
        public bool $taxIncluded,
        public bool $serviceChargeIncluded,
    ) {
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}