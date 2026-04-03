@props([
    'active' => [],
    'count' => null, // Alpine expression string, e.g. "activeFiltersCount"
    'badgeClass' => 'absolute -top-2 -right-2',
    'dropdownTheme' => 'default',
])

@php
    // Count non-empty values (excluding null, empty string, but keep '0' as it's a valid value for verified filter)
    $phpCount = collect($active)->filter(fn ($v) => $v !== null && $v !== '')->count();
@endphp

<div
    class="filter-button-root relative z-[100] inline-block flex-shrink-0 align-top max-w-full"
    x-data="{ open: false }"
    @close-filter-dropdown.window="open = false"
    @filter-applied.window="open = false"
>
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

    @if($dropdownTheme !== 'smm')
        <x-filter-dropdown-backdrop open="open" />
    @endif
    <x-filter-dropdown open="open" :theme="$dropdownTheme">
        {{ $dropdown }}
    </x-filter-dropdown>
</div>

