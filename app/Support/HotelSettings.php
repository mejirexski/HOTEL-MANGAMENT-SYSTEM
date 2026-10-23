<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class HotelSettings
{
    // Placeholder defaults only. Confirm the real tax, service charge and
    // policy values with the hotel and a local accountant.
    public const DEFAULTS = [
        'hotel_name' => 'Blue Diamond Hotel',
        'hotel_address' => '',
        'hotel_phone' => '',
        'hotel_email' => '',
        'currency_code' => 'NGN',
        'currency_symbol' => '₦',
        'tax_rate' => '7.5',
        'tax_included' => '0',
        'service_charge_rate' => '0',
        'service_charge_included' => '0',
        'check_in_time' => '14:00',
        'check_out_time' => '12:00',
        'deposit_percent' => '30',
        'pending_expiry_hours' => '24',
        'free_cancellation_hours' => '48',
        'waitlist_offer_hours' => '24',
        'credit_limit_kobo' => '5000000',
        'bar_opens_at' => '10:00',
        'bar_closes_at' => '23:00',
        'gym_opens_at' => '06:00',
        'gym_closes_at' => '21:00',
        'gym_capacity' => '30',
    ];

    public static function keys(): array
    {
        return array_keys(self::DEFAULTS);
    }

    public static function all(): array
    {
        return Cache::rememberForever('hotel_settings', fn () => Setting::pluck('value', 'key')->all());
    }

    public static function get(string $key): ?string
    {
        self::assertKnown($key);

        return self::all()[$key] ?? self::DEFAULTS[$key];
    }

    public static function set(string $key, ?string $value): void
    {
        self::assertKnown($key);

        Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('hotel_settings');
    }

    private static function assertKnown(string $key): void
    {
        if (! array_key_exists($key, self::DEFAULTS)) {
            throw new InvalidArgumentException("Unknown setting [{$key}].");
        }
    }
}