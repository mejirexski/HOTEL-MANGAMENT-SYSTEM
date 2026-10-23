<?php

namespace App\Models;

use App\Support\Bookings\BookingException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RoomType extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 
        'description', 
        'base_price', 
        'max_adults', 
        'max_children',
        'bed_type', 
        'size_sqm', 
        'is_active',
        'weekend_price', 
        'extra_person_fee', 
        'base_occupancy', 
        'min_stay', 
        'max_stay',
    ];

    protected static function booted(): void
    {
        static::deleting(function (RoomType $type) {
            if ($type->rooms()->exists()) {
                throw new BookingException('This room type still has rooms. Move or remove those rooms first.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'base_price' => 'integer',
            'max_adults' => 'integer',
            'max_children' => 'integer',
            'size_sqm' => 'integer',
            'is_active' => 'boolean',
            'weekend_price' => 'integer',
            'extra_person_fee' => 'integer',
            'base_occupancy' => 'integer',
            'min_stay' => 'integer',
            'max_stay' => 'integer',
        ];
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(RoomTypePhoto::class)->orderBy('sort_order')->orderBy('id');
    }

    public function seasonalRates(): HasMany
    {
        return $this->hasMany(SeasonalRate::class)->orderBy('start_date');
    }

    public function stayRestrictions(): HasMany
    {
        return $this->hasMany(StayRestriction::class)->orderBy('start_date');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}