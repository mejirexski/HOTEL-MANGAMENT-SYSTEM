<?php

namespace App\Livewire\Rooms;

use App\Support\Bookings\BookingException;
use App\Models\Amenity;
use App\Models\RoomType;
use App\Models\RoomTypePhoto;
use App\Support\AuditLog;
use App\Support\HotelSettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class RoomTypeManager extends Component
{
    use WithFileUploads;

    private const VIEW_ROLES = ['owner', 'general-manager', 'receptionist', 'cashier', 'housekeeping', 'maintenance'];
    private const MANAGE_ROLES = ['owner', 'general-manager'];
    private const BED_TYPES = ['Single', 'Twin', 'Double', 'Queen', 'King'];
    private const MAX_PHOTOS = 10;

    public bool $showForm = false;
    public ?int $editingId = null;
    public string $name = '';
    public string $description = '';
    public string $basePrice = '';       // entered in naira, stored in kobo
    public string $maxAdults = '2';
    public string $maxChildren = '0';
    public string $bedType = '';
    public string $sizeSqm = '';
    public bool $isActive = true;
    public array $selectedAmenities = [];
    public string $newAmenity = '';
    public array $newPhotos = [];

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

    protected function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('room_types', 'name')->ignore($this->editingId)->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'basePrice' => ['required', 'numeric', 'gt:0', 'max:100000000'],
            'maxAdults' => ['required', 'integer', 'min:1', 'max:10'],
            'maxChildren' => ['required', 'integer', 'min:0', 'max:10'],
            'bedType' => ['required', Rule::in(self::BED_TYPES)],
            'sizeSqm' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'isActive' => ['boolean'],
            'selectedAmenities' => ['array'],
            'selectedAmenities.*' => ['integer', Rule::exists('amenities', 'id')],
        ];
    }

    private function snapshot(RoomType $roomType): array
    {
        return [
            'name' => $roomType->name,
            'description' => $roomType->description,
            'base_price' => $roomType->base_price,
            'max_adults' => $roomType->max_adults,
            'max_children' => $roomType->max_children,
            'bed_type' => $roomType->bed_type,
            'size_sqm' => $roomType->size_sqm,
            'is_active' => $roomType->is_active,
            'amenities' => $roomType->amenities->pluck('name')->sort()->values()->all(),
        ];
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingId', 'name', 'description', 'basePrice', 'bedType',
            'sizeSqm', 'selectedAmenities', 'newAmenity', 'newPhotos',
        ]);
        $this->maxAdults = '2';
        $this->maxChildren = '0';
        $this->isActive = true;
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

        $roomType = RoomType::with('amenities')->findOrFail($id);

        $this->resetValidation();
        $this->newPhotos = [];
        $this->editingId = $roomType->id;
        $this->name = $roomType->name;
        $this->description = $roomType->description ?? '';
        $this->basePrice = rtrim(rtrim(number_format($roomType->base_price / 100, 2, '.', ''), '0'), '.');
        $this->maxAdults = (string) $roomType->max_adults;
        $this->maxChildren = (string) $roomType->max_children;
        $this->bedType = $roomType->bed_type;
        $this->sizeSqm = $roomType->size_sqm ? (string) $roomType->size_sqm : '';
        $this->isActive = $roomType->is_active;
        $this->selectedAmenities = $roomType->amenities->pluck('id')->map(fn ($v) => (string) $v)->all();
        $this->showForm = true;
    }

    public function addAmenity(): void
    {
        $this->authorizeManage();

        $this->validate([
            'newAmenity' => ['required', 'string', 'max:60', Rule::unique('amenities', 'name')],
        ]);

        $amenity = Amenity::create(['name' => trim($this->newAmenity)]);
        AuditLog::record('amenity.created', $amenity, null, ['name' => $amenity->name]);

        $this->selectedAmenities[] = (string) $amenity->id;
        $this->newAmenity = '';
    }

    public function save(): void
    {
        $this->authorizeManage();

        $data = $this->validate();

        $attributes = [
            'name' => trim($data['name']),
            'description' => $data['description'] ?: null,
            'base_price' => (int) round(((float) $data['basePrice']) * 100),
            'max_adults' => (int) $data['maxAdults'],
            'max_children' => (int) $data['maxChildren'],
            'bed_type' => $data['bedType'],
            'size_sqm' => $data['sizeSqm'] !== '' ? (int) $data['sizeSqm'] : null,
            'is_active' => (bool) $data['isActive'],
        ];
        $amenityIds = array_map('intval', $data['selectedAmenities'] ?? []);

        DB::transaction(function () use ($attributes, $amenityIds) {
            if ($this->editingId) {
                $roomType = RoomType::with('amenities')->findOrFail($this->editingId);
                $before = $this->snapshot($roomType);

                $roomType->update($attributes);
                $roomType->amenities()->sync($amenityIds);
                $roomType->load('amenities');
                $after = $this->snapshot($roomType);

                AuditLog::record('room_type.updated', $roomType, $before, $after);

                if ($before['base_price'] !== $after['base_price']) {
                    AuditLog::record(
                        'room_type.price_changed',
                        $roomType,
                        ['base_price' => $before['base_price']],
                        ['base_price' => $after['base_price']],
                    );
                }

                session()->flash('status', 'Room type updated.');
            } else {
                $roomType = RoomType::create($attributes);
                $roomType->amenities()->sync($amenityIds);
                $roomType->load('amenities');

                AuditLog::record('room_type.created', $roomType, null, $this->snapshot($roomType));

                session()->flash('status', 'Room type created.');
            }
        });

        $this->showForm = false;
        $this->resetForm();
    }

    public function cancel(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    public function toggleActive(int $id): void
    {
        $this->authorizeManage();

        $roomType = RoomType::findOrFail($id);
        $roomType->update(['is_active' => ! $roomType->is_active]);

        AuditLog::record(
            $roomType->is_active ? 'room_type.activated' : 'room_type.deactivated',
            $roomType,
            ['is_active' => ! $roomType->is_active],
            ['is_active' => $roomType->is_active],
        );
    }

    public function deleteType(int $id): void
    {
        $this->authorizeManage();

        $roomType = RoomType::with('amenities')->findOrFail($id);
        $before = $this->snapshot($roomType);

        try {
            $roomType->delete(); // soft delete; refused while it still has rooms
        } catch (BookingException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        AuditLog::record('room_type.deleted', $roomType, $before, null);

        session()->flash('status', 'Room type deleted.');
    }

    public function uploadPhotos(): void
    {
        $this->authorizeManage();
        abort_unless($this->editingId, 422);

        $this->validate([
            'newPhotos' => ['required', 'array', 'min:1'],
            'newPhotos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $roomType = RoomType::findOrFail($this->editingId);
        $existing = RoomTypePhoto::where('room_type_id', $roomType->id)->count();

        if ($existing + count($this->newPhotos) > self::MAX_PHOTOS) {
            $this->addError('newPhotos', 'A room type can have at most ' . self::MAX_PHOTOS . ' photos.');
            return;
        }

        $nextOrder = (int) RoomTypePhoto::where('room_type_id', $roomType->id)->max('sort_order') + 1;

        foreach ($this->newPhotos as $file) {
            $path = $file->store('room-types/' . $roomType->id, 'public');

            $photo = RoomTypePhoto::create([
                'room_type_id' => $roomType->id,
                'path' => $path,
                'sort_order' => $nextOrder++,
            ]);

            AuditLog::record('room_type.photo_added', $roomType, null, ['photo_id' => $photo->id, 'path' => $path]);
        }

        $this->reset('newPhotos');
    }

    public function makeCover(int $photoId): void
    {
        $this->authorizeManage();
        abort_unless($this->editingId, 422);

        $photo = RoomTypePhoto::where('room_type_id', $this->editingId)->findOrFail($photoId);
        $lowest = (int) RoomTypePhoto::where('room_type_id', $this->editingId)->min('sort_order');

        $photo->update(['sort_order' => $lowest - 1]);
    }

    public function removePhoto(int $photoId): void
    {
        $this->authorizeManage();
        abort_unless($this->editingId, 422);

        $photo = RoomTypePhoto::where('room_type_id', $this->editingId)->findOrFail($photoId);
        $roomType = RoomType::findOrFail($this->editingId);

        Storage::disk('public')->delete($photo->path);
        $photo->delete();

        AuditLog::record('room_type.photo_removed', $roomType, ['photo_id' => $photoId, 'path' => $photo->path], null);
    }

    public function render()
    {
        return view('livewire.rooms.room-type-manager', [
            'roomTypes' => RoomType::with(['amenities', 'photos'])->withCount('rooms')->orderBy('name')->get(),
            'photos' => $this->editingId
                ? RoomTypePhoto::where('room_type_id', $this->editingId)->orderBy('sort_order')->orderBy('id')->get()
                : collect(),
            'amenities' => Amenity::orderBy('name')->get(),
            'bedTypes' => self::BED_TYPES,
            'canManage' => $this->canManage(),
            'symbol' => HotelSettings::get('currency_symbol'),
        ]);
    }
}