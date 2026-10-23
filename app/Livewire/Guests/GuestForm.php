<?php

namespace App\Livewire\Guests;

use App\Models\Guest;
use App\Support\AuditLog;
use App\Support\GuestAccess;
use Illuminate\Validation\Rule;
use Livewire\Component;

class GuestForm extends Component
{
    public const ID_TYPES = [
        'National ID (NIN)',
        "Driver's licence",
        'International passport',
        "Voter's card",
        'Other',
    ];

    public ?int $guestId = null;
    public string $fullName = '';
    public string $phone = '';
    public string $email = '';
    public string $address = '';
    public string $nationality = '';
    public string $notes = '';
    public string $idType = '';
    public string $idNumber = '';
    public bool $clearId = false;

    public bool $confirmDuplicate = false;
    public array $duplicates = [];

    public function mount(?int $guestId = null): void
    {
        abort_unless(GuestAccess::canManage(), 403);

        if ($guestId !== null) {
            $guest = Guest::findOrFail($guestId);

            $this->guestId = $guest->id;
            $this->fullName = $guest->full_name;
            $this->phone = $guest->phone ?? '';
            $this->email = $guest->email ?? '';
            $this->address = $guest->address ?? '';
            $this->nationality = $guest->nationality ?? '';
            $this->notes = $guest->notes ?? '';
            $this->idType = $guest->id_type ?? '';
            // The ID number itself is never loaded into the form.
        }
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['phone', 'email'], true)) {
            $this->confirmDuplicate = false;
            $this->duplicates = [];
        }
    }

    protected function rules(): array
    {
        $rules = [
            'fullName' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:30', 'regex:/\d{6,}/'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'nationality' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];

        if (GuestAccess::canSeeIdDetails()) {
            $rules['idType'] = ['nullable', Rule::in(self::ID_TYPES), 'required_with:idNumber'];
            $rules['idNumber'] = ['nullable', 'string', 'max:50'];
            $rules['clearId'] = ['boolean'];
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'phone.regex' => 'Enter a phone number with at least 6 digits.',
            'idType.required_with' => 'Choose the type of ID that goes with the number.',
        ];
    }

    private function snapshot(Guest $guest): array
    {
        return [
            'full_name' => $guest->full_name,
            'phone' => $guest->phone,
            'email' => $guest->email,
            'address' => $guest->address,
            'nationality' => $guest->nationality,
            'id_type' => $guest->id_type,
            'id_on_file' => filled($guest->id_number),
            'notes' => $guest->notes,
        ];
    }

    private function clean(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    public function save()
    {
        abort_unless(GuestAccess::canManage(), 403);

        $this->validate();

        $duplicates = Guest::findDuplicates($this->phone, $this->email, $this->guestId);

        if ($duplicates->isNotEmpty() && ! $this->confirmDuplicate) {
            $this->duplicates = $duplicates->map(fn (Guest $g) => [
                'id' => $g->id,
                'full_name' => $g->full_name,
                'phone' => $g->phone,
                'email' => $g->email,
            ])->all();

            return;
        }

        $this->duplicates = [];

        $attributes = [
            'full_name' => trim($this->fullName),
            'phone' => trim($this->phone),
            'email' => $this->clean($this->email),
            'address' => $this->clean($this->address),
            'nationality' => $this->clean($this->nationality),
            'notes' => $this->clean($this->notes),
        ];

        $idNumberReplaced = false;

        if (GuestAccess::canSeeIdDetails()) {
            if ($this->clearId) {
                $attributes['id_type'] = null;
                $attributes['id_number'] = null;
            } else {
                $attributes['id_type'] = $this->clean($this->idType);

                if (trim($this->idNumber) !== '') {
                    $attributes['id_number'] = trim($this->idNumber);
                    $idNumberReplaced = true;
                }
            }
        }

        if ($this->guestId) {
            $guest = Guest::findOrFail($this->guestId);
            $before = $this->snapshot($guest);

            $guest->update($attributes);

            $after = $this->snapshot($guest->refresh());

            if ($idNumberReplaced) {
                $after['id_number_replaced'] = true; // the number itself is never written to the log
            }

            AuditLog::record('guest.updated', $guest, $before, $after);
            session()->flash('status', 'Guest updated.');
        } else {
            $guest = Guest::create($attributes);

            AuditLog::record('guest.created', $guest, null, $this->snapshot($guest->refresh()));
            session()->flash('status', 'Guest added.');
        }

        return $this->redirect(route('guests.show', $guest));
    }

    public function saveAnyway()
    {
        $this->confirmDuplicate = true;

        return $this->save();
    }

    public function render()
    {
        return view('livewire.guests.guest-form', [
            'idTypes' => self::ID_TYPES,
            'canSeeId' => GuestAccess::canSeeIdDetails(),
            'maskedId' => $this->guestId ? Guest::find($this->guestId)?->maskedIdNumber() : null,
        ]);
    }
}