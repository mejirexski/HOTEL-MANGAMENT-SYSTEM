<?php

namespace App\Models;

use App\Enums\RoomStatus;
use App\Support\Bookings\BookingException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model
{
    use SoftDeletes;

    protected $fillable = 
        [
            'room_number', 
            'floor', 
            'room_type_id', 
            'status', 
            'notes'
        ];

    protected static function booted(): void
    {
        static::deleting(function (Room $room) {
            if ($room->bookings()->currentOrFuture()->exists()) {
                throw new BookingException(
                    "Room {$room->room_number} has current or future bookings and cannot be deleted."
                );
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => RoomStatus::class,
            'floor' => 'integer',
        ];
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}