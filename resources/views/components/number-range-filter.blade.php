@props([
    'minLabel' => 'Min',
    'maxLabel' => 'Max',
    'minName' => 'min',
    'maxName' => 'max',
    'minId' => 'min',
    'maxId' => 'max',
    'minValue' => null,
    'maxValue' => null,
    'step' => '0.01',
    'min' => '0',
    'placeholder' => '0.00',
])

@php
    $currentMinValue = $minValue ?? request($minName);
    $currentMaxValue = $maxValue ?? request($maxName);
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
    <div>
        <label for="{{ $minId }}" class="block text-xs font-medium text-gray-700 mb-1 whitespace-nowrap">
            {{ $minLabel }}
        </label>
        <input
            type="number"
            id="{{ $minId }}"
            name="{{ $minName }}"
            step="{{ $step }}"
            min="{{ $min }}"
            value="{{ $currentMinValue }}"
            placeholder="{{ $placeholder }}"
            class="block w-full py-1.5 px-2.5 text-xs border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
        />
    </div>
    <div>
        <label for="{{ $maxId }}" class="block text-xs font-medium text-gray-700 mb-1 whitespace-nowrap">
            {{ $maxLabel }}
        </label>
        <input
            type="number"
            id="{{ $maxId }}"
            name="{{ $maxName }}"
            step="{{ $step }}"
            min="{{ $min }}"
            value="{{ $currentMaxValue }}"
            placeholder="{{ $placeholder }}"
            class="block w-full py-1.5 px-2.5 text-xs border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
        />
    </div>
</div>


