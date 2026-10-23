@use('App\Enums\BookingStatus')

@php
    $m = fn (int $kobo) => $symbol . number_format($kobo / 100, 2);
@endphp

<div class="py-8">
    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">

        <div class="px-4 sm:px-0">
            <a href="{{ route('guests.index') }}" class="text-sm text-indigo-600 hover:underline">&larr; Back to guests</a>
        </div>

        @if (session('status'))
            <div class="rounded-md bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-md bg-red-50 p-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        {{-- Header --}}
        <div class="bg-white p-4 shadow sm:rounded-lg">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">{{ $guest->full_name }}</h3>
                    <p class="text-sm text-gray-500">Guest since {{ $guest->created_at->format('d M Y') }}</p>
                </div>

                <div class="flex flex-wrap gap-2">
                    @if ($canManage)
                        <a href="{{ route('guests.edit', $guest) }}"
                           class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Edit</a>
                    @endif
                    @if ($canDelete)
                        <button type="button" wire:click="deleteGuest" wire:confirm="Delete this guest?"
                                class="rounded-md border border-red-300 px-3 py-2 text-sm text-red-700 hover:bg-red-50">Delete</button>
                    @endif
                </div>
            </div>

            <div class="mt-3 flex flex-wrap gap-2">
                @foreach (BookingStatus::cases() as $s)
                    @if (($stats[$s->value] ?? 0) > 0)
                        <span class="rounded-full px-2 py-0.5 text-xs {{ $s->badgeClasses() }}">{{ $s->label() }}: {{ $stats[$s->value] }}</span>
                    @endif
                @endforeach
                @if ($stats->isEmpty())
                    <span class="text-sm text-gray-500">No bookings yet.</span>
                @endif
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            {{-- Contact --}}
            <div class="bg-white p-4 shadow sm:rounded-lg">
                <h3 class="mb-3 font-semibold text-gray-800">Contact</h3>

                <dl class="space-y-3 text-sm">
                    <div><dt class="text-xs uppercase text-gray-500">Phone</dt><dd class="text-gray-900">{{ $guest->phone ?: '-' }}</dd></div>
                    <div><dt class="text-xs uppercase text-gray-500">Email</dt><dd class="text-gray-900">{{ $guest->email ?: '-' }}</dd></div>
                    <div><dt class="text-xs uppercase text-gray-500">Address</dt><dd class="text-gray-900">{{ $guest->address ?: '-' }}</dd></div>
                    <div><dt class="text-xs uppercase text-gray-500">Nationality</dt><dd class="text-gray-900">{{ $guest->nationality ?: '-' }}</dd></div>
                    @if ($guest->notes)
                        <div><dt class="text-xs uppercase text-gray-500">Notes</dt><dd class="whitespace-pre-line text-gray-900">{{ $guest->notes }}</dd></div>
                    @endif
                </dl>
            </div>

            {{-- ID details --}}
            @if ($canSeeId)
                <div class="bg-white p-4 shadow sm:rounded-lg">
                    <h3 class="mb-3 font-semibold text-gray-800">ID details</h3>

                    @if (filled($guest->id_number))
                        <dl class="space-y-3 text-sm">
                            <div><dt class="text-xs uppercase text-gray-500">Type</dt><dd class="text-gray-900">{{ $guest->id_type ?: '-' }}</dd></div>
                            <div>
                                <dt class="text-xs uppercase text-gray-500">Number</dt>
                                <dd class="font-mono text-gray-900">{{ $showId ? $guest->id_number : $guest->maskedIdNumber() }}</dd>
                            </div>
                        </dl>

                        <div class="mt-3">
                            @if ($showId)
                                <button type="button" wire:click="hideId" class="text-sm text-indigo-600 hover:underline">Hide</button>
                            @else
                                <button type="button" wire:click="revealId" class="text-sm text-indigo-600 hover:underline">Reveal number</button>
                            @endif
                            <p class="mt-1 text-xs text-gray-500">Every reveal is recorded in the audit log.</p>
                        </div>
                    @else
                        <p class="text-sm text-gray-500">No ID on file.</p>
                    @endif
                </div>
            @endif
        </div>

        {{-- Stay history --}}
        <div class="bg-white p-4 shadow sm:rounded-lg">
            <h3 class="mb-3 font-semibold text-gray-800">Bookings</h3>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="text-left text-xs font-semibold uppercase text-gray-500">
                        <tr>
                            <th class="py-2 pr-4">Reference</th>
                            <th class="py-2 pr-4">Stay</th>
                            <th class="py-2 pr-4">Room</th>
                            <th class="py-2 pr-4">Status</th>
                            <th class="py-2 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($bookings as $b)
                            <tr wire:key="stay-{{ $b->id }}">
                                <td class="whitespace-nowrap py-2 pr-4 font-mono text-xs font-semibold">
                                    <a href="{{ route('bookings.show', $b) }}" class="text-indigo-600 hover:underline">{{ $b->reference }}</a>
                                </td>
                                <td class="whitespace-nowrap py-2 pr-4">
                                    {{ $b->check_in->format('d M Y') }} to {{ $b->check_out->format('d M Y') }}
                                    <span class="text-xs text-gray-500">({{ $b->nights() }} {{ \Illuminate\Support\Str::plural('night', $b->nights()) }})</span>
                                </td>
                                <td class="py-2 pr-4">{{ $b->room?->room_number }} <span class="text-xs text-gray-500">{{ $b->roomType?->name }}</span></td>
                                <td class="py-2 pr-4">
                                    <span class="rounded-full px-2 py-0.5 text-xs {{ $b->status->badgeClasses() }}">{{ $b->status->label() }}</span>
                                </td>
                                <td class="whitespace-nowrap py-2 text-right">{{ $m($b->total) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-4 text-center text-gray-500">No bookings yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Waitlist --}}
        @if ($waitlist->isNotEmpty())
            <div class="bg-white p-4 shadow sm:rounded-lg">
                <h3 class="mb-3 font-semibold text-gray-800">Waitlist</h3>

                <ul class="divide-y divide-gray-100 text-sm">
                    @foreach ($waitlist as $w)
                        <li class="flex flex-wrap items-center justify-between gap-2 py-2" wire:key="wl-{{ $w->id }}">
                            <span>
                                {{ $w->roomType?->name }}, {{ $w->check_in->format('d M Y') }} to {{ $w->check_out->format('d M Y') }}
                            </span>
                            <span class="rounded-full px-2 py-0.5 text-xs {{ $w->status->badgeClasses() }}">{{ $w->status->label() }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</div>