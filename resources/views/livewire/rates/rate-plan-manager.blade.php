<div class="py-8">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

        @if (session('status'))
            <div class="rounded-md bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-md bg-red-50 p-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        @if ($canManage)
            <div class="flex justify-end px-4 sm:px-0">
                <button type="button" wire:click="create"
                        class="rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">
                    Add rate plan
                </button>
            </div>
        @endif

        @if ($canManage && $showForm)
            <div class="bg-white p-4 shadow sm:rounded-lg">
                <h3 class="mb-3 font-semibold text-gray-800">{{ $editingId ? 'Edit rate plan' : 'New rate plan' }}</h3>

                <form wire:submit="save" class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Name</label>
                        <input type="text" wire:model="name" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                        @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Includes (optional)</label>
                        <input type="text" wire:model="inclusions" placeholder="e.g. Breakfast, gym access"
                               class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                        @error('inclusions') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Price adjustment (%)</label>
                        <input type="number" step="0.01" wire:model="adjustmentPercent" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                        <p class="mt-1 text-xs text-gray-500">0 = base price. Use a negative number for a discount, e.g. -10.</p>
                        @error('adjustmentPercent') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Add-on per night ({{ $symbol }})</label>
                        <input type="number" step="0.01" min="0" wire:model="extraPerNight" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                        <p class="mt-1 text-xs text-gray-500">Added after the percentage, e.g. breakfast.</p>
                        @error('extraPerNight') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" wire:model.live="isRefundable" class="rounded border-gray-300">
                            Refundable
                        </label>
                    </div>

                    @if ($isRefundable)
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Free cancellation until (hours before check-in)</label>
                            <input type="number" min="0" wire:model="freeCancellationHours"
                                   placeholder="Blank = hotel default ({{ $defaultHours }} h)"
                                   class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                            @error('freeCancellationHours') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea wire:model="description" rows="2" class="mt-1 w-full rounded-md border-gray-300 shadow-sm"></textarea>
                        @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" wire:model="isActive" class="rounded border-gray-300">
                            Active (can be selected when booking)
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
                        <th class="px-4 py-3">Rate plan</th>
                        <th class="px-4 py-3">Adjustment</th>
                        <th class="px-4 py-3">Add-on / night</th>
                        <th class="px-4 py-3">Cancellation</th>
                        <th class="px-4 py-3">Includes</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($plans as $plan)
                        <tr wire:key="plan-{{ $plan->id }}">
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900">{{ $plan->name }}</p>
                                @if ($plan->description)
                                    <p class="text-xs text-gray-500">{{ $plan->description }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $plan->adjustmentLabel() }}</td>
                            <td class="whitespace-nowrap px-4 py-3">
                                {{ $plan->extra_per_night > 0 ? $symbol . number_format($plan->extra_per_night / 100, 2) : '-' }}
                            </td>
                            <td class="px-4 py-3">
                                @if (! $plan->is_refundable)
                                    <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-800">Non-refundable</span>
                                @else
                                    Free until {{ $plan->free_cancellation_hours ?? $defaultHours }} h before
                                    {{ $plan->free_cancellation_hours === null ? '(hotel default)' : '' }}
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $plan->inclusions ?? '-' }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs {{ $plan->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $plan->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="space-x-3 whitespace-nowrap px-4 py-3 text-right">
                                @if ($canManage)
                                    <button type="button" wire:click="edit({{ $plan->id }})" class="text-indigo-600 hover:underline">Edit</button>
                                    <button type="button" wire:click="toggleActive({{ $plan->id }})" class="text-gray-600 hover:underline">
                                        {{ $plan->is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                    <button type="button" wire:click="deletePlan({{ $plan->id }})"
                                            wire:confirm="Delete this rate plan?"
                                            class="text-red-600 hover:underline">Delete</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">No rate plans yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>