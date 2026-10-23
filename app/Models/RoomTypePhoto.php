<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomTypePhoto extends Model
{
    protected $fillable = ['room_type_id', 'path', 'sort_order'];

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function url(): string
    {
        return asset('storage/' . $this->path);
    }
}