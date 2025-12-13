<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Users Management') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <!-- Search and Filters -->
                    <div class="mb-6">
                        <form method="GET" action="{{ route('admin.users.index') }}" id="filter-form">
                            <!-- Filter Card -->
                            <div class="bg-gradient-to-r from-indigo-50 to-purple-50 rounded-lg p-4 border border-indigo-100 shadow-sm">
                                <div class="flex items-center gap-2 mb-3">
                                    <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                                    </svg>
                                    <h3 class="text-xs font-semibold text-gray-700">{{ __('Filters') }}</h3>
                                </div>

                                <div class="flex flex-col md:flex-row gap-4 items-end">
                                    <!-- Left Side: Filters (Role and Verified) -->
                                    <div class="flex flex-1 gap-4">
                                        <!-- Role Filter -->
                                        <div class="flex-1">
                                            <label for="role" class="block text-xs font-medium text-gray-700 mb-1">
                                                <span class="flex items-center gap-1">
                                                    <svg class="w-3 h-3 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                                    </svg>
                                                    {{ __('Role') }}
                                                </span>
                                            </label>
                                            <select
                                                id="role"
                                                name="role"
                                                class="block w-full py-1.5 px-2 text-xs rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 bg-white transition-all duration-200"
                                            >
                                                <option value="">{{ __('All Roles') }}</option>
                                                @foreach($roles as $role)
                                                    <option value="{{ $role->name }}" {{ request('role') === $role->name ? 'selected' : '' }}>
                                                        {{ ucfirst($role->name) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <!-- Verification Filter -->
                                        <div class="flex-1">
                                            <label for="verified" class="block text-xs font-medium text-gray-700 mb-1">
                                                <span class="flex items-center gap-1">
                                                    <svg class="w-3 h-3 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                    {{ __('Verified') }}
                                                </span>
                                            </label>
                                            <select
                                                id="verified"
                                                name="verified"
                                                class="block w-full py-1.5 px-2 text-xs rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 bg-white transition-all duration-200"
                                            >
                                                <option value="">{{ __('All') }}</option>
                                                <option value="1" {{ request('verified') === '1' ? 'selected' : '' }}>{{ __('Yes') }}</option>
                                                <option value="0" {{ request('verified') === '0' ? 'selected' : '' }}>{{ __('No') }}</option>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Right Side: Search -->
                                    <div class="w-full md:w-50">
                                        <label for="q" class="block text-xs font-medium text-gray-700 mb-1">
                                            <span class="flex items-center gap-1">
                                                <svg class="w-3 h-3 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                                </svg>
                                                {{ __('Search') }}
                                            </span>
                                        </label>
                                        <div class="relative">
                                            <input
                                                type="text"
                                                id="q"
                                                name="q"
                                                value="{{ request('q') }}"
                                                placeholder="{{ __('Name or email...') }}"
                                                class="block w-full pl-8 pr-2 py-1.5 text-xs rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 transition-all duration-200 bg-white"
                                                autocomplete="off"
                                            />
                                            <div class="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none">
                                                <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                                </svg>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Active Filters & Clear Button -->
                                <div id="active-filters-container" class="mt-3 pt-3 border-t border-indigo-200 flex flex-wrap items-center gap-1.5" style="{{ !request()->hasAny(['q', 'role', 'verified']) ? 'display: none;' : '' }}">
                                        <span class="text-xs font-medium text-gray-600">{{ __('Active:') }}</span>
                                        @if(request('q'))
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                                {{ __('Search') }}: "{{ \Illuminate\Support\Str::limit(request('q'), 15) }}"
                                                <button type="button" onclick="clearFilter('q')" class="hover:text-indigo-600">
                                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                </button>
                                            </span>
                                        @endif
                                        @if(request('role'))
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                                {{ __('Role') }}: {{ ucfirst(request('role')) }}
                                                <button type="button" onclick="clearFilter('role')" class="hover:text-indigo-600">
                                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                </button>
                                            </span>
                                        @endif
                                        @if(request('verified'))
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                                {{ __('Verified') }}: {{ request('verified') === '1' ? __('Yes') : __('No') }}
                                                <button type="button" onclick="clearFilter('verified')" class="hover:text-indigo-600">
                                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                </button>
                                            </span>
                                        @endif
                                        <a
                                            href="{{ route('admin.users.index', ['sort' => request('sort'), 'dir' => request('dir')]) }}"
                                            class="ml-auto inline-flex items-center gap-1 px-2.5 py-1 rounded text-xs font-medium bg-white text-indigo-600 border border-indigo-200 hover:bg-indigo-50 transition-all duration-200"
                                        >
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                            {{ __('Clear') }}
                                        </a>
                                    </div>

                                <!-- Hidden fields to preserve sort -->
                                @if(request('sort'))
                                    <input type="hidden" name="sort" value="{{ request('sort') }}">
                                @endif
                                @if(request('dir'))
                                    <input type="hidden" name="dir" value="{{ request('dir') }}">
                                @endif
                            </div>
                        </form>
                    </div>

                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const form = document.getElementById('filter-form');
                            const searchInput = document.getElementById('q');
                            const roleSelect = document.getElementById('role');
                            const verifiedSelect = document.getElementById('verified');
                            const tableContainer = document.getElementById('users-table-container');
                            const loadingIndicator = document.getElementById('loading-indicator');

                            if (!form || !searchInput || !roleSelect || !verifiedSelect || !tableContainer || !loadingIndicator) {
                                console.error('Required elements not found');
                                return;
                            }

                            // Prevent form submission
                            form.addEventListener('submit', function(e) {
                                e.preventDefault();
                                loadUsers();
                            });

                            let searchTimeout;

                            // Get current sort from URL or default
                            function getCurrentSort() {
                                const params = new URLSearchParams(window.location.search);
                                return params.get('sort') || 'created_at';
                            }

                            // Get current direction from URL or default
                            function getCurrentDir() {
                                const params = new URLSearchParams(window.location.search);
                                return params.get('dir') || 'desc';
                            }

                            let currentSort = getCurrentSort();
                            let currentDir = getCurrentDir();

                            // Function to update sort indicators
                            function updateSortIndicators() {
                                document.querySelectorAll('.sort-indicator').forEach(indicator => {
                                    const sortField = indicator.getAttribute('data-sort');
                                    indicator.innerHTML = '';

                                    if (currentSort === sortField) {
                                        if (currentDir === 'asc') {
                                            indicator.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path></svg>';
                                        } else {
                                            indicator.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>';
                                        }
                                    }
                                });
                            }

                            // Function to load users via AJAX
                            function loadUsers(url = null) {
                                const formData = new FormData(form);
                                const params = new URLSearchParams();

                                // Add form data
                                for (const [key, value] of formData.entries()) {
                                    if (value) {
                                        params.append(key, value);
                                    }
                                }

                                // Add sort if exists
                                if (currentSort) {
                                    params.set('sort', currentSort);
                                }
                                if (currentDir) {
                                    params.set('dir', currentDir);
                                }

                                const requestUrl = url || '{{ route('admin.users.index') }}?' + params.toString();

                                // Show loading
                                loadingIndicator.classList.remove('hidden');
                                tableContainer.style.opacity = '0.5';

                                fetch(requestUrl, {
                                    method: 'GET',
                                    headers: {
                                        'X-Requested-With': 'XMLHttpRequest',
                                        'Accept': 'text/html',
                                    }
                                })
                                .then(response => {
                                    if (!response.ok) {
                                        throw new Error('Network response was not ok');
                                    }
                                    return response.text();
                                })
                                .then(html => {
                                    // For AJAX requests, the controller returns just the table partial
                                    // So we can directly use the HTML
                                    tableContainer.innerHTML = html;

                                    // Update URL without refresh first
                                    const cleanUrl = requestUrl.split('?')[0] + (params.toString() ? '?' + params.toString() : '');
                                    window.history.pushState({}, '', cleanUrl);

                                    // Update current sort/dir from URL after updating URL
                                    currentSort = getCurrentSort();
                                    currentDir = getCurrentDir();
                                    
                                    // Re-initialize Alpine.js for dropdowns
                                    if (window.Alpine) {
                                        // Use requestAnimationFrame to ensure DOM is fully updated
                                        requestAnimationFrame(() => {
                                            // Find all Alpine components in the new content
                                            const alpineElements = tableContainer.querySelectorAll('[x-data]');
                                            alpineElements.forEach(el => {
                                                // Clean up any existing Alpine instances
                                                if (el._x_dataStack) {
                                                    try {
                                                        window.Alpine.destroyTree(el);
                                                    } catch (e) {
                                                        // Ignore errors if element is already destroyed
                                                    }
                                                }
                                            });
                                            
                                            // Initialize Alpine on the new content
                                            window.Alpine.initTree(tableContainer);
                                        });
                                    }
                                    
                                    // Update sort indicators first
                                    updateSortIndicators();
                                    
                                    // Update pagination links to use AJAX
                                    updatePaginationLinks();
                                    
                                    // Update sort links
                                    updateSortLinks();
                                    
                                    // Update active filters display
                                    updateActiveFiltersDisplay();
                                })
                                .catch(error => {
                                    console.error('Error loading users:', error);
                                    loadingIndicator.classList.add('hidden');
                                    tableContainer.style.opacity = '1';
                                    alert('{{ __('An error occurred while loading users. Please refresh the page.') }}');
                                })
                                .finally(() => {
                                    loadingIndicator.classList.add('hidden');
                                    tableContainer.style.opacity = '1';
                                });
                            }

                            // Update active filters display
                            function updateActiveFiltersDisplay() {
                                const params = new URLSearchParams(window.location.search);
                                const q = params.get('q');
                                const role = params.get('role');
                                const verified = params.get('verified');

                                // Update filter inputs
                                if (document.getElementById('q')) {
                                    document.getElementById('q').value = q || '';
                                }
                                if (document.getElementById('role')) {
                                    document.getElementById('role').value = role || '';
                                }
                                if (document.getElementById('verified')) {
                                    document.getElementById('verified').value = verified || '';
                                }

                                // Update active filters badges
                                const activeFiltersContainer = document.getElementById('active-filters-container');
                                if (activeFiltersContainer) {
                                    if (q || role || verified) {
                                        activeFiltersContainer.style.display = 'flex';
                                        let filtersHtml = '<span class="text-xs font-medium text-gray-600">{{ __('Active:') }}</span>';

                                        if (q) {
                                            const qDisplay = q.length > 15 ? q.substring(0, 15) + '...' : q;
                                            filtersHtml += `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                                {{ __('Search') }}: "${qDisplay}"
                                                <button type="button" onclick="clearFilter('q')" class="hover:text-indigo-600">
                                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                </button>
                                            </span>`;
                                        }

                                        if (role) {
                                            filtersHtml += `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                                {{ __('Role') }}: ${role.charAt(0).toUpperCase() + role.slice(1)}
                                                <button type="button" onclick="clearFilter('role')" class="hover:text-indigo-600">
                                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                </button>
                                            </span>`;
                                        }

                                        if (verified) {
                                            filtersHtml += `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                                {{ __('Verified') }}: ${verified === '1' ? '{{ __('Yes') }}' : '{{ __('No') }}'}
                                                <button type="button" onclick="clearFilter('verified')" class="hover:text-indigo-600">
                                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                </button>
                                            </span>`;
                                        }

                                        filtersHtml += `<a href="{{ route('admin.users.index', ['sort' => request('sort'), 'dir' => request('dir')]) }}" class="ml-auto inline-flex items-center gap-1 px-2.5 py-1 rounded text-xs font-medium bg-white text-indigo-600 border border-indigo-200 hover:bg-indigo-50 transition-all duration-200">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                            {{ __('Clear') }}
                                        </a>`;

                                        activeFiltersContainer.innerHTML = filtersHtml;
                                    } else {
                                        activeFiltersContainer.style.display = 'none';
                                    }
                                }
                            }

                            // Update pagination links to use AJAX
                            function updatePaginationLinks() {
                                // Find all pagination links (Laravel pagination structure)
                                const paginationLinks = tableContainer.querySelectorAll('.pagination-links a, nav[role="navigation"] a, .pagination a');
                                paginationLinks.forEach(link => {
                                    // Remove old listeners by cloning
                                    const newLink = link.cloneNode(true);
                                    link.parentNode.replaceChild(newLink, link);

                                    // Add event listener to new link
                                    newLink.addEventListener('click', function(e) {
                                        e.preventDefault();
                                        if (this.href) {
                                            loadUsers(this.href);
                                        }
                                    });
                                });
                            }

                            // Update sort links
                            function updateSortLinks() {
                                // Remove old event listeners by cloning and replacing
                                document.querySelectorAll('.sort-link').forEach(link => {
                                    const newLink = link.cloneNode(true);
                                    link.parentNode.replaceChild(newLink, link);

                                    newLink.addEventListener('click', function(e) {
                                        e.preventDefault();
                                        const sortField = this.getAttribute('data-sort');

                                        if (currentSort === sortField) {
                                            currentDir = currentDir === 'asc' ? 'desc' : 'asc';
                                        } else {
                                            currentSort = sortField;
                                            currentDir = 'asc';
                                        }

                                        loadUsers();
                                    });
                                });
                            }


                            // Auto-submit on search input (with debounce)
                            searchInput.addEventListener('input', function() {
                                clearTimeout(searchTimeout);
                                searchTimeout = setTimeout(function() {
                                    loadUsers();
                                }, 500); // Wait 500ms after user stops typing
                            });

                            // Auto-submit on select change
                            roleSelect.addEventListener('change', function() {
                                loadUsers();
                            });

                            verifiedSelect.addEventListener('change', function() {
                                loadUsers();
                            });

                            // Clear individual filter - make it globally accessible
                            window.clearFilter = function(filterName) {
                                const input = document.getElementById(filterName);
                                if (input) {
                                    if (input.type === 'text') {
                                        input.value = '';
                                    } else if (input.tagName === 'SELECT') {
                                        input.value = '';
                                    }
                                    loadUsers();
                                }
                            };

                            // Initialize on page load
                            updateSortIndicators();
                            updatePaginationLinks();
                            updateSortLinks();

                            // Handle browser back/forward
                            window.addEventListener('popstate', function() {
                                currentSort = getCurrentSort();
                                currentDir = getCurrentDir();
                                loadUsers(window.location.href);
                            });
                        });
                    </script>

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

                    <!-- Loading Indicator -->
                    <div id="loading-indicator" class="hidden mb-4 text-center py-8">
                        <div class="inline-flex items-center gap-2 text-indigo-600">
                            <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span class="text-sm font-medium">{{ __('Loading...') }}</span>
                        </div>
                    </div>

                    <!-- Users Table Container (will be updated via AJAX) -->
                    <div id="users-table-container">
                        @include('admin.users.partials.table', ['users' => $users])
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
