<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Clients Management') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto sm:px-6 lg:px-8" style="max-width: 90rem;">
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 w-full">
                    <!-- Search Bar and Filter Button -->
                    <div class="mb-6 relative z-10" style="isolation: isolate;">
                        <form method="GET" action="{{ route('staff.clients.index') }}" id="filter-form">
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
                                        @php
                                            $activeFiltersCount = 0;
                                            if ((request('staff_id') || request('no_staff')) && auth()->guard('staff')->check() && auth()->guard('staff')->user()->hasRole('super_admin')) $activeFiltersCount++;
                                            if (request('balance_min')) $activeFiltersCount++;
                                            if (request('balance_max')) $activeFiltersCount++;
                                            if (request('spent_min')) $activeFiltersCount++;
                                            if (request('spent_max')) $activeFiltersCount++;
                                            if (request('date_filter')) $activeFiltersCount++;
                                            if (request('created_at_filter')) $activeFiltersCount++;
                                        @endphp
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
                                            <!-- Staff Member Filter (only visible to super_admin) -->
                                            @if(auth()->guard('staff')->check() && auth()->guard('staff')->user()->hasRole('super_admin'))
                                                <x-custom-select
                                                    label="{{ __('Staff Member') }}"
                                                    name="staff_id"
                                                    id="staff_id"
                                                    :value="request('staff_id', '')"
                                                    :options="['' => __('All Staff'), 'null' => __('No Staff Assigned')]
                                                            + collect($staffMembers)->pluck('name', 'id')->toArray()"
                                                />
                                            @endif

                                            <!-- Balance Range -->
                                            <div>
                                                <x-number-range-filter
                                                    min-label="{{ __('Balance Min') }}"
                                                    max-label="{{ __('Balance Max') }}"
                                                    min-name="balance_min"
                                                    max-name="balance_max"
                                                    min-id="balance_min"
                                                    max-id="balance_max"
                                                />
                                            </div>

                                            <!-- Spent Range -->
                                            <div>
                                                <x-number-range-filter
                                                    min-label="{{ __('Spent Min') }}"
                                                    max-label="{{ __('Spent Max') }}"
                                                    min-name="spent_min"
                                                    max-name="spent_max"
                                                    min-id="spent_min"
                                                    max-id="spent_max"
                                                />
                                            </div>

                                            <!-- Date Filter -->
                                            <x-custom-date-filter
                                                label="{{ __('Last Auth Date') }}"
                                                name="date_filter"
                                                id="date_filter"
                                                custom-range-id="custom-date-range"
                                                from-name="date_from"
                                                to-name="date_to"
                                                from-id="date_from"
                                                to-id="date_to"
                                            />

                                            <!-- Created At Date Filter -->
                                            <x-custom-date-filter
                                                label="{{ __('Created At Date') }}"
                                                name="created_at_filter"
                                                id="created_at_filter"
                                                custom-range-id="custom-created-at-range"
                                                from-name="created_at_from"
                                                to-name="created_at_to"
                                                from-id="created_at_from"
                                                to-id="created_at_to"
                                            />

                                            <!-- Hidden fields to preserve sort and search -->
                                            @if(request('sort'))
                                                <input type="hidden" name="sort" value="{{ request('sort') }}">
                                            @endif
                                            @if(request('dir'))
                                                <input type="hidden" name="dir" value="{{ request('dir') }}">
                                            @endif

                                            <!-- Apply Button -->
                                            <x-filter-actions clear-route="staff.clients.index" />
                                    </x-filter-dropdown>
                                </div>

                                <!-- Search Input (after filter button) -->
                                <x-search-input />
                            </div>

                            <!-- Active Filters Display -->
                            @php
                                $hasActiveFilters = request()->hasAny(['q', 'staff_id', 'balance_min', 'balance_max', 'spent_min', 'spent_max', 'date_filter', 'created_at_filter']);
                            @endphp
                            @if($hasActiveFilters)
                                <div class="flex flex-wrap items-center gap-2 mb-4">
                                    <span class="text-sm text-gray-600">{{ $clients->total() }} {{ __('results found') }}</span>
                                    <div class="flex flex-wrap gap-2">
                                        @if(request('staff_id') && auth()->guard('staff')->check() && auth()->guard('staff')->user()->hasRole('super_admin'))
                                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-medium bg-gray-200 text-gray-800">
                                                {{ __('Staff') }}:
                                                @if(request('staff_id') === 'null')
                                                    {{ __('No Staff Assigned') }}
                                                @else
                                                    @php
                                                        $selectedStaffId = (int)request('staff_id');
                                                        $selectedStaff = $staffMembers->firstWhere('id', $selectedStaffId);
                                                    @endphp
                                                    {{ $selectedStaff->name ?? 'N/A' }}
                                                @endif
                                                <button type="button" onclick="clearFilter('staff_id')" class="hover:text-gray-900">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                </button>
                                            </span>
                                        @endif
                                        @if(request('balance_min'))
                                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-medium bg-gray-200 text-gray-800">
                                                {{ __('Balance Min') }}: ${{ number_format(request('balance_min'), 2) }}
                                                <button type="button" onclick="clearFilter('balance_min')" class="hover:text-gray-900">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                </button>
                                            </span>
                                        @endif
                                        @if(request('balance_max'))
                                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-medium bg-gray-200 text-gray-800">
                                                {{ __('Balance Max') }}: ${{ number_format(request('balance_max'), 2) }}
                                                <button type="button" onclick="clearFilter('balance_max')" class="hover:text-gray-900">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                </button>
                                            </span>
                                        @endif
                                        @if(request('spent_min'))
                                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-medium bg-gray-200 text-gray-800">
                                                {{ __('Spent Min') }}: ${{ number_format(request('spent_min'), 2) }}
                                                <button type="button" onclick="clearFilter('spent_min')" class="hover:text-gray-900">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                </button>
                                            </span>
                                        @endif
                                        @if(request('spent_max'))
                                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-medium bg-gray-200 text-gray-800">
                                                {{ __('Spent Max') }}: ${{ number_format(request('spent_max'), 2) }}
                                                <button type="button" onclick="clearFilter('spent_max')" class="hover:text-gray-900">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                </button>
                                            </span>
                                        @endif
                                        @if(request('date_filter'))
                                            @php
                                                $dateFilterLabels = [
                                                    'today' => __('Today'),
                                                    'yesterday' => __('Previous Day'),
                                                    '7days' => __('Last 7 Days'),
                                                    '30days' => __('Last 30 Days'),
                                                    '90days' => __('Last 90 Days'),
                                                    'custom' => __('Custom Range'),
                                                ];
                                            @endphp
                                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-medium bg-gray-200 text-gray-800">
                                                {{ __('Date') }}: {{ $dateFilterLabels[request('date_filter')] ?? request('date_filter') }}
                                                @if(request('date_filter') === 'custom' && request('date_from') && request('date_to'))
                                                    ({{ request('date_from') }} - {{ request('date_to') }})
                                                @endif
                                                <button type="button" onclick="clearFilter('date_filter')" class="hover:text-gray-900">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                </button>
                                            </span>
                                        @endif
                                        @if(request('created_at_filter'))
                                            @php
                                                $createdAtFilterLabels = [
                                                    'today' => __('Today'),
                                                    'yesterday' => __('Previous Day'),
                                                    '7days' => __('Last 7 Days'),
                                                    '30days' => __('Last 30 Days'),
                                                    '90days' => __('Last 90 Days'),
                                                    'custom' => __('Custom Range'),
                                                ];
                                            @endphp
                                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm font-medium bg-gray-200 text-gray-800">
                                                {{ __('Created At') }}: {{ $createdAtFilterLabels[request('created_at_filter')] ?? request('created_at_filter') }}
                                                @if(request('created_at_filter') === 'custom' && request('created_at_from') && request('created_at_to'))
                                                    ({{ request('created_at_from') }} - {{ request('created_at_to') }})
                                                @endif
                                                <button type="button" onclick="clearFilter('created_at_filter')" class="hover:text-gray-900">
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
                                    <span class="text-sm text-gray-600">{{ $clients->total() }} {{ __('results found') }}</span>
                                </div>
                            @endif
                        </form>
                    </div>

                    <script>
                        // Select All Checkbox Functionality
                        document.addEventListener('DOMContentLoaded', function() {
                            const selectAllCheckbox = document.getElementById('select-all');
                            const clientCheckboxes = document.querySelectorAll('.client-checkbox');

                            if (selectAllCheckbox) {
                                selectAllCheckbox.addEventListener('change', function() {
                                    clientCheckboxes.forEach(checkbox => {
                                        checkbox.checked = this.checked;
                                    });
                                });

                                // Update select all checkbox when individual checkboxes change
                                clientCheckboxes.forEach(checkbox => {
                                    checkbox.addEventListener('change', function() {
                                        const allChecked = Array.from(clientCheckboxes).every(cb => cb.checked);
                                        const someChecked = Array.from(clientCheckboxes).some(cb => cb.checked);
                                        selectAllCheckbox.checked = allChecked;
                                        selectAllCheckbox.indeterminate = someChecked && !allChecked;
                                    });
                                });
                            }
                        });
                    </script>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const form = document.getElementById('filter-form');
                            const searchInput = document.getElementById('q');
                            const tableContainer = document.getElementById('clients-table');

                            if (!form || !tableContainer) {
                                return;
                            }

                            let searchTimeout = null;
                            let abortController = null;
                            const baseUrl = form.getAttribute('action') || form.action || '{{ route('staff.clients.index') }}';

                            // AJAX search function
                            function performAjaxSearch() {
                                // Cancel previous request
                                if (abortController) {
                                    abortController.abort();
                                }
                                abortController = new AbortController();

                                // Build URL from form data
                                const params = new URLSearchParams();
                                const formData = new FormData(form);

                                // Iterate through form data and add to params
                                // This ensures field names are preserved correctly
                                for (const [key, value] of formData.entries()) {
                                    // Skip empty values to keep URL clean
                                    if (value !== null && value !== undefined && String(value).trim() !== '') {
                                        params.append(key, value);
                                    }
                                }

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

                            // Auto-search on typing with debounce (AJAX only)
                            if (searchInput) {
                                searchInput.addEventListener('input', function() {
                                    clearTimeout(searchTimeout);
                                    searchTimeout = setTimeout(function() {
                                        performAjaxSearch();
                                    }, 300); // 300ms delay after user stops typing
                                });

                                // Enter => submit immediately via AJAX
                                searchInput.addEventListener('keydown', function(e) {
                                    if (e.key === 'Enter') {
                                        e.preventDefault();
                                        clearTimeout(searchTimeout);
                                        performAjaxSearch();
                                    }
                                });
                            }

                            // Handle date filter custom range visibility (using component)
                            document.querySelectorAll('.date-filter-select').forEach(function(select) {
                                function toggleCustomRange() {
                                    const customRangeId = select.getAttribute('data-custom-range-id');
                                    const customRange = document.getElementById(customRangeId);
                                    if (customRange) {
                                        if (select.value === 'custom') {
                                            customRange.classList.remove('hidden');
                                        } else {
                                            customRange.classList.add('hidden');
                                        }
                                    }
                                }

                                // Initial state
                                toggleCustomRange();

                                // Listen for changes
                                select.addEventListener('change', toggleCustomRange);
                            });

                            // Only handle clearFilter - form submission is now handled by regular form submit
                            window.clearFilter = function(filterName) {
                                // Find all inputs with this name (including hidden inputs from custom-select)
                                const inputs = form.querySelectorAll(`[name="${filterName}"]`);
                                inputs.forEach(function(input) {
                                    if (input.type === 'hidden' || input.type === 'text' || input.type === 'number' || input.type === 'date') {
                                        input.value = '';
                                    } else if (input.tagName === 'SELECT') {
                                        input.value = '';
                                    }
                                });

                                // Also check for related date filter inputs (date_from, date_to, etc.)
                                if (filterName === 'date_filter') {
                                    const dateFrom = form.querySelector('[name="date_from"]');
                                    const dateTo = form.querySelector('[name="date_to"]');
                                    if (dateFrom) dateFrom.value = '';
                                    if (dateTo) dateTo.value = '';
                                } else if (filterName === 'created_at_filter') {
                                    const createdAtFrom = form.querySelector('[name="created_at_from"]');
                                    const createdAtTo = form.querySelector('[name="created_at_to"]');
                                    if (createdAtFrom) createdAtFrom.value = '';
                                    if (createdAtTo) createdAtTo.value = '';
                                }

                                // Submit form after clearing filter (full page reload)
                                form.submit();
                            };
                        });
                    </script>

                    <!-- Status Messages -->
                    @if (session('status') === 'client-deleted')
                        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                            <p class="text-sm text-green-800">{{ __('Client deleted successfully.') }}</p>
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

                    <!-- Loading Indicator -->
                    <!-- Assign Staff Script (must be before table for function availability) -->
                    @if(auth()->guard('staff')->check() && auth()->guard('staff')->user()->hasRole('super_admin'))
                        <script>
                            (function() {
                                function handleAssignStaff(clientId, clientName, currentStaffId) {
                                    // Close all dropdowns first
                                    document.querySelectorAll('[x-data*="open"]').forEach(el => {
                                        try {
                                            if (typeof Alpine !== 'undefined' && Alpine.$data) {
                                                const data = Alpine.$data(el);
                                                if (data && typeof data.open !== 'undefined') {
                                                    data.open = false;
                                                }
                                            }
                                        } catch (e) {
                                            // Ignore errors
                                        }
                                    });

                                    // Wait a bit for dropdown to close, then open modal
                                    setTimeout(() => {
                                        openAssignStaffModal(clientId, clientName, currentStaffId);
                                    }, 150);
                                }

                                function openAssignStaffModal(clientId, clientName, currentStaffId = null) {
                                    const modal = document.getElementById('assignStaffModal');
                                    if (!modal) {
                                        console.error('Assign staff modal not found');
                                        return;
                                    }

                                    // Wait for Alpine to be ready if needed
                                    const tryOpen = () => {
                                        // Get Alpine data
                                        let alpineData = null;
                                        try {
                                            if (typeof Alpine !== 'undefined' && Alpine.$data) {
                                                alpineData = Alpine.$data(modal);
                                            }
                                        } catch (e) {
                                            console.error('Error getting Alpine data:', e);
                                        }

                                        if (!alpineData) {
                                            // Retry after a short delay if Alpine isn't ready
                                            if (typeof Alpine === 'undefined') {
                                                setTimeout(tryOpen, 50);
                                                return;
                                            }
                                            console.error('Alpine component not found for modal');
                                            return;
                                        }

                                        // Update form action
                                        const form = document.getElementById('assignStaffForm');
                                        if (form) {
                                            const formAction = `/staff/clients/${clientId}/assign-staff`;
                                            form.setAttribute('action', formAction);

                                            // Update staff select value
                                            const staffSelect = form.querySelector('[name="staff_id"]');
                                            if (staffSelect) {
                                                if (currentStaffId) {
                                                    staffSelect.value = currentStaffId;
                                                } else {
                                                    staffSelect.value = '';
                                                }
                                            }
                                        }

                                        // Update Alpine data
                                        alpineData.clientId = clientId;
                                        alpineData.clientName = clientName || '';
                                        alpineData.currentStaffId = currentStaffId || null;
                                        alpineData.open = true;
                                    };

                                    tryOpen();
                                }

                                // Make functions globally available
                                window.handleAssignStaff = handleAssignStaff;
                                window.openAssignStaffModal = openAssignStaffModal;
                            })();
                        </script>
                    @endif

                    <!-- Clients Table -->
                    <div id="clients-table" class="relative z-0">
                        @include('staff.clients.partials.table', ['clients' => $clients])
                    </div>

                    <!-- Assign Staff Modal -->
                    @if(auth()->guard('staff')->check() && auth()->guard('staff')->user()->hasRole('super_admin'))
                        <div id="assignStaffModal" x-data="{ open: false, clientId: null, clientName: '', currentStaffId: null }" x-show="open" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
                            <div class="flex items-center justify-center min-h-screen px-4 py-4">
                                <div x-show="open"
                                     x-transition:enter="ease-out duration-300"
                                     x-transition:enter-start="opacity-0"
                                     x-transition:enter-end="opacity-100"
                                     x-transition:leave="ease-in duration-200"
                                     x-transition:leave-start="opacity-100"
                                     x-transition:leave-end="opacity-0"
                                     class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75"
                                     @click="open = false"></div>

                                <div x-show="open"
                                     x-transition:enter="ease-out duration-300"
                                     x-transition:enter-start="opacity-0 translate-y-4 scale-95"
                                     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                     x-transition:leave="ease-in duration-200"
                                     x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                     x-transition:leave-end="opacity-0 translate-y-4 scale-95"
                                     class="relative bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-full max-w-lg">
                                    <form method="POST" :action="`/staff/clients/${clientId}/assign-staff`" id="assignStaffForm">
                                        @csrf
                                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                            <div class="sm:flex sm:items-start">
                                                <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                                                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                                        <span x-text="currentStaffId ? '{{ __('Change Staff Member') }}' : '{{ __('Assign Staff Member') }}'"></span>
                                                    </h3>
                                                    <p class="text-sm text-gray-500 mb-4">
                                                        <span x-text="currentStaffId ? '{{ __('Change staff member for:') }}' : '{{ __('Assign a staff member to:') }}'"></span>
                                                        <span x-text="clientName" class="font-medium text-gray-900"></span>
                                                    </p>
                                                    <div>
                                                        <label for="assign_staff_id" class="block text-sm font-medium text-gray-700 mb-2">
                                                            {{ __('Staff Member') }}
                                                        </label>
                                                        <select
                                                            id="assign_staff_id"
                                                            name="staff_id"
                                                            required
                                                            x-model="currentStaffId"
                                                            class="block w-full py-2 px-3 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                                        >
                                                            <option value="">{{ __('Select Staff Member') }}</option>
                                                            @foreach($staffMembers as $staff)
                                                                <option value="{{ $staff->id }}">{{ $staff->name }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="bg-gray-50 px-4 py-3 sm:px-6 flex flex-row-reverse gap-3">
                                            <button
                                                type="submit"
                                                class="inline-flex justify-center items-center rounded-md border border-transparent shadow-sm px-6 py-2 bg-indigo-600 text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 min-w-[100px]">
                                                <span x-text="currentStaffId ? '{{ __('Change') }}' : '{{ __('Assign') }}'"></span>
                                            </button>
                                            <button
                                                type="button"
                                                @click="open = false"
                                                class="inline-flex justify-center items-center rounded-md border border-gray-300 shadow-sm px-6 py-2 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 min-w-[100px]">
                                                {{ __('Cancel') }}
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
