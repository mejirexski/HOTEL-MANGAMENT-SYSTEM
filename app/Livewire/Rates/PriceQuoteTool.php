<?php

namespace App\Livewire\Rates;

use App\Models\PromoCode;
use App\Models\RatePlan;
use App\Models\RoomType;
use App\Support\HotelSettings;
use App\Support\Pricing\PriceCalculator;
use App\Support\Pricing\PricingException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

class PriceQuoteTool extends Component
{
    private const ROLES = ['owner', 'general-manager', 'receptionist', 'cashier'];

    public string $roomTypeId = '';
    public string $ratePlanId = '';
    public string $checkIn = '';
    public string $checkOut = '';
    public string $adults = '2';
    public string $children = '0';
    public string $promoCode = '';

    public ?array $quote = null;
    public string $error = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(self::ROLES), 403);
    }

    public function calculate(): void
    {
        abort_unless(Auth::user()->hasAnyRole(self::ROLES), 403);

        $this->quote = null;
        $this->error = '';

        $data = $this->validate([
            'roomTypeId' => ['required', Rule::exists('room_types', 'id')->whereNull('deleted_at')],
            'ratePlanId' => ['required', Rule::exists('rate_plans', 'id')->whereNull('deleted_at')],
            'checkIn' => ['required', 'date'],
            'checkOut' => ['required', 'date', 'after:checkIn'],
            'adults' => ['required', 'integer', 'min:1', 'max:20'],
            'children' => ['required', 'integer', 'min:0', 'max:20'],
            'promoCode' => ['nullable', 'string', 'max:30'],
        ]);

        $promo = null;
        $code = trim((string) ($data['promoCode'] ?? ''));

        if ($code !== '') {
            $promo = PromoCode::findByCode($code);

            if (! $promo) {
                $this->error = 'Promo code not found.';
                return;
            }
        }

        try {
            $quote = app(PriceCalculator::class)->quote(
                RoomType::findOrFail($data['roomTypeId']),
                RatePlan::findOrFail($data['ratePlanId']),
                CarbonImmutable::parse($data['checkIn']),
                CarbonImmutable::parse($data['checkOut']),
                (int) $data['adults'],
                (int) $data['children'],
                $promo,
            );
        } catch (PricingException $e) {
            $this->error = $e->getMessage();
            return;
        }

        $this->quote = $quote->toArray();
    }

    public function render()
    {
        return view('livewire.rates.price-quote-tool', [
            'roomTypes' => RoomType::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'ratePlans' => RatePlan::where('is_active', true)->orderBy('id')->get(['id', 'name']),
            'symbol' => HotelSettings::get('currency_symbol'),
        ]);
    }
}