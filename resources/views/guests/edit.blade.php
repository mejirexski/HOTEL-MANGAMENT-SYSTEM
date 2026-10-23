<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit guest</h2>
    </x-slot>

    @livewire('guests.guest-form', ['guestId' => $guest->id])
</x-app-layout>