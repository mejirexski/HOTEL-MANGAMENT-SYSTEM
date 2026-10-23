<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class GuestAccess
{
    public const VIEW = ['owner', 'general-manager', 'receptionist', 'cashier'];
    public const MANAGE = ['owner', 'general-manager', 'receptionist'];
    public const ID_DETAILS = ['owner', 'general-manager', 'receptionist'];
    public const DELETE = ['owner', 'general-manager'];

    public static function canView(?User $user = null): bool
    {
        return self::has($user, self::VIEW);
    }

    public static function canManage(?User $user = null): bool
    {
        return self::has($user, self::MANAGE);
    }

    public static function canSeeIdDetails(?User $user = null): bool
    {
        return self::has($user, self::ID_DETAILS);
    }

    public static function canDelete(?User $user = null): bool
    {
        return self::has($user, self::DELETE);
    }

    private static function has(?User $user, array $roles): bool
    {
        $user ??= Auth::user();

        return $user !== null && $user->hasAnyRole($roles);
    }
}