<?php

namespace App\Livewire\Rates;

use App\Models\PromoCode;
use App\Models\RatePlan;
use App\Support\AuditLog;
use App\Support\HotelSettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

class PromoCodeManager extends Component
{
    private const ROLES = ['owner', 'general-manager'];

    public bool $showForm = false;
    public ?int $editingId = null;
    public string $code = '';
    public string $description = '';
    public string $type = PromoCode::PERCENT;
    public string $value = '';                 // percent, or naira; stored x100
    public string $validFrom = '';
    public string $validTo = '';
    public string $usageLimit = '';
    public array $selectedPlans = [];
    public bool $isActive = true;

    public function mount(): void
    {
        $this->authorizeAccess();
    }

    private function authorizeAccess(): void
    {
        abort_unless(Auth::user()->hasAnyRole(self::ROLES), 403);
    }

    private function plain(float $number): string
    {
        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }

    protected function rules(): array
    {
        $used = $this->editingId
            ? (int) PromoCode::whereKey($this->editingId)->value('used_count')
            : 0;

        return [
            'code' => [
                'required', 'string', 'min:3', 'max:30', 'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('promo_codes', 'code')->ignore($this->editingId),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in([PromoCode::PERCENT, PromoCode::FIXED])],
            'value' => $this->type === PromoCode::PERCENT
                ? ['required', 'numeric', 'gt:0', 'max:100']
                : ['required', 'numeric', 'gt:0', 'max:100000000'],
            'validFrom' => ['nullable', 'date'],
            'validTo' => ['nullable', 'date', 'after_or_equal:validFrom'],
            'usageLimit' => ['nullable', 'integer', 'min:' . max(1, $used)],
            'selectedPlans' => ['array'],
            'selectedPlans.*' => ['integer', Rule::exists('rate_plans', 'id')->whereNull('deleted_at')],
            'isActive' => ['boolean'],
        ];
    }

    private function snapshot(PromoCode $promo): array
    {
        return [
            'code' => $promo->code,
            'description' => $promo->description,
            'type' => $promo->type,
            'value' => $promo->value,
            'valid_from' => $promo->valid_from?->toDateString(),
            'valid_to' => $promo->valid_to?->toDateString(),
            'usage_limit' => $promo->usage_limit,
            'is_active' => $promo->is_active,
            'rate_plans' => $promo->ratePlans->pluck('name')->sort()->values()->all(),
        ];
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'code', 'description', 'value', 'validFrom', 'validTo', 'usageLimit', 'selectedPlans']);
        $this->type = PromoCode::PERCENT;
        $this->isActive = true;
        $this->resetValidation();
    }

    public function create(): void
    {
        $this->authorizeAccess();
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorizeAccess();

        $promo = PromoCode::with('ratePlans')->findOrFail($id);

        $this->resetValidation();
        $this->editingId = $promo->id;
        $this->code = $promo->code;
        $this->description = $promo->description ?? '';
        $this->type = $promo->type;
        $this->value = $this->plain($promo->value / 100);
        $this->validFrom = $promo->valid_from?->format('Y-m-d') ?? '';
        $this->validTo = $promo->valid_to?->format('Y-m-d') ?? '';
        $this->usageLimit = $promo->usage_limit !== null ? (string) $promo->usage_limit : '';
        $this->selectedPlans = $promo->ratePlans->pluck('id')->map(fn ($v) => (string) $v)->all();
        $this->isActive = $promo->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorizeAccess();

        $data = $this->validate();

        $attributes = [
            'code' => strtoupper(trim($data['code'])),
            'description' => $data['description'] ?: null,
            'type' => $data['type'],
            'value' => (int) round(((float) $data['value']) * 100),
            'valid_from' => $data['validFrom'] ?: null,
            'valid_to' => $data['validTo'] ?: null,
            'usage_limit' => $data['usageLimit'] !== '' ? (int) $data['usageLimit'] : null,
            'is_active' => (bool) $data['isActive'],
        ];
        $planIds = array_map('intval', $data['selectedPlans'] ?? []);

        DB::transaction(function () use ($attributes, $planIds) {
            if ($this->editingId) {
                $promo = PromoCode::with('ratePlans')->findOrFail($this->editingId);
                $before = $this->snapshot($promo);

                $promo->update($attributes);
                $promo->ratePlans()->sync($planIds);
                $promo->load('ratePlans');

                AuditLog::record('promo_code.updated', $promo, $before, $this->snapshot($promo));
                session()->flash('status', 'Promo code updated.');
            } else {
                $promo = PromoCode::create($attributes);
                $promo->ratePlans()->sync($planIds);
                $promo->load('ratePlans');

                AuditLog::record('promo_code.created', $promo, null, $this->snapshot($promo));
                session()->flash('status', 'Promo code created.');
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
        $this->authorizeAccess();

        $promo = PromoCode::findOrFail($id);
        $promo->update(['is_active' => ! $promo->is_active]);

        AuditLog::record(
            $promo->is_active ? 'promo_code.activated' : 'promo_code.deactivated',
            $promo,
            ['is_active' => ! $promo->is_active],
            ['is_active' => $promo->is_active],
        );
    }

    public function deleteCode(int $id): void
    {
        $this->authorizeAccess();

        $promo = PromoCode::with('ratePlans')->findOrFail($id);
        $before = $this->snapshot($promo);
        $promo->delete(); // soft delete

        AuditLog::record('promo_code.deleted', $promo, $before, null);
        session()->flash('status', 'Promo code deleted.');
    }

    public function render()
    {
        return view('livewire.rates.promo-code-manager', [
            'promos' => PromoCode::with('ratePlans')->orderByDesc('id')->get(),
            'plans' => RatePlan::orderBy('name')->get(['id', 'name']),
            'symbol' => HotelSettings::get('currency_symbol'),
        ]);
    }
}