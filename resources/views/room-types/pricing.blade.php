<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Pricing rules: {{ $roomType->name }}</h2>
    </x-slot>

    @livewire('rates.room-type-pricing', ['roomType' => $roomType])
</x-app-layout>