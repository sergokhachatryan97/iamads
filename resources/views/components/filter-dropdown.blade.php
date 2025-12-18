@props([
    'open' => 'open', // Alpine.js variable name for open state
    'maxHeight' => '600px',
])

<div
    x-show="{{ $open }}"
    x-cloak
    @click.away="{{ $open }} = false"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 scale-95 translate-y-2"
    x-transition:enter-end="opacity-100 scale-100 translate-y-0"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100 scale-100 translate-y-0"
    x-transition:leave-end="opacity-0 scale-95 translate-y-2"
    x-effect="
        const parentEl = $el.closest('[x-data*=\'{{ $open }}\']');
        const isOpen = parentEl ? Alpine.$data(parentEl).{{ $open }} : false;
        if (isOpen) {
            const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
            document.body.style.overflow = 'hidden';
            document.body.style.overflowX = 'hidden';
            if (scrollbarWidth > 0) {
                document.body.style.paddingRight = scrollbarWidth + 'px';
            }
        } else {
            document.body.style.overflow = '';
            document.body.style.overflowX = '';
            document.body.style.paddingRight = '';
        }
    "
    class="fixed sm:absolute left-4 right-4 sm:left-auto sm:right-0 top-1/2 sm:top-full -translate-y-1/2 sm:translate-y-0 mt-0 sm:mt-2 w-[calc(100vw-2rem)] sm:w-[380px] md:w-[420px] rounded-lg shadow-2xl border border-gray-200 overflow-y-auto overflow-x-hidden max-h-[calc(100vh-4rem)] sm:max-h-[500px]"
    style="display: none; contain: layout style paint; background-color: white !important; scrollbar-width: thin; max-width: calc(100vw - 2rem); z-index: 99999 !important; isolation: isolate; backdrop-filter: none;"
>
    <!-- Mobile header with close button -->
    <div class="sticky top-0 bg-white border-b border-gray-200 px-3 py-2 flex items-center justify-between sm:hidden z-10">
        <h3 class="text-sm font-semibold text-gray-900">{{ __('Filters') }}</h3>
        <button
            type="button"
            @click="{{ $open }} = false"
            class="text-gray-400 hover:text-gray-600 focus:outline-none focus:text-gray-600 transition-colors"
            aria-label="{{ __('Close filters') }}"
        >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>
    
    <div class="p-3 sm:p-4 space-y-3 bg-white flex flex-col" style="position: relative; z-index: 1; background-color: white; isolation: isolate;">
        {{ $slot }}
    </div>
</div>

