<?php

namespace App\Models;

use App\Support\HotelSettings;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PromoCode extends Model
{
    use SoftDeletes;

    public const PERCENT = 'percent';
    public const FIXED = 'fixed';

    protected $fillable = [
        'code', 
        'description', 
        'type', 
        'value',
        'valid_from', 
        'valid_to', 
        'usage_limit', 
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'valid_from' => 'date',
            'valid_to' => 'date',
            'usage_limit' => 'integer',
            'used_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function ratePlans(): BelongsToMany
    {
        return $this->belongsToMany(RatePlan::class, 'promo_code_rate_plan');
    }

    public static function findByCode(string $code): ?self
    {
        return static::where('code', strtoupper(trim($code)))->first();
    }

    /** Discount in kobo for a room subtotal in kobo. Never more than the subtotal. */
    public function discountFor(int $subtotalKobo): int
    {
        $discount = $this->type === self::PERCENT
            ? intdiv($subtotalKobo * $this->value + 5000, 10000)
            : $this->value;

        return max(0, min($discount, $subtotalKobo));
    }

    /** Returns null when the code can be used, or a plain-language reason when it can't. */
    public function problemFor(?int $ratePlanId, ?CarbonInterface $on = null): ?string
    {
        $day = ($on ?? now())->toDateString();

        if (! $this->is_active) {
            return 'This promo code is not active.';
        }
        if ($this->valid_from && $day < $this->valid_from->toDateString()) {
            return 'This promo code is not valid yet.';
        }
        if ($this->valid_to && $day > $this->valid_to->toDateString()) {
            return 'This promo code has expired.';
        }
        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return 'This promo code has reached its usage limit.';
        }
        if ($ratePlanId !== null
            && $this->ratePlans()->exists()
            && ! $this->ratePlans()->whereKey($ratePlanId)->exists()) {
            return 'This promo code does not apply to the chosen rate plan.';
        }

        return null;
    }

    public function discountLabel(): string
    {
        if ($this->type === self::PERCENT) {
            return rtrim(rtrim(number_format($this->value / 100, 2, '.', ''), '0'), '.') . '% off';
        }

        return HotelSettings::get('currency_symbol') . number_format($this->value / 100, 2) . ' off';
    }
}