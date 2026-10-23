<?php

namespace App\Livewire\Guests;

use App\Models\Guest;
use App\Support\AuditLog;
use App\Support\Bookings\BookingException;
use App\Support\GuestAccess;
use App\Support\HotelSettings;
use Livewire\Attributes\Locked;
use Livewire\Component;

class GuestProfile extends Component
{
    public Guest $guest;

    // Locked: the browser cannot switch this on by itself. Only revealId() can, and it writes the audit entry.
    #[Locked]
    public bool $showId = false;

    public function mount(Guest $guest): void
    {
        abort_unless(GuestAccess::canView(), 403);

        $this->guest = $guest;
    }

    public function revealId(): void
    {
        abort_unless(GuestAccess::canSeeIdDetails(), 403);

        if (filled($this->guest->id_number)) {
            AuditLog::record('guest.id_viewed', $this->guest, null, ['id_type' => $this->guest->id_type]);
        }

        $this->showId = true;
    }

    public function hideId(): void
    {
        $this->showId = false;
    }

    public function deleteGuest()
    {
        abort_unless(GuestAccess::canDelete(), 403);

        $before = [
            'full_name' => $this->guest->full_name,
            'phone' => $this->guest->phone,
            'email' => $this->guest->email,
        ];

        try {
            $this->guest->delete(); // soft delete; refused while bookings or a waitlist entry are still open
        } catch (BookingException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        AuditLog::record('guest.deleted', $this->guest, $before, null);
        session()->flash('status', 'Guest deleted.');

        return $this->redirect(route('guests.index'));
    }

    public function render()
    {
        return view('livewire.guests.guest-profile', [
            'stats' => $this->guest->bookings()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'bookings' => $this->guest->bookings()
                ->with(['room', 'roomType'])
                ->orderByDesc('check_in')
                ->limit(50)
                ->get(),
            'waitlist' => $this->guest->waitlistEntries()
                ->with('roomType')
                ->orderByDesc('id')
                ->limit(10)
                ->get(),
            'canManage' => GuestAccess::canManage(),
            'canSeeId' => GuestAccess::canSeeIdDetails(),
            'canDelete' => GuestAccess::canDelete(),
            'symbol' => HotelSettings::get('currency_symbol'),
        ]);
    }
}