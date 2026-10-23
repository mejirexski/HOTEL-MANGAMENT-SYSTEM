<div class="py-8">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

        <div class="grid gap-3 px-4 sm:grid-cols-4 sm:px-0">
            <select wire:model.live="action" class="rounded-md border-gray-300 text-sm shadow-sm">
                <option value="">All actions</option>
                @foreach ($actions as $a)
                    <option value="{{ $a }}">{{ $a }}</option>
                @endforeach
            </select>
            <input type="search" wire:model.live.debounce.300ms="userSearch"
                   placeholder="User name or email"
                   class="rounded-md border-gray-300 text-sm shadow-sm">
            <input type="date" wire:model.live="from" class="rounded-md border-gray-300 text-sm shadow-sm">
            <input type="date" wire:model.live="to" class="rounded-md border-gray-300 text-sm shadow-sm">
        </div>

        <div class="overflow-x-auto bg-white shadow sm:rounded-lg">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Time</th>
                        <th class="px-4 py-3">User</th>
                        <th class="px-4 py-3">Action</th>
                        <th class="px-4 py-3">Subject</th>
                        <th class="px-4 py-3">IP</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>

                @forelse ($logs as $log)
                    <tbody wire:key="log-{{ $log->id }}" class="divide-y divide-gray-100 border-t border-gray-100">
                        <tr>
                            <td class="whitespace-nowrap px-4 py-3">{{ $log->created_at->format('d M Y H:i:s') }}</td>
                            <td class="px-4 py-3">{{ $log->user?->name ?? 'System' }}</td>
                            <td class="px-4 py-3 font-mono text-xs">{{ $log->action }}</td>
                            <td class="px-4 py-3">
                                {{ $log->subject_type ? class_basename($log->subject_type) . ' #' . $log->subject_id : '-' }}
                            </td>
                            <td class="px-4 py-3">{{ $log->ip_address }}</td>
                            <td class="px-4 py-3 text-right">
                                @if ($log->before || $log->after)
                                    <button type="button" wire:click="toggle({{ $log->id }})"
                                            class="text-indigo-600 hover:underline">
                                        {{ $openId === $log->id ? 'Hide' : 'Details' }}
                                    </button>
                                @endif
                            </td>
                        </tr>

                        @if ($openId === $log->id)
                            <tr class="bg-gray-50">
                                <td colspan="6" class="px-4 py-3">
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <p class="mb-1 text-xs font-semibold uppercase text-gray-500">Before</p>
                                            <pre class="overflow-x-auto rounded bg-white p-2 text-xs">{{ json_encode($log->before, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </div>
                                        <div>
                                            <p class="mb-1 text-xs font-semibold uppercase text-gray-500">After</p>
                                            <pre class="overflow-x-auto rounded bg-white p-2 text-xs">{{ json_encode($log->after, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                @empty
                    <tbody>
                        <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">No log entries found.</td></tr>
                    </tbody>
                @endforelse
            </table>
        </div>

        <div class="px-4 sm:px-0">{{ $logs->links() }}</div>
    </div>
</div>