<div class="py-8">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

        @if (session('status'))
            <div class="rounded-md bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-md bg-red-50 p-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        <div class="flex flex-col gap-3 px-4 sm:flex-row sm:items-center sm:justify-between sm:px-0">
            <input type="search" wire:model.live.debounce.300ms="search"
                   placeholder="Name, phone, email or booking reference"
                   class="w-full rounded-md border-gray-300 text-sm shadow-sm sm:w-96">

            @if ($canManage)
                <a href="{{ route('guests.create') }}"
                   class="rounded-md bg-gray-800 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-gray-700">
                    New guest
                </a>
            @endif
        </div>

        <div class="overflow-x-auto bg-white shadow sm:rounded-lg">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Guest</th>
                        <th class="px-4 py-3">Phone</th>
                        <th class="px-4 py-3">Email</th>
                        <th class="px-4 py-3">Nationality</th>
                        <th class="px-4 py-3">Bookings</th>
                        <th class="px-4 py-3">Last arrival</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($guests as $g)
                        <tr wire:key="guest-{{ $g->id }}">
                            <td class="px-4 py-3">
                                <a href="{{ route('guests.show', $g) }}" class="font-medium text-indigo-600 hover:underline">{{ $g->full_name }}</a>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $g->phone }}</td>
                            <td class="px-4 py-3">{{ $g->email }}</td>
                            <td class="px-4 py-3">{{ $g->nationality }}</td>
                            <td class="px-4 py-3">{{ $g->bookings_count }}</td>
                            <td class="whitespace-nowrap px-4 py-3">
                                {{ $g->last_check_in ? \Carbon\Carbon::parse($g->last_check_in)->format('d M Y') : '-' }}
                            </td>
                            <td class="space-x-3 whitespace-nowrap px-4 py-3 text-right">
                                <a href="{{ route('guests.show', $g) }}" class="text-indigo-600 hover:underline">View</a>
                                @if ($canManage)
                                    <a href="{{ route('guests.edit', $g) }}" class="text-indigo-600 hover:underline">Edit</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">No guests found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-4 sm:px-0">{{ $guests->links() }}</div>
    </div>
</div>