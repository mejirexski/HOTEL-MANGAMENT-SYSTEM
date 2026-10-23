@props(['active' => false, 'label'])

@php
    $classes = $active
        ? 'inline-flex items-center px-2 pt-1 border-b-2 border-indigo-400 text-gray-900 focus:outline-none focus:border-indigo-700 transition duration-150 ease-in-out'
        : 'inline-flex items-center px-2 pt-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes, 'title' => $label, 'aria-label' => $label]) }}
   @if ($active) aria-current="page" @endif>
    {{ $slot }}
</a>