@props(['header'])

<x-client-app>
    <x-slot name="header">
        {{ $header }}
    </x-slot>

    {{ $slot }}
</x-client-app>


