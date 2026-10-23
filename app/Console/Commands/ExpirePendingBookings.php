<?php

namespace App\Console\Commands;

use App\Support\Bookings\BookingActions;
use Illuminate\Console\Command;

class ExpirePendingBookings extends Command
{
    protected $signature = 'bookings:expire-pending';

    protected $description = 'Cancels pending bookings whose deposit was not received in time';

    public function handle(BookingActions $actions): int
    {
        $count = $actions->expireOverdue();

        $this->info("Cancelled {$count} expired pending booking(s).");

        return self::SUCCESS;
    }
}