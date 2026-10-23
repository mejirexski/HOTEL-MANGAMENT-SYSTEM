@use('App\Enums\BookingStatus')

@php
    $m = fn (int $kobo) => $symbol . number_format($kobo / 100, 2);
    $pb = $booking->price_breakdown ?? [];
    $sourceLabels = ['base' => 'Base', 'weekend' => 'Weekend', 'seasonal' => 'Seasonal'];
    $moneyKeys = ['subtotal', 'discount', 'service_charge', 'tax', 'total', 'deposit_required'];

    $diff = function (?array $before, ?array $after) use ($m, $moneyKeys): array {
        $format = function ($key, $value) use ($m, $moneyKeys) {
            if ($value === null) {
                return '-';
            }
            if (is_array($value)) {
                return json_encode($value);
            }
            if (is_bool($value)) {
                return $value ? 'yes' : 'no';
            }
            if (in_array($key, $moneyKeys, true) && is_int($value)) {
                return $m($value);
            }

            return (string) $value;
        };

        $rows = [];

        foreach (($after ?? []) as $key => $value) {
            $old = $before[$key] ?? null;

            if ($old === $value) {
                continue;
            }

            $rows[] = $key . ': ' . $format($key, $old) . ' → ' . $format($key, $value);
        }

        return $rows;
    };
@endphp

<div class="py-8">
    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">

        <div class="px-4 sm:px-0">
            <a href="{{ route('bookings.index') }}" class="text-sm text-indigo-600 hover:underline">&larr; Back to bookings</a>
        </div>

        @if (session('status'))
            <div class="rounded-md bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif
        @if ($actionError !== '')
            <div class="rounded-md bg-red-50 p-3 text-sm text-red-800">{{ $actionError }}</div>
        @endif

        {{-- Header and actions --}}
        <div class="bg-white p-4 shadow sm:rounded-lg">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="font-mono text-lg font-semibold text-gray-900">{{ $booking->reference }}</p>
                    <div class="mt-1 flex items-center gap-2">
                        <span class="rounded-full px-2 py-0.5 text-xs {{ $booking->status->badgeClasses() }}">{{ $booking->status->label() }}</span>
                        @if ($booking->status === BookingStatus::Pending && $booking->expires_at)
                            <span class="text-xs {{ $booking->expires_at->isPast() ? 'text-red-600' : 'text-gray-500' }}">
                                {{ $booking->expires_at->isPast() ? 'Hold expired' : 'Held until' }} {{ $booking->expires_at->format('d M Y H:i') }}
                            </span>
                        @endif
                    </div>
                </div>

                @if ($canManage)
                    <div class="flex flex-wrap gap-2">
                        @if ($booking->status === BookingStatus::Pending)
                            <button type="button" wire:click="confirmBooking" wire:confirm="Confirm this booking?"
                                    class="rounded-md bg-gray-800 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-700">Confirm</button>
                        @endif

                        @if ($changeable)
                            <button type="button" wire:click="openChange"
                                    class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Change dates or room</button>
                        @endif

                        @if ($booking->status === BookingStatus::Confirmed && $arrivalReached)
                            <button type="button" wire:click="markNoShow" wire:confirm="Mark this booking as a no-show?"
                                    class="rounded-md border border-orange-300 px-3 py-2 text-sm text-orange-700 hover:bg-orange-50">Mark no-show</button>
                        @endif

                        @if ($changeable)
                            <button type="button" wire:click="openCancel"
                                    class="rounded-md border border-red-300 px-3 py-2 text-sm text-red-700 hover:bg-red-50">Cancel booking</button>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Cancel panel --}}
            @if ($canManage && $showCancel)
                <div class="mt-4 rounded-md bg-red-50 p-3">
                    <p class="text-sm font-medium text-red-900">Cancel this booking</p>

                    <p class="mt-1 text-xs text-red-800">
                        @if (! $terms['refundable'])
                            This rate plan is non-refundable. Any fee or refund is handled when payments are added.
                        @elseif ($terms['within_free_window'])
                            Free cancellation applies until {{ $terms['free_until']->format('d M Y H:i') }}.
                        @else
                            Free cancellation ended on {{ $terms['free_until']->format('d M Y H:i') }}. A cancellation fee may apply.
                        @endif
                    </p>

                    <label class="mt-3 block text-sm font-medium text-gray-700">Reason</label>
                    <input type="text" wire:model="cancelReason" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                    @error('cancelReason') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror

                    <div class="mt-3 flex gap-2">
                        <button type="button" wire:click="submitCancel"
                                class="rounded-md bg-red-700 px-3 py-2 text-sm font-semibold text-white hover:bg-red-600">Confirm cancellation</button>
                        <button type="button" wire:click="closeCancel"
                                class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Keep booking</button>
                    </div>
                </div>
            @endif

            {{-- Change panel --}}
            @if ($canManage && $showChange)
                <div class="mt-4 rounded-md bg-gray-50 p-3">
                    <p class="mb-3 text-sm font-medium text-gray-900">Change dates, guests or room</p>

                    <div class="grid gap-3 sm:grid-cols-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Check-in</label>
                            <input type="date" wire:model="newCheckIn" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                            @error('newCheckIn') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Check-out</label>
                            <input type="date" wire:model="newCheckOut" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                            @error('newCheckOut') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Adults</label>
                            <input type="number" min="1" wire:model="newAdults" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                            @error('newAdults') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Children</label>
                            <input type="number" min="0" wire:model="newChildren" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                            @error('newChildren') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="mt-3 flex gap-2">
                        <button type="button" wire:click="previewChange"
                                class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Check price and rooms</button>
                        <button type="button" wire:click="closeChange"
                                class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Close</button>
                    </div>

                    @if ($preview)
                        @php
                            $newTotal = $preview['quote']['total'];
                            $difference = $newTotal - $booking->total;
                        @endphp

                        <div class="mt-4 space-y-3 border-t border-gray-200 pt-3 text-sm">
                            <div class="flex justify-between"><span>Current total</span><span>{{ $m($booking->total) }}</span></div>
                            <div class="flex justify-between font-semibold text-gray-900"><span>New total</span><span>{{ $m($newTotal) }}</span></div>
                            <div class="flex justify-between {{ $difference > 0 ? 'text-red-700' : 'text-green-700' }}">
                                <span>Difference</span>
                                <span>{{ $difference > 0 ? '+' : '-' }}{{ $m(abs($difference)) }}</span>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Room</label>
                                <select wire:model="newRoomId" class="mt-1 w-full rounded-md border-gray-300 shadow-sm sm:w-96">
                                    <option value="">Keep the current room if it is free, otherwise the lowest free room</option>
                                    @foreach ($preview['rooms'] as $r)
                                        <option value="{{ $r['id'] }}">Room {{ $r['room_number'] }}</option>
                                    @endforeach
                                </select>
                                @if ($preview['rooms'] === [])
                                    <p class="mt-1 text-sm text-red-600">No room of this type is free for those dates.</p>
                                @endif
                            </div>

                            <button type="button" wire:click="saveChange"
                                    class="rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">Save changes</button>
                        </div>
                    @endif
                </div>
            @endif
        </div>

        {{-- Details --}}
        <div class="bg-white p-4 shadow sm:rounded-lg">
            <h3 class="mb-3 font-semibold text-gray-800">Details</h3>

            <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-xs uppercase text-gray-500">Guest</dt>
                    <dd class="text-gray-900">{{ $booking->guest?->full_name }}</dd>
                    <dd class="text-gray-600">{{ $booking->guest?->phone }} {{ $booking->guest?->email }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase text-gray-500">Stay</dt>
                    <dd class="text-gray-900">{{ $booking->check_in->format('D d M Y') }} to {{ $booking->check_out->format('D d M Y') }}</dd>
                    <dd class="text-gray-600">{{ $booking->nights() }} {{ \Illuminate\Support\Str::plural('night', $booking->nights()) }}, {{ $booking->adults }} adults, {{ $booking->children }} children</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase text-gray-500">Room</dt>
                    <dd class="text-gray-900">Room {{ $booking->room?->room_number }}</dd>
                    <dd class="text-gray-600">{{ $booking->roomType?->name }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase text-gray-500">Rate plan</dt>
                    <dd class="text-gray-900">{{ $booking->ratePlan?->name }}</dd>
                    <dd class="text-gray-600">
                        @if (! $terms['refundable'])
                            Non-refundable
                        @else
                            Free cancellation until {{ $terms['free_until']->format('d M Y H:i') }}
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs uppercase text-gray-500">Promo code</dt>
                    <dd class="text-gray-900">{{ $booking->promoCode?->code ?? 'None' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase text-gray-500">Booking type</dt>
                    <dd class="text-gray-900">{{ $booking->source === 'walk_in' ? 'Walk-in' : ucfirst($booking->source) }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase text-gray-500">Created</dt>
                    <dd class="text-gray-900">{{ $booking->created_at->format('d M Y H:i') }}</dd>
                    <dd class="text-gray-600">{{ $booking->creator ? 'by ' . $booking->creator->name : '' }}</dd>
                </div>
                @if ($booking->notes)
                    <div>
                        <dt class="text-xs uppercase text-gray-500">Notes</dt>
                        <dd class="text-gray-900">{{ $booking->notes }}</dd>
                    </div>
                @endif
                @if ($booking->status === BookingStatus::Cancelled)
                    <div class="sm:col-span-2">
                        <dt class="text-xs uppercase text-gray-500">Cancelled</dt>
                        <dd class="text-gray-900">
                            {{ $booking->cancelled_at?->format('d M Y H:i') }}: {{ $booking->cancellation_reason }}
                        </dd>
                    </div>
                @endif
            </dl>
        </div>

        {{-- Price --}}
        <div class="bg-white p-4 shadow sm:rounded-lg">
            <h3 class="mb-3 font-semibold text-gray-800">Price</h3>

            @if (! empty($pb['nights']))
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="text-left text-xs font-semibold uppercase text-gray-500">
                        <tr>
                            <th class="py-2 pr-4">Night</th>
                            <th class="py-2 pr-4">Price type</th>
                            <th class="py-2 text-right">Rate after plan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($pb['nights'] as $n)
                            <tr>
                                <td class="py-2 pr-4">{{ \Carbon\Carbon::parse($n['date'])->format('D d M Y') }}</td>
                                <td class="py-2 pr-4">{{ $sourceLabels[$n['source']] ?? $n['source'] }}</td>
                                <td class="py-2 text-right">{{ $m($n['rate']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <dl class="mt-4 space-y-2 border-t border-gray-200 pt-3 text-sm">
                <div class="flex justify-between"><dt>Room and extra guests</dt><dd>{{ $m($booking->subtotal) }}</dd></div>

                @if ($booking->discount > 0)
                    <div class="flex justify-between text-green-700">
                        <dt>Promo {{ $booking->promoCode?->code }}</dt>
                        <dd>-{{ $m($booking->discount) }}</dd>
                    </div>
                @endif

                <div class="flex justify-between">
                    <dt>Service charge {{ ($pb['serviceChargeIncluded'] ?? false) ? '(included)' : '' }}</dt>
                    <dd>{{ $m($booking->service_charge) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt>Tax {{ ($pb['taxIncluded'] ?? false) ? '(included)' : '' }}</dt>
                    <dd>{{ $m($booking->tax) }}</dd>
                </div>
                <div class="flex justify-between border-t border-gray-200 pt-2 text-base font-semibold text-gray-900">
                    <dt>Total</dt><dd>{{ $m($booking->total) }}</dd>
                </div>
                <div class="flex justify-between text-gray-600">
                    <dt>Deposit required</dt><dd>{{ $m($booking->deposit_required) }}</dd>
                </div>
            </dl>
        </div>

        {{-- History --}}
        <div class="bg-white p-4 shadow sm:rounded-lg">
            <h3 class="mb-3 font-semibold text-gray-800">History</h3>

            <ul class="divide-y divide-gray-100 text-sm">
                @forelse ($history as $entry)
                    <li class="py-2" wire:key="history-{{ $entry->id }}">
                        <div class="flex flex-wrap justify-between gap-2">
                            <span class="font-mono text-xs font-semibold text-gray-900">{{ $entry->action }}</span>
                            <span class="text-xs text-gray-500">
                                {{ $entry->created_at->format('d M Y H:i') }} by {{ $entry->user?->name ?? 'System' }}
                            </span>
                        </div>
                        @foreach ($diff($entry->before, $entry->after) as $line)
                            <p class="text-xs text-gray-600">{{ $line }}</p>
                        @endforeach
                    </li>
                @empty
                    <li class="py-2 text-gray-500">No history yet.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>