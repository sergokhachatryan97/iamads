@if($categories->isEmpty())
    {{-- Empty table when no results found --}}
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200">
                <colgroup>
                    <col class="w-10">
                    <col class="w-16">
                    <col>
                    <col class="w-40">
                    <col class="w-24">
                    <col class="w-24">
                    <col class="w-32">
                    <col class="w-28">
                    <col class="w-28">
                    <col class="w-32">
                </colgroup>
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-4 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"></th>
                        <th scope="col" class="px-4 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">ID</th>
                        <th scope="col" class="px-4 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th scope="col" class="px-4 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Service Type</th>
                        <th scope="col" class="px-4 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Mode</th>
                        <th scope="col" class="px-4 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Min</th>
                        <th scope="col" class="px-4 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Max</th>
                        <th scope="col" class="px-4 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Rate</th>
                        <th scope="col" class="px-4 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Status</th>
                        <th scope="col" class="px-4 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr>
                        <td colspan="10" class="px-4 py-8 text-center text-gray-500">
                            {{ __('No services found matching your search criteria.') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
@else
    @php
        $currentSort = request('sort', 'id');
        $currentDir = request('dir', 'asc');
    @endphp

    {{-- Staff View: Full table with actions --}}
        <!-- Single Table with Header and All Services -->
        <div class="bg-white shadow-sm sm:rounded-lg" style="position: relative;">
            <div style="position: relative; overflow-x: auto; overflow-y: visible;">
                <table class="w-full divide-y divide-gray-200">
                    <colgroup>
                        <col class="w-10">
                        <col class="w-16">
                        <col>
                        <col class="w-40">
                        <col class="w-24">
                        <col class="w-24">
                        <col class="w-32">
                        <col class="w-28">
                        <col class="w-28">
                        <col class="w-32">
                    </colgroup>
                    <thead class="bg-gray-50 sticky top-0">
                    <tr>
                        <th scope="col" class="px-4 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">
                            <input type="checkbox"
                                   x-ref="selectAllCheckbox"
                                   @change="toggleSelectAll($event.target.checked)"
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        </th>
                        <th scope="col" class="px-4 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap bg-gray-50">
                            <button type="button"
                                    @click="$dispatch('sort-table', { column: 'id' })"
                                    class="flex items-center gap-1 hover:text-gray-700 focus:outline-none whitespace-nowrap w-full text-left -ml-4 pl-4">
                                ID
                                <span class="flex flex-col">
                                    <svg class="w-3 h-3 {{ $currentSort === 'id' && $currentDir === 'asc' ? 'text-indigo-600' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />
                                    </svg>
                                    <svg class="w-3 h-3 -mt-1 {{ $currentSort === 'id' && $currentDir === 'desc' ? 'text-indigo-600' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </span>
                            </button>
                        </th>
                        <th scope="col" class="px-4 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">
                            <button type="button"
                                    @click="$dispatch('sort-table', { column: 'name' })"
                                    class="flex items-center gap-1 hover:text-gray-700 focus:outline-none w-full text-left -ml-4 pl-4">
                                Name
                                <span class="flex flex-col">
                                    <svg class="w-3 h-3 {{ $currentSort === 'name' && $currentDir === 'asc' ? 'text-indigo-600' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />
                                    </svg>
                                    <svg class="w-3 h-3 -mt-1 {{ $currentSort === 'name' && $currentDir === 'desc' ? 'text-indigo-600' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </span>
                            </button>
                        </th>
                        <th scope="col" class="px-4 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap bg-gray-50">
                            <button type="button"
                                    @click="$dispatch('sort-table', { column: 'service_type' })"
                                    class="flex items-center gap-1 hover:text-gray-700 focus:outline-none whitespace-nowrap w-full text-left -ml-4 pl-4">
                                Service Type
                                <span class="flex flex-col">
                                    <svg class="w-3 h-3 {{ $currentSort === 'service_type' && $currentDir === 'asc' ? 'text-indigo-600' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />
                                    </svg>
                                    <svg class="w-3 h-3 -mt-1 {{ $currentSort === 'service_type' && $currentDir === 'desc' ? 'text-indigo-600' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </span>
                            </button>
                        </th>
                        <th scope="col" class="px-4 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap bg-gray-50">
                            <button type="button"
                                    @click="$dispatch('sort-table', { column: 'mode' })"
                                    class="flex items-center gap-1 hover:text-gray-700 focus:outline-none whitespace-nowrap w-full text-left -ml-4 pl-4">
                                Mode
                                <span class="flex flex-col">
                                    <svg class="w-3 h-3 {{ $currentSort === 'mode' && $currentDir === 'asc' ? 'text-indigo-600' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />
                                    </svg>
                                    <svg class="w-3 h-3 -mt-1 {{ $currentSort === 'mode' && $currentDir === 'desc' ? 'text-indigo-600' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </span>
                            </button>
                        </th>
                        <th scope="col" class="px-4 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap bg-gray-50">
                            <button type="button"
                                    @click="$dispatch('sort-table', { column: 'min_quantity' })"
                                    class="flex items-center gap-1 hover:text-gray-700 focus:outline-none whitespace-nowrap w-full text-left -ml-4 pl-4">
                                Min
                                <span class="flex flex-col">
                                    <svg class="w-3 h-3 {{ $currentSort === 'min_quantity' && $currentDir === 'asc' ? 'text-indigo-600' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />
                                    </svg>
                                    <svg class="w-3 h-3 -mt-1 {{ $currentSort === 'min_quantity' && $currentDir === 'desc' ? 'text-indigo-600' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </span>
                            </button>
                        </th>
                        <th scope="col" class="px-4 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap bg-gray-50">
                            <button type="button"
                                    @click="$dispatch('sort-table', { column: 'max_quantity' })"
                                    class="flex items-center gap-1 hover:text-gray-700 focus:outline-none whitespace-nowrap w-full text-left -ml-4 pl-4">
                                Max
                                <span class="flex flex-col">
                                    <svg class="w-3 h-3 {{ $currentSort === 'max_quantity' && $currentDir === 'asc' ? 'text-indigo-600' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />
                                    </svg>
                                    <svg class="w-3 h-3 -mt-1 {{ $currentSort === 'max_quantity' && $currentDir === 'desc' ? 'text-indigo-600' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </span>
                            </button>
                        </th>
                        <th scope="col" class="px-4 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap bg-gray-50">
                            <button type="button"
                                    @click="$dispatch('sort-table', { column: 'rate_per_1000' })"
                                    class="flex items-center gap-1 hover:text-gray-700 focus:outline-none whitespace-nowrap w-full text-left -ml-4 pl-4">
                                Rate
                                <span class="flex flex-col">
                                    <svg class="w-3 h-3 {{ $currentSort === 'rate_per_1000' && $currentDir === 'asc' ? 'text-indigo-600' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />
                                    </svg>
                                    <svg class="w-3 h-3 -mt-1 {{ $currentSort === 'rate_per_1000' && $currentDir === 'desc' ? 'text-indigo-600' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </span>
                            </button>
                        </th>
                        <th scope="col" class="px-4 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap bg-gray-50">
                            <button type="button"
                                    @click="$dispatch('sort-table', { column: 'is_active' })"
                                    class="flex items-center gap-1 hover:text-gray-700 focus:outline-none whitespace-nowrap w-full text-left -ml-4 pl-4">
                                Status
                                <span class="flex flex-col">
                                    <svg class="w-3 h-3 {{ $currentSort === 'is_active' && $currentDir === 'asc' ? 'text-indigo-600' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />
                                    </svg>
                                    <svg class="w-3 h-3 -mt-1 {{ $currentSort === 'is_active' && $currentDir === 'desc' ? 'text-indigo-600' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </span>
                            </button>
                        </th>
                        <th scope="col" class="px-4 py-1.5 text-end text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap bg-gray-50">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($categories as $category)
                        {{-- Category Header Row --}}
                        <tr class="bg-gray-50 border-b-2 border-gray-200">
                            <td colspan="10" class="px-6 py-2">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        @if($category->icon)
                                            <span class="text-xl">
                                                @if(Str::startsWith($category->icon, '<svg'))
                                                    {!! $category->icon !!}
                                                @elseif(Str::startsWith($category->icon, 'data:'))
                                                    <img src="{{ $category->icon }}" alt="icon" class="w-5 h-5 object-contain">
                                                @elseif(Str::startsWith($category->icon, 'fas ') || Str::startsWith($category->icon, 'far ') || Str::startsWith($category->icon, 'fab ') || Str::startsWith($category->icon, 'fal ') || Str::startsWith($category->icon, 'fad '))
                                                    <i class="{{ $category->icon }}"></i>
                                                @else
                                                    <span class="inline-block">{{ $category->icon }}</span>
                                                @endif
                                            </span>
                                        @endif
                                        <h3 class="text-lg font-semibold {{ ($category->status ?? true) ? 'text-gray-900' : 'text-gray-500' }}">{{ $category->name }}</h3>
                                        @if(!($category->status ?? true))
                                            <span class="px-2 py-0.5 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">
                                                {{ __('Disabled') }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <button type="button"
                                                @click="toggleCategory({{ $category->id }})"
                                                class="inline-flex items-center gap-1 text-sm text-gray-600 hover:text-gray-900 focus:outline-none">
                                            <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': isCategoryCollapsed({{ $category->id }}) }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                            <span>{{ __('Collapse') }} ({{ $category->services->count() }})</span>
                                        </button>

                                        @if(!($category->status ?? true))
                                            <!-- Enable Category Button (shown when disabled) -->
                                            <form method="POST" action="{{ route('staff.categories.toggle-status', $category) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                                                    {{ __('Enable category') }}
                                                </button>
                                            </form>
                                        @else
                                            <!-- Actions Dropdown (shown when enabled) -->
                                            <div class="relative" x-data="{ open: false }">
                                                <button type="button"
                                                        @click="open = !open"
                                                        class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                                    Actions
                                                    <svg class="ml-2 -mr-1 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                                    </svg>
                                                </button>
                                                <div x-show="open"
                                                     @click.away="open = false"
                                                     x-cloak
                                                     class="absolute right-0 mt-2 w-56 bg-white rounded-md shadow-lg py-1 border border-gray-200"
                                                     style="display: none; z-index: 9999;">
                                                    <a href="#"
                                                       @click.prevent="openEditModal(@js(['id' => $category->id, 'name' => $category->name, 'icon' => $category->icon ?? ''])); open = false"
                                                       class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                        Edit category
                                                    </a>
                                                    <form method="POST" action="{{ route('staff.categories.toggle-status', $category) }}" class="inline">
                                                        @csrf
                                                        <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                            {{ __('Disable category') }}
                                                        </button>
                                                    </form>
                                                    <a href="#"
                                                       @click.prevent="selectAllCategoryServices({{ $category->id }}); open = false"
                                                       class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                        Select all category services
                                                    </a>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>

                        {{-- Service Rows (shown/hidden based on collapse state) --}}
                        @if($category->services->isEmpty())
                            <tr x-show="!isCategoryCollapsed({{ $category->id }})" x-transition>
                                <td colspan="10" class="px-4 py-6 text-center text-gray-500">
                                    {{ __('No services in this category.') }}
                                </td>
                            </tr>
                        @else
                            @foreach($category->services as $service)
                                <tr x-show="!isCategoryCollapsed({{ $category->id }})"
                                    x-transition
                                    class="hover:bg-gray-50 {{ !($service->is_active ?? true) ? 'opacity-60 bg-gray-50' : '' }}">
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            <input type="checkbox"
                                                   data-category-id="{{ $category->id }}"
                                                   data-service-id="{{ $service->id }}"
                                                   value="{{ $service->id }}"
                                                   :checked="selectedServices.includes({{ $service->id }})"
                                                   class="service-checkbox rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 {{ !($service->is_active ?? true) ? 'opacity-50' : '' }}"
                                                   @change="toggleServiceSelection({{ $service->id }}, $event.target.checked, {{ $category->id }})">
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">
                                            {{ $service->id }}
                                        </td>
                                        <td class="px-4 py-2 text-sm font-medium text-gray-900">
                                            {{ $service->name }}
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">
                                            @php
                                                $serviceTypes = [
                                                    'default' => 'Default',
                                                    'custom_comments' => 'Custom Comments',
                                                    'mentions' => 'Mentions',
                                                    'package' => 'Package',
                                                ];
                                            @endphp
                                            {{ $serviceTypes[$service->service_type ?? 'default'] ?? ucfirst($service->service_type ?? 'Default') }}
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">
                                            {{ ucfirst($service->mode) }}
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">
                                            {{ number_format($service->min_quantity ?? $service->min_order ?? 1) }}
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">
                                            {{ number_format($service->max_quantity ?? $service->max_order ?? 1) }}
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">
                                            ${{ number_format($service->rate_per_1000 ?? $service->rate ?? 0, 4) }}
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            @if($service->is_active ?? true)
                                                <span class="px-2 py-0.5 text-xs font-medium bg-green-100 text-green-800 rounded-full">
                                                    Enabled
                                                </span>
                                            @else
                                                <span class="px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">
                                                    Disabled
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm" style="position: relative; overflow: visible;">
                                            @if(!($service->is_active ?? true))
                                                <!-- Enable Service Button (shown when disabled) -->
                                                <form method="POST" action="{{ route('staff.services.toggle-status', $service) }}" class="inline">
                                                    @csrf
                                                    <button type="submit" class="inline-flex items-center px-3 py-1 text-sm font-medium text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                                                        {{ __('Enable') }}
                                                    </button>
                                                </form>
                                            @else
                                                @if($service->trashed())
                                                    <!-- Restore Button for Deleted Services -->
                                                    <button type="button" 
                                                            @click.stop="setTimeout(function() { window.dispatchEvent(new CustomEvent('show-restore-confirm', { detail: { serviceId: {{ $service->id }}, serviceName: '{{ addslashes($service->name) }}' }, bubbles: true })); }, 150);"
                                                            class="inline-flex items-center px-3 py-1 text-sm font-medium text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                                        </svg>
                                                        {{ __('Restore') }}
                                                    </button>
                                                @else
                                                    <!-- Actions Dropdown (shown when enabled) -->
                                                    <div class="relative" x-data="{ open{{ $service->id }}: false }" style="position: relative;">
                                                        <button type="button"
                                                                @click="open{{ $service->id }} = !open{{ $service->id }}"
                                                                class="inline-flex items-center px-3 py-1 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                                            Actions
                                                            <svg class="ml-1 -mr-1 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                                            </svg>
                                                        </button>

                                                        <x-filter-dropdown-backdrop open="open{{ $service->id }}" />

                                                        <div
                                                            x-show="open{{ $service->id }}"
                                                            x-cloak
                                                            @click.away="open{{ $service->id }} = false"
                                                            x-transition:enter="transition ease-out duration-100"
                                                            x-transition:enter-start="transform opacity-0 scale-95"
                                                            x-transition:enter-end="transform opacity-100 scale-100"
                                                            x-transition:leave="transition ease-in duration-75"
                                                            x-transition:leave-start="transform opacity-100 scale-100"
                                                            x-transition:leave-end="transform opacity-0 scale-95"
                                                            class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 border border-gray-200"
                                                            style="display: none; z-index: 9999;">
                                                            <a href="{{ route('staff.services.edit', $service) }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Edit</a>
                                                            <form method="POST" action="{{ route('staff.services.duplicate', $service) }}" class="inline">
                                                                @csrf
                                                                <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                                    {{ __('Duplicate') }}
                                                                </button>
                                                            </form>
                                                            <form method="POST" action="{{ route('staff.services.toggle-status', $service) }}" class="inline">
                                                                @csrf
                                                                <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                                    {{ __('Disable') }}
                                                                </button>
                                                            </form>
                                                            <button type="button"
                                                                    @click.stop="open{{ $service->id }} = false; setTimeout(function() { window.dispatchEvent(new CustomEvent('show-delete-confirm', { detail: { serviceId: {{ $service->id }}, serviceName: '{{ addslashes($service->name) }}' }, bubbles: true })); }, 150);"
                                                                    class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
                                                                {{ __('Delete') }}
                                                            </button>
                                                        </div>
                                                    </div>
                                                @endif
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
