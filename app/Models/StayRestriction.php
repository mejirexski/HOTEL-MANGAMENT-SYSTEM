<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StayRestriction extends Model
{
    use SoftDeletes;

    protected $fillable = 
        [
            'room_type_id', 
            'start_date', 
            'end_date', 
            'closed_to_arrival', 
            'min_stay', 
            'note'
        ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'closed_to_arrival' => 'boolean',
            'min_stay' => 'integer',
        ];
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }
}