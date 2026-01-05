@props([
    'column' => null,
    'label' => '',
    'currentSortBy' => null,
    'currentSortDir' => 'asc',
    'route' => null,
    'params' => [],
    'ajax' => false,
    'sortEvent' => 'table-sort',
])

@php
    // Build sort URL
    $sortParams = request()->except(['sort_by', 'sort_dir', 'page']);

    // Merge additional params (like status, filters, etc.)
    if (!empty($params)) {
        // Filter out null values
        $params = array_filter($params, function($value) {
            return $value !== null && $value !== '';
        });
        $sortParams = array_merge($sortParams, $params);
    }

    // Set sort column
    $sortParams['sort_by'] = $column;

    // Toggle sort direction
    if ($currentSortBy === $column) {
        $sortParams['sort_dir'] = $currentSortDir === 'asc' ? 'desc' : 'asc';
    } else {
        $sortParams['sort_dir'] = 'asc';
    }

    // Build URL
    if ($route) {
        $url = route($route, $sortParams);
    } else {
        // Fallback to current URL with query params
        $url = request()->url() . '?' . http_build_query($sortParams);
    }

    // Determine icon
    $icon = '';
    if ($currentSortBy !== $column) {
        // Unsorted - show neutral icon
        $icon = '<svg class="w-4 h-4 inline ml-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path></svg>';
    } elseif ($currentSortDir === 'asc') {
        // Ascending
        $icon = '<svg class="w-4 h-4 inline ml-1 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path></svg>';
    } else {
        // Descending
        $icon = '<svg class="w-4 h-4 inline ml-1 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>';
    }
@endphp

<th {{ $attributes->merge(['scope' => 'col', 'class' => 'px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider']) }}>
    @if($ajax)
        <button
            type="button"
            data-column="{{ $column }}"
            onclick="window.dispatchEvent(new CustomEvent('{{ $sortEvent }}', { detail: { column: '{{ $column }}' } })); return false;"
            class="group inline-flex items-center hover:text-gray-700 cursor-pointer bg-transparent border-0 p-0 text-left w-full text-left"
            style="background: transparent; border: none; padding: 0;"
        >
            {{ $label }}
            {!! $icon !!}
        </button>
    @else
        <a href="{{ $url }}" class="group inline-flex items-center hover:text-gray-700">
            {{ $label }}
            {!! $icon !!}
        </a>
    @endif
</th>

