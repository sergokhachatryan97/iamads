<x-app-layout>
    @php
        $filters = request()->only(['q','role','verified','sort','dir']);

        $activeFiltersCount = collect($filters)
            ->only(['role','verified'])
            ->filter(fn($v) => $v !== null && $v !== '')
            ->count();

        $hasActiveFilters = collect($filters)
            ->only(['q','role','verified'])
            ->filter(fn($v) => $v !== null && $v !== '')
            ->isNotEmpty();
    @endphp

    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Managers Management') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 w-full">

                    <!-- Search Bar and Filter Button -->
                    <div class="mb-6 relative z-10" style="isolation: isolate;">
                        <form method="GET" action="{{ route('staff.users.index') }}" id="filter-form">
                            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 sm:gap-4 mb-4 justify-end">

                                <!-- Filter Button with Badge -->
                                <div class="relative z-50 flex-shrink-0" x-data="{ open: false }" style="isolation: isolate;">
                                    <button
                                        type="button"
                                        @click="open = !open"
                                        class="relative inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                    >
                                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                                        </svg>

                                        @if($activeFiltersCount > 0)
                                            <span class="ml-2 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white bg-indigo-600 rounded-full">
                                                {{ $activeFiltersCount }}
                                            </span>
                                        @endif
                                    </button>

                                    <!-- Backdrop overlay for mobile -->
                                    <x-filter-dropdown-backdrop />

                                    <!-- Filter Dropdown -->
                                    <x-filter-dropdown>
                                            <!-- Role Filter -->
                                            <x-custom-select
                                                label="{{ __('Role') }}"
                                                name="role"
                                                id="role"
                                                :options="collect($roles)->mapWithKeys(fn($role) => [$role->name => ucfirst($role->name)])->toArray()"
                                                :value="$filters['role'] ?? ''"
                                                placeholder="{{ __('All Roles') }}"
                                            />

                                            <!-- Verification Filter -->
                                            <x-custom-select
                                                label="{{ __('Verified') }}"
                                                name="verified"
                                                id="verified"
                                                :options="['' => __('All'), '1' => __('Yes'), '0' => __('No')]"
                                                :value="$filters['verified'] ?? ''"
                                            />

                                            <!-- Apply Button -->
                                            <x-filter-actions clear-route="staff.users.index" />
                                    </x-filter-dropdown>
                                </div>

                                <!-- Search Input -->
                                <x-search-input />
                            </div>

                            <!-- Active Filters Display -->
                            @if($hasActiveFilters)
                                <div class="flex flex-wrap items-center gap-2 mb-4">
                                    <span class="text-sm text-gray-600">{{ $users->total() }} {{ __('results found') }}</span>

                                    <div class="flex flex-wrap gap-2">
                                        @if(!empty($filters['role']))
                                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-medium bg-gray-200 text-gray-800">
                                                {{ __('Role') }}: {{ ucfirst($filters['role']) }}
                                                <button type="button" onclick="clearFilter('role')" class="hover:text-gray-900">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                </button>
                                            </span>
                                        @endif

                                        @if(($filters['verified'] ?? '') !== '')
                                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-medium bg-gray-200 text-gray-800">
                                                {{ __('Verified') }}: {{ ($filters['verified'] === '1') ? __('Yes') : __('No') }}
                                                <button type="button" onclick="clearFilter('verified')" class="hover:text-gray-900">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                </button>
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @else
                                <div class="mb-4">
                                    <span class="text-sm text-gray-600">{{ $users->total() }} {{ __('results found') }}</span>
                                </div>
                            @endif
                        </form>
                    </div>

                    <!-- Status Messages -->
                    @if (session('status') === 'user-deleted')
                        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                            <p class="text-sm text-green-800">{{ __('User deleted successfully.') }}</p>
                        </div>
                    @endif

                    @if (session('status') === 'verification-resent')
                        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                            <p class="text-sm text-green-800">{{ __('Verification email sent successfully.') }}</p>
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                            <ul class="text-sm text-red-800">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <!-- Users Table -->
                    <div id="users-table" class="relative z-0">
                        @include('staff.users.partials.table', ['users' => $users])
                    </div>
                </div>
            </div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const form = document.getElementById('filter-form');
                const searchInput = document.getElementById('q');
                const tableContainer = document.getElementById('users-table');
                if (!form || !tableContainer) return;

                let searchTimeout = null;
                let abortController = null;
                const baseUrl = form.getAttribute('action') || form.action;

                // AJAX search function
                function performAjaxSearch() {
                    // Cancel previous request
                    if (abortController) {
                        abortController.abort();
                    }
                    abortController = new AbortController();

                    // Build URL from form data
                    const params = new URLSearchParams(new FormData(form));
                    // Remove page parameter when search changes
                    params.delete('page');

                    const url = params.toString()
                        ? `${baseUrl}?${params.toString()}`
                        : baseUrl;

                    // Show loading state
                    tableContainer.style.opacity = '0.6';

                    fetch(url, {
                        method: 'GET',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'text/html',
                        },
                        signal: abortController.signal
                    })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Network response was not ok');
                            }
                            return response.text();
                        })
                        .then(html => {
                            if (html && html.trim()) {
                                tableContainer.innerHTML = html.trim();
                                tableContainer.style.opacity = '1';

                                // Update URL without reload
                                history.replaceState(null, '', url);
                            }
                        })
                        .catch(error => {
                            if (error.name !== 'AbortError') {
                                console.error('Error fetching table:', error);
                                tableContainer.style.opacity = '1';
                            }
                        })
                        .finally(() => {
                            abortController = null;
                        });
                }

                // Auto-search on typing with debounce
                if (searchInput) {
                    searchInput.addEventListener('input', () => {
                        clearTimeout(searchTimeout);
                        searchTimeout = setTimeout(() => {
                            performAjaxSearch();
                        }, 300);
                    });

                    // Enter => submit immediately
                    searchInput.addEventListener('keydown', (e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            clearTimeout(searchTimeout);
                            performAjaxSearch();
                        }
                    });
                }

                // Clear filter function - submits form normally (full page reload)
                window.clearFilter = function (filterName) {
                    // Find all inputs with this name (including hidden inputs from custom-select)
                    const inputs = form.querySelectorAll(`[name="${filterName}"]`);
                    inputs.forEach(function(input) {
                        if (input.type === 'hidden' || input.type === 'text' || input.type === 'number' || input.type === 'date') {
                            input.value = '';
                        } else if (input.tagName === 'SELECT') {
                            input.value = '';
                        }
                    });

                    // Submit form normally (full page reload for filters)
                    form.submit();
                };
            });
        </script>
    </div>
</x-app-layout>
