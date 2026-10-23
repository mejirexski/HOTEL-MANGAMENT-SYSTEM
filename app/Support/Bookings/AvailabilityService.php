<?php

namespace App\Support\Bookings;

use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomType;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class AvailabilityService
{
    /**
     * Free rooms of a type for the stay, lowest room number first (9, 10, 101, ...).
     * Pass $ignoreBookingId when changing an existing booking so it does not block itself.
     *
     * @return Collection<int, Room>
     */
    public function freeRooms(
        RoomType $roomType,
        CarbonInterface|string $checkIn,
        CarbonInterface|string $checkOut,
        ?int $ignoreBookingId = null,
    ): Collection {
        [$in, $out] = $this->range($checkIn, $checkOut);

        return Room::query()
            ->where('room_type_id', $roomType->id)
            ->whereNotIn('id', $this->takenRooms($in, $out, $ignoreBookingId)->select('room_id'))
            ->orderByRaw('LENGTH(room_number), room_number')
            ->get();
    }

    public function isRoomFree(
        Room $room,
        CarbonInterface|string $checkIn,
        CarbonInterface|string $checkOut,
        ?int $ignoreBookingId = null,
    ): bool {
        [$in, $out] = $this->range($checkIn, $checkOut);

        return ! $this->takenRooms($in, $out, $ignoreBookingId)
            ->where('room_id', $room->id)
            ->exists();
    }

    /**
     * Active room types that can take this party and still have a free room, with the free count.
     *
     * @return Collection<int, array{room_type: RoomType, free: int}>
     */
    public function availableRoomTypes(
        CarbonInterface|string $checkIn,
        CarbonInterface|string $checkOut,
        int $adults = 1,
        int $children = 0,
    ): Collection {
        [$in, $out] = $this->range($checkIn, $checkOut);

        return RoomType::query()
            ->where('is_active', true)
            ->where('max_adults', '>=', $adults)
            ->where('max_children', '>=', $children)
            ->orderBy('name')
            ->get()
            ->map(fn (RoomType $type) => [
                'room_type' => $type,
                'free' => $this->freeRooms($type, $in, $out)->count(),
            ])
            ->filter(fn (array $row) => $row['free'] > 0)
            ->values();
    }

    /** Bookings that hold a room during the stay (the overlap rule from the PRD). */
    private function takenRooms(string $in, string $out, ?int $ignoreBookingId): Builder
    {
        return Booking::query()
            ->blocking()
            ->overlapping($in, $out)
            ->when($ignoreBookingId, fn (Builder $q) => $q->where('id', '!=', $ignoreBookingId));
    }

    /** @return array{0: string, 1: string} */
    private function range(CarbonInterface|string $checkIn, CarbonInterface|string $checkOut): array
    {
        $in = CarbonImmutable::parse($checkIn)->toDateString();
        $out = CarbonImmutable::parse($checkOut)->toDateString();

        if ($out <= $in) {
            throw new InvalidArgumentException('Check-out must be after check-in.');
        }

        return [$in, $out];
    }
}