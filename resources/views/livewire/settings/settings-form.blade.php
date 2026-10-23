@php
    $sections = [
        'Hotel details' => [
            ['hotel_name', 'Hotel name', 'text'],
            ['hotel_address', 'Address', 'text'],
            ['hotel_phone', 'Phone', 'text'],
            ['hotel_email', 'Email', 'email'],
        ],
        'Currency, tax and service charge' => [
            ['currency_code', 'Currency code (3 letters)', 'text'],
            ['currency_symbol', 'Currency symbol', 'text'],
            ['tax_rate', 'Tax rate (%)', 'number'],
            ['service_charge_rate', 'Service charge (%)', 'number'],
        ],
        'Stay times' => [
            ['check_in_time', 'Check-in time', 'time'],
            ['check_out_time', 'Check-out time', 'time'],
        ],
        'Booking policy' => [
            ['deposit_percent', 'Deposit required (%)', 'number'],
            ['pending_expiry_hours', 'Unpaid booking expires after (hours)', 'number'],
            ['free_cancellation_hours', 'Free cancellation until (hours before check-in)', 'number'],
            ['waitlist_offer_hours', 'Hold an offered waitlist room for (hours)', 'number'],
        ],
        'Charge to room' => [
            ['credit_limit', 'Credit limit per guest (₦)', 'number'],
        ],
        'Bar and gym' => [
            ['bar_opens_at', 'Bar opens', 'time'],
            ['bar_closes_at', 'Bar closes', 'time'],
            ['gym_opens_at', 'Gym opens', 'time'],
            ['gym_closes_at', 'Gym closes', 'time'],
            ['gym_capacity', 'Gym capacity (people)', 'number'],
        ],
    ];
@endphp

<div class="py-8">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

        @if (session('status'))
            <div class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <form wire:submit="save" class="space-y-6">
            @foreach ($sections as $title => $fields)
                <div class="bg-white p-4 shadow sm:rounded-lg">
                    <h3 class="mb-3 font-semibold text-gray-800">{{ $title }}</h3>

                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach ($fields as [$field, $label, $type])
                            <div>
                                <label class="block text-sm font-medium text-gray-700">{{ $label }}</label>
                                <input type="{{ $type }}" wire:model="form.{{ $field }}"
                                       @if ($type === 'number') step="any" min="0" @endif
                                       class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                @error("form.$field") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                    </div>

                    @if ($title === 'Currency, tax and service charge')
                        <div class="mt-4 space-y-2">
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" wire:model="form.tax_included" class="rounded border-gray-300">
                                Tax is already included in listed prices
                            </label>
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" wire:model="form.service_charge_included" class="rounded border-gray-300">
                                Service charge is already included in listed prices
                            </label>
                        </div>
                    @endif
                </div>
            @endforeach

            <button type="submit" class="rounded-md bg-gray-800 px-5 py-2 text-sm font-semibold text-white hover:bg-gray-700">
                Save settings
            </button>
        </form>
    </div>
</div>