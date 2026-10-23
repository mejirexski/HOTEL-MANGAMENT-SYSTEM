<?php

namespace App\Livewire\Bookings;


use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Support\HotelSettings;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class BookingList extends Component
{
    use WithPagination;

    private const VIEW_ROLES = 
        [
            'owner',
            'general-manager', 
            'receptionist', 
            'cashier'
        ];
    private const CREATE_ROLES = 
        [
            'owner', 
            'general-manager', 
            'receptionist'
        ];

    public string $search = '';
    public string $statusFilter = '';
    public string $from = '';
    public string $to = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(self::VIEW_ROLES), 403);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'statusFilter', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $bookings = Booking::query()
            ->with(['guest:id,full_name,phone', 'room:id,room_number', 'roomType:id,name', 'ratePlan:id,name'])
            ->when($this->search !== '', function ($query) {
                $term = '%' . trim($this->search) . '%';

                $query->where(function ($w) use ($term) {
                    $w->where('reference', 'like', $term)
                        ->orWhereHas('guest', fn ($g) => $g
                            ->where('full_name', 'like', $term)
                            ->orWhere('phone', 'like', $term)
                            ->orWhere('email', 'like', $term));
                });
            })
            ->when(
                BookingStatus::tryFrom($this->statusFilter) !== null,
                fn ($q) => $q->where('status', $this->statusFilter)
            )
            ->when($this->from !== '', fn ($q) => $q->whereDate('check_in', '>=', $this->from))
            ->when($this->to !== '', fn ($q) => $q->whereDate('check_in', '<=', $this->to))
            ->orderByDesc('id')
            ->paginate(15);

        return view('livewire.bookings.booking-list', [
            'bookings' => $bookings,
            'statuses' => BookingStatus::cases(),
            'canCreate' => Auth::user()->hasAnyRole(self::CREATE_ROLES),
            'symbol' => HotelSettings::get('currency_symbol'),
        ]);
    }
}