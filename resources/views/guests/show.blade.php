<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Guest</h2>
    </x-slot>

    @livewire('guests.guest-profile', ['guest' => $guest])
</x-app-layout>