<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Booking {{ $booking->reference }}</h2>
    </x-slot>

    @livewire('bookings.booking-detail', ['booking' => $booking])
</x-app-layout>