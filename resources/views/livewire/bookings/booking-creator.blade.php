@php
    $m = fn (int $kobo) => $symbol . number_format($kobo / 100, 2);
    $sourceLabels = ['base' => 'Base', 'weekend' => 'Weekend', 'seasonal' => 'Seasonal'];
@endphp

<div class="py-8">
            @if ($fullTypes->isNotEmpty())
                <div class="bg-white p-4 shadow sm:rounded-lg">
                    <h3 class="mb-2 font-semibold text-gray-800">Fully booked for these dates</h3>

                    <ul class="divide-y divide-gray-100 text-sm">
                        @foreach ($fullTypes as $t)
                            <li class="flex items-center justify-between py-2" wire:key="full-{{ $t->id }}">
                                <span>{{ $t->name }}</span>
                                <a href="{{ route('waitlist.index', ['room_type' => $t->id, 'check_in' => $checkIn, 'check_out' => $checkOut, 'adults' => $adults, 'children' => $children]) }}"
                                   class="text-indigo-600 hover:underline">Join the waitlist</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">

        <div class="px-4 sm:px-0">
            <a href="{{ route('bookings.index') }}" class="text-sm text-indigo-600 hover:underline">&larr; Back to bookings</a>
        </div>

        {{-- 1. Dates and guests --}}
        <div class="bg-white p-4 shadow sm:rounded-lg">
            <h3 class="mb-3 font-semibold text-gray-800">1. Dates and guests</h3>

            <form wire:submit="search" class="grid gap-4 sm:grid-cols-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Check-in</label>
                    <input type="date" wire:model="checkIn" min="{{ now()->toDateString() }}" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                    @error('checkIn') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Check-out</label>
                    <input type="date" wire:model="checkOut" min="{{ now()->toDateString() }}" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
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
                <div class="flex items-end">
                    <button type="submit" class="w-full rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">
                        Search rooms
                    </button>
                </div>
            </form>
        </div>

        {{-- 2. Room types and rate plans --}}
        @if ($searched)
            <div class="bg-white p-4 shadow sm:rounded-lg">
                <h3 class="mb-3 font-semibold text-gray-800">2. Choose a room type and rate plan</h3>

                @forelse ($options as $opt)
                    <div class="mb-3 rounded-md border border-gray-200 p-3" wire:key="opt-{{ $opt['room_type_id'] }}">
                        <div class="flex items-center justify-between">
                            <p class="font-medium text-gray-900">
                                {{ $opt['name'] }} <span class="text-sm font-normal text-gray-500">({{ $opt['bed_type'] }})</span>
                            </p>
                            <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-800">{{ $opt['free'] }} free</span>
                        </div>

                        <div class="mt-2 divide-y divide-gray-100 text-sm">
                            @foreach ($opt['plans'] as $plan)
                                <div class="flex items-center justify-between py-2" wire:key="opt-{{ $opt['room_type_id'] }}-{{ $plan['rate_plan_id'] }}">
                                    <span>{{ $plan['name'] }}</span>

                                    @if ($plan['total'] !== null)
                                        <span class="flex items-center gap-3">
                                            <span class="font-semibold">{{ $m($plan['total']) }}</span>
                                            @if ($roomTypeId === $opt['room_type_id'] && $ratePlanId === $plan['rate_plan_id'])
                                                <span class="rounded-md bg-green-100 px-3 py-1 text-xs font-semibold text-green-800">Selected</span>
                                            @else
                                                <button type="button" wire:click="select({{ $opt['room_type_id'] }}, {{ $plan['rate_plan_id'] }})"
                                                        class="rounded-md border border-gray-300 px-3 py-1 text-xs text-gray-700 hover:bg-gray-50">Select</button>
                                            @endif
                                        </span>
                                    @else
                                        <span class="text-xs text-red-600">{{ $plan['error'] }}</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No room type is free for those dates and guests.</p>
                @endforelse
            </div>
            
                @if ($fullTypes->isNotEmpty())
                <div class="bg-white p-4 shadow sm:rounded-lg">
                    <h3 class="mb-2 font-semibold text-gray-800">Fully booked for these dates</h3>

                    <ul class="divide-y divide-gray-100 text-sm">
                        @foreach ($fullTypes as $t)
                            <li class="flex items-center justify-between py-2" wire:key="full-{{ $t->id }}">
                                <span>{{ $t->name }}</span>
                                <a href="{{ route('waitlist.index', ['room_type' => $t->id, 'check_in' => $checkIn, 'check_out' => $checkOut, 'adults' => $adults, 'children' => $children]) }}"
                                   class="text-indigo-600 hover:underline">Join the waitlist</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endif

        {{-- 3. Price, promo code, guest, save --}}
        @if ($quote)
            <div class="bg-white p-4 shadow sm:rounded-lg">
                <h3 class="font-semibold text-gray-800">3. Price</h3>
                <p class="mb-3 text-sm text-gray-600">{{ $selectedType?->name }}, {{ $selectedPlan?->name }}</p>

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
                            <dt>Extra guest fee ({{ $quote['extraGuests'] }})</dt>
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
                        <dt>Service charge {{ $quote['serviceChargeIncluded'] ? '(included)' : '' }}</dt>
                        <dd>{{ $m($quote['serviceCharge']) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>Tax {{ $quote['taxIncluded'] ? '(included)' : '' }}</dt>
                        <dd>{{ $m($quote['tax']) }}</dd>
                    </div>

                    <div class="flex justify-between border-t border-gray-200 pt-2 text-base font-semibold text-gray-900">
                        <dt>Total</dt>
                        <dd>{{ $m($quote['total']) }}</dd>
                    </div>
                    <div class="flex justify-between text-gray-600">
                        <dt>Deposit ({{ $depositPercent }}%)</dt>
                        <dd>{{ $m($depositDue) }}</dd>
                    </div>
                </dl>

                <div class="mt-4 flex flex-wrap items-end gap-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Promo code (optional)</label>
                        <input type="text" wire:model="promoCode" class="mt-1 rounded-md border-gray-300 uppercase shadow-sm">
                    </div>
                    <button type="button" wire:click="applyPromo"
                            class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Apply</button>
                </div>
                @if ($quoteError !== '')
                    <p class="mt-2 text-sm text-red-600">{{ $quoteError }}</p>
                @endif
            </div>

            <div class="bg-white p-4 shadow sm:rounded-lg">
                <h3 class="mb-3 font-semibold text-gray-800">4. Guest</h3>

                <div class="mb-4 flex gap-6 text-sm text-gray-700">
                    <label class="flex items-center gap-2">
                        <input type="radio" wire:model.live="guestMode" value="new"> New guest
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="radio" wire:model.live="guestMode" value="existing"> Existing guest
                    </label>
                </div>

                @if ($guestMode === 'new')
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Full name</label>
                            <input type="text" wire:model="guestName" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                            @error('guestName') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Phone</label>
                            <input type="text" wire:model="guestPhone" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                            @error('guestPhone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Email (optional)</label>
                            <input type="email" wire:model="guestEmail" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                            @error('guestEmail') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nationality (optional)</label>
                            <input type="text" wire:model="guestNationality" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                            @error('guestNationality') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                @else
                    @if ($selectedGuest)
                        <div class="flex items-center justify-between rounded-md bg-gray-50 p-3 text-sm">
                            <span>
                                <span class="font-medium text-gray-900">{{ $selectedGuest->full_name }}</span>
                                <span class="text-gray-500">{{ $selectedGuest->phone }} {{ $selectedGuest->email }}</span>
                            </span>
                            <button type="button" wire:click="clearGuest" class="text-indigo-600 hover:underline">Change</button>
                        </div>
                    @else
                        <input type="search" wire:model.live.debounce.300ms="guestSearch" placeholder="Search by name, phone or email"
                               class="w-full rounded-md border-gray-300 text-sm shadow-sm sm:w-96">

                        @if ($matches->isNotEmpty())
                            <ul class="mt-2 divide-y divide-gray-100 rounded-md border border-gray-200 text-sm">
                                @foreach ($matches as $g)
                                    <li class="flex items-center justify-between p-2" wire:key="guest-{{ $g->id }}">
                                        <span>
                                            <span class="font-medium text-gray-900">{{ $g->full_name }}</span>
                                            <span class="text-gray-500">{{ $g->phone }} {{ $g->email }}</span>
                                        </span>
                                        <button type="button" wire:click="selectGuest({{ $g->id }})" class="text-indigo-600 hover:underline">Select</button>
                                    </li>
                                @endforeach
                            </ul>
                        @elseif (mb_strlen(trim($guestSearch)) >= 2)
                            <p class="mt-2 text-sm text-gray-500">No guests found. Use "New guest" instead.</p>
                        @endif
                    @endif
                    @error('guestId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                @endif

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Booking type</label>
                        <select wire:model="source" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                            <option value="staff">Booked by staff</option>
                            <option value="walk_in">Walk-in</option>
                        </select>
                        @error('source') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex items-end">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" wire:model="confirmNow" class="rounded border-gray-300">
                            Confirm now (deposit received or walk-in)
                        </label>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Notes (optional)</label>
                        <textarea wire:model="notes" rows="2" class="mt-1 w-full rounded-md border-gray-300 shadow-sm"></textarea>
                        @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                @if ($createError !== '')
                    <div class="mt-4 rounded-md bg-red-50 p-3 text-sm text-red-800">{{ $createError }}</div>
                @endif

                <div class="mt-4">
                    <button type="button" wire:click="create" wire:loading.attr="disabled" wire:target="create"
                            class="rounded-md bg-gray-800 px-5 py-2 text-sm font-semibold text-white hover:bg-gray-700">
                        Create booking
                    </button>
                </div>
            </div>
        @endif
    </div>
</div>