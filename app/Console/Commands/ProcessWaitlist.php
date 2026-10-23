<?php

namespace App\Console\Commands;

use App\Support\Bookings\WaitlistService;
use Illuminate\Console\Command;

class ProcessWaitlist extends Command
{
    protected $signature = 'waitlist:process';

    protected $description = 'Ends stale waitlist offers and offers freed rooms to the next guests in line';

    public function handle(WaitlistService $waitlist): int
    {
        $result = $waitlist->processAll();

        $this->info("Waitlist processed: {$result['notified']} notified, {$result['lapsed']} lapsed.");

        return self::SUCCESS;
    }
}