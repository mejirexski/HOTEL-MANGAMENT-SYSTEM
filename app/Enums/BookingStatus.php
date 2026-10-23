<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case CheckedOut = 'checked_out';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Confirmed => 'Confirmed',
            self::CheckedIn => 'Checked-in',
            self::CheckedOut => 'Checked-out',
            self::Cancelled => 'Cancelled',
            self::NoShow => 'No-show',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-yellow-100 text-yellow-800',
            self::Confirmed => 'bg-blue-100 text-blue-800',
            self::CheckedIn => 'bg-green-100 text-green-800',
            self::CheckedOut => 'bg-gray-100 text-gray-700',
            self::Cancelled => 'bg-red-100 text-red-800',
            self::NoShow => 'bg-orange-100 text-orange-800',
        };
    }
}