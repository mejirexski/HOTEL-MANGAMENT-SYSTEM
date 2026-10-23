@use('App\Enums\WaitlistStatus')

<div class="py-8">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

        @if (session('status'))
            <div class="rounded-md bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-md bg-red-50 p-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        <div class="flex flex-col gap-3 px-4 sm:px-0 lg:flex-row lg:items-center lg:justify-between">
            <div class="grid gap-2 sm:grid-cols-3">
                <select wire:model.live="statusFilter" class="rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="active">In the queue</option>
                    @foreach ($statuses as $s)
                        <option value="{{ $s->value }}">{{ $s->label() }}</option>
                    @endforeach
                    <option value="all">All entries</option>
                </select>

                <select wire:model.live="typeFilter" class="rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">All room types</option>
                    @foreach ($allRoomTypes as $t)
                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                    @endforeach
                </select>

                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Guest name, phone or email"
                       class="rounded-md border-gray-300 text-sm shadow-sm">
            </div>

            <div class="flex gap-2">
                <button type="button" wire:click="processNow" wire:loading.attr="disabled" wire:target="processNow"
                        class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                    Check for free rooms now
                </button>
                <button type="button" wire:click="openForm"
                        class="rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">
                    Add to waitlist
                </button>
            </div>
        </div>

        @if ($showForm)
            <div class="bg-white p-4 shadow sm:rounded-lg">
                <h3 class="mb-1 font-semibold text-gray-800">Add a guest to the waitlist</h3>
                <p class="mb-3 text-xs text-gray-500">Only possible when no room of that type is free for the dates. If one is free, make a booking instead.</p>

                <div class="grid gap-4 sm:grid-cols-5">
                    <div class="sm:col-span-2">
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
                        <label class="block text-sm font-medium text-gray-700">Check-in</label>
                        <input type="date" wire:model="checkIn" min="{{ now()->toDateString() }}" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                        @error('checkIn') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Check-out</label>
                        <input type="date" wire:model="checkOut" min="{{ now()->toDateString() }}" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                        @error('checkOut') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-2">
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
                    </div>
                </div>

                <div class="mt-4 flex gap-6 text-sm text-gray-700">
                    <label class="flex items-center gap-2">
                        <input type="radio" wire:model.live="guestMode" value="new"> New guest
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="radio" wire:model.live="guestMode" value="existing"> Existing guest
                    </label>
                </div>

                @if ($guestMode === 'new')
                    <div class="mt-3 grid gap-4 sm:grid-cols-3">
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
                            <label class="block text-sm font-medium text-gray-700">Email (optional, used to send the offer)</label>
                            <input type="email" wire:model="guestEmail" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                            @error('guestEmail') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                @else
                    <div class="mt-3">
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
                                        <li class="flex items-center justify-between p-2" wire:key="wl-guest-{{ $g->id }}">
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
                    </div>
                @endif

                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700">Notes (optional)</label>
                    <input type="text" wire:model="notes" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                    @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                @if ($formError !== '')
                    <div class="mt-4 rounded-md bg-red-50 p-3 text-sm text-red-800">{{ $formError }}</div>
                @endif

                <div class="mt-4 flex gap-2">
                    <button type="button" wire:click="join" wire:loading.attr="disabled" wire:target="join"
                            class="rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">Add to waitlist</button>
                    <button type="button" wire:click="closeForm"
                            class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
                </div>
            </div>
        @endif

        <div class="overflow-x-auto bg-white shadow sm:rounded-lg">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Guest</th>
                        <th class="px-4 py-3">Room type</th>
                        <th class="px-4 py-3">Stay</th>
                        <th class="px-4 py-3">Joined</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($entries as $e)
                        <tr wire:key="entry-{{ $e->id }}">
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900">{{ $e->guest?->full_name }}</p>
                                <p class="text-xs text-gray-500">{{ $e->guest?->phone }}</p>
                                <p class="text-xs text-gray-500">{{ $e->guest?->email }}</p>
                            </td>
                            <td class="px-4 py-3">{{ $e->roomType?->name }}</td>
                            <td class="whitespace-nowrap px-4 py-3">
                                {{ $e->check_in->format('d M Y') }} to {{ $e->check_out->format('d M Y') }}
                                <p class="text-xs text-gray-500">
                                    {{ (int) round($e->check_in->diffInDays($e->check_out)) }} nights, {{ $e->adults }} adults, {{ $e->children }} children
                                </p>
                                @if ($e->notes)
                                    <p class="text-xs text-gray-500">{{ $e->notes }}</p>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $e->created_at->format('d M Y H:i') }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs {{ $e->status->badgeClasses() }}">{{ $e->status->label() }}</span>

                                @if ($e->status === WaitlistStatus::Notified)
                                    <p class="mt-1 text-xs text-gray-600">
                                        Held until {{ $e->offer_expires_at?->format('d M Y H:i') }}
                                    </p>
                                    <p class="text-xs {{ $e->guest?->email ? 'text-gray-500' : 'font-semibold text-red-600' }}">
                                        {{ $e->guest?->email ? 'Email sent' : 'No email: please phone the guest' }}
                                    </p>
                                @endif
                            </td>
                            <td class="space-x-3 whitespace-nowrap px-4 py-3 text-right">
                                @if (in_array($e->status, [WaitlistStatus::Waiting, WaitlistStatus::Notified], true))
                                    @if ($e->status === WaitlistStatus::Notified)
                                        <a href="{{ route('bookings.create', ['waitlist' => $e->id]) }}"
                                           class="text-indigo-600 hover:underline">Book this guest</a>
                                    @endif
                                    <button type="button" wire:click="markBooked({{ $e->id }})"
                                            wire:confirm="Mark this entry as booked?"
                                            class="text-indigo-600 hover:underline">Mark booked</button>
                                    <button type="button" wire:click="cancelEntry({{ $e->id }})"
                                            wire:confirm="Cancel this waitlist entry?"
                                            class="text-red-600 hover:underline">Cancel</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">No waitlist entries found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-4 sm:px-0">{{ $entries->links() }}</div>
    </div>
</div>