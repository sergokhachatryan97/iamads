@props([
    'applyText' => 'Apply',
    'clearText' => 'Clear',
    'clearRoute' => null,
    'preserveParams' => ['sort', 'dir'],
    'closeOnClick' => true,
    'open' => 'open', // Alpine.js variable name for dropdown open state
    'showApply' => true, // Show Apply button
])

@php
    // Build clear URL with preserved parameters
    $clearUrl = '#';
    if ($clearRoute) {
        $params = [];
        foreach ($preserveParams as $param) {
            if (request($param)) {
                $params[$param] = request($param);
            }
        }
        $clearUrl = route($clearRoute, $params);
    }
@endphp

<div class="flex flex-col sm:flex-row gap-2 p-3 sm:p-4 pt-3 border-t border-gray-200 flex-shrink-0 bg-white sticky bottom-0 z-10" @if($closeOnClick) @click.stop @endif>
    @if($showApply)
        <button
            type="submit"
            @click="$dispatch('filter-applied')"
            class="flex-1 inline-flex justify-center items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-1 focus:ring-offset-1 focus:ring-indigo-500 transition-colors"
        >
            {{ __($applyText) }}
        </button>
    @endif
    @if($clearRoute)
        <a
            href="{{ $clearUrl }}"
            onclick="event.stopPropagation();"
            class="{{ $showApply ? 'flex-1' : 'w-full' }} inline-flex justify-center items-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-1 focus:ring-offset-1 focus:ring-indigo-500 transition-colors"
        >
            {{ __($clearText) }}
        </a>
    @endif
</div>

