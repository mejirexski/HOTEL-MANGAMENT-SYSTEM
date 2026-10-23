<?php

namespace App\Enums;

enum WaitlistStatus: string
{
    case Waiting = 'waiting';
    case Notified = 'notified';
    case Booked = 'booked';
    case Cancelled = 'cancelled';
    case Lapsed = 'lapsed';

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'Waiting',
            self::Notified => 'Room offered',
            self::Booked => 'Booked',
            self::Cancelled => 'Cancelled',
            self::Lapsed => 'Lapsed',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Waiting => 'bg-yellow-100 text-yellow-800',
            self::Notified => 'bg-blue-100 text-blue-800',
            self::Booked => 'bg-green-100 text-green-800',
            self::Cancelled => 'bg-red-100 text-red-800',
            self::Lapsed => 'bg-gray-100 text-gray-700',
        };
    }
}