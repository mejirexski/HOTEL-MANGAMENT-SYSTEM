<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'reference', 
        'guest_id', 
        'room_id', 
        'room_type_id', 
        'rate_plan_id', 
        'promo_code_id',
        'check_in', 
        'check_out', 
        'adults', 
        'children', 
        'status', 
        'source',
        'subtotal', 
        'discount', 
        'service_charge', 
        'tax', 
        'total', 
        'deposit_required', 
        'price_breakdown',
        'notes', 
        'expires_at', 
        'confirmed_at', 
        'cancelled_at', 
        'checked_in_at', 
        'checked_out_at',
        'cancellation_reason', 
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'check_in' => 'date',
            'check_out' => 'date',
            'adults' => 'integer',
            'children' => 'integer',
            'status' => BookingStatus::class,
            'subtotal' => 'integer',
            'discount' => 'integer',
            'service_charge' => 'integer',
            'tax' => 'integer',
            'total' => 'integer',
            'deposit_required' => 'integer',
            'price_breakdown' => 'array',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
        ];
    }

    /**
     * Bookings that hold a room: Confirmed, Checked-in, and Pending ones that have not expired.
     */
    public function scopeBlocking(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereIn('status', [BookingStatus::Confirmed->value, BookingStatus::CheckedIn->value])
                ->orWhere(function (Builder $pending) {
                    $pending->where('status', BookingStatus::Pending->value)
                        ->where(function (Builder $e) {
                            $e->whereNull('expires_at')->orWhere('expires_at', '>', now());
                        });
                });
        });
    }

    /**
     * The overlap rule from the PRD: an existing booking clashes with a new stay when it checks in
     * before the new check-out AND checks out after the new check-in. Dates are 'Y-m-d' strings.
     */
    public function scopeOverlapping(Builder $query, string $checkIn, string $checkOut): Builder
    {
        return $query->whereDate('check_in', '<', $checkOut)
            ->whereDate('check_out', '>', $checkIn);
    }

    /**
     * Bookings that still matter to a room or rate plan: held or confirmed and not finished yet,
     * plus guests who are checked in.
     */
    public function scopeCurrentOrFuture(Builder $query): Builder
    {
        return $query->blocking()->where(function (Builder $q) {
            $q->whereDate('check_out', '>=', now()->toDateString())
                ->orWhere('status', BookingStatus::CheckedIn->value);
        });
    }

    public function nights(): int
    {
        return (int) round($this->check_in->diffInDays($this->check_out));
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class)->withTrashed();
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class)->withTrashed();
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class)->withTrashed();
    }

    public function ratePlan(): BelongsTo
    {
        return $this->belongsTo(RatePlan::class)->withTrashed();
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}