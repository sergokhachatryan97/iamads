@props([
    'applyText' => 'Apply',
    'clearText' => 'Clear',
    'clearRoute' => null,
    'preserveParams' => ['sort', 'dir'],
    'closeOnClick' => true,
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

<div class="flex gap-2 mt-auto pt-2" @if($closeOnClick) @click.stop @endif>
    <button
        type="submit"
        @if($closeOnClick) @click="open = false" @endif
        class="flex-1 inline-flex justify-center items-center px-3 py-2 border border-transparent text-xs font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
    >
        {{ __($applyText) }}
    </button>
    @if($clearRoute)
        <a
            href="{{ $clearUrl }}"
            onclick="event.stopPropagation();"
            class="flex-1 inline-flex justify-center items-center px-3 py-1 border border-gray-300 text-xs font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
        >
            {{ __($clearText) }}
        </a>
    @endif
</div>

