@props([
    'open' => 'open', // Alpine.js variable name for open state
])

<!-- Backdrop overlay for mobile -->
<div
    x-show="{{ $open }}"
    x-cloak
    @click="{{ $open }} = false"
    x-transition:enter="transition-opacity ease-linear duration-75"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition-opacity ease-linear duration-75"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 bg-black bg-opacity-50 z-[9998] sm:hidden"
    style="display: none;"
></div>

