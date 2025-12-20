@props([
    'active' => [],
    'count' => null, // Alpine expression string, e.g. "activeFiltersCount"
    'badgeClass' => 'absolute -top-2 -right-2',
])

@php
    // Count non-empty values (excluding null, empty string, but keep '0' as it's a valid value for verified filter)
    $phpCount = collect($active)->filter(fn ($v) => $v !== null && $v !== '')->count();
@endphp

<div class="relative z-50 flex-shrink-0" x-data="{ open: false }" @close-filter-dropdown.window="open = false" @filter-applied.window="open = false" style="isolation:isolate;">
    <div class="relative inline-block" @click="open = !open">
        {{ $trigger }}

        {{-- JS/Alpine-driven count --}}
        @if($count)
            <span
                x-show="{{ $count }} > 0"
                x-cloak
                class="{{ $badgeClass }} inline-flex items-center justify-center min-w-[20px] h-5 px-1 text-xs font-bold leading-none text-white bg-indigo-600 rounded-full z-10"
                x-text="{{ $count }}"
                style="display:none;"
            ></span>
        @else
            {{-- PHP-driven count --}}
            @if($phpCount > 0)
                <span class="{{ $badgeClass }} inline-flex items-center justify-center min-w-[20px] h-5 px-1 text-xs font-bold leading-none text-white bg-indigo-600 rounded-full z-10">
                    {{ $phpCount }}
                </span>
            @endif
        @endif
    </div>

    <x-filter-dropdown-backdrop open="open" />
    <x-filter-dropdown open="open">
        {{ $dropdown }}
    </x-filter-dropdown>
</div>

