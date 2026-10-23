<div class="py-8">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

        @if (session('status'))
            <div class="rounded-md bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif
        {{--  --}}
        @if (session('error'))
            <div class="rounded-md bg-red-50 p-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        <div class="flex flex-col gap-3 px-4 sm:px-0 lg:flex-row lg:items-center lg:justify-between">
            <div class="grid gap-2 sm:grid-cols-4">
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Room number"
                       class="rounded-md border-gray-300 text-sm shadow-sm">

                <select wire:model.live="statusFilter" class="rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $s)
                        <option value="{{ $s->value }}">{{ $s->label() }}</option>
                    @endforeach
                </select>

                <select wire:model.live="typeFilter" class="rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">All room types</option>
                    @foreach ($roomTypes as $t)
                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                    @endforeach
                </select>

                <select wire:model.live="floorFilter" class="rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">All floors</option>
                    @foreach ($floors as $f)
                        <option value="{{ $f }}">Floor {{ $f }}</option>
                    @endforeach
                </select>
            </div>

            @if ($canManage)
                <button type="button" wire:click="create"
                        class="rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">
                    Add room
                </button>
            @endif
        </div>

        @if ($canManage && $showForm)
            <div class="bg-white p-4 shadow sm:rounded-lg">
                <h3 class="mb-3 font-semibold text-gray-800">{{ $editingId ? 'Edit room' : 'New room' }}</h3>

                <form wire:submit="save" class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Room number</label>
                        <input type="text" wire:model="roomNumber" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                        @error('roomNumber') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Floor</label>
                        <input type="number" wire:model="floor" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                        @error('floor') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Room type</label>
                        <select wire:model="roomTypeId" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                            <option value="">Select a room type</option>
                            @foreach ($roomTypes as $t)
                                <option value="{{ $t->id }}">{{ $t->name }}</option>
                            @endforeach
                        </select>
                        @error('roomTypeId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Status</label>
                        <select wire:model="status" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                            @foreach ($statuses as $s)
                                <option value="{{ $s->value }}">{{ $s->label() }}</option>
                            @endforeach
                        </select>
                        @error('status') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Notes</label>
                        <textarea wire:model="notes" rows="2" class="mt-1 w-full rounded-md border-gray-300 shadow-sm"></textarea>
                        @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
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
                        <th class="px-4 py-3">Room</th>
                        <th class="px-4 py-3">Floor</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Notes</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($rooms as $room)
                        <tr wire:key="room-{{ $room->id }}">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $room->room_number }}</td>
                            <td class="px-4 py-3">{{ $room->floor ?? '-' }}</td>
                            <td class="px-4 py-3">{{ $room->roomType?->name ?? '-' }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs {{ $room->status->badgeClasses() }}">
                                    {{ $room->status->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $room->notes }}</td>
                            <td class="space-x-3 whitespace-nowrap px-4 py-3 text-right">
                                @if ($canManage)
                                    <button type="button" wire:click="edit({{ $room->id }})" class="text-indigo-600 hover:underline">Edit</button>
                                    <button type="button" wire:click="deleteRoom({{ $room->id }})"
                                            wire:confirm="Delete room {{ $room->room_number }}?"
                                            class="text-red-600 hover:underline">Delete</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">No rooms found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-4 sm:px-0">{{ $rooms->links() }}</div>
    </div>
</div>