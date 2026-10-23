<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Guest;
use App\Models\RatePlan;
use App\Models\Room;
use App\Models\RoomType;
use App\Support\Bookings\BookingException;
use App\Support\Bookings\BookingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckBookingLock extends Command
{
    private const CHECK_IN = '2027-06-01';
    private const CHECK_OUT = '2027-06-03';
    private const HOLD_SECONDS = 10;

    protected $signature = 'hotel:check-booking-lock
        {--worker= : Internal use: holder or rival}
        {--room-type= : Internal use}
        {--guest= : Internal use}';

    protected $description = 'Proves on MySQL that two simultaneous bookings cannot take the last room';

    public function handle(BookingService $bookings): int
    {
        if (! app()->environment('local')) {
            $this->error('This check only runs in the local environment.');
            return self::FAILURE;
        }

        if (DB::getDriverName() !== 'mysql') {
            $this->error('This check needs MySQL. Row locks do nothing on SQLite.');
            return self::FAILURE;
        }

        return match ($this->option('worker')) {
            'holder' => $this->holder($bookings),
            'rival' => $this->rival($bookings),
            default => $this->runCheck(),
        };
    }

    // ------------------------------------------------------------ the two worker processes

    /** Books the room inside a transaction and keeps it open for a while before committing. */
    private function holder(BookingService $bookings): int
    {
        [$type, $guest, $plan] = $this->workerModels();

        DB::beginTransaction();

        $booking = $bookings->create(
            roomType: $type, ratePlan: $plan, guest: $guest,
            checkIn: self::CHECK_IN, checkOut: self::CHECK_OUT, adults: 2,
        );

        $this->line('HOLDER booked ' . $booking->reference);

        sleep(self::HOLD_SECONDS);

        DB::commit();
        $this->line('HOLDER committed');

        return self::SUCCESS;
    }

    /** Tries to book the same room while the holder is still holding its lock. */
    private function rival(BookingService $bookings): int
    {
        [$type, $guest, $plan] = $this->workerModels();

        $started = microtime(true);

        try {
            $booking = $bookings->create(
                roomType: $type, ratePlan: $plan, guest: $guest,
                checkIn: self::CHECK_IN, checkOut: self::CHECK_OUT, adults: 2,
            );
            $this->line('RIVAL booked ' . $booking->reference);
        } catch (BookingException $e) {
            $this->line('RIVAL refused: ' . $e->getMessage());
        }

        $this->line(sprintf('RIVAL waited %.1f seconds', microtime(true) - $started));

        return self::SUCCESS;
    }

    private function workerModels(): array
    {
        return [
            RoomType::findOrFail($this->option('room-type')),
            Guest::findOrFail($this->option('guest')),
            RatePlan::where('is_active', true)->firstOrFail(),
        ];
    }

    // ------------------------------------------------------------ the check itself

    private function runCheck(): int
    {
        if (! RatePlan::where('is_active', true)->exists()) {
            $this->error('Create at least one active rate plan first.');
            return self::FAILURE;
        }

        $suffix = strtoupper(bin2hex(random_bytes(3)));

        $type = RoomType::create([
            'name' => "LockCheck {$suffix}", 'base_price' => 1000000, 'max_adults' => 2,
            'max_children' => 0, 'bed_type' => 'Double', 'is_active' => true,
        ]);
        $room = Room::create(['room_number' => "LC-{$suffix}", 'floor' => 1, 'room_type_id' => $type->id]);
        $guestA = Guest::create(['full_name' => "Lock Check A {$suffix}"]);
        $guestB = Guest::create(['full_name' => "Lock Check B {$suffix}"]);

        try {
            return $this->race($type, $room, $guestA, $guestB);
        } finally {
            Booking::withTrashed()->where('room_id', $room->id)->forceDelete();
            $room->forceDelete();
            $type->forceDelete();
            $guestA->forceDelete();
            $guestB->forceDelete();
            $this->line('Temporary test records removed.');
        }
    }

    private function race(RoomType $type, Room $room, Guest $guestA, Guest $guestB): int
    {
        $this->info('Two separate PHP processes will try to book the only room of a temporary room type.');

        [$holder, $hPipes] = $this->start('holder', $type->id, $guestA->id);

        $first = fgets($hPipes[1]);

        if ($first === false || ! str_contains($first, 'HOLDER booked')) {
            $this->error('The holder process failed to start.');
            $this->line((string) stream_get_contents($hPipes[2]));
            $this->finish($holder, $hPipes);

            return self::FAILURE;
        }

        $this->line('1. Holder booked the room and is holding the lock without committing yet.');

        [$rival, $rPipes] = $this->start('rival', $type->id, $guestB->id);
        $this->line('2. Rival is now trying to book the same room...');

        $holderOut = (string) stream_get_contents($hPipes[1]);
        $rivalOut = (string) stream_get_contents($rPipes[1]);
        $rivalErr = (string) stream_get_contents($rPipes[2]);

        $this->finish($holder, $hPipes);
        $this->finish($rival, $rPipes);

        $refused = str_contains($rivalOut, 'RIVAL refused: No room of this type is available');
        $waited = preg_match('/RIVAL waited ([\d.]+) seconds/', $rivalOut, $m) ? (float) $m[1] : 0.0;
        $bookings = Booking::where('room_id', $room->id)->count();

        $this->newLine();
        $this->line('Holder: ' . trim($first) . ' | ' . trim($holderOut));
        $this->line('Rival:  ' . trim(str_replace("\n", ' | ', $rivalOut)) . ($rivalErr !== '' ? ' | errors: ' . trim($rivalErr) : ''));
        $this->line("Bookings saved for the room: {$bookings}");
        $this->newLine();

        if ($refused && $waited >= 3.0 && $bookings === 1) {
            $this->info("PASS: the rival waited {$waited} seconds for the holder, then was refused. Only one booking exists.");

            return self::SUCCESS;
        }

        if (str_contains($rivalOut, 'RIVAL booked') || $bookings > 1) {
            $this->error('FAIL: both bookings were saved for the same room. The lock is NOT protecting the room.');

            return self::FAILURE;
        }

        if ($refused) {
            $this->warn('INCONCLUSIVE: the rival was refused but did not wait, so it probably started after the holder finished. Run the check again.');

            return self::FAILURE;
        }

        $this->error('Something unexpected happened. Copy the lines above and send them to me.');

        return self::FAILURE;
    }

    /** @return array{0: resource, 1: array} */
    private function start(string $worker, int $typeId, int $guestId): array
    {
        $process = proc_open(
            [PHP_BINARY, base_path('artisan'), 'hotel:check-booking-lock', "--worker={$worker}", "--room-type={$typeId}", "--guest={$guestId}"],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            base_path(),
        );

        if (! is_resource($process)) {
            throw new \RuntimeException('Could not start a worker process.');
        }

        return [$process, $pipes];
    }

    private function finish($process, array $pipes): void
    {
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        proc_close($process);
    }
}