<div class="py-8">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">

        <div class="px-4 sm:px-0">
            <a href="{{ $guestId ? route('guests.show', $guestId) : route('guests.index') }}"
               class="text-sm text-indigo-600 hover:underline">&larr; Back</a>
        </div>

        <div class="bg-white p-4 shadow sm:rounded-lg">
            <h3 class="mb-3 font-semibold text-gray-800">{{ $guestId ? 'Edit guest' : 'New guest' }}</h3>

            <form wire:submit="save" class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Full name</label>
                    <input type="text" wire:model="fullName" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                    @error('fullName') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Phone</label>
                    <input type="text" wire:model="phone" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                    @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Email (optional)</label>
                    <input type="email" wire:model="email" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                    @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Nationality (optional)</label>
                    <input type="text" wire:model="nationality" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                    @error('nationality') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Address (optional)</label>
                    <input type="text" wire:model="address" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                    @error('address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                @if ($canSeeId)
                    <div class="rounded-md bg-gray-50 p-3 sm:col-span-2">
                        <p class="text-sm font-medium text-gray-800">ID details</p>
                        <p class="mb-3 text-xs text-gray-500">Only collect what you need. Staff without permission never see these fields.</p>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">ID type</label>
                                <select wire:model="idType" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                    <option value="">None</option>
                                    @foreach ($idTypes as $t)
                                        <option value="{{ $t }}">{{ $t }}</option>
                                    @endforeach
                                </select>
                                @error('idType') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">ID number</label>
                                <input type="text" wire:model="idNumber" autocomplete="off"
                                       placeholder="{{ $maskedId ? 'Leave blank to keep the current number' : '' }}"
                                       class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                @if ($maskedId)
                                    <p class="mt-1 text-xs text-gray-500">On file: {{ $maskedId }}</p>
                                @endif
                                @error('idNumber') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        @if ($maskedId)
                            <label class="mt-3 flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" wire:model="clearId" class="rounded border-gray-300">
                                Remove the ID details from this guest
                            </label>
                        @endif
                    </div>
                @endif

                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Notes (optional)</label>
                    <textarea wire:model="notes" rows="3" class="mt-1 w-full rounded-md border-gray-300 shadow-sm"></textarea>
                    @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                @if ($duplicates !== [])
                    <div class="rounded-md bg-yellow-50 p-3 text-sm text-yellow-900 sm:col-span-2">
                        <p class="font-medium">A guest with the same phone number or email already exists:</p>

                        <ul class="mt-2 list-disc pl-5">
                            @foreach ($duplicates as $d)
                                <li wire:key="dup-{{ $d['id'] }}">
                                    <a href="{{ route('guests.show', $d['id']) }}" target="_blank" class="text-indigo-700 underline">{{ $d['full_name'] }}</a>
                                    {{ $d['phone'] }} {{ $d['email'] }}
                                </li>
                            @endforeach
                        </ul>

                        <p class="mt-2">If this is the same person, open their profile instead. If it is someone else, save anyway.</p>

                        <button type="button" wire:click="saveAnyway"
                                class="mt-2 rounded-md bg-yellow-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-yellow-500">Save anyway</button>
                    </div>
                @endif

                <div class="flex gap-2 sm:col-span-2">
                    <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">Save guest</button>
                    <a href="{{ $guestId ? route('guests.show', $guestId) : route('guests.index') }}"
                       class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>