<?php

namespace App\Livewire\Settings;

use App\Support\AuditLog;
use App\Support\HotelSettings;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class SettingsForm extends Component
{
    public array $form = [];

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['owner', 'general-manager']), 403);

        foreach (HotelSettings::keys() as $key) {
            $this->form[$key] = HotelSettings::get($key);
        }

        $this->form['tax_included'] = (bool) (int) $this->form['tax_included'];
        $this->form['service_charge_included'] = (bool) (int) $this->form['service_charge_included'];

        // The form shows the credit limit in naira; it is stored in kobo.
        $this->form['credit_limit'] = (string) ((int) $this->form['credit_limit_kobo'] / 100);
        unset($this->form['credit_limit_kobo']);
    }

    protected function rules(): array
    {
        return [
            'form.hotel_name' => ['required', 'string', 'max:150'],
            'form.hotel_address' => ['nullable', 'string', 'max:255'],
            'form.hotel_phone' => ['nullable', 'string', 'max:30'],
            'form.hotel_email' => ['nullable', 'email', 'max:150'],
            'form.currency_code' => ['required', 'string', 'size:3'],
            'form.currency_symbol' => ['required', 'string', 'max:5'],
            'form.tax_rate' => ['required', 'numeric', 'between:0,100'],
            'form.tax_included' => ['boolean'],
            'form.service_charge_rate' => ['required', 'numeric', 'between:0,100'],
            'form.service_charge_included' => ['boolean'],
            'form.check_in_time' => ['required', 'date_format:H:i'],
            'form.check_out_time' => ['required', 'date_format:H:i'],
            'form.deposit_percent' => ['required', 'numeric', 'between:0,100'],
            'form.pending_expiry_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'form.free_cancellation_hours' => ['required', 'integer', 'min:0', 'max:720'],
            'form.waitlist_offer_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'form.credit_limit' => ['required', 'numeric', 'min:0'],
            'form.bar_opens_at' => ['required', 'date_format:H:i'],
            'form.bar_closes_at' => ['required', 'date_format:H:i'],
            'form.gym_opens_at' => ['required', 'date_format:H:i'],
            'form.gym_closes_at' => ['required', 'date_format:H:i'],
            'form.gym_capacity' => ['required', 'integer', 'min:1', 'max:1000'],
        ];
    }

    private function toStored(): array
    {
        $out = [];

        foreach (HotelSettings::keys() as $key) {
            if ($key === 'credit_limit_kobo') {
                $out[$key] = (string) (int) round(((float) $this->form['credit_limit']) * 100);
                continue;
            }

            $value = $this->form[$key] ?? '';
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }
            $out[$key] = trim((string) $value);
        }

        return $out;
    }

    public function save(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['owner', 'general-manager']), 403);

        $this->validate();

        $before = [];
        $after = [];

        foreach ($this->toStored() as $key => $value) {
            $old = HotelSettings::get($key);

            if ((string) $old !== $value) {
                $before[$key] = $old;
                $after[$key] = $value;
                HotelSettings::set($key, $value);
            }
        }

        if ($after !== []) {
            AuditLog::record('settings.updated', null, $before, $after);
            session()->flash('status', 'Settings saved.');
        } else {
            session()->flash('status', 'No changes to save.');
        }
    }

    public function render()
    {
        return view('livewire.settings.settings-form');
    }
}