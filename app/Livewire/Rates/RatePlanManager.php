<?php

namespace App\Livewire\Rates;

use App\Support\Bookings\BookingException;
use App\Models\RatePlan;
use App\Support\AuditLog;
use App\Support\HotelSettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

class RatePlanManager extends Component
{
    private const VIEW_ROLES = ['owner', 'general-manager', 'receptionist', 'cashier', 'housekeeping', 'maintenance'];
    private const MANAGE_ROLES = ['owner', 'general-manager'];

    public bool $showForm = false;
    public ?int $editingId = null;
    public string $name = '';
    public string $description = '';
    public string $adjustmentPercent = '0';   // entered as a percentage, stored as basis points
    public string $extraPerNight = '0';       // entered in naira, stored in kobo
    public bool $isRefundable = true;
    public string $freeCancellationHours = '';
    public string $inclusions = '';
    public bool $isActive = true;

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
                Rule::unique('rate_plans', 'name')->ignore($this->editingId)->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'adjustmentPercent' => ['required', 'numeric', 'between:-90,200'],
            'extraPerNight' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'isRefundable' => ['boolean'],
            'freeCancellationHours' => ['nullable', 'integer', 'min:0', 'max:720'],
            'inclusions' => ['nullable', 'string', 'max:255'],
            'isActive' => ['boolean'],
        ];
    }

    private function snapshot(RatePlan $plan): array
    {
        return [
            'name' => $plan->name,
            'description' => $plan->description,
            'adjustment_bps' => $plan->adjustment_bps,
            'extra_per_night' => $plan->extra_per_night,
            'is_refundable' => $plan->is_refundable,
            'free_cancellation_hours' => $plan->free_cancellation_hours,
            'inclusions' => $plan->inclusions,
            'is_active' => $plan->is_active,
        ];
    }

    private function plain(float $number): string
    {
        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'description', 'freeCancellationHours', 'inclusions']);
        $this->adjustmentPercent = '0';
        $this->extraPerNight = '0';
        $this->isRefundable = true;
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

        $plan = RatePlan::findOrFail($id);

        $this->resetValidation();
        $this->editingId = $plan->id;
        $this->name = $plan->name;
        $this->description = $plan->description ?? '';
        $this->adjustmentPercent = $this->plain($plan->adjustment_bps / 100);
        $this->extraPerNight = $this->plain($plan->extra_per_night / 100);
        $this->isRefundable = $plan->is_refundable;
        $this->freeCancellationHours = $plan->free_cancellation_hours !== null ? (string) $plan->free_cancellation_hours : '';
        $this->inclusions = $plan->inclusions ?? '';
        $this->isActive = $plan->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorizeManage();

        $data = $this->validate();

        $attributes = [
            'name' => trim($data['name']),
            'description' => $data['description'] ?: null,
            'adjustment_bps' => (int) round(((float) $data['adjustmentPercent']) * 100),
            'extra_per_night' => (int) round(((float) $data['extraPerNight']) * 100),
            'is_refundable' => (bool) $data['isRefundable'],
            'free_cancellation_hours' => $data['freeCancellationHours'] !== '' ? (int) $data['freeCancellationHours'] : null,
            'inclusions' => $data['inclusions'] ?: null,
            'is_active' => (bool) $data['isActive'],
        ];

        if ($this->editingId) {
            $plan = RatePlan::findOrFail($this->editingId);
            $before = $this->snapshot($plan);

            $plan->update($attributes);

            AuditLog::record('rate_plan.updated', $plan, $before, $this->snapshot($plan->refresh()));

            session()->flash('status', 'Rate plan updated.');
        } else {
            $plan = RatePlan::create($attributes);

            AuditLog::record('rate_plan.created', $plan, null, $this->snapshot($plan->refresh()));

            session()->flash('status', 'Rate plan created.');
        }

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

        $plan = RatePlan::findOrFail($id);
        $plan->update(['is_active' => ! $plan->is_active]);

        AuditLog::record(
            $plan->is_active ? 'rate_plan.activated' : 'rate_plan.deactivated',
            $plan,
            ['is_active' => ! $plan->is_active],
            ['is_active' => $plan->is_active],
        );
    }

    public function deletePlan(int $id): void
    {
        $this->authorizeManage();

        $plan = RatePlan::findOrFail($id);
        $before = $this->snapshot($plan);

        try {
            $plan->delete(); // soft delete; refused while current or future bookings use it
        } catch (BookingException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        AuditLog::record('rate_plan.deleted', $plan, $before, null);

        session()->flash('status', 'Rate plan deleted.');
    }

    public function render()
    {
        return view('livewire.rates.rate-plan-manager', [
            'plans' => RatePlan::orderBy('id')->get(),
            'canManage' => $this->canManage(),
            'symbol' => HotelSettings::get('currency_symbol'),
            'defaultHours' => HotelSettings::get('free_cancellation_hours'),
        ]);
    }
}