@php
    $m = fn (int $kobo) => $symbol . number_format($kobo / 100, 2);
    $sourceLabels = ['base' => 'Base', 'weekend' => 'Weekend', 'seasonal' => 'Seasonal'];
@endphp

<div class="py-8">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">

        <div class="bg-white p-4 shadow sm:rounded-lg">
            <form wire:submit="calculate" class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Room type</label>
                    <select wire:model="roomTypeId" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">Select</option>
                        @foreach ($roomTypes as $t)
                            <option value="{{ $t->id }}">{{ $t->name }}</option>
                        @endforeach
                    </select>
                    @error('roomTypeId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Rate plan</label>
                    <select wire:model="ratePlanId" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">Select</option>
                        @foreach ($ratePlans as $p)
                            <option value="{{ $p->id }}">{{ $p->name }}</option>
                        @endforeach
                    </select>
                    @error('ratePlanId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Check-in</label>
                    <input type="date" wire:model="checkIn" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                    @error('checkIn') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Check-out</label>
                    <input type="date" wire:model="checkOut" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                    @error('checkOut') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Adults</label>
                    <input type="number" min="1" wire:model="adults" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                    @error('adults') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Children</label>
                    <input type="number" min="0" wire:model="children" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                    @error('children') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Promo code (optional)</label>
                    <input type="text" wire:model="promoCode" class="mt-1 w-full rounded-md border-gray-300 uppercase shadow-sm sm:w-64">
                    @error('promoCode') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">
                        Get price
                    </button>
                </div>
            </form>
        </div>

        @if ($error !== '')
            <div class="rounded-md bg-red-50 p-3 text-sm text-red-800">{{ $error }}</div>
        @endif

        @if ($quote)
            <div class="bg-white p-4 shadow sm:rounded-lg">
                <h3 class="mb-3 font-semibold text-gray-800">Price breakdown</h3>

                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="text-left text-xs font-semibold uppercase text-gray-500">
                        <tr>
                            <th class="py-2 pr-4">Night</th>
                            <th class="py-2 pr-4">Price type</th>
                            <th class="py-2 text-right">Rate after plan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($quote['nights'] as $n)
                            <tr>
                                <td class="py-2 pr-4">{{ \Carbon\Carbon::parse($n['date'])->format('D d M Y') }}</td>
                                <td class="py-2 pr-4">{{ $sourceLabels[$n['source']] ?? $n['source'] }}</td>
                                <td class="py-2 text-right">{{ $m($n['rate']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <dl class="mt-4 space-y-2 border-t border-gray-200 pt-3 text-sm">
                    <div class="flex justify-between"><dt>Room total</dt><dd>{{ $m($quote['roomSubtotal']) }}</dd></div>

                    @if ($quote['extraGuests'] > 0)
                        <div class="flex justify-between">
                            <dt>Extra guest fee ({{ $quote['extraGuests'] }} {{ \Illuminate\Support\Str::plural('guest', $quote['extraGuests']) }})</dt>
                            <dd>{{ $m($quote['extraPersonTotal']) }}</dd>
                        </div>
                    @endif

                    @if ($quote['discount'] > 0)
                        <div class="flex justify-between text-green-700">
                            <dt>Promo {{ $quote['promoCode'] }}</dt>
                            <dd>-{{ $m($quote['discount']) }}</dd>
                        </div>
                    @endif

                    <div class="flex justify-between">
                        <dt>Service charge {{ $quote['serviceChargeIncluded'] ? '(included in price)' : '' }}</dt>
                        <dd>{{ $m($quote['serviceCharge']) }}</dd>
                    </div>

                    <div class="flex justify-between">
                        <dt>Tax {{ $quote['taxIncluded'] ? '(included in price)' : '' }}</dt>
                        <dd>{{ $m($quote['tax']) }}</dd>
                    </div>

                    <div class="flex justify-between border-t border-gray-200 pt-2 text-base font-semibold text-gray-900">
                        <dt>Total</dt>
                        <dd>{{ $m($quote['total']) }}</dd>
                    </div>
                </dl>
            </div>
        @endif
    </div>
</div>