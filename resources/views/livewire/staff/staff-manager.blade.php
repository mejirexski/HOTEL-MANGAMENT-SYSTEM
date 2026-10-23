<div class="py-8">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

        @if (session('status'))
            <div class="rounded-md bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="flex flex-col gap-3 px-4 sm:flex-row sm:items-center sm:justify-between sm:px-0">
            <div class="flex gap-2">
                <input type="search" wire:model.live.debounce.300ms="search"
                       placeholder="Search name or email"
                       class="rounded-md border-gray-300 text-sm shadow-sm">
                <select wire:model.live="roleFilter" class="rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">All roles</option>
                    @foreach ($roles as $r)
                        <option value="{{ $r }}">{{ ucwords(str_replace('-', ' ', $r)) }}</option>
                    @endforeach
                </select>
            </div>
            <button wire:click="create" type="button"
                    class="rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">
                Add staff
            </button>
        </div>

        @if ($showForm)
            <div class="bg-white p-4 shadow sm:rounded-lg">
                <h3 class="mb-3 font-semibold text-gray-800">
                    {{ $editingId ? 'Edit staff member' : 'New staff member' }}
                </h3>

                <form wire:submit="save" class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Name</label>
                        <input type="text" wire:model="name" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                        @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" wire:model="email" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                        @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Phone</label>
                        <input type="text" wire:model="phone" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                        @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Role</label>
                        <select wire:model="role" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                            <option value="">Select a role</option>
                            @foreach ($roles as $r)
                                <option value="{{ $r }}">{{ ucwords(str_replace('-', ' ', $r)) }}</option>
                            @endforeach
                        </select>
                        @error('role') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">
                            {{ $editingId ? 'New password (leave blank to keep the current one)' : 'Temporary password' }}
                        </label>
                        <input type="text" wire:model="password" autocomplete="off"
                               class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                        @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
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
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Email</th>
                        <th class="px-4 py-3">Role</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Last login</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($staff as $member)
                        <tr wire:key="staff-{{ $member->id }}">
                            <td class="px-4 py-3">{{ $member->name }}</td>
                            <td class="px-4 py-3">{{ $member->email }}</td>
                            <td class="px-4 py-3">{{ ucwords(str_replace('-', ' ', $member->getRoleNames()->first() ?? '')) }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs {{ $member->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $member->is_active ? 'Active' : 'Deactivated' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">{{ $member->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                            <td class="space-x-3 px-4 py-3 text-right">
                                @if ($member->id !== $me)
                                    <button type="button" wire:click="edit({{ $member->id }})" class="text-indigo-600 hover:underline">Edit</button>
                                    <button type="button" wire:click="toggleActive({{ $member->id }})"
                                            wire:confirm="{{ $member->is_active ? 'Deactivate this account?' : 'Activate this account?' }}"
                                            class="text-gray-600 hover:underline">
                                        {{ $member->is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">No staff found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-4 sm:px-0">{{ $staff->links() }}</div>
    </div>
</div>