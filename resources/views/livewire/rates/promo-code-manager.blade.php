<div class="py-8">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

        @if (session('status'))
            <div class="rounded-md bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="flex justify-end px-4 sm:px-0">
            <button type="button" wire:click="create"
                    class="rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">
                Add promo code
            </button>
        </div>

        @if ($showForm)
            <div class="bg-white p-4 shadow sm:rounded-lg">
                <h3 class="mb-3 font-semibold text-gray-800">{{ $editingId ? 'Edit promo code' : 'New promo code' }}</h3>

                <form wire:submit="save" class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Code</label>
                        <input type="text" wire:model="code" placeholder="e.g. WELCOME10"
                               class="mt-1 w-full rounded-md border-gray-300 uppercase shadow-sm">
                        <p class="mt-1 text-xs text-gray-500">Letters, numbers, dash and underscore. Saved in capitals.</p>
                        @error('code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Description (optional)</label>
                        <input type="text" wire:model="description" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                        @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Discount type</label>
                        <select wire:model.live="type" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                            <option value="percent">Percentage off</option>
                            <option value="fixed">Fixed amount off</option>
                        </select>
                        @error('type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            {{ $type === 'percent' ? 'Percentage (%)' : 'Amount (' . $symbol . ')' }}
                        </label>
                        <input type="number" step="0.01" min="0" wire:model="value" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                        @error('value') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Valid for bookings made from (optional)</label>
                        <input type="date" wire:model="validFrom" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                        @error('validFrom') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Valid until (optional)</label>
                        <input type="date" wire:model="validTo" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                        @error('validTo') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Usage limit (optional)</label>
                        <input type="number" min="1" wire:model="usageLimit" placeholder="Blank = unlimited"
                               class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                        @error('usageLimit') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <p class="block text-sm font-medium text-gray-700">Eligible rate plans</p>
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-2">
                            @foreach ($plans as $p)
                                <label class="flex items-center gap-2 text-sm text-gray-700" wire:key="plan-{{ $p->id }}">
                                    <input type="checkbox" wire:model="selectedPlans" value="{{ $p->id }}" class="rounded border-gray-300">
                                    {{ $p->name }}
                                </label>
                            @endforeach
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Leave all unticked to allow every rate plan.</p>
                        @error('selectedPlans.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" wire:model="isActive" class="rounded border-gray-300">
                            Active
                        </label>
                    </div>

                    <div class="flex gap-2 sm:col-span-2">
                        <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">Save</button>
                        <button type="button" wire:click="cancel" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
                    </div>
                </form>
            </div>
        @endif

        <div class="overflow-x-auto bg-white shadow sm:rounded-lg">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Code</th>
                        <th class="px-4 py-3">Discount</th>
                        <th class="px-4 py-3">Valid</th>
                        <th class="px-4 py-3">Used</th>
                        <th class="px-4 py-3">Rate plans</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($promos as $promo)
                        <tr wire:key="promo-{{ $promo->id }}">
                            <td class="px-4 py-3">
                                <p class="font-mono font-semibold text-gray-900">{{ $promo->code }}</p>
                                @if ($promo->description)
                                    <p class="text-xs text-gray-500">{{ $promo->description }}</p>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $promo->discountLabel() }}</td>
                            <td class="whitespace-nowrap px-4 py-3">
                                @if ($promo->valid_from || $promo->valid_to)
                                    {{ $promo->valid_from?->format('d M Y') ?? 'Any time' }} to {{ $promo->valid_to?->format('d M Y') ?? 'no end' }}
                                @else
                                    Always
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $promo->used_count }} / {{ $promo->usage_limit ?? 'unlimited' }}</td>
                            <td class="px-4 py-3">
                                @forelse ($promo->ratePlans as $rp)
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700">{{ $rp->name }}</span>
                                @empty
                                    All
                                @endforelse
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs {{ $promo->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $promo->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="space-x-3 whitespace-nowrap px-4 py-3 text-right">
                                <button type="button" wire:click="edit({{ $promo->id }})" class="text-indigo-600 hover:underline">Edit</button>
                                <button type="button" wire:click="toggleActive({{ $promo->id }})" class="text-gray-600 hover:underline">
                                    {{ $promo->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                                <button type="button" wire:click="deleteCode({{ $promo->id }})"
                                        wire:confirm="Delete this promo code?"
                                        class="text-red-600 hover:underline">Delete</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">No promo codes yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>