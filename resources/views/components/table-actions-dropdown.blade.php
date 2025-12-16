@props([
    'id' => null,
    'trigger' => null,
])

@php
    $dropdownId = $attributes->get('id') ?? $id ?? 'dropdown-' . uniqid();
    $triggerText = $attributes->get('trigger') ?? $trigger ?? __('Actions');
@endphp

<div
    x-data="{
        open: false,
        position: { top: 0, right: 0 }
    }"
    class="relative inline-block text-left"
    x-id="['dropdown-{{ $dropdownId }}']"
    @click.outside="open = false"
>
    <button
        type="button"
        @click="
            open = !open;
            if (open) {
                const rect = $el.getBoundingClientRect();
                position.top = rect.bottom + window.scrollY + 8;
                position.right = window.innerWidth - rect.right + window.scrollX;
            }
        "
        @keydown.escape="open = false"
        class="inline-flex items-center justify-center px-2 sm:px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
        :aria-expanded="open"
        :aria-controls="$id('dropdown-{{ $dropdownId }}')"
        aria-label="{{ $triggerText }}"
    >
        <span class="hidden sm:inline">{{ $triggerText }}</span>
        <svg class="h-4 w-4 sm:ml-2 sm:-mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path>
        </svg>
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="transform opacity-0 scale-95"
        x-transition:enter-end="transform opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95"
        :id="$id('dropdown-{{ $dropdownId }}')"
        class="fixed w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-[9999]"
        :style="open ? 'top: ' + position.top + 'px; right: ' + position.right + 'px;' : 'display: none;'"
        style="display: none;"
    >
        <div class="py-1">
            {{ $slot }}
        </div>
    </div>
</div>

