@props([
    'name' => 'q',
    'id' => null,
    'value' => null,
    'placeholder' => 'Search',
    'maxWidth' => '200px',
])

@php
    $inputId = $id ?? $name;
    $currentValue = $value ?? request($name, '');
@endphp

<div class="relative flex-shrink-0 w-full sm:w-auto" style="max-width: {{ $maxWidth }};">
    <div class="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none">
        <svg class="h-3.5 w-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
        </svg>
    </div>

    <label for="{{ $inputId }}" class="sr-only">{{ __($placeholder) }}</label>
    <input
        type="text"
        id="{{ $inputId }}"
        name="{{ $name }}"
        value="{{ $currentValue }}"
        placeholder="{{ __($placeholder) }}"
        aria-label="{{ __($placeholder) }}"
        class="block w-full pl-7 pr-2 py-2.5 text-xs border border-gray-300 rounded-md leading-4 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
        autocomplete="off"
        {{ $attributes }}
    />
</div>

