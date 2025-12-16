@props([
    'label' => 'Date',
    'name' => 'date_filter',
    'id' => 'date_filter',
    'customRangeId' => 'custom-date-range',
    'fromName' => 'date_from',
    'toName' => 'date_to',
    'fromId' => 'date_from',
    'toId' => 'date_to',
    'value' => null,
])

@php
    $currentValue = $value ?? request($name);
    
    // Build options array for custom-select
    $dateOptions = [
        ['value' => '', 'label' => __('All Dates')],
        ['value' => 'today', 'label' => __('Today')],
        ['value' => 'yesterday', 'label' => __('Previous Day')],
        ['value' => '7days', 'label' => __('Last 7 Days')],
        ['value' => '30days', 'label' => __('Last 30 Days')],
        ['value' => '90days', 'label' => __('Last 90 Days')],
        ['value' => 'custom', 'label' => __('Custom Range')],
    ];
@endphp

<div>
    <x-custom-select
        :label="$label"
        :name="$name"
        :id="$id"
        :value="$currentValue"
        placeholder="{{ __('Select date range') }}"
        :options="$dateOptions"
    />
</div>

<!-- Custom Date Range (shown when custom is selected) -->
<div 
    id="{{ $customRangeId }}" 
    x-data="{
        showCustomRange: {{ $currentValue === 'custom' ? 'true' : 'false' }},
        init() {
            // Listen for changes on the hidden input from custom-select
            const hiddenInput = document.querySelector('input[name=\'{{ $name }}\']');
            if (hiddenInput) {
                // Set initial state
                this.showCustomRange = hiddenInput.value === 'custom';
                
                // Listen for changes
                hiddenInput.addEventListener('change', (e) => {
                    this.showCustomRange = e.target.value === 'custom';
                });
            }
        }
    }"
    x-show="showCustomRange"
    x-cloak
>
    <div class="grid grid-cols-2 gap-2 mt-2">
        <div>
            <label for="{{ $fromId }}" class="block text-sm font-medium text-gray-700 mb-1">
                {{ __('From') }}
            </label>
            <input
                type="date"
                id="{{ $fromId }}"
                name="{{ $fromName }}"
                value="{{ request($fromName) }}"
                class="block w-full py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
            />
        </div>
        <div>
            <label for="{{ $toId }}" class="block text-sm font-medium text-gray-700 mb-1">
                {{ __('To') }}
            </label>
            <input
                type="date"
                id="{{ $toId }}"
                name="{{ $toName }}"
                value="{{ request($toName) }}"
                class="block w-full py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
            />
        </div>
    </div>
</div>

