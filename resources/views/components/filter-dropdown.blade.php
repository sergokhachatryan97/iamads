@props([
    'open' => 'open', // Alpine.js variable name for open state
    'maxHeight' => '400px',
])

<div
    x-show="{{ $open }}"
    x-cloak
    @click.away="{{ $open }} = false"
    x-transition:enter="transition ease-out duration-100"
    x-transition:enter-start="transform opacity-0 scale-95"
    x-transition:enter-end="transform opacity-100 scale-100"
    x-transition:leave="transition ease-in duration-75"
    x-transition:leave-start="transform opacity-100 scale-100"
    x-transition:leave-end="transform opacity-0 scale-95"
    x-effect="{{ $open }} && $nextTick(() => { if (window.innerWidth < 640) document.body.style.overflow = 'hidden'; })"
    x-effect="!{{ $open }} && (document.body.style.overflow = '')"
    class="fixed sm:absolute inset-x-4 sm:inset-x-auto right-0 top-20 sm:top-full mt-0 sm:mt-2 w-auto sm:w-80 md:w-96 bg-white rounded-lg shadow-2xl z-[9999] border border-gray-200 overflow-y-auto"
    style="display: none; contain: layout style paint; background-color: white; max-height: {{ $maxHeight }}; scrollbar-width: thin;"
>
    <div class="p-4 sm:p-5 space-y-4 bg-white flex flex-col" style="position: relative; z-index: 1; background-color: white;">
        {{ $slot }}
    </div>
</div>

