<?php

namespace App\Livewire\Rooms;

use App\Enums\RoomStatus;
use App\Models\Room;
use App\Models\RoomType;
use App\Support\AuditLog;
use App\Support\Bookings\BookingException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class RoomManager extends Component
{
    use WithPagination;

    private const VIEW_ROLES = ['owner', 'general-manager', 'receptionist', 'cashier', 'housekeeping', 'maintenance'];
    private const MANAGE_ROLES = ['owner', 'general-manager'];

    public string $search = '';
    public string $statusFilter = '';
    public string $typeFilter = '';
    public string $floorFilter = '';

    public bool $showForm = false;
    public ?int $editingId = null;
    public string $roomNumber = '';
    public string $floor = '';
    public string $roomTypeId = '';
    public string $status = 'available';
    public string $notes = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(self::VIEW_ROLES), 403);
    }

    private function canManage(): bool
    {
        return Auth::user()->hasAnyRole(self::MANAGE_ROLES);
    }

    private function authorizeManage(): void
    {
        abort_unless($this->canManage(), 403);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'statusFilter', 'typeFilter', 'floorFilter'], true)) {
            $this->resetPage();
        }
    }

    protected function rules(): array
    {
        return [
            'roomNumber' => [
                'required', 'string', 'max:20',
                Rule::unique('rooms', 'room_number')->ignore($this->editingId),
            ],
            'floor' => ['nullable', 'integer', 'min:-5', 'max:100'],
            'roomTypeId' => ['required', Rule::exists('room_types', 'id')->whereNull('deleted_at')],
            'status' => ['required', Rule::enum(RoomStatus::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function snapshot(Room $room): array
    {
        return [
            'room_number' => $room->room_number,
            'floor' => $room->floor,
            'room_type_id' => $room->room_type_id,
            'status' => $room->status->value,
            'notes' => $room->notes,
        ];
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'roomNumber', 'floor', 'roomTypeId', 'notes']);
        $this->status = RoomStatus::Available->value;
        $this->resetValidation();
    }

    public function create(): void
    {
        $this->authorizeManage();
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorizeManage();

        $room = Room::findOrFail($id);

        $this->resetValidation();
        $this->editingId = $room->id;
        $this->roomNumber = $room->room_number;
        $this->floor = $room->floor !== null ? (string) $room->floor : '';
        $this->roomTypeId = (string) $room->room_type_id;
        $this->status = $room->status->value;
        $this->notes = $room->notes ?? '';
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorizeManage();

        $data = $this->validate();

        $attributes = [
            'room_number' => trim($data['roomNumber']),
            'floor' => $data['floor'] !== '' ? (int) $data['floor'] : null,
            'room_type_id' => (int) $data['roomTypeId'],
            'status' => $data['status'],
            'notes' => $data['notes'] ?: null,
        ];

        if ($this->editingId) {
            $room = Room::findOrFail($this->editingId);
            $before = $this->snapshot($room);

            $room->update($attributes);
            $after = $this->snapshot($room->refresh());

            AuditLog::record('room.updated', $room, $before, $after);

            if ($before['status'] !== $after['status']) {
                AuditLog::record(
                    'room.status_changed',
                    $room,
                    ['status' => $before['status']],
                    ['status' => $after['status']],
                );
            }

            session()->flash('status', 'Room updated.');
        } else {
            $room = Room::create($attributes);
            AuditLog::record('room.created', $room, null, $this->snapshot($room->refresh()));

            session()->flash('status', 'Room created.');
        }

        $this->showForm = false;
        $this->resetForm();
    }

    public function cancel(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

        public function deleteRoom(int $id): void
    {
        $this->authorizeManage();

        $room = Room::findOrFail($id);
        $before = $this->snapshot($room);

        try {
            $room->delete(); // soft delete; refused while the room has current or future bookings
        } catch (BookingException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        AuditLog::record('room.deleted', $room, $before, null);

        session()->flash('status', 'Room deleted.');
    }

    public function render()
    {
        $rooms = Room::query()
            ->with('roomType:id,name')
            ->when($this->search !== '', fn ($q) => $q->where('room_number', 'like', "%{$this->search}%"))
            ->when(
                RoomStatus::tryFrom($this->statusFilter) !== null,
                fn ($q) => $q->where('status', $this->statusFilter)
            )
            ->when($this->typeFilter !== '', fn ($q) => $q->where('room_type_id', (int) $this->typeFilter))
            ->when($this->floorFilter !== '', fn ($q) => $q->where('floor', (int) $this->floorFilter))
            ->orderBy('room_number')
            ->paginate(15);

        return view('livewire.rooms.room-manager', [
            'rooms' => $rooms,
            'roomTypes' => RoomType::orderBy('name')->get(['id', 'name']),
            'statuses' => RoomStatus::cases(),
            'floors' => Room::query()->whereNotNull('floor')->distinct()->orderBy('floor')->pluck('floor'),
            'canManage' => $this->canManage(),
        ]);
    }
}