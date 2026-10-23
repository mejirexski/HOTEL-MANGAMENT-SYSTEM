@php
    $m = fn (int $kobo) => $symbol . number_format($kobo / 100, 2);
@endphp

<div class="py-8">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

        @if (session('status'))
            <div class="rounded-md bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="flex flex-col gap-3 px-4 sm:px-0 lg:flex-row lg:items-end lg:justify-between">
            <div class="grid gap-2 sm:grid-cols-4">
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Reference, guest, phone"
                       class="rounded-md border-gray-300 text-sm shadow-sm">

                <select wire:model.live="statusFilter" class="rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $s)
                        <option value="{{ $s->value }}">{{ $s->label() }}</option>
                    @endforeach
                </select>

                <div>
                    <input type="date" wire:model.live="from" class="w-full rounded-md border-gray-300 text-sm shadow-sm">
                    <p class="text-xs text-gray-500">Arriving from</p>
                </div>

                <div>
                    <input type="date" wire:model.live="to" class="w-full rounded-md border-gray-300 text-sm shadow-sm">
                    <p class="text-xs text-gray-500">Arriving until</p>
                </div>
            </div>

            @if ($canCreate)
                <a href="{{ route('bookings.create') }}"
                   class="rounded-md bg-gray-800 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-gray-700">
                    New booking
                </a>
            @endif
        </div>

        <div class="overflow-x-auto bg-white shadow sm:rounded-lg">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Reference</th>
                        <th class="px-4 py-3">Guest</th>
                        <th class="px-4 py-3">Stay</th>
                        <th class="px-4 py-3">Room</th>
                        <th class="px-4 py-3">Rate plan</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($bookings as $b)
                        <tr wire:key="booking-{{ $b->id }}">
                            <td class="whitespace-nowrap px-4 py-3 font-mono text-xs font-semibold">
                                <a href="{{ route('bookings.show', $b) }}" class="text-indigo-600 hover:underline">{{ $b->reference }}</a>
                            </td>
                            <td class="px-4 py-3">
                                @if ($b->guest && ! $b->guest->trashed())
                                    <a href="{{ route('guests.show', $b->guest) }}" class="font-medium text-indigo-600 hover:underline">{{ $b->guest->full_name }}</a>
                                @else
                                    <p class="font-medium text-gray-900">{{ $b->guest?->full_name }}</p>
                                @endif
                                <p class="text-xs text-gray-500">{{ $b->guest?->phone }}</p>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                {{ $b->check_in->format('d M Y') }} to {{ $b->check_out->format('d M Y') }}
                                <p class="text-xs text-gray-500">{{ $b->nights() }} {{ \Illuminate\Support\Str::plural('night', $b->nights()) }}, {{ $b->adults }} adults, {{ $b->children }} children</p>
                            </td>
                            <td class="px-4 py-3">
                                {{ $b->room?->room_number }}
                                <p class="text-xs text-gray-500">{{ $b->roomType?->name }}</p>
                            </td>
                            <td class="px-4 py-3">{{ $b->ratePlan?->name }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs {{ $b->status->badgeClasses() }}">{{ $b->status->label() }}</span>
                                @if ($b->status === \App\Enums\BookingStatus::Pending && $b->expires_at?->isPast())
                                    <p class="text-xs text-red-600">Expired</p>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">{{ $m($b->total) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">No bookings found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-4 sm:px-0">{{ $bookings->links() }}</div>
    </div>
</div>