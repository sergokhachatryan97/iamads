@props([
    'applyText' => 'Apply',
    'clearText' => 'Clear',
    'clearRoute' => null,
    'preserveParams' => ['sort', 'dir'],
    'closeOnClick' => true,
    'open' => 'open', // Alpine.js variable name for dropdown open state
    'showApply' => true, // Show Apply button
    'variant' => 'default',
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

@php
    $smm = $variant === 'smm';
    $barBorder = $smm ? 'border-[var(--border)]' : 'border-gray-200';
    $barBg = $smm ? 'bg-[var(--card)]' : 'bg-white';
    $clearClass = $smm
        ? 'cs-filter-actions-clear inline-flex justify-center items-center px-3 py-1.5 border border-[var(--border)] text-xs font-medium rounded-md focus:outline-none focus:ring-1 focus:ring-offset-1 focus:ring-purple-500 transition-colors focus:ring-offset-[var(--card)]'
        : 'inline-flex justify-center items-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-1 focus:ring-offset-1 focus:ring-indigo-500 transition-colors';
    $applyClass = $smm
        ? 'flex-1 inline-flex justify-center items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-[var(--purple)] hover:brightness-110 focus:outline-none focus:ring-1 focus:ring-offset-1 focus:ring-purple-500 transition-colors focus:ring-offset-[var(--card)]'
        : 'flex-1 inline-flex justify-center items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-1 focus:ring-offset-1 focus:ring-indigo-500 transition-colors';
@endphp

<div class="flex flex-col sm:flex-row gap-2 p-3 sm:p-4 pt-3 border-t {{ $barBorder }} flex-shrink-0 {{ $barBg }} sticky bottom-0 z-10" @if($closeOnClick) @click.stop @endif>
    @if($showApply)
        <button
            type="submit"
            @click="$dispatch('filter-applied')"
            class="{{ $applyClass }}"
        >
            {{ __($applyText) }}
        </button>
    @endif
    @if($clearRoute)
        <a
            href="{{ $clearUrl }}"
            onclick="event.stopPropagation();"
            class="{{ $showApply ? 'flex-1' : 'w-full' }} {{ $clearClass }}"
        >
            {{ __($clearText) }}
        </a>
    @endif
</div>

