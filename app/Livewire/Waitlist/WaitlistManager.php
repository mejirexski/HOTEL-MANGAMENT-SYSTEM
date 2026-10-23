<?php

namespace App\Livewire\Waitlist;

use App\Enums\WaitlistStatus;
use App\Models\Guest;
use App\Models\RoomType;
use App\Models\WaitlistEntry;
use App\Support\AuditLog;
use App\Support\Bookings\BookingException;
use App\Support\Bookings\WaitlistService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class WaitlistManager extends Component
{
    use WithPagination;

    private const ROLES = ['owner', 'general-manager', 'receptionist'];

    public string $statusFilter = 'active';
    public string $typeFilter = '';
    public string $search = '';

    public bool $showForm = false;
    public string $roomTypeId = '';
    public string $checkIn = '';
    public string $checkOut = '';
    public string $adults = '2';
    public string $children = '0';
    public string $notes = '';
    public string $guestMode = 'new';
    public string $guestSearch = '';
    public ?int $guestId = null;
    public string $guestName = '';
    public string $guestPhone = '';
    public string $guestEmail = '';
    public string $formError = '';

    public function mount(): void
    {
        $this->authorizeAccess();

        // Arriving from a booking search where a room type was full: open the form with the stay filled in.
        $type = request()->query('room_type');
        $in = request()->query('check_in');
        $out = request()->query('check_out');

        if (is_scalar($type) && is_string($in) && is_string($out)) {
            $this->roomTypeId = (string) (int) $type;
            $this->checkIn = $in;
            $this->checkOut = $out;

            $adults = request()->query('adults');
            $children = request()->query('children');

            $this->adults = is_scalar($adults) ? (string) max(1, (int) $adults) : '2';
            $this->children = is_scalar($children) ? (string) max(0, (int) $children) : '0';
            $this->showForm = true;
        }
    }

    private function authorizeAccess(): void
    {
        abort_unless(Auth::user()->hasAnyRole(self::ROLES), 403);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['statusFilter', 'typeFilter', 'search'], true)) {
            $this->resetPage();
        }
    }

    // ------------------------------------------------------------------ add to the waitlist

    private function resetForm(): void
    {
        $this->reset([
            'roomTypeId', 'checkIn', 'checkOut', 'notes', 'guestSearch', 'guestId',
            'guestName', 'guestPhone', 'guestEmail', 'formError',
        ]);
        $this->adults = '2';
        $this->children = '0';
        $this->guestMode = 'new';
        $this->resetValidation();
    }

    public function openForm(): void
    {
        $this->authorizeAccess();
        $this->resetForm();
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    public function selectGuest(int $id): void
    {
        $this->authorizeAccess();

        if (Guest::whereKey($id)->exists()) {
            $this->guestId = $id;
            $this->guestSearch = '';
        }
    }

    public function clearGuest(): void
    {
        $this->guestId = null;
    }

    public function join(): void
    {
        $this->authorizeAccess();
        $this->formError = '';

        $guestRules = $this->guestMode === 'existing'
            ? ['guestId' => ['required', Rule::exists('guests', 'id')->whereNull('deleted_at')]]
            : [
                'guestName' => ['required', 'string', 'max:150'],
                'guestPhone' => ['required', 'string', 'max:30'],
                'guestEmail' => ['nullable', 'email', 'max:150'],
            ];

        $this->validate(array_merge([
            'roomTypeId' => ['required', Rule::exists('room_types', 'id')->whereNull('deleted_at')],
            'checkIn' => ['required', 'date', 'after_or_equal:today'],
            'checkOut' => ['required', 'date', 'after:checkIn'],
            'adults' => ['required', 'integer', 'min:1', 'max:20'],
            'children' => ['required', 'integer', 'min:0', 'max:20'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], $guestRules));

        $createdGuest = null;

        try {
            if ($this->guestMode === 'existing') {
                $guest = Guest::findOrFail($this->guestId);
            } else {
                $guest = $createdGuest = Guest::create([
                    'full_name' => trim($this->guestName),
                    'phone' => trim($this->guestPhone),
                    'email' => trim($this->guestEmail) !== '' ? trim($this->guestEmail) : null,
                ]);
            }

            app(WaitlistService::class)->join(
                RoomType::findOrFail((int) $this->roomTypeId),
                $guest,
                $this->checkIn,
                $this->checkOut,
                (int) $this->adults,
                (int) $this->children,
                trim($this->notes) !== '' ? trim($this->notes) : null,
                Auth::user(),
            );
        } catch (BookingException $e) {
            $createdGuest?->forceDelete();
            $this->formError = $e->getMessage();

            return;
        }

        if ($createdGuest) {
            AuditLog::record('guest.created', $createdGuest, null, [
                'full_name' => $createdGuest->full_name,
                'phone' => $createdGuest->phone,
                'email' => $createdGuest->email,
            ]);
        }

        $this->closeForm();
        session()->flash('status', 'Guest added to the waitlist.');
    }

    // ------------------------------------------------------------------ entry actions

    public function cancelEntry(int $id): void
    {
        $this->authorizeAccess();

        try {
            app(WaitlistService::class)->cancel(WaitlistEntry::findOrFail($id), Auth::user());
        } catch (BookingException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        session()->flash('status', 'Waitlist entry cancelled.');
    }

    public function markBooked(int $id): void
    {
        $this->authorizeAccess();

        try {
            app(WaitlistService::class)->markBooked(WaitlistEntry::findOrFail($id), Auth::user());
        } catch (BookingException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        session()->flash('status', 'Waitlist entry marked as booked.');
    }

    public function processNow(): void
    {
        $this->authorizeAccess();

        $result = app(WaitlistService::class)->processAll();

        session()->flash(
            'status',
            "Waitlist checked: {$result['notified']} room(s) offered, {$result['lapsed']} entr" . ($result['lapsed'] === 1 ? 'y' : 'ies') . ' lapsed.'
        );
    }

    // ------------------------------------------------------------------ render

    public function render()
    {
        $isQueue = $this->statusFilter === 'active';

        $entries = WaitlistEntry::query()
            ->with(['guest', 'roomType'])
            ->when($isQueue, fn ($q) => $q->active())
            ->when(
                ! $isQueue && WaitlistStatus::tryFrom($this->statusFilter) !== null,
                fn ($q) => $q->where('status', $this->statusFilter)
            )
            ->when($this->typeFilter !== '', fn ($q) => $q->where('room_type_id', (int) $this->typeFilter))
            ->when($this->search !== '', function ($query) {
                $term = '%' . trim($this->search) . '%';

                $query->whereHas('guest', fn ($g) => $g
                    ->where('full_name', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('email', 'like', $term));
            })
            ->when(
                $isQueue,
                fn ($q) => $q->orderBy('created_at')->orderBy('id'),
                fn ($q) => $q->orderByDesc('id')
            )
            ->paginate(15);

        $matches = collect();

        if ($this->guestMode === 'existing' && mb_strlen(trim($this->guestSearch)) >= 2) {
            $term = '%' . trim($this->guestSearch) . '%';

            $matches = Guest::query()
                ->where(fn ($q) => $q->where('full_name', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('email', 'like', $term))
                ->orderBy('full_name')
                ->limit(8)
                ->get(['id', 'full_name', 'phone', 'email']);
        }

        return view('livewire.waitlist.waitlist-manager', [
            'entries' => $entries,
            'roomTypes' => RoomType::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'allRoomTypes' => RoomType::withTrashed()->orderBy('name')->get(['id', 'name']),
            'statuses' => WaitlistStatus::cases(),
            'matches' => $matches,
            'selectedGuest' => $this->guestId ? Guest::find($this->guestId, ['id', 'full_name', 'phone', 'email']) : null,
        ]);
    }
}