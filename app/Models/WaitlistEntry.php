<?php

namespace App\Models;

use App\Enums\WaitlistStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WaitlistEntry extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'guest_id', 
        'room_type_id', 
        'check_in', 
        'check_out', 
        'adults', 
        'children',
        'status', 
        'notified_at', 
        'offer_expires_at', 
        'notes', 
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'check_in' => 'date',
            'check_out' => 'date',
            'adults' => 'integer',
            'children' => 'integer',
            'status' => WaitlistStatus::class,
            'notified_at' => 'datetime',
            'offer_expires_at' => 'datetime',
        ];
    }

    /** Entries still in the queue: waiting for a room, or holding an offer. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [WaitlistStatus::Waiting->value, WaitlistStatus::Notified->value]);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class)->withTrashed();
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}