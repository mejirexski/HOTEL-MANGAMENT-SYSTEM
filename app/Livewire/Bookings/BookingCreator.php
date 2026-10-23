<?php

namespace App\Livewire\Bookings;

use App\Enums\BookingStatus;
use App\Models\Guest;
use App\Models\PromoCode;
use App\Models\RatePlan;
use App\Models\RoomType;
use App\Models\WaitlistEntry;
use App\Support\AuditLog;
use App\Support\Bookings\AvailabilityService;
use App\Support\Bookings\BookingException;
use App\Support\Bookings\BookingService;
use App\Support\Bookings\WaitlistService;
use App\Support\HotelSettings;
use App\Support\Pricing\PriceCalculator;
use App\Support\Pricing\PricingException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

class BookingCreator extends Component
{
    private const ROLES = ['owner', 'general-manager', 'receptionist'];

    // 1. Search
    public string $checkIn = '';
    public string $checkOut = '';
    public string $adults = '2';
    public string $children = '0';
    public bool $searched = false;
    public array $options = [];

    // 2. Selection and price
    public ?int $roomTypeId = null;
    public ?int $ratePlanId = null;
    public string $promoCode = '';
    public ?array $quote = null;
    public string $quoteError = '';

    // 3. Guest and extras
    public string $guestMode = 'new';
    public string $guestSearch = '';
    public ?int $guestId = null;
    public string $guestName = '';
    public string $guestPhone = '';
    public string $guestEmail = '';
    public string $guestNationality = '';
    public string $notes = '';
    public string $source = 'staff';
    public bool $confirmNow = false;
    public string $createError = '';

    // Set when the booking is made for a guest from the waitlist
    public ?int $waitlistId = null;

    public function mount(): void
    {
        $this->authorizeAccess();

        // Arriving from a waitlist offer: fill in the stay and the guest, then search.
        $waitlistId = (int) request()->query('waitlist', 0);

        if ($waitlistId > 0) {
            $entry = WaitlistEntry::query()->active()->find($waitlistId);

            if ($entry && $entry->check_in->toDateString() >= now()->toDateString()) {
                $this->waitlistId = $entry->id;
                $this->checkIn = $entry->check_in->toDateString();
                $this->checkOut = $entry->check_out->toDateString();
                $this->adults = (string) $entry->adults;
                $this->children = (string) $entry->children;
                $this->guestMode = 'existing';
                $this->guestId = $entry->guest_id;

                $this->search();
            }
        }
    }

    private function authorizeAccess(): void
    {
        abort_unless(Auth::user()->hasAnyRole(self::ROLES), 403);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['checkIn', 'checkOut', 'adults', 'children'], true)) {
            $this->resetSelection();
        }
    }

    private function resetSelection(): void
    {
        $this->reset(['searched', 'options', 'roomTypeId', 'ratePlanId', 'promoCode', 'quote', 'quoteError', 'createError']);
    }

    private function searchRules(): array
    {
        return [
            'checkIn' => ['required', 'date', 'after_or_equal:today'],
            'checkOut' => ['required', 'date', 'after:checkIn'],
            'adults' => ['required', 'integer', 'min:1', 'max:20'],
            'children' => ['required', 'integer', 'min:0', 'max:20'],
        ];
    }

    public function search(): void
    {
        $this->authorizeAccess();
        $this->validate($this->searchRules());
        $this->resetSelection();

        $in = CarbonImmutable::parse($this->checkIn);
        $out = CarbonImmutable::parse($this->checkOut);
        $adults = (int) $this->adults;
        $children = (int) $this->children;

        $types = app(AvailabilityService::class)->availableRoomTypes($in, $out, $adults, $children);
        $plans = RatePlan::where('is_active', true)->orderBy('id')->get();
        $calculator = app(PriceCalculator::class);

        $this->options = $types->map(function (array $row) use ($plans, $calculator, $in, $out, $adults, $children) {
            $type = $row['room_type'];

            return [
                'room_type_id' => $type->id,
                'name' => $type->name,
                'bed_type' => $type->bed_type,
                'free' => $row['free'],
                'plans' => $plans->map(function (RatePlan $plan) use ($calculator, $type, $in, $out, $adults, $children) {
                    try {
                        $quote = $calculator->quote($type, $plan, $in, $out, $adults, $children);

                        return ['rate_plan_id' => $plan->id, 'name' => $plan->name, 'total' => $quote->total, 'error' => null];
                    } catch (PricingException $e) {
                        return ['rate_plan_id' => $plan->id, 'name' => $plan->name, 'total' => null, 'error' => $e->getMessage()];
                    }
                })->all(),
            ];
        })->all();

        $this->searched = true;
    }

    public function select(int $roomTypeId, int $ratePlanId): void
    {
        $this->authorizeAccess();

        $option = collect($this->options)->firstWhere('room_type_id', $roomTypeId);
        $plan = $option ? collect($option['plans'])->firstWhere('rate_plan_id', $ratePlanId) : null;

        if (! $plan || $plan['total'] === null) {
            return;
        }

        $this->roomTypeId = $roomTypeId;
        $this->ratePlanId = $ratePlanId;
        $this->createError = '';
        $this->buildQuote();
    }

    public function applyPromo(): void
    {
        $this->authorizeAccess();
        $this->buildQuote();
    }

    private function buildQuote(): void
    {
        $this->quote = null;
        $this->quoteError = '';

        $type = $this->roomTypeId ? RoomType::find($this->roomTypeId) : null;
        $plan = $this->ratePlanId ? RatePlan::find($this->ratePlanId) : null;

        if (! $type || ! $plan) {
            return;
        }

        $in = CarbonImmutable::parse($this->checkIn);
        $out = CarbonImmutable::parse($this->checkOut);
        $calculator = app(PriceCalculator::class);

        $promo = null;
        $code = trim($this->promoCode);

        if ($code !== '') {
            $promo = PromoCode::findByCode($code);

            if (! $promo) {
                $this->quoteError = 'Promo code not found.';
            }
        }

        try {
            $this->quote = $calculator
                ->quote($type, $plan, $in, $out, (int) $this->adults, (int) $this->children, $promo)
                ->toArray();
        } catch (PricingException $e) {
            $this->quoteError = $e->getMessage();

            // A promo code that does not work must not hide the normal price.
            if ($promo) {
                try {
                    $this->quote = $calculator
                        ->quote($type, $plan, $in, $out, (int) $this->adults, (int) $this->children)
                        ->toArray();
                } catch (PricingException $inner) {
                    $this->quoteError = $inner->getMessage();
                }
            }
        }
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

    public function create()
    {
        $this->authorizeAccess();
        $this->createError = '';

        if ($this->quote === null || ! $this->roomTypeId || ! $this->ratePlanId) {
            $this->createError = 'Choose a room type and rate plan first.';

            return;
        }

        $guestRules = $this->guestMode === 'existing'
            ? ['guestId' => ['required', Rule::exists('guests', 'id')->whereNull('deleted_at')]]
            : [
                'guestName' => ['required', 'string', 'max:150'],
                'guestPhone' => ['required', 'string', 'max:30'],
                'guestEmail' => ['nullable', 'email', 'max:150'],
                'guestNationality' => ['nullable', 'string', 'max:80'],
            ];

        $this->validate(array_merge($this->searchRules(), $guestRules, [
            'notes' => ['nullable', 'string', 'max:1000'],
            'source' => ['required', Rule::in(['staff', 'walk_in'])],
        ]));

        $createdGuest = null;

        try {
            if ($this->guestMode === 'existing') {
                $guest = Guest::findOrFail($this->guestId);
            } else {
                $guest = $createdGuest = Guest::create([
                    'full_name' => trim($this->guestName),
                    'phone' => trim($this->guestPhone),
                    'email' => trim($this->guestEmail) !== '' ? trim($this->guestEmail) : null,
                    'nationality' => trim($this->guestNationality) !== '' ? trim($this->guestNationality) : null,
                ]);
            }

            $booking = app(BookingService::class)->create(
                roomType: RoomType::findOrFail($this->roomTypeId),
                ratePlan: RatePlan::findOrFail($this->ratePlanId),
                guest: $guest,
                checkIn: $this->checkIn,
                checkOut: $this->checkOut,
                adults: (int) $this->adults,
                children: (int) $this->children,
                promoCode: trim($this->promoCode) !== '' ? $this->promoCode : null,
                source: $this->source,
                notes: $this->notes !== '' ? $this->notes : null,
                actor: Auth::user(),
                status: $this->confirmNow ? BookingStatus::Confirmed : BookingStatus::Pending,
            );
        } catch (BookingException $e) {
            $createdGuest?->forceDelete();
            $this->createError = $e->getMessage() . ' Search again to see what is available now.';

            return;
        } catch (PricingException $e) {
            $createdGuest?->forceDelete();
            $this->createError = $e->getMessage();

            return;
        }

        if ($createdGuest) {
            AuditLog::record('guest.created', $createdGuest, null, [
                'full_name' => $createdGuest->full_name,
                'phone' => $createdGuest->phone,
                'email' => $createdGuest->email,
            ]);
        }

        // A booking made for a guest on the waitlist closes their waitlist entry.
        if ($this->waitlistId) {
            $entry = WaitlistEntry::query()->active()->find($this->waitlistId);

            if ($entry && $entry->guest_id === $booking->guest_id) {
                try {
                    app(WaitlistService::class)->markBooked($entry, Auth::user());
                } catch (BookingException) {
                    // The entry changed in the meantime. The booking itself is saved.
                }
            }
        }

        session()->flash('status', "Booking {$booking->reference} created.");

        return $this->redirect(route('bookings.index'));
    }

    public function render()
    {
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

        $depositDue = null;

        if ($this->quote) {
            $bps = (int) round(((float) HotelSettings::get('deposit_percent')) * 100);
            $depositDue = intdiv($this->quote['total'] * $bps + 5000, 10000);
        }

        // Room types that fit the party but have no free room: offer the waitlist for them.
        $fullTypes = collect();

        if ($this->searched) {
            $fullTypes = RoomType::query()
                ->where('is_active', true)
                ->where('max_adults', '>=', (int) $this->adults)
                ->where('max_children', '>=', (int) $this->children)
                ->whereHas('rooms')
                ->whereNotIn('id', collect($this->options)->pluck('room_type_id'))
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        return view('livewire.bookings.booking-creator', [
            'symbol' => HotelSettings::get('currency_symbol'),
            'matches' => $matches,
            'selectedGuest' => $this->guestId ? Guest::find($this->guestId, ['id', 'full_name', 'phone', 'email']) : null,
            'selectedType' => $this->roomTypeId ? RoomType::find($this->roomTypeId, ['id', 'name']) : null,
            'selectedPlan' => $this->ratePlanId ? RatePlan::find($this->ratePlanId, ['id', 'name']) : null,
            'depositDue' => $depositDue,
            'depositPercent' => HotelSettings::get('deposit_percent'),
            'fullTypes' => $fullTypes,
        ]);
    }
}