<?php

namespace Database\Seeders;

use App\Models\RatePlan;
use Illuminate\Database\Seeder;

class RatePlanSeeder extends Seeder
{
    public function run(): void
    {
        // Starting values only. Edit them on the Rate plans screen.
        $plans = [
            [
                'name' => 'Best Available',
                'description' => 'Standard rate with the hotel cancellation policy.',
                'adjustment_bps' => 0,
                'extra_per_night' => 0,
                'is_refundable' => true,
                'free_cancellation_hours' => null,
                'inclusions' => null,
            ],
            [
                'name' => 'Flexible',
                'description' => 'Higher rate with free cancellation up to the day before arrival.',
                'adjustment_bps' => 1000,
                'extra_per_night' => 0,
                'is_refundable' => true,
                'free_cancellation_hours' => 24,
                'inclusions' => null,
            ],
            [
                'name' => 'Non-refundable',
                'description' => 'Discounted rate. Not refundable once booked.',
                'adjustment_bps' => -1000,
                'extra_per_night' => 0,
                'is_refundable' => false,
                'free_cancellation_hours' => null,
                'inclusions' => null,
            ],
            [
                'name' => 'Package',
                'description' => 'Room with breakfast included.',
                'adjustment_bps' => 0,
                'extra_per_night' => 500000,
                'is_refundable' => true,
                'free_cancellation_hours' => null,
                'inclusions' => 'Breakfast',
            ],
        ];

        foreach ($plans as $plan) {
            RatePlan::firstOrCreate(['name' => $plan['name']], $plan);
        }
    }
}