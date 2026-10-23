<div class="py-8">
    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex items-center justify-between px-4 sm:px-0">
            <a href="{{ route('room-types.index') }}" class="text-sm text-indigo-600 hover:underline">&larr; Back to room types</a>
            <p class="text-sm text-gray-600">
                Base price: <span class="font-semibold">{{ $symbol }}{{ number_format($roomType->base_price / 100, 2) }}</span> per night
            </p>
        </div>

        @if (session('status'))
            <div class="rounded-md bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        {{-- Pricing rules --}}
        <div class="bg-white p-4 shadow sm:rounded-lg">
            <h3 class="font-semibold text-gray-800">Pricing rules</h3>
            <p class="mb-3 text-xs text-gray-500">Seasonal rate overrides the weekend price, which overrides the base price. Weekend nights are Friday and Saturday.</p>

            <form wire:submit="savePricing" class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Weekend price per night ({{ $symbol }}, optional)</label>
                    <input type="number" step="0.01" min="0" wire:model="weekendPrice" @disabled(! $canManage)
                           class="mt-1 w-full rounded-md border-gray-300 shadow-sm disabled:bg-gray-100">
                    @error('weekendPrice') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Extra person fee per night ({{ $symbol }})</label>
                    <input type="number" step="0.01" min="0" wire:model="extraPersonFee" @disabled(! $canManage)
                           class="mt-1 w-full rounded-md border-gray-300 shadow-sm disabled:bg-gray-100">
                    @error('extraPersonFee') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Guests included in the price</label>
                    <input type="number" min="1" wire:model="baseOccupancy" @disabled(! $canManage)
                           class="mt-1 w-full rounded-md border-gray-300 shadow-sm disabled:bg-gray-100">
                    @error('baseOccupancy') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Minimum stay (nights)</label>
                        <input type="number" min="1" wire:model="minStay" @disabled(! $canManage)
                               class="mt-1 w-full rounded-md border-gray-300 shadow-sm disabled:bg-gray-100">
                        @error('minStay') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Maximum stay (optional)</label>
                        <input type="number" min="1" wire:model="maxStay" @disabled(! $canManage)
                               class="mt-1 w-full rounded-md border-gray-300 shadow-sm disabled:bg-gray-100">
                        @error('maxStay') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                @if ($canManage)
                    <div class="sm:col-span-2">
                        <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">Save pricing rules</button>
                    </div>
                @endif
            </form>
        </div>

        {{-- Seasonal rates --}}
        <div class="bg-white p-4 shadow sm:rounded-lg">
            <div class="mb-3 flex items-center justify-between">
                <div>
                    <h3 class="font-semibold text-gray-800">Seasonal rates</h3>
                    <p class="text-xs text-gray-500">One price for every night from the first night to the last night, inclusive.</p>
                </div>
                @if ($canManage)
                    <button type="button" wire:click="createSeason"
                            class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Add seasonal rate</button>
                @endif
            </div>

            @if ($canManage && $showSeasonForm)
                <form wire:submit="saveSeason" class="mb-4 grid gap-3 rounded-md bg-gray-50 p-3 sm:grid-cols-4">
                    <div class="sm:col-span-4">
                        <label class="block text-sm font-medium text-gray-700">Name</label>
                        <input type="text" wire:model="seasonName" placeholder="e.g. Christmas"
                               class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                        @error('seasonName') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">First night</label>
                        <input type="date" wire:model="seasonStart" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                        @error('seasonStart') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Last night</label>
                        <input type="date" wire:model="seasonEnd" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                        @error('seasonEnd') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Price per night ({{ $symbol }})</label>
                        <input type="number" step="0.01" min="0" wire:model="seasonPrice" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                        @error('seasonPrice') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">Save</button>
                        <button type="button" wire:click="cancelSeason" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Cancel</button>
                    </div>
                </form>
            @endif

            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="text-left text-xs font-semibold uppercase text-gray-500">
                    <tr>
                        <th class="py-2 pr-4">Name</th>
                        <th class="py-2 pr-4">From</th>
                        <th class="py-2 pr-4">To</th>
                        <th class="py-2 pr-4">Price / night</th>
                        <th class="py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($seasons as $s)
                        <tr wire:key="season-{{ $s->id }}">
                            <td class="py-2 pr-4 font-medium text-gray-900">{{ $s->name }}</td>
                            <td class="py-2 pr-4">{{ $s->start_date->format('d M Y') }}</td>
                            <td class="py-2 pr-4">{{ $s->end_date->format('d M Y') }}</td>
                            <td class="whitespace-nowrap py-2 pr-4">{{ $symbol }}{{ number_format($s->price / 100, 2) }}</td>
                            <td class="space-x-3 whitespace-nowrap py-2 text-right">
                                @if ($canManage)
                                    <button type="button" wire:click="editSeason({{ $s->id }})" class="text-indigo-600 hover:underline">Edit</button>
                                    <button type="button" wire:click="deleteSeason({{ $s->id }})"
                                            wire:confirm="Delete this seasonal rate?"
                                            class="text-red-600 hover:underline">Delete</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-4 text-center text-gray-500">No seasonal rates.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Stay restrictions --}}
        <div class="bg-white p-4 shadow sm:rounded-lg">
            <div class="mb-3 flex items-center justify-between">
                <div>
                    <h3 class="font-semibold text-gray-800">Stay restrictions</h3>
                    <p class="text-xs text-gray-500">Applies to the arrival date. A minimum stay here replaces the default above for arrivals in the range.</p>
                </div>
                @if ($canManage)
                    <button type="button" wire:click="createRule"
                            class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Add restriction</button>
                @endif
            </div>

            @if ($canManage && $showRuleForm)
                <form wire:submit="saveRule" class="mb-4 grid gap-3 rounded-md bg-gray-50 p-3 sm:grid-cols-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">First date</label>
                        <input type="date" wire:model="ruleStart" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                        @error('ruleStart') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Last date</label>
                        <input type="date" wire:model="ruleEnd" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                        @error('ruleEnd') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Minimum stay (nights)</label>
                        <input type="number" min="1" wire:model="ruleMinStay" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                        @error('ruleMinStay') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex items-end">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" wire:model="ruleClosed" class="rounded border-gray-300">
                            Closed to arrival
                        </label>
                    </div>
                    <div class="sm:col-span-4">
                        <label class="block text-sm font-medium text-gray-700">Note (optional)</label>
                        <input type="text" wire:model="ruleNote" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                        @error('ruleNote') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex gap-2 sm:col-span-4">
                        <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">Save</button>
                        <button type="button" wire:click="cancelRule" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Cancel</button>
                    </div>
                </form>
            @endif

            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="text-left text-xs font-semibold uppercase text-gray-500">
                    <tr>
                        <th class="py-2 pr-4">From</th>
                        <th class="py-2 pr-4">To</th>
                        <th class="py-2 pr-4">Rule</th>
                        <th class="py-2 pr-4">Note</th>
                        <th class="py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($rules as $r)
                        <tr wire:key="rule-{{ $r->id }}">
                            <td class="py-2 pr-4">{{ $r->start_date->format('d M Y') }}</td>
                            <td class="py-2 pr-4">{{ $r->end_date->format('d M Y') }}</td>
                            <td class="py-2 pr-4">
                                @if ($r->closed_to_arrival)
                                    <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-800">Closed to arrival</span>
                                @endif
                                @if ($r->min_stay)
                                    <span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-800">Min {{ $r->min_stay }} nights</span>
                                @endif
                            </td>
                            <td class="py-2 pr-4 text-gray-600">{{ $r->note }}</td>
                            <td class="space-x-3 whitespace-nowrap py-2 text-right">
                                @if ($canManage)
                                    <button type="button" wire:click="editRule({{ $r->id }})" class="text-indigo-600 hover:underline">Edit</button>
                                    <button type="button" wire:click="deleteRule({{ $r->id }})"
                                            wire:confirm="Delete this restriction?"
                                            class="text-red-600 hover:underline">Delete</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-4 text-center text-gray-500">No restrictions.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>