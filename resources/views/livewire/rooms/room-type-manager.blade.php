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
                    Add room type
                </button>
            </div>
        @endif

        @if ($canManage && $showForm)
            <div class="bg-white p-4 shadow sm:rounded-lg">
                <h3 class="mb-3 font-semibold text-gray-800">
                    {{ $editingId ? 'Edit room type' : 'New room type' }}
                </h3>

                <form wire:submit="save" class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Name</label>
                        <input type="text" wire:model="name" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                        @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Base price per night ({{ $symbol }})</label>
                        <input type="number" step="0.01" min="0" wire:model="basePrice" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                        @error('basePrice') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Bed type</label>
                        <select wire:model="bedType" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                            <option value="">Select</option>
                            @foreach ($bedTypes as $b)
                                <option value="{{ $b }}">{{ $b }}</option>
                            @endforeach
                        </select>
                        @error('bedType') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Size (square metres, optional)</label>
                        <input type="number" min="1" wire:model="sizeSqm" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                        @error('sizeSqm') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Max adults</label>
                        <input type="number" min="1" wire:model="maxAdults" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                        @error('maxAdults') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Max children</label>
                        <input type="number" min="0" wire:model="maxChildren" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                        @error('maxChildren') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea wire:model="description" rows="3" class="mt-1 w-full rounded-md border-gray-300 shadow-sm"></textarea>
                        @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <p class="block text-sm font-medium text-gray-700">Amenities</p>
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-2">
                            @forelse ($amenities as $a)
                                <label class="flex items-center gap-2 text-sm text-gray-700" wire:key="amenity-{{ $a->id }}">
                                    <input type="checkbox" wire:model="selectedAmenities" value="{{ $a->id }}" class="rounded border-gray-300">
                                    {{ $a->name }}
                                </label>
                            @empty
                                <span class="text-sm text-gray-500">No amenities yet. Add one below.</span>
                            @endforelse
                        </div>

                        <div class="mt-3 flex gap-2">
                            <input type="text" wire:model="newAmenity" wire:keydown.enter.prevent="addAmenity"
                                   placeholder="New amenity, e.g. Wi-Fi"
                                   class="w-64 rounded-md border-gray-300 text-sm shadow-sm">
                            <button type="button" wire:click="addAmenity"
                                    class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                Add amenity
                            </button>
                        </div>
                        @error('newAmenity') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        @error('selectedAmenities.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <p class="block text-sm font-medium text-gray-700">Photos</p>

                        @if ($editingId)
                            <div class="mt-2 flex flex-wrap gap-3">
                                @forelse ($photos as $photo)
                                    <div class="w-32" wire:key="photo-{{ $photo->id }}">
                                        <img src="{{ $photo->url() }}" alt="" class="h-24 w-32 rounded-md object-cover">
                                        <div class="mt-1 flex items-center justify-between text-xs">
                                            @if ($loop->first)
                                                <span class="font-semibold text-green-700">Cover</span>
                                            @else
                                                <button type="button" wire:click="makeCover({{ $photo->id }})" class="text-indigo-600 hover:underline">Make cover</button>
                                            @endif
                                            <button type="button" wire:click="removePhoto({{ $photo->id }})"
                                                    wire:confirm="Remove this photo?"
                                                    class="text-red-600 hover:underline">Remove</button>
                                        </div>
                                    </div>
                                @empty
                                    <span class="text-sm text-gray-500">No photos yet.</span>
                                @endforelse
                            </div>

                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <input type="file" wire:model="newPhotos" multiple accept="image/jpeg,image/png,image/webp"
                                       class="text-sm text-gray-700">
                                <button type="button" wire:click="uploadPhotos" wire:loading.attr="disabled" wire:target="newPhotos,uploadPhotos"
                                        class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    Upload
                                </button>
                                <span wire:loading wire:target="newPhotos,uploadPhotos" class="text-sm text-gray-500">Working...</span>
                            </div>
                            @error('newPhotos') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            @error('newPhotos.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            <p class="mt-1 text-xs text-gray-500">JPG, PNG or WebP, up to 4 MB each, 10 photos per room type.</p>
                        @else
                            <p class="mt-1 text-sm text-gray-500">Save the room type first, then edit it to add photos.</p>
                        @endif
                    </div>

                    <div class="sm:col-span-2">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" wire:model="isActive" class="rounded border-gray-300">
                            Active (can be booked)
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
                        <th class="px-4 py-3">Room type</th>
                        <th class="px-4 py-3">Bed</th>
                        <th class="px-4 py-3">Guests</th>
                        <th class="px-4 py-3">Price / night</th>
                        <th class="px-4 py-3">Amenities</th>
                        <th class="px-4 py-3">Rooms</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($roomTypes as $rt)
                        <tr wire:key="room-type-{{ $rt->id }}">
                            <td class="px-4 py-3 font-medium text-gray-900">
                                <div class="flex items-center gap-3">
                                    @if ($rt->photos->first())
                                        <img src="{{ $rt->photos->first()->url() }}" alt="" class="h-10 w-14 rounded object-cover">
                                    @endif
                                    <span>{{ $rt->name }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">{{ $rt->bed_type }}</td>
                            <td class="px-4 py-3">{{ $rt->max_adults }} adults, {{ $rt->max_children }} children</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $symbol }}{{ number_format($rt->base_price / 100, 2) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($rt->amenities as $a)
                                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700">{{ $a->name }}</span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-4 py-3">{{ $rt->rooms_count }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs {{ $rt->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $rt->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="space-x-3 whitespace-nowrap px-4 py-3 text-right">
                                <a href="{{ route('room-types.pricing', $rt) }}" class="text-indigo-600 hover:underline">Pricing</a>
                                @if ($canManage)
                                    <button type="button" wire:click="edit({{ $rt->id }})" class="text-indigo-600 hover:underline">Edit</button>
                                    <button type="button" wire:click="toggleActive({{ $rt->id }})" class="text-gray-600 hover:underline">
                                        {{ $rt->is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                    <button type="button" wire:click="deleteType({{ $rt->id }})"
                                            wire:confirm="Delete this room type?"
                                            class="text-red-600 hover:underline">Delete</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-6 text-center text-gray-500">No room types yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>