<?php

namespace App\Livewire\Guests;

use App\Models\Guest;
use App\Support\GuestAccess;
use Livewire\Component;
use Livewire\WithPagination;

class GuestList extends Component
{
    use WithPagination;

    public string $search = '';

    public function mount(): void
    {
        abort_unless(GuestAccess::canView(), 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $guests = Guest::query()
            ->search($this->search)
            ->withCount('bookings')
            ->withMax('bookings as last_check_in', 'check_in')
            ->orderBy('full_name')
            ->paginate(15);

        return view('livewire.guests.guest-list', [
            'guests' => $guests,
            'canManage' => GuestAccess::canManage(),
        ]);
    }
}