@props([
    'name' => 'search',
    'id' => null,
    'value' => null,
    'searchBy' => 'all',
    'searchByLabel' => 'All',
    'placeholder' => 'Search',
    'maxWidth' => '300px',
    'searchByOptions' => null, // Array of ['key' => 'label'] options
])

@php
    $inputId = $id ?? $name;
    $currentValue = $value ?? '';
    
    // Default search options if not provided
    $searchByOptions = $searchByOptions ?? [
        'all' => __('All'),
    ];
    
    // Ensure 'all' option exists
    if (!isset($searchByOptions['all'])) {
        $searchByOptions = ['all' => __('All')] + $searchByOptions;
    }
@endphp

<div class="flex gap-0" style="max-width: {{ $maxWidth }};" x-data="searchWithFilter(@js($searchByOptions), @js($searchBy), @js($currentValue))">
    <div class="relative flex-1">
        <div class="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none">
            <svg class="h-3.5 w-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
        </div>
        <input
            type="text"
            id="{{ $inputId }}"
            name="{{ $name }}"
            x-model="searchValue"
            @input.debounce.300ms="performSearch()"
            placeholder="{{ __($placeholder) }}"
            class="block w-full pl-7 pr-2 py-2.5 text-xs border border-gray-300 border-r-0 rounded-l-md rounded-r-none leading-4 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
            autocomplete="off"
            {{ $attributes }}
        />
    </div>
    
    {{-- Search By Dropdown --}}
    <div class="relative">
        <button type="button"
                @click="searchByOpen = !searchByOpen"
                @click.away="searchByOpen = false"
                class="inline-flex items-center px-3 py-2.5 border border-gray-300 border-l-0 rounded-r-md rounded-l-none bg-white text-xs font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
            <span x-text="currentSearchByLabel"></span>
            <svg class="ml-1.5 -mr-1 h-4 w-4 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
            </svg>
        </button>
        <input type="hidden" name="search_by" :value="currentSearchBy">
        
        <div x-show="searchByOpen"
             x-cloak
             x-transition
             class="absolute z-10 mt-1 w-40 bg-white shadow-lg max-h-60 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none"
             style="display: none;">
            <template x-for="(label, key) in searchByOptions" :key="key">
                <a href="#"
                   @click.prevent="setSearchBy(key, label); searchByOpen = false; performSearch()"
                   :class="currentSearchBy === key ? 'bg-indigo-50 text-indigo-900' : 'text-gray-900'"
                   class="flex items-center px-3 py-2 text-xs hover:bg-gray-100">
                    <span class="mr-2" x-show="currentSearchBy === key">✓</span>
                    <span x-text="label"></span>
                </a>
            </template>
        </div>
    </div>
</div>

<script>
    function searchWithFilter(searchByOptions, initialSearchBy, initialValue) {
        return {
            searchByOptions: searchByOptions,
            currentSearchBy: initialSearchBy,
            currentSearchByLabel: searchByOptions[initialSearchBy] || 'All',
            searchValue: initialValue,
            searchByOpen: false,
            
            setSearchBy(key, label) {
                this.currentSearchBy = key;
                this.currentSearchByLabel = label;
            },
            
            performSearch() {
                // Dispatch event for parent component to handle
                this.$dispatch('search-changed', {
                    search: this.searchValue,
                    search_by: this.currentSearchBy
                });
            }
        };
    }
</script>

