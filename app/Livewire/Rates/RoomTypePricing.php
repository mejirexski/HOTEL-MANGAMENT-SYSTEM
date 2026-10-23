<?php

namespace App\Livewire\Rates;


use App\Models\RoomType;
use App\Models\SeasonalRate;
use App\Models\StayRestriction;
use App\Support\AuditLog;
use App\Support\HotelSettings;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class RoomTypePricing extends Component
{
    private const VIEW_ROLES = ['owner', 'general-manager', 'receptionist', 'cashier', 'housekeeping', 'maintenance'];
    private const MANAGE_ROLES = ['owner', 'general-manager'];

    public RoomType $roomType;

    // Basic pricing rules (money entered in naira, stored in kobo)
    public string $weekendPrice = '';
    public string $extraPersonFee = '0';
    public string $baseOccupancy = '2';
    public string $minStay = '1';
    public string $maxStay = '';

    // Seasonal rates
    public bool $showSeasonForm = false;
    public ?int $seasonId = null;
    public string $seasonName = '';
    public string $seasonStart = '';
    public string $seasonEnd = '';
    public string $seasonPrice = '';

    // Stay restrictions
    public bool $showRuleForm = false;
    public ?int $ruleId = null;
    public string $ruleStart = '';
    public string $ruleEnd = '';
    public bool $ruleClosed = false;
    public string $ruleMinStay = '';
    public string $ruleNote = '';

    public function mount(RoomType $roomType): void
    {
        abort_unless(Auth::user()->hasAnyRole(self::VIEW_ROLES), 403);

        $this->roomType = $roomType;
        $this->loadPricing();
    }

    private function canManage(): bool
    {
        return Auth::user()->hasAnyRole(self::MANAGE_ROLES);
    }

    private function authorizeManage(): void
    {
        abort_unless($this->canManage(), 403);
    }

    private function plain(float $number): string
    {
        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }

    private function kobo(string $naira): int
    {
        return (int) round(((float) $naira) * 100);
    }

    private function loadPricing(): void
    {
        $rt = $this->roomType->refresh();

        $this->weekendPrice = $rt->weekend_price !== null ? $this->plain($rt->weekend_price / 100) : '';
        $this->extraPersonFee = $this->plain($rt->extra_person_fee / 100);
        $this->baseOccupancy = (string) $rt->base_occupancy;
        $this->minStay = (string) $rt->min_stay;
        $this->maxStay = $rt->max_stay !== null ? (string) $rt->max_stay : '';
    }

    // ---------------------------------------------------------------- pricing rules

    private function pricingSnapshot(): array
    {
        $rt = $this->roomType;

        return [
            'weekend_price' => $rt->weekend_price,
            'extra_person_fee' => $rt->extra_person_fee,
            'base_occupancy' => $rt->base_occupancy,
            'min_stay' => $rt->min_stay,
            'max_stay' => $rt->max_stay,
        ];
    }

    public function savePricing(): void
    {
        $this->authorizeManage();

        $data = $this->validate([
            'weekendPrice' => ['nullable', 'numeric', 'gt:0', 'max:100000000'],
            'extraPersonFee' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'baseOccupancy' => ['required', 'integer', 'min:1', 'max:10'],
            'minStay' => ['required', 'integer', 'min:1', 'max:365'],
            'maxStay' => ['nullable', 'integer', 'min:1', 'max:365', 'gte:minStay'],
        ]);

        $before = $this->pricingSnapshot();

        $this->roomType->update([
            'weekend_price' => $data['weekendPrice'] !== '' ? $this->kobo($data['weekendPrice']) : null,
            'extra_person_fee' => $this->kobo($data['extraPersonFee']),
            'base_occupancy' => (int) $data['baseOccupancy'],
            'min_stay' => (int) $data['minStay'],
            'max_stay' => $data['maxStay'] !== '' ? (int) $data['maxStay'] : null,
        ]);

        $after = $this->pricingSnapshot();

        if ($before !== $after) {
            AuditLog::record('room_type.pricing_updated', $this->roomType, $before, $after);
            session()->flash('status', 'Pricing rules saved.');
        } else {
            session()->flash('status', 'No changes to save.');
        }
    }

    // ---------------------------------------------------------------- seasonal rates

    private function seasonSnapshot(SeasonalRate $s): array
    {
        return [
            'name' => $s->name,
            'start_date' => $s->start_date->toDateString(),
            'end_date' => $s->end_date->toDateString(),
            'price' => $s->price,
        ];
    }

    private function resetSeasonForm(): void
    {
        $this->reset(['seasonId', 'seasonName', 'seasonStart', 'seasonEnd', 'seasonPrice']);
        $this->resetValidation();
    }

    public function createSeason(): void
    {
        $this->authorizeManage();
        $this->resetSeasonForm();
        $this->showSeasonForm = true;
    }

    public function editSeason(int $id): void
    {
        $this->authorizeManage();

        $season = SeasonalRate::where('room_type_id', $this->roomType->id)->findOrFail($id);

        $this->resetValidation();
        $this->seasonId = $season->id;
        $this->seasonName = $season->name;
        $this->seasonStart = $season->start_date->format('Y-m-d');
        $this->seasonEnd = $season->end_date->format('Y-m-d');
        $this->seasonPrice = $this->plain($season->price / 100);
        $this->showSeasonForm = true;
    }

    public function saveSeason(): void
    {
        $this->authorizeManage();

        $data = $this->validate([
            'seasonName' => ['required', 'string', 'max:100'],
            'seasonStart' => ['required', 'date'],
            'seasonEnd' => ['required', 'date', 'after_or_equal:seasonStart'],
            'seasonPrice' => ['required', 'numeric', 'gt:0', 'max:100000000'],
        ]);

        $overlaps = SeasonalRate::where('room_type_id', $this->roomType->id)
            ->when($this->seasonId, fn ($q) => $q->where('id', '!=', $this->seasonId))
            ->where('start_date', '<=', $data['seasonEnd'])
            ->where('end_date', '>=', $data['seasonStart'])
            ->exists();

        if ($overlaps) {
            $this->addError('seasonStart', 'These dates overlap another seasonal rate for this room type.');
            return;
        }

        $attributes = [
            'name' => trim($data['seasonName']),
            'start_date' => $data['seasonStart'],
            'end_date' => $data['seasonEnd'],
            'price' => $this->kobo($data['seasonPrice']),
        ];

        if ($this->seasonId) {
            $season = SeasonalRate::where('room_type_id', $this->roomType->id)->findOrFail($this->seasonId);
            $before = $this->seasonSnapshot($season);

            $season->update($attributes);

            AuditLog::record('seasonal_rate.updated', $season, $before, $this->seasonSnapshot($season->refresh()));
            session()->flash('status', 'Seasonal rate updated.');
        } else {
            $season = SeasonalRate::create($attributes + ['room_type_id' => $this->roomType->id]);

            AuditLog::record('seasonal_rate.created', $season, null, $this->seasonSnapshot($season->refresh()));
            session()->flash('status', 'Seasonal rate added.');
        }

        $this->showSeasonForm = false;
        $this->resetSeasonForm();
    }

    public function cancelSeason(): void
    {
        $this->showSeasonForm = false;
        $this->resetSeasonForm();
    }

    public function deleteSeason(int $id): void
    {
        $this->authorizeManage();

        $season = SeasonalRate::where('room_type_id', $this->roomType->id)->findOrFail($id);
        $before = $this->seasonSnapshot($season);
        $season->delete(); // soft delete

        AuditLog::record('seasonal_rate.deleted', $season, $before, null);
        session()->flash('status', 'Seasonal rate deleted.');
    }

    // ---------------------------------------------------------------- stay restrictions

    private function ruleSnapshot(StayRestriction $r): array
    {
        return [
            'start_date' => $r->start_date->toDateString(),
            'end_date' => $r->end_date->toDateString(),
            'closed_to_arrival' => $r->closed_to_arrival,
            'min_stay' => $r->min_stay,
            'note' => $r->note,
        ];
    }

    private function resetRuleForm(): void
    {
        $this->reset(['ruleId', 'ruleStart', 'ruleEnd', 'ruleMinStay', 'ruleNote']);
        $this->ruleClosed = false;
        $this->resetValidation();
    }

    public function createRule(): void
    {
        $this->authorizeManage();
        $this->resetRuleForm();
        $this->showRuleForm = true;
    }

    public function editRule(int $id): void
    {
        $this->authorizeManage();

        $rule = StayRestriction::where('room_type_id', $this->roomType->id)->findOrFail($id);

        $this->resetValidation();
        $this->ruleId = $rule->id;
        $this->ruleStart = $rule->start_date->format('Y-m-d');
        $this->ruleEnd = $rule->end_date->format('Y-m-d');
        $this->ruleClosed = $rule->closed_to_arrival;
        $this->ruleMinStay = $rule->min_stay !== null ? (string) $rule->min_stay : '';
        $this->ruleNote = $rule->note ?? '';
        $this->showRuleForm = true;
    }

    public function saveRule(): void
    {
        $this->authorizeManage();

        $data = $this->validate([
            'ruleStart' => ['required', 'date'],
            'ruleEnd' => ['required', 'date', 'after_or_equal:ruleStart'],
            'ruleClosed' => ['boolean'],
            'ruleMinStay' => ['nullable', 'integer', 'min:1', 'max:365'],
            'ruleNote' => ['nullable', 'string', 'max:255'],
        ]);

        if (! $data['ruleClosed'] && $data['ruleMinStay'] === '') {
            $this->addError('ruleMinStay', 'Choose closed to arrival, a minimum stay, or both.');
            return;
        }

        $overlaps = StayRestriction::where('room_type_id', $this->roomType->id)
            ->when($this->ruleId, fn ($q) => $q->where('id', '!=', $this->ruleId))
            ->where('start_date', '<=', $data['ruleEnd'])
            ->where('end_date', '>=', $data['ruleStart'])
            ->exists();

        if ($overlaps) {
            $this->addError('ruleStart', 'These dates overlap another restriction for this room type.');
            return;
        }

        $attributes = [
            'start_date' => $data['ruleStart'],
            'end_date' => $data['ruleEnd'],
            'closed_to_arrival' => (bool) $data['ruleClosed'],
            'min_stay' => $data['ruleMinStay'] !== '' ? (int) $data['ruleMinStay'] : null,
            'note' => $data['ruleNote'] ?: null,
        ];

        if ($this->ruleId) {
            $rule = StayRestriction::where('room_type_id', $this->roomType->id)->findOrFail($this->ruleId);
            $before = $this->ruleSnapshot($rule);

            $rule->update($attributes);

            AuditLog::record('stay_restriction.updated', $rule, $before, $this->ruleSnapshot($rule->refresh()));
            session()->flash('status', 'Restriction updated.');
        } else {
            $rule = StayRestriction::create($attributes + ['room_type_id' => $this->roomType->id]);

            AuditLog::record('stay_restriction.created', $rule, null, $this->ruleSnapshot($rule->refresh()));
            session()->flash('status', 'Restriction added.');
        }

        $this->showRuleForm = false;
        $this->resetRuleForm();
    }

    public function cancelRule(): void
    {
        $this->showRuleForm = false;
        $this->resetRuleForm();
    }

    public function deleteRule(int $id): void
    {
        $this->authorizeManage();

        $rule = StayRestriction::where('room_type_id', $this->roomType->id)->findOrFail($id);
        $before = $this->ruleSnapshot($rule);
        $rule->delete(); // soft delete

        AuditLog::record('stay_restriction.deleted', $rule, $before, null);
        session()->flash('status', 'Restriction deleted.');
    }

    public function render()
    {
        return view('livewire.rates.room-type-pricing', [
            'seasons' => SeasonalRate::where('room_type_id', $this->roomType->id)->orderBy('start_date')->get(),
            'rules' => StayRestriction::where('room_type_id', $this->roomType->id)->orderBy('start_date')->get(),
            'canManage' => $this->canManage(),
            'symbol' => HotelSettings::get('currency_symbol'),
        ]);
    }
}