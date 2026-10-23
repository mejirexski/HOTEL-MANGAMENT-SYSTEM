<?php

namespace App\Models;

use App\Support\Bookings\BookingException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RatePlan extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 
        'description', 
        'adjustment_bps', 
        'extra_per_night',
        'is_refundable', 
        'free_cancellation_hours', 
        'inclusions', 
        'is_active',
    ];

    protected static function booted(): void
    {
        static::deleting(function (RatePlan $plan) {
            if ($plan->bookings()->currentOrFuture()->exists()) {
                throw new BookingException(
                    'This rate plan is used by current or future bookings and cannot be deleted. Deactivate it instead.'
                );
            }
        });
    }

    protected function casts(): array
    {
        return [
            'adjustment_bps' => 'integer',
            'extra_per_night' => 'integer',
            'is_refundable' => 'boolean',
            'free_cancellation_hours' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** Nightly price in kobo after this plan's percentage and add-on (integer maths, rounded half up). */
    public function adjustNightlyRate(int $baseKobo): int
    {
        $adjusted = intdiv($baseKobo * (10000 + $this->adjustment_bps) + 5000, 10000);

        return $adjusted + $this->extra_per_night;
    }

    public function adjustmentLabel(): string
    {
        if ($this->adjustment_bps === 0) {
            return 'Base price';
        }

        $percent = rtrim(rtrim(number_format($this->adjustment_bps / 100, 2, '.', ''), '0'), '.');

        return ($this->adjustment_bps > 0 ? '+' : '') . $percent . '%';
    }
}