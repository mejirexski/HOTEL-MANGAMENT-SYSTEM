<?php

namespace App\Livewire\Bookings;

use App\Support\Bookings\WaitlistService;
use App\Enums\BookingStatus;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Room;
use App\Support\Bookings\BookingActions;
use App\Support\Bookings\BookingException;
use App\Support\HotelSettings;
use App\Support\Pricing\PricingException;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;


class BookingDetail extends Component
{
    private const VIEW_ROLES = ['owner', 'general-manager', 'receptionist', 'cashier'];
    private const MANAGE_ROLES = ['owner', 'general-manager', 'receptionist'];

    public Booking $booking;

    public string $actionError = '';

    public bool $showCancel = false;
    public string $cancelReason = '';

    public bool $showChange = false;
    public string $newCheckIn = '';
    public string $newCheckOut = '';
    public string $newAdults = '';
    public string $newChildren = '';
    public string $newRoomId = '';
    public ?array $preview = null;

    public function mount(Booking $booking): void
    {
        abort_unless(Auth::user()->hasAnyRole(self::VIEW_ROLES), 403);

        $this->booking = $booking;
    }

    private function canManage(): bool
    {
        return Auth::user()->hasAnyRole(self::MANAGE_ROLES);
    }

    private function authorizeManage(): void
    {
        abort_unless($this->canManage(), 403);
    }

    private function actions(): BookingActions
    {
        return app(BookingActions::class);
    }

        /** After a booking is cancelled or moved, offers the freed room to the next guest on the waitlist. */
    private function offerFreedRooms(): void
    {
        try {
            if ($type = $this->booking->roomType) {
                app(WaitlistService::class)->processRoomType($type);
            }
        } catch (\Throwable $e) {
            report($e); // a waitlist problem must never break a cancellation
        }
    }

    // ------------------------------------------------------------------ confirm

    public function confirmBooking(): void
    {
        $this->authorizeManage();
        $this->actionError = '';

        try {
            $this->actions()->confirm($this->booking, Auth::user());
        } catch (BookingException $e) {
            $this->actionError = $e->getMessage();

            return;
        }

        $this->booking->refresh();
        session()->flash('status', 'Booking confirmed.');
    }

    // ------------------------------------------------------------------ cancel

    public function openCancel(): void
    {
        $this->authorizeManage();
        $this->closeChange();
        $this->actionError = '';
        $this->cancelReason = '';
        $this->resetValidation();
        $this->showCancel = true;
    }

    public function closeCancel(): void
    {
        $this->showCancel = false;
        $this->cancelReason = '';
        $this->resetValidation();
    }

    public function submitCancel(): void
    {
        $this->authorizeManage();
        $this->actionError = '';

        $this->validate(['cancelReason' => ['required', 'string', 'min:3', 'max:255']]);

        try {
            $this->actions()->cancel($this->booking, $this->cancelReason, Auth::user());
        } catch (BookingException $e) {
            $this->actionError = $e->getMessage();

            return;
        }

        $this->booking->refresh();
        $this->offerFreedRooms();
        $this->closeCancel();
        $this->closeCancel();
        session()->flash('status', 'Booking cancelled.');
    }

    // ------------------------------------------------------------------ no-show

    public function markNoShow(): void
    {
        $this->authorizeManage();
        $this->actionError = '';

        try {
            $this->actions()->markNoShow($this->booking, Auth::user());
        } catch (BookingException $e) {
            $this->actionError = $e->getMessage();

            return;
        }

        $this->booking->refresh();
        session()->flash('status', 'Booking marked as a no-show.');
    }

    // ------------------------------------------------------------------ change dates, guests or room

    public function openChange(): void
    {
        $this->authorizeManage();
        $this->closeCancel();
        $this->actionError = '';
        $this->resetValidation();

        $this->newCheckIn = $this->booking->check_in->toDateString();
        $this->newCheckOut = $this->booking->check_out->toDateString();
        $this->newAdults = (string) $this->booking->adults;
        $this->newChildren = (string) $this->booking->children;
        $this->newRoomId = '';
        $this->preview = null;
        $this->showChange = true;
    }

    public function closeChange(): void
    {
        $this->showChange = false;
        $this->preview = null;
        $this->newRoomId = '';
        $this->resetValidation();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['newCheckIn', 'newCheckOut', 'newAdults', 'newChildren'], true)) {
            $this->preview = null;
            $this->newRoomId = '';
        }
    }

    private function changeRules(): array
    {
        $arrivalChanged = $this->newCheckIn !== $this->booking->check_in->toDateString();

        return [
            'newCheckIn' => array_merge(['required', 'date'], $arrivalChanged ? ['after_or_equal:today'] : []),
            'newCheckOut' => ['required', 'date', 'after:newCheckIn'],
            'newAdults' => ['required', 'integer', 'min:1', 'max:20'],
            'newChildren' => ['required', 'integer', 'min:0', 'max:20'],
        ];
    }

    public function previewChange(): void
    {
        $this->authorizeManage();
        $this->actionError = '';
        $this->preview = null;

        $this->validate($this->changeRules());

        try {
            $result = $this->actions()->previewChange(
                $this->booking,
                $this->newCheckIn,
                $this->newCheckOut,
                (int) $this->newAdults,
                (int) $this->newChildren,
            );
        } catch (BookingException|PricingException $e) {
            $this->actionError = $e->getMessage();

            return;
        }

        $this->preview = [
            'quote' => $result['quote']->toArray(),
            'rooms' => $result['rooms']
                ->map(fn (Room $room) => ['id' => $room->id, 'room_number' => $room->room_number])
                ->all(),
        ];
    }

    public function saveChange(): void
    {
        $this->authorizeManage();
        $this->actionError = '';

        $this->validate($this->changeRules());

        $room = null;

        if ($this->newRoomId !== '') {
            $room = Room::find((int) $this->newRoomId);

            if (! $room) {
                $this->actionError = 'That room no longer exists.';

                return;
            }
        }

        try {
            $this->actions()->modify(
                $this->booking,
                $this->newCheckIn,
                $this->newCheckOut,
                (int) $this->newAdults,
                (int) $this->newChildren,
                $room,
                Auth::user(),
            );
        } catch (BookingException|PricingException $e) {
            $this->actionError = $e->getMessage();

            return;
        }

        $this->booking->refresh();
        $this->offerFreedRooms();
        $this->closeChange();
        $this->closeChange();
        session()->flash('status', 'Booking updated.');
    }

    // ------------------------------------------------------------------ render

    public function render()
    {
        $this->booking->loadMissing(['guest', 'room', 'roomType', 'ratePlan', 'promoCode', 'creator']);

        $history = ActivityLog::query()
            ->where('subject_type', $this->booking->getMorphClass())
            ->where('subject_id', $this->booking->id)
            ->with('user:id,name')
            ->orderByDesc('id')
            ->get();

        return view('livewire.bookings.booking-detail', [
            'canManage' => $this->canManage(),
            'changeable' => in_array($this->booking->status, [BookingStatus::Pending, BookingStatus::Confirmed], true),
            'arrivalReached' => $this->booking->check_in->toDateString() <= now()->toDateString(),
            'terms' => $this->actions()->cancellationTerms($this->booking),
            'history' => $history,
            'symbol' => HotelSettings::get('currency_symbol'),
        ]);
    }
}