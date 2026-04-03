@props([
    'open' => 'open', // Alpine.js variable name for open state
    'maxHeight' => '600px',
    'theme' => 'default',
])

@php
    $smm = $theme === 'smm';
    $outerBorder = $smm ? 'border-[var(--border)]' : 'border-gray-200';
    $panelBg = $smm ? 'bg-[var(--card)]' : 'bg-white';
    $headerTitle = $smm ? 'text-[var(--text)]' : 'text-gray-900';
    $closeBtn = $smm ? 'text-[var(--text3)] hover:text-[var(--text)]' : 'text-gray-400 hover:text-gray-600 focus:text-gray-600';
    /*
     * SMM client: never use viewport-fixed + full-width panel — it spans the whole window and
     * overlaps the sidebar. Stay anchored to the filter trigger (absolute, right-0, top-full).
     */
    $positionClasses = $smm
        ? 'absolute left-auto right-0 top-full mt-2 w-[min(26.25rem,calc(100vw-1.5rem))] sm:w-[380px] md:w-[420px] max-h-[min(32rem,calc(100vh-6rem))] z-[99999]'
        : 'fixed sm:absolute left-4 right-4 sm:left-auto sm:right-0 top-1/2 sm:top-full -translate-y-1/2 sm:translate-y-0 mt-0 sm:mt-2 w-[calc(100vw-2rem)] sm:w-[380px] md:w-[420px] max-h-[calc(100vh-4rem)] sm:max-h-[500px] z-[99999]';
    $outerStyle = $smm
        ? 'display: none; scrollbar-width: thin; backdrop-filter: none;'
        : 'display: none; contain: layout style paint; background-color: white !important; scrollbar-width: thin; max-width: calc(100vw - 2rem); z-index: 99999 !important; isolation: isolate; backdrop-filter: none;';
    $scrollBgStyle = $smm
        ? 'position: relative; z-index: 1; scrollbar-width: thin;'
        : 'position: relative; z-index: 1; background-color: white; isolation: isolate; scrollbar-width: thin;';
@endphp

<div
    x-show="{{ $open }}"
    x-cloak
    @click.away="{{ $open }} = false"
    @if($smm)
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 translate-y-1"
    x-transition:enter-end="opacity-100 translate-y-0"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100 translate-y-0"
    x-transition:leave-end="opacity-0 translate-y-1"
    @else
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 scale-95 translate-y-2"
    x-transition:enter-end="opacity-100 scale-100 translate-y-0"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100 scale-100 translate-y-0"
    x-transition:leave-end="opacity-0 scale-95 translate-y-2"
    @endif
    class="{{ $positionClasses }} rounded-xl shadow-2xl border {{ $outerBorder }} overflow-hidden flex flex-col {{ $smm ? $panelBg : '' }}"
    style="{{ $outerStyle }}"
>
    <!-- Mobile header with close button -->
    <div class="sticky top-0 {{ $panelBg }} border-b {{ $outerBorder }} px-3 py-2 flex items-center justify-between sm:hidden z-10">
        <h3 class="text-sm font-semibold {{ $headerTitle }}">{{ __('Filters') }}</h3>
        <button
            type="button"
            @click="{{ $open }} = false"
            class="{{ $closeBtn }} focus:outline-none transition-colors"
            aria-label="{{ __('Close filters') }}"
        >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>

    <div class="{{ $panelBg }} flex flex-col flex-1 min-h-0 overflow-y-auto" style="{{ $scrollBgStyle }}">
        <div class="p-3 sm:p-4 space-y-3">
            {{ $slot }}
        </div>
    </div>
</div>

