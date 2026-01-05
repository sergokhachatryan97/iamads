<x-client-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Services') }}
        </h2>
    </x-slot>

    <div class="py-12"
         x-data="serviceManagement(@js($filters ?? []))"
         @sort-table.window="sortTable($event.detail.column)"
         @filter-applied.window="handleFormSubmit($event)"
         x-init="
             window.addEventListener('popstate', () => { window.location.reload(); });
             
             // Initialize active filters from form inputs
             $nextTick(() => {
                 if (typeof this.updateActiveFilters === 'function') {
                     this.updateActiveFilters();
                 }
                 if (typeof this.updateFilterCount === 'function') {
                     this.updateFilterCount();
                 }
                 if (typeof this.performAjaxSearch === 'function') {
                     this.performAjaxSearch();
                 }
                 if (typeof this.initScrollSync === 'function') {
                     this.initScrollSync();
                 }
             });
         ">
        <div class="max-w-[95%] mx-auto sm:px-6 lg:px-8">
            <!-- Search Bar and Filter Button -->
            <div class="mb-6 relative z-10" style="isolation: isolate;">
                <form method="GET" action="{{ route('client.services.index') }}" id="filter-form" @submit.prevent="handleFormSubmit($event)">
                    @php
                        $currentCategoryId = request('category_id', '');
                        $currentFavoritesOnly = request('favorites_only', '');
                        $currentSearch = request('search', '');
                        
                        // Calculate initial filter count
                        $initialFilterCount = 0;
                        if ($currentCategoryId) $initialFilterCount++;
                        if ($currentFavoritesOnly === '1') $initialFilterCount++;
                        if ($currentSearch) $initialFilterCount++;
                    @endphp
                    
                    <!-- Hidden inputs for filters (initialized from URL parameters) -->
                    <input type="hidden" name="category_id" value="{{ $currentCategoryId }}">
                    <input type="hidden" name="favorites_only" value="{{ $currentFavoritesOnly === '1' ? '1' : '0' }}">
                    
                    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 sm:gap-4 mb-4 justify-end">
                        <!-- Filter Button with Badge -->
                        <x-filter-button count="activeFiltersCount">
                            <x-slot name="trigger">
                                <button
                                    type="button"
                                    class="relative inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                >
                                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                                    </svg>
                                </button>
                            </x-slot>

                            <x-slot name="dropdown">
                                @php
                                    $currentCategoryId = request('category_id', '');
                                    $currentFavoritesOnly = request('favorites_only', '');
                                @endphp

                                <!-- Filter List -->
                                <div class="space-y-1">
                                    <!-- All Option -->
                                    <button type="button"
                                            @click="selectFilter('', false); $dispatch('close-filter-dropdown');"
                                            class="w-full text-left px-3 py-2 text-sm rounded-md transition-colors"
                                            :class="!getActiveCategoryId() && getActiveFavoritesOnly() !== '1' && !getActiveSearch() ? 'text-blue-500 bg-blue-50' : 'text-gray-700 hover:bg-gray-100'">
                                        {{ __('All') }}
                                    </button>

                                    <!-- Favorite Services Option -->
                                    <button type="button"
                                            @click="selectFilter('', true); $dispatch('close-filter-dropdown');"
                                            class="w-full text-left px-3 py-2 text-sm rounded-md transition-colors flex items-center gap-2"
                                            :class="!getActiveCategoryId() && getActiveFavoritesOnly() === '1' && !getActiveSearch() ? 'text-blue-500 bg-blue-50' : 'text-gray-700 hover:bg-gray-100'">
                                        <svg class="w-4 h-4" :class="!getActiveCategoryId() && getActiveFavoritesOnly() === '1' && !getActiveSearch() ? 'text-blue-500' : 'text-gray-400'" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                        </svg>
                                        <span>{{ __('Favorite services') }}</span>
                                    </button>

                                    <!-- Category Options -->
                                    @if(isset($categoriesList) && $categoriesList)
                                        @foreach($categoriesList as $category)
                                            <button type="button"
                                                    @click="selectFilter({{ $category->id }}, false); $dispatch('close-filter-dropdown');"
                                                    class="w-full text-left px-3 py-2 text-sm rounded-md transition-colors flex items-center gap-2"
                                                    :class="String(getActiveCategoryId()) === '{{ $category->id }}' && getActiveFavoritesOnly() !== '1' && !getActiveSearch() ? 'text-blue-500 bg-blue-50' : 'text-gray-700 hover:bg-gray-100'">
                                                @if($category->icon)
                                                    <span class="text-base flex-shrink-0">
                                                        @if(Str::startsWith($category->icon, '<svg'))
                                                            <span class="inline-block w-4 h-4" :class="String(getActiveCategoryId()) === '{{ $category->id }}' && getActiveFavoritesOnly() !== '1' && !getActiveSearch() ? 'text-blue-500' : 'text-gray-400'">{!! $category->icon !!}</span>
                                                        @elseif(Str::startsWith($category->icon, 'data:'))
                                                            <img src="{{ $category->icon }}" alt="icon" class="w-4 h-4 object-contain">
                                                        @elseif(Str::startsWith($category->icon, 'fas ') || Str::startsWith($category->icon, 'far ') || Str::startsWith($category->icon, 'fab ') || Str::startsWith($category->icon, 'fal ') || Str::startsWith($category->icon, 'fad '))
                                                            <i class="{{ $category->icon }}" :class="String(getActiveCategoryId()) === '{{ $category->id }}' && getActiveFavoritesOnly() !== '1' && !getActiveSearch() ? 'text-blue-500' : 'text-gray-400'"></i>
                                                        @else
                                                            <span class="inline-block" :class="String(getActiveCategoryId()) === '{{ $category->id }}' && getActiveFavoritesOnly() !== '1' && !getActiveSearch() ? 'text-blue-500' : 'text-gray-400'">{{ $category->icon }}</span>
                                                        @endif
                                                    </span>
                                                @endif
                                                <span>{{ $category->name }}</span>
                                            </button>
                                        @endforeach
                                    @endif
                                </div>

                                <!-- Clear Filter Button -->
                                <x-filter-actions 
                                    clear-route="client.services.index"
                                    :closeOnClick="false"
                                    :showApply="false"
                                />
                            </x-slot>
                        </x-filter-button>

                        <!-- Search Input -->
                        <div class="relative" style="max-width: 300px;">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </div>
                            <input type="text"
                                   name="search"
                                   id="search"
                                   value="{{ request('search', '') }}"
                                   placeholder="{{ __('Search by service name') }}"
                                   @input.debounce.500ms="handleSearchInput($event.target.value)"
                                   class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                        </div>
                    </div>

                    <!-- Active Filters Display -->
                    <div x-show="hasActiveFilters()" x-cloak class="flex flex-wrap items-center gap-2 mb-4" style="display: none;">
                        <span class="text-sm text-gray-600">{{ __('Active filters:') }}</span>
                        <div class="flex flex-wrap gap-2">
                            <template x-if="getActiveCategoryId()">
                                <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-medium bg-gray-200 text-gray-800">
                                    <span x-text="'{{ __('Category') }}: ' + getCategoryName(getActiveCategoryId())"></span>
                                    <button type="button" @click="clearFilter('category_id')" class="hover:text-gray-900">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </button>
                                </span>
                            </template>
                            <template x-if="getActiveFavoritesOnly() === '1'">
                                <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-medium bg-gray-200 text-gray-800">
                                    {{ __('Favorite services') }}
                                    <button type="button" @click="clearFilter('favorites_only')" class="hover:text-gray-900">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </button>
                                </span>
                            </template>
                            <template x-if="getActiveSearch()">
                                <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-medium bg-gray-200 text-gray-800">
                                    <span x-text="'{{ __('Search') }}: ' + getActiveSearch()"></span>
                                    <button type="button" @click="clearFilter('search')" class="hover:text-gray-900">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </button>
                                </span>
                            </template>
                        </div>
                    </div>
                </form>
            </div>

            <div id="services-list-container">
                @include('client.services.partials.services-list', [
                    'categories' => $categories,
                    'favoriteServiceIds' => $favoriteServiceIds ?? []
                ])
            </div>
        </div>
    </div>

    <script>
        function serviceManagement(filters = {}) {
            return {
                currentSort: filters?.sort || 'id',
                currentDir: filters?.dir || 'asc',
                favoriteServices: @js($favoriteServiceIds ?? []),
                categoriesList: @js(isset($categoriesList) ? $categoriesList->map(fn($cat) => ['id' => $cat->id, 'name' => $cat->name])->values()->all() : []),
                activeFiltersCount: {{ $initialFilterCount }},
                activeCategoryId: '{{ $currentCategoryId }}',
                activeFavoritesOnly: '{{ $currentFavoritesOnly === '1' ? '1' : '0' }}',
                activeSearch: '{{ $currentSearch }}',

                // Helper functions for active filters display
                getFormData() {
                    const form = document.getElementById('filter-form');
                    if (!form) return null;
                    return new FormData(form);
                },
                getActiveCategoryId() {
                    return this.activeCategoryId || '';
                },
                getActiveFavoritesOnly() {
                    return this.activeFavoritesOnly || '0';
                },
                getActiveSearch() {
                    return this.activeSearch || '';
                },
                updateActiveFilters() {
                    const form = document.getElementById('filter-form');
                    if (form) {
                        const categoryInput = form.querySelector('input[name="category_id"]');
                        const favoritesInput = form.querySelector('input[name="favorites_only"]');
                        const searchInput = form.querySelector('input[name="search"]');
                        
                        this.activeCategoryId = categoryInput ? categoryInput.value || '' : '';
                        this.activeFavoritesOnly = favoritesInput ? favoritesInput.value || '0' : '0';
                        this.activeSearch = searchInput ? searchInput.value || '' : '';
                    } else {
                        // Fallback to URL params
                        const urlParams = new URLSearchParams(window.location.search);
                        this.activeCategoryId = urlParams.get('category_id') || '';
                        this.activeFavoritesOnly = urlParams.get('favorites_only') || '0';
                        this.activeSearch = urlParams.get('search') || '';
                    }
                },
                hasActiveFilters() {
                    const categoryId = this.getActiveCategoryId();
                    const favoritesOnly = this.getActiveFavoritesOnly();
                    const search = this.getActiveSearch();
                    return !!(categoryId || favoritesOnly === '1' || search);
                },
                getCategoryName(categoryId) {
                    if (!categoryId || !this.categoriesList) return 'N/A';
                    const category = this.categoriesList.find(cat => String(cat.id) === String(categoryId));
                    return category ? category.name : 'N/A';
                },
                updateFilterCount() {
                    // Update active filter values first
                    this.updateActiveFilters();
                    
                    let count = 0;
                    if (this.activeCategoryId) count++;
                    if (this.activeFavoritesOnly === '1') count++;
                    if (this.activeSearch) count++;
                    
                    this.activeFiltersCount = count;
                },
                handleSearchInput(value) {
                    const form = document.getElementById('filter-form');
                    if (form) {
                        const searchInput = form.querySelector('input[name="search"]');
                        if (searchInput) searchInput.value = value || '';
                    }
                    this.activeSearch = value || '';
                    this.updateFilterCount();
                    this.performAjaxSearch();
                },
                selectFilter(categoryId, favoritesOnly) {
                    const form = document.getElementById('filter-form');
                    if (!form) return;

                    const getOrCreateInput = (name) => {
                        let input = form.querySelector(`input[name="${name}"]`);
                        if (!input) {
                            input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = name;
                            form.appendChild(input);
                        }
                        return input;
                    };

                    // Update category_id
                    const categoryInput = getOrCreateInput('category_id');
                    categoryInput.value = categoryId || '';
                    this.activeCategoryId = categoryId || '';

                    // Update favorites_only
                    const favoritesInput = getOrCreateInput('favorites_only');
                    favoritesInput.value = favoritesOnly ? '1' : '0';
                    this.activeFavoritesOnly = favoritesOnly ? '1' : '0';

                    // Update URL
                    this.updateURL();

                    // Update filter count
                    this.updateFilterCount();

                    // Perform search
                    this.performAjaxSearch();
                },
                clearFilter(filterName) {
                    const form = document.getElementById('filter-form');
                    if (!form) return;

                    if (filterName === 'category_id') {
                        const input = form.querySelector('input[name="category_id"]');
                        if (input) input.value = '';
                        this.activeCategoryId = '';
                    } else if (filterName === 'favorites_only') {
                        const input = form.querySelector('input[name="favorites_only"]');
                        if (input) input.value = '0';
                        this.activeFavoritesOnly = '0';
                    } else if (filterName === 'search') {
                        const input = form.querySelector('input[name="search"]');
                        if (input) input.value = '';
                        this.activeSearch = '';
                    }

                    // Update URL
                    this.updateURL();

                    // Update filter count
                    this.updateFilterCount();

                    // Perform search
                    this.performAjaxSearch();
                },
                updateURL() {
                    const formData = this.getFormData();
                    if (!formData) return;

                    const params = new URLSearchParams();
                    const search = formData.get('search');
                    const categoryId = formData.get('category_id');
                    const favoritesOnly = formData.get('favorites_only');
                    const sort = formData.get('sort') || this.currentSort || 'id';
                    const dir = formData.get('dir') || this.currentDir || 'asc';

                    if (search) params.set('search', search);
                    if (categoryId) params.set('category_id', categoryId);
                    if (favoritesOnly === '1') params.set('favorites_only', '1');
                    if (sort && sort !== 'id') params.set('sort', sort);
                    if (dir && dir !== 'asc') params.set('dir', dir);

                    const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
                    window.history.pushState({}, '', newUrl);
                },
                sortTable(column) {
                    if (this.currentSort === column) {
                        this.currentDir = this.currentDir === 'asc' ? 'desc' : 'asc';
                    } else {
                        this.currentSort = column;
                        this.currentDir = 'asc';
                    }

                    // Update hidden inputs
                    const form = document.getElementById('filter-form');
                    if (form) {
                        const getOrCreateInput = (name) => {
                            let input = form.querySelector(`input[name="${name}"]`);
                            if (!input) {
                                input = document.createElement('input');
                                input.type = 'hidden';
                                input.name = name;
                                form.appendChild(input);
                            }
                            return input;
                        };
                        getOrCreateInput('sort').value = this.currentSort;
                        getOrCreateInput('dir').value = this.currentDir;
                    }
                    this.performAjaxSearch();
                },
                initScrollSync() {
                    // Simplified scroll sync for client view
                },
                getCsrfToken() {
                    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                },
                handleFormSubmit(event) {
                    event.preventDefault();
                    this.performAjaxSearch();
                    // Close dropdown after form submission
                    this.$dispatch('close-filter-dropdown');
                },
                performAjaxSearch() {
                    const formData = this.getFormData();
                    if (!formData) return;

                    const searchValue = formData.get('search') || '';
                    const searchBy = 'service_name'; // Only search by service name
                    const categoryId = formData.get('category_id') || '';
                    const favoritesOnly = formData.get('favorites_only') || '0';
                    const sort = formData.get('sort') || this.currentSort || 'id';
                    const dir = formData.get('dir') || this.currentDir || 'asc';

                    // Show loading state
                    const container = document.getElementById('services-list-container');
                    if (container) {
                        container.innerHTML = '<div class="text-center p-8"><div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div><p class="mt-2 text-gray-500">Searching...</p></div>';
                    }

                    // Make AJAX request - use relative URL to avoid mixed content issues
                    fetch('{{ parse_url(route("client.services.search"), PHP_URL_PATH) }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.getCsrfToken()
                        },
                        body: JSON.stringify({
                            search: searchValue,
                            search_by: searchBy,
                            category_id: categoryId,
                            favorites_only: favoritesOnly,
                            sort: sort,
                            dir: dir
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (container) {
                            container.innerHTML = data.html;
                            // Update favoriteServices from the response
                            // Extract favorite service IDs from the rendered HTML or update from server
                            // Re-initialize Alpine.js for the new content
                            if (window.Alpine) {
                                Alpine.initTree(container);
                            }
                            // Update active filters and count after AJAX search
                            if (typeof this.updateActiveFilters === 'function') {
                                this.updateActiveFilters();
                            }
                            if (typeof this.updateFilterCount === 'function') {
                                this.updateFilterCount();
                            }
                            // Re-initialize scroll sync after AJAX update
                            this.$nextTick(() => {
                                if (typeof this.initScrollSync === 'function') {
                                    this.initScrollSync();
                                }
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Search error:', error);
                        if (container) {
                            container.innerHTML = '<div class="text-center p-8 text-red-500">Error loading results. Please refresh the page.</div>';
                        }
                    });
                },
                toggleFavorite(serviceId) {
                    const button = event.target.closest('button');
                    const originalState = this.favoriteServices.includes(serviceId);

                    // Optimistic update
                    if (originalState) {
                        this.favoriteServices = this.favoriteServices.filter(id => id !== serviceId);
                    } else {
                        if (!this.favoriteServices.includes(serviceId)) {
                            this.favoriteServices.push(serviceId);
                        }
                    }

                    fetch(`{{ route('client.services.favorite.toggle', ':id') }}`.replace(':id', serviceId), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.getCsrfToken()
                        }
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.ok) {
                            if (data.favorited) {
                                if (!this.favoriteServices.includes(serviceId)) {
                                    this.favoriteServices.push(serviceId);
                                }
                            } else {
                                this.favoriteServices = this.favoriteServices.filter(id => id !== serviceId);
                            }
                        } else {
                            // Revert optimistic update
                            if (originalState) {
                                if (!this.favoriteServices.includes(serviceId)) {
                                    this.favoriteServices.push(serviceId);
                                }
                            } else {
                                this.favoriteServices = this.favoriteServices.filter(id => id !== serviceId);
                            }
                            alert(data.error || 'An error occurred');
                        }
                    })
                    .catch(error => {
                        // Revert optimistic update
                        if (originalState) {
                            if (!this.favoriteServices.includes(serviceId)) {
                                this.favoriteServices.push(serviceId);
                            }
                        } else {
                            this.favoriteServices = this.favoriteServices.filter(id => id !== serviceId);
                        }
                        console.error('Error toggling favorite:', error);
                        alert('Failed to update favorite. Please try again.');
                    });
                },
                openServiceModal(serviceId) {
                    window.dispatchEvent(new CustomEvent('open-modal', { detail: 'service-view-' + serviceId }));
                }
            };
        }
    </script>

    {{-- Service View Modal --}}
    @foreach($categories as $category)
        @foreach($category->services->where('is_active', true) as $service)
            <x-modal name="service-view-{{ $service->id }}" maxWidth="2xl">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-gray-900">{{ $service->name }}</h3>
                        <button type="button" @click="$dispatch('close-modal', 'service-view-{{ $service->id }}')" class="text-gray-400 hover:text-gray-500">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <div class="space-y-6">
                        {{-- Service Details --}}
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Service ID') }}</label>
                                <p class="text-sm text-gray-900">{{ $service->id }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Rate per 1000') }}</label>
                                @php
                                    $defaultRate = $service->default_rate ?? $service->rate_per_1000 ?? 0;
                                    $customRate = $service->client_price ?? $defaultRate;
                                    $hasCustomRate = $service->has_custom_rate ?? false;
                                @endphp
                                @if($hasCustomRate && $customRate != 0 && $defaultRate != $customRate)
                                    <div class="flex flex-col">
                                        <span class="text-gray-500 line-through text-xs">${{ number_format($defaultRate, 2) }}</span>
                                        <span class="text-indigo-600 font-semibold text-sm">${{ number_format($customRate, 2) }}</span>
                                    </div>
                                @else
                                    <p class="text-sm text-gray-900 font-medium">${{ number_format($customRate, 2) }}</p>
                                @endif
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Min Quantity') }}</label>
                                <p class="text-sm text-gray-900">{{ number_format($service->min_quantity ?? 1) }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Max Quantity') }}</label>
                                <p class="text-sm text-gray-900">{{ number_format($service->max_quantity ?? 1) }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Average Time') }}</label>
                                <p class="text-sm text-gray-900">{{ __('2 hours 30 minutes') }}</p>
                            </div>
                        </div>

                        @if($service->description)
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Description') }}</label>
                                <p class="text-sm text-gray-600">{{ $service->description }}</p>
                            </div>
                        @endif

                        {{-- Create Order Button --}}
                        <div class="border-t border-gray-200 pt-6">
                            <div class="flex items-center justify-end gap-3">
                                <button type="button"
                                        @click="$dispatch('close-modal', 'service-view-{{ $service->id }}')"
                                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    {{ __('Close') }}
                                </button>
                                <a href="{{ route('client.orders.create', ['category_id' => $service->category_id, 'service_id' => $service->id]) }}"
                                   class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                    </svg>
                                    {{ __('Create Order') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </x-modal>
        @endforeach
    @endforeach
</x-client-layout>

