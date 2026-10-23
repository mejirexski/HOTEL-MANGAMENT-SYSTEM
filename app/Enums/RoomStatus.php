<?php

namespace App\Enums;

enum RoomStatus: string
{
    case Available = 'available';
    case Occupied = 'occupied';
    case Dirty = 'dirty';
    case Cleaning = 'cleaning';
    case Maintenance = 'maintenance';
    case OutOfOrder = 'out_of_order';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Occupied => 'Occupied',
            self::Dirty => 'Dirty',
            self::Cleaning => 'Cleaning',
            self::Maintenance => 'Maintenance',
            self::OutOfOrder => 'Out of Order',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Available => 'bg-green-100 text-green-800',
            self::Occupied => 'bg-blue-100 text-blue-800',
            self::Dirty => 'bg-yellow-100 text-yellow-800',
            self::Cleaning => 'bg-orange-100 text-orange-800',
            self::Maintenance => 'bg-purple-100 text-purple-800',
            self::OutOfOrder => 'bg-red-100 text-red-800',
        };
    }
}