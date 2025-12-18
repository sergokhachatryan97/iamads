<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Services Management') }}
            </h2>
            <div class="flex gap-3">
                <a href="{{ route('staff.services.index', ['show_deleted' => request('show_deleted') == '1' ? '0' : '1']) }}"
                   class="inline-flex items-center px-4 py-2 {{ request('show_deleted') == '1' ? 'bg-yellow-600 hover:bg-yellow-700' : 'bg-gray-600 hover:bg-gray-700' }} border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    {{ request('show_deleted') == '1' ? __('Show Active') : __('Show Deleted') }}
                </a>
                <button type="button"
                        x-data
                        @click="$dispatch('open-create-category-modal')"
                        class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    {{ __('Create Category') }}
                </button>
                <a href="{{ route('staff.services.create') }}"
                   class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    {{ __('Create Service') }}
                </a>
            </div>
        </div>
    </x-slot>

        @php
            $searchByLabels = [
                'all' => __('All'),
                'service_name' => __('Service name'),
                'service_id' => __('Service ID'),
                'category_name' => __('Category name'),
            ];
        @endphp

        <div class="py-12"
             x-data="serviceManagement(@js($filters ?? []))"
             @open-create-category-modal.window="openCategoryModal = true"
             @search-changed.window="handleSearchChanged($event.detail)"
             @sort-table.window="sortTable($event.detail.column)"
             @show-delete-confirm.window="showDeleteSingleConfirm($event.detail.serviceId, $event.detail.serviceName)"
             @close-filter-dropdown.window="updateFilterCount()"
             @filter-value-changed.window="updateFilterCount()"
             @filter-applied.window="updateFilterCount()"
             x-init="
                 window.addEventListener('popstate', () => { window.location.reload(); });

                 // Store element reference to access Alpine component data
                 var element = $el;
                 var deleteHandler = function(event) {
                     if (event.detail && event.detail.serviceId && event.detail.serviceName) {
                         // Get Alpine component data from the element
                         var componentData = Alpine.$data(element);
                         if (componentData && typeof componentData.showDeleteSingleConfirm === 'function') {
                             componentData.showDeleteSingleConfirm(event.detail.serviceId, event.detail.serviceName);
                         }
                     }
                 };
                 window.addEventListener('show-delete-confirm', deleteHandler);

                 $nextTick(() => {
                     if (typeof this.getActiveFiltersCount === 'function') this.getActiveFiltersCount();
                     var form = document.getElementById('filter-form');
                     if (form) {
                         form.addEventListener('input', () => { if (typeof this.getActiveFiltersCount === 'function') this.getActiveFiltersCount(); });
                         form.addEventListener('change', () => { if (typeof this.getActiveFiltersCount === 'function') this.getActiveFiltersCount(); });
                         form.addEventListener('submit', () => { if (typeof this.getActiveFiltersCount === 'function') this.getActiveFiltersCount(); });
                     }
                     // Status select listener
                     var statusSelect = document.querySelector('#status_select');
                     if (statusSelect) {
                         var statusContainer = statusSelect.closest('[x-data]');
                         var statusHiddenInput = statusContainer ? statusContainer.querySelector('input[type=hidden][name=status]') : null;
                         if (statusHiddenInput) {
                             var updateStatusCount = () => {
                                 setTimeout(() => {
                                     if (typeof this.getActiveFiltersCount === 'function') this.getActiveFiltersCount();
                                 }, 10);
                             };
                             statusHiddenInput.addEventListener('change', updateStatusCount);
                             var statusObserver = new MutationObserver(updateStatusCount);
                             statusObserver.observe(statusHiddenInput, { attributes: true, attributeFilter: ['value'] });
                         }
                     }

                     // Category select listener
                     var categorySelect = document.querySelector('#category_id_select');
                     if (categorySelect) {
                         var categoryContainer = categorySelect.closest('[x-data]');
                         var categoryHiddenInput = categoryContainer ? categoryContainer.querySelector('input[type=hidden][name=category_id]') : null;
                         if (categoryHiddenInput) {
                             var updateCategoryCount = () => {
                                 setTimeout(() => {
                                     if (typeof this.getActiveFiltersCount === 'function') this.getActiveFiltersCount();
                                 }, 10);
                             };
                             categoryHiddenInput.addEventListener('change', updateCategoryCount);
                             var categoryObserver = new MutationObserver(updateCategoryCount);
                             categoryObserver.observe(categoryHiddenInput, { attributes: true, attributeFilter: ['value'] });
                         }
                     }
                     if (typeof this.updateSelectAllCheckbox === 'function') this.updateSelectAllCheckbox();
                     if (typeof this.initScrollSync === 'function') this.initScrollSync();
                 });
             ">
            <div class="max-w-[95%] mx-auto sm:px-6 lg:px-8">
            <!-- Status Messages -->
            @php
                $statusMessages = [
                    'category-created' => ['message' => __('Category created successfully.'), 'type' => 'success'],
                    'service-created' => ['message' => __('Service created successfully.'), 'type' => 'success'],
                    'category-updated' => ['message' => __('Category updated successfully.'), 'type' => 'success'],
                    'service-enabled' => ['message' => __('Service enabled successfully.'), 'type' => 'success'],
                    'service-disabled' => ['message' => __('Service disabled successfully.'), 'type' => 'warning'],
                    'service-updated' => ['message' => __('Service updated successfully.'), 'type' => 'success'],
                    'service-duplicated' => ['message' => __('Service duplicated successfully.'), 'type' => 'success'],
                    'service-deleted' => ['message' => __('Service deleted successfully.'), 'type' => 'error'],
                    'service-mode-updated' => ['message' => __('Service mode updated successfully.'), 'type' => 'success'],
                ];
                $statusType = session('status');
                $statusConfig = $statusMessages[$statusType] ?? null;
            @endphp

            @if($statusConfig)
                <div class="mb-4 p-4 border rounded-lg {{ $statusConfig['type'] === 'error' ? 'bg-red-50 border-red-200' : ($statusConfig['type'] === 'warning' ? 'bg-yellow-50 border-yellow-200' : 'bg-green-50 border-green-200') }}">
                    <p class="text-sm {{ $statusConfig['type'] === 'error' ? 'text-red-800' : ($statusConfig['type'] === 'warning' ? 'text-yellow-800' : 'text-green-800') }}">{{ $statusConfig['message'] }}</p>
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

                {{-- Search and Filters --}}
            <div class="bg-white shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-gray-900 w-full">
                    <!-- Search Bar and Filter Button -->
                    <div class="mb-6 relative z-10" style="isolation: isolate;">
                        <form method="GET" action="{{ route('staff.services.index') }}" id="filter-form" @submit.prevent="handleFormSubmit($event)">
                            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 sm:gap-4 mb-4 justify-end">
                                <!-- Bulk Actions Button (shown when services are selected) -->
                                <div x-show="selectedServices.length > 0"
                                     x-cloak
                                     class="relative flex-shrink-0"
                                     x-data="{ open: false }"
                                     style="display: none; z-index: 1000; isolation: isolate;">
                                    <button type="button"
                                            @click="open = !open"
                                            class="relative inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                        <span class="mr-2">{{ __('Bulk Operations') }}</span>
                                        <span class="inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white bg-indigo-600 rounded-full" x-text="selectedServices.length"></span>
                                        <svg class="ml-2 -mr-1 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                        </svg>
                                    </button>
                                    <x-filter-dropdown-backdrop open="open" />
                                    <div x-show="open"
                                         @click.away="open = false"
                                         x-cloak
                                         class="absolute right-0 mt-2 w-56 bg-white rounded-md shadow-lg py-1 border border-gray-200"
                                         style="display: none; z-index: 1001; position: absolute;">
                                        @if(request('show_deleted') == '1')
                                            <!-- Restore All (only shown when viewing deleted services) -->
                                            <button type="button"
                                                    @click="showBulkRestoreConfirm(); open = false"
                                                    class="block w-full text-left px-4 py-2 text-sm text-green-600 hover:bg-gray-100">
                                                {{ __('Restore all') }}
                                            </button>
                                        @else
                                            <!-- Regular bulk operations (shown when viewing active services) -->
                                            <button type="button"
                                                    @click="showBulkEnableConfirm(); open = false"
                                                    class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                {{ __('Enable all') }}
                                            </button>
                                            <button type="button"
                                                    @click="showBulkDisableConfirm(); open = false"
                                                    class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                {{ __('Disable all') }}
                                            </button>
                                            <div class="border-t border-gray-200 my-1"></div>
                                            <button type="button"
                                                    @click="showBulkDeleteConfirm(); open = false"
                                                    class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
                                                {{ __('Delete all') }}
                                            </button>
                                        @endif
                                    </div>
                                </div>

                                <!-- Filter Button with Badge -->
                                <div class="relative flex-shrink-0"
                                     x-data="{ open: false }"
                                     @close-filter-dropdown.window="open = false"
                                     style="isolation: isolate; z-index: 100000;">
                                    <button
                                        type="button"
                                        @click="open = !open"
                                        class="relative inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                    >
                                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                                        </svg>
                                        <span
                                            x-show="$root && $root.activeFiltersCount > 0"
                                            x-text="$root ? $root.activeFiltersCount : 0"
                                            class="ml-2 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white bg-indigo-600 rounded-full"
                                        ></span>
                                    </button>

                                    <!-- Backdrop overlay for mobile -->
                                    <x-filter-dropdown-backdrop open="open" />

                                    <!-- Filter Dropdown -->
                                    <x-filter-dropdown open="open">

                                        <!-- Category Filter -->
                                            @php
                                                $categoryOptions = ['' => __('All')];
                                                if (isset($categoriesList) && $categoriesList) {
                                                    foreach ($categoriesList as $category) {
                                                        $categoryOptions[$category->id] = $category->name;
                                                    }
                                                }
                                            @endphp
                                            <x-custom-select
                                                label="{{ __('Category') }}"
                                                name="category_id"
                                                id="category_id"
                                                size="small"
                                                :options="$categoryOptions"
                                                :value="request('category_id', '')"
                                            />

                                        <!-- Status Filter -->
                                            <x-custom-select
                                                label="{{ __('Status') }}"
                                                name="status"
                                                id="status"
                                                size="small"
                                                :options="[
                                                'all' => __('All'),
                                                'active' => __('Active'),
                                                'inactive' => __('Inactive'),
                                            ]"
                                                :value="request('status', 'all')"
                                            />

                                        <!-- Apply Button -->
                                        <x-filter-actions clear-route="staff.services.index" open="open" />
                                    </x-filter-dropdown>
                                </div>

                                <!-- Search Input with Filter (after filter button) -->
                                <x-search-with-filter
                                    name="search"
                                    value="{{ request('search', '') }}"
                                    searchBy="{{ request('search_by', 'all') }}"
                                    :searchByOptions="$searchByLabels"
                                    placeholder="{{ __('Search') }}"
                                    maxWidth="300px"
                                    @search-changed="handleSearchChanged($event.detail)"
                                />
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div id="services-list-container">
                @include('staff.services.partials.services-list', ['categories' => $categories])
            </div>

            <!-- Create Category Modal -->
            <div x-show="openCategoryModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
                <div class="flex items-baseline justify-center min-h-screen px-4 py-2">
            <div x-show="openCategoryModal"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75"
                 @click="openCategoryModal = false"></div>

            <div x-show="openCategoryModal"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 scale-95"
                 class="relative bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-full max-w-md">
                <form method="POST" action="{{ route('staff.categories.store') }}" class="p-6">
                    @csrf
                    <h2 class="text-lg font-medium text-gray-900 mb-4">
                        {{ __('Create New Category') }}
                    </h2>

                    <div class="space-y-4">
                        <!-- Icon and Name Row -->
                        <div class="flex items-start gap-3">
                            <!-- Icon Picker Button -->
                            <div class="flex flex-col gap-1">
                                <label class="text-sm font-medium text-gray-700 whitespace-nowrap">{{ __('Add icon') }}</label>
                                <x-icon-picker name="icon" value="{{ old('icon') }}" />
                            </div>

                            <!-- Name Field -->
                            <div class="flex-1 flex flex-col gap-1">
                                <label for="category_name" class="text-sm font-medium text-gray-700 whitespace-nowrap">
                                    {{ __('Category name') }} <span class="text-red-500">*</span>
                                </label>
                                <input type="text"
                                       name="name"
                                       id="category_name"
                                       value="{{ old('name') }}"
                                       required
                                       maxlength="150"
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('name') border-red-300 @enderror"
                                       placeholder="{{ __('Enter category name') }}">
                                @error('name')
                                    <p class="text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button"
                                @click="openCategoryModal = false"
                                class="inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                            {{ __('Cancel') }}
                        </button>
                        <button type="submit"
                                class="inline-flex justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                            {{ __('Create Category') }}
                        </button>
                    </div>
                </form>
                </div>
                </div>
            </div>

                <!-- Confirmation Modal -->
                <div x-show="showConfirmModal" class="fixed inset-0 z-50 overflow-y-auto">
                    <div class="flex items-center justify-center min-h-screen px-4 py-2">
                        <div x-show="showConfirmModal"
                             x-transition:enter="ease-out duration-300"
                             x-transition:enter-start="opacity-0"
                             x-transition:enter-end="opacity-100"
                             x-transition:leave="ease-in duration-200"
                             x-transition:leave-start="opacity-100"
                             x-transition:leave-end="opacity-0"
                             class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75"
                             @click="cancelConfirm()"></div>

                        <div x-show="showConfirmModal"
                             x-transition:enter="ease-out duration-300"
                             x-transition:enter-start="opacity-0 translate-y-4 scale-95"
                             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                             x-transition:leave="ease-in duration-200"
                             x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                             x-transition:leave-end="opacity-0 translate-y-4 scale-95"
                             class="relative bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-full max-w-md">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4" x-text="confirmModalTitle"></h3>
                                <p class="text-sm text-gray-500 mb-6" x-text="confirmModalMessage"></p>

                                <div class="flex justify-end gap-3">
                                    <button type="button"
                                            @click="cancelConfirm()"
                                            class="inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                        {{ __('Cancel') }}
                                    </button>
                                    <button type="button"
                                            @click="confirmAction()"
                                            :class="confirmModalActionType === 'delete' || confirmModalActionType === 'delete-single' ? 'bg-red-600 hover:bg-red-700 focus:ring-red-500' : 'bg-indigo-600 hover:bg-indigo-700 focus:ring-indigo-500'"
                                            class="inline-flex justify-center rounded-md border border-transparent px-4 py-2 text-sm font-medium text-white shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2">
                                        {{ __('OK') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Edit Category Modal -->
                <div x-show="openEditCategoryModal && editingCategory" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
                <div class="flex items-baseline justify-center min-h-screen px-4 py-2">
            <div x-show="openEditCategoryModal"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75"
                 @click="closeEditModal()"></div>

            <div x-show="openEditCategoryModal"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 scale-95"
                 class="relative bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-full max-w-md">
                <template x-if="editingCategory">
                    <form method="POST" x-bind:action="`{{ url('/staff/categories') }}/${editingCategory.id}`" class="p-6">
                        @csrf
                        @method('PUT')
                        <h2 class="text-lg font-medium text-gray-900 mb-4">
                            {{ __('Edit Category') }}
                        </h2>

                        <div class="space-y-4">
                            <!-- Icon and Name Row -->
                            <div class="flex items-start gap-3">
                                <!-- Icon Picker Button -->
                                <div class="flex flex-col gap-1">
                                    <label class="text-sm font-medium text-gray-700 whitespace-nowrap">{{ __('Add icon') }}</label>
                                    <div x-ref="editIconPickerContainer">
                                        <x-icon-picker name="icon" value="" />
                                    </div>
                                </div>

                                <!-- Name Field -->
                                <div class="flex-1 flex flex-col gap-1">
                                    <label for="edit_category_name" class="text-sm font-medium text-gray-700 whitespace-nowrap">
                                        {{ __('Category name') }} <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text"
                                           name="name"
                                           id="edit_category_name"
                                           x-model="editingCategory.name"
                                           :value="editingCategory.name"
                                           required
                                           maxlength="150"
                                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                           placeholder="{{ __('Enter category name') }}">
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end gap-3">
                            <button type="button"
                                    @click="closeEditModal()"
                                    class="inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                {{ __('Cancel') }}
                            </button>
                            <button type="submit"
                                    class="inline-flex justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                {{ __('Update Category') }}
                            </button>
                        </div>
                    </form>
                </template>
                </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function serviceManagement(filters = {}) {
            const searchByOptions = {
                'all': '{{ __('All') }}',
                'service_name': '{{ __('Service name') }}',
                'service_id': '{{ __('Service ID') }}',
                'category_name': '{{ __('Category name') }}'
            };

            const statusOptions = {
                'all': '{{ __('All') }}',
                'active': '{{ __('Active') }}',
                'inactive': '{{ __('Inactive') }}'
            };

            return {
                openCategoryModal: false,
                openEditCategoryModal: false,
                editingCategory: null,
                collapsedCategories: {},
                selectedServices: [],
                activeFiltersCount: 0,
                showConfirmModal: false,
                confirmModalTitle: '',
                confirmModalMessage: '',
                confirmModalAction: null,
                confirmModalActionType: '', // 'enable', 'disable', 'delete', 'delete-single'
                currentSort: filters?.sort || 'id',
                currentDir: filters?.dir || 'asc',

                // Wrapper method for event listeners
                updateFilterCount() {
                    // Use setTimeout to ensure DOM is updated
                    setTimeout(() => {
                        if (typeof this.getActiveFiltersCount === 'function') {
                            this.getActiveFiltersCount();
                        }
                    }, 10);
                },
                // Helper: Get form data
                getFormData() {
                    const form = document.getElementById('filter-form');
                    if (!form) return null;
                    return new FormData(form);
                },
                initScrollSync() {
                    const headerScroll = document.querySelector('.scroll-sync-header');
                    const bodyScrolls = document.querySelectorAll('.scroll-sync-body');

                    if (!headerScroll || bodyScrolls.length === 0) return;

                    // Remove existing data attribute to prevent duplicate listeners
                    if (headerScroll.dataset.scrollListener) return;
                    headerScroll.dataset.scrollListener = 'true';

                    // Sync body scrolls to header
                    headerScroll.addEventListener('scroll', () => {
                        const scrollLeft = headerScroll.scrollLeft;
                        bodyScrolls.forEach(bodyScroll => {
                            if (bodyScroll.scrollLeft !== scrollLeft) {
                                bodyScroll.scrollLeft = scrollLeft;
                            }
                        });
                    }, { passive: true });

                    // Sync header scroll to any body scroll
                    bodyScrolls.forEach(bodyScroll => {
                        if (bodyScroll.dataset.scrollListener) return;
                        bodyScroll.dataset.scrollListener = 'true';

                        bodyScroll.addEventListener('scroll', () => {
                            const scrollLeft = bodyScroll.scrollLeft;
                            if (headerScroll.scrollLeft !== scrollLeft) {
                                headerScroll.scrollLeft = scrollLeft;
                            }
                            // Sync all other body scrolls
                            bodyScrolls.forEach(otherBody => {
                                if (otherBody !== bodyScroll && otherBody.scrollLeft !== scrollLeft) {
                                    otherBody.scrollLeft = scrollLeft;
                                }
                            });
                        }, { passive: true });
                    });
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
                getActiveFiltersCount() {
                    let count = 0;


                    // Count category filter - get from hidden input or Alpine component
                    let categoryValue = '';

                    // Try to get from hidden input first (most reliable)
                    const categoryHidden = document.querySelector('input[type="hidden"][name="category_id"]') || document.querySelector('input[name="category_id"]');
                    if (categoryHidden && categoryHidden.value) {
                        categoryValue = String(categoryHidden.value || '');
                    }

                    // If still empty, try to get from Alpine.js component
                    if (!categoryValue && window.Alpine) {
                        try {
                            const categorySelectContainer = document.querySelector('#category_id_select')?.closest('[x-data]');
                            if (categorySelectContainer) {
                                const categoryData = Alpine.$data(categorySelectContainer);
                                if (categoryData && categoryData.selectedValue !== undefined) {
                                    categoryValue = String(categoryData.selectedValue || '');
                                }
                            }
                        } catch (e) {
                            // Ignore errors
                        }
                    }

                    // Count category if it's not empty (empty string means "All")
                    if (categoryValue && categoryValue.trim() !== '' && categoryValue !== '0') {
                        count++;
                    }

                    // Count status filter - get from hidden input or Alpine component
                    let statusValue = 'all';

                    // Try to get from hidden input first (most reliable)
                    const statusHidden = document.querySelector('input[type="hidden"][name="status"]') || document.querySelector('input[name="status"]');
                    if (statusHidden && statusHidden.value) {
                        statusValue = String(statusHidden.value || 'all');
                    }

                    // If still 'all', try to get from Alpine.js component
                    if (statusValue === 'all' && window.Alpine) {
                        try {
                            const statusSelectContainer = document.querySelector('#status_select')?.closest('[x-data]');
                            if (statusSelectContainer) {
                                const statusData = Alpine.$data(statusSelectContainer);
                                if (statusData && statusData.selectedValue !== undefined) {
                                    statusValue = String(statusData.selectedValue || 'all');
                                }
                            }
                        } catch (e) {
                            // Ignore errors
                        }
                    }

                    // Count status if it's not 'all'
                    if (statusValue && statusValue !== 'all' && statusValue.trim() !== '') {
                        count++;
                    }

                    this.activeFiltersCount = count;
                    return count;
                },
                getActiveFilters() {
                    const formData = this.getFormData();
                    if (!formData) return [];

                    const filters = [];

                    // Search filter
                    const searchValue = formData.get('search') || '';
                    const searchBy = formData.get('search_by') || 'all';
                    if (searchValue.trim()) {
                        const searchByLabel = searchByOptions[searchBy] || '{{ __('All') }}';
                        filters.push({
                            key: 'search',
                            label: searchByLabel + ': ' + searchValue.trim()
                        });
                    }


                    // Status filter
                    const status = formData.get('status') || 'all';
                    if (status !== 'all') {
                        const statusLabel = statusOptions[status] || status;
                        filters.push({
                            key: 'status',
                            label: '{{ __('Status') }}: ' + statusLabel
                        });
                    }

                    return filters;
                },
                toggleCategory(categoryId) {
                    this.collapsedCategories[categoryId] = !this.collapsedCategories[categoryId];
                },
                isCategoryCollapsed(categoryId) {
                    return !!this.collapsedCategories[categoryId];
                },
                selectAllCategoryServices(categoryId) {
                    const checkboxes = document.querySelectorAll('input.service-checkbox[data-category-id="' + categoryId + '"]');
                    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                    const serviceIds = [];
                    checkboxes.forEach(cb => {
                        cb.checked = !allChecked;
                        const serviceId = parseInt(cb.value);
                        if (serviceId) {
                            if (!allChecked && !this.selectedServices.includes(serviceId)) {
                                this.selectedServices.push(serviceId);
                            } else if (allChecked) {
                                this.selectedServices = this.selectedServices.filter(id => id !== serviceId);
                            }
                        }
                    });
                    this.updateCategoryCheckbox(categoryId);
                    this.updateSelectAllCheckbox();
                },
                    toggleCategoryServices(categoryId, checked) {
                        const serviceCheckboxes = document.querySelectorAll('input.service-checkbox[data-category-id="' + categoryId + '"]');
                        serviceCheckboxes.forEach(cb => {
                            cb.checked = checked;
                            const serviceId = parseInt(cb.value);
                            if (checked && !this.selectedServices.includes(serviceId)) {
                                this.selectedServices.push(serviceId);
                            } else if (!checked) {
                                this.selectedServices = this.selectedServices.filter(id => id !== serviceId);
                            }
                        });
                        this.updateSelectAllCheckbox();
                    },
                    toggleSelectAll(checked) {
                        if (!this.selectedServices) {
                            this.selectedServices = [];
                        }
                        const allCheckboxes = document.querySelectorAll('input.service-checkbox');
                        if (checked) {
                            // Select all - add all service IDs to selectedServices
                            allCheckboxes.forEach(cb => {
                                const serviceId = parseInt(cb.value);
                                if (serviceId && !this.selectedServices.includes(serviceId)) {
                                    this.selectedServices.push(serviceId);
                                }
                            });
                        } else {
                            // Deselect all - clear selectedServices
                            this.selectedServices = [];
                        }
                        // Update all checkboxes to reflect the new state
                        this.$nextTick(() => {
                            allCheckboxes.forEach(cb => {
                                const serviceId = parseInt(cb.value);
                                if (serviceId) {
                                    cb.checked = this.selectedServices.includes(serviceId);
                                }
                            });
                            // Update category checkboxes
                            const categoryIds = new Set();
                            allCheckboxes.forEach(cb => {
                                const catId = cb.getAttribute('data-category-id');
                                if (catId) {
                                    categoryIds.add(catId);
                                }
                            });
                            categoryIds.forEach(catId => {
                                this.updateCategoryCheckbox(catId);
                            });
                        });
                    },
                    updateSelectAllCheckbox() {
                        if (!this.selectedServices) {
                            this.selectedServices = [];
                        }
                        this.$nextTick(() => {
                            const allCheckboxes = document.querySelectorAll('input.service-checkbox');
                            const selectAllCheckbox = this.$refs.selectAllCheckbox;
                            if (selectAllCheckbox && allCheckboxes.length > 0) {
                                const allSelected = Array.from(allCheckboxes).every(cb => {
                                    const serviceId = parseInt(cb.value);
                                    return serviceId && this.selectedServices.includes(serviceId);
                                });
                                const someSelected = Array.from(allCheckboxes).some(cb => {
                                    const serviceId = parseInt(cb.value);
                                    return serviceId && this.selectedServices.includes(serviceId);
                                });
                                selectAllCheckbox.checked = allSelected;
                                selectAllCheckbox.indeterminate = someSelected && !allSelected;
                            }
                        });
                    },
                    // Helper: Get CSRF token
                    getCsrfToken() {
                        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                    },
                    // Helper: Perform bulk operation
                    async performBulkOperation(action, endpoint, body = {}) {
                        if (this.selectedServices.length === 0) return;

                        try {
                            const promises = this.selectedServices.map(serviceId => {
                                const method = action === 'delete' ? 'DELETE' : 'POST';
                                return fetch(`/staff/services/${serviceId}${endpoint}`, {
                                    method: method,
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': this.getCsrfToken(),
                                        'Accept': 'application/json',
                                        'X-Requested-With': 'XMLHttpRequest'
                                    },
                                    body: Object.keys(body).length > 0 ? JSON.stringify(body) : undefined
                                }).then(response => {
                                    if (!response.ok) {
                                        throw new Error(`Failed to ${action} service ${serviceId}`);
                                    }
                                    return response.json();
                                });
                            });

                            await Promise.all(promises);
                            this.selectedServices = [];
                            this.showConfirmModal = false;
                            window.location.reload();
                        } catch (error) {
                            console.error('Bulk operation error:', error);
                            const actionText = action === 'delete' ? 'deleted' : action === 'enable' ? 'enabled' : action === 'disable' ? 'disabled' : action === 'restore' ? 'restored' : 'updated';
                            alert('Some services could not be ' + actionText + '. Please try again.');
                            this.showConfirmModal = false;
                        }
                    },
                    showBulkEnableConfirm() {
                        if (this.selectedServices.length === 0) return;
                        this.confirmModalTitle = '{{ __('Enable Services') }}';
                        this.confirmModalMessage = '{{ __('Are you sure you want to enable the selected services?') }}';
                        this.confirmModalActionType = 'enable';
                        this.showConfirmModal = true;
                    },
                    async bulkEnable() {
                        await this.performBulkOperation('enable', '/toggle-status', { is_active: true });
                    },
                    showBulkDisableConfirm() {
                        if (this.selectedServices.length === 0) return;
                        this.confirmModalTitle = '{{ __('Disable Services') }}';
                        this.confirmModalMessage = '{{ __('Are you sure you want to disable the selected services?') }}';
                        this.confirmModalActionType = 'disable';
                        this.showConfirmModal = true;
                    },
                    async bulkDisable() {
                        await this.performBulkOperation('disable', '/toggle-status', { is_active: false });
                    },
                    showBulkDeleteConfirm() {
                        if (this.selectedServices.length === 0) return;
                        this.confirmModalTitle = '{{ __('Delete Services') }}';
                        this.confirmModalMessage = '{{ __('Are you sure you want to delete the selected services? This action cannot be undone.') }}';
                        this.confirmModalActionType = 'delete';
                        this.showConfirmModal = true;
                    },
                    async bulkDelete() {
                        await this.performBulkOperation('delete', '');
                    },
                    showBulkRestoreConfirm() {
                        if (this.selectedServices.length === 0) return;
                        this.confirmModalTitle = '{{ __('Restore Services') }}';
                        this.confirmModalMessage = '{{ __('Are you sure you want to restore the selected services?') }}';
                        this.confirmModalActionType = 'restore';
                        this.showConfirmModal = true;
                    },
                    async bulkRestore() {
                        await this.performBulkOperation('restore', '/restore');
                    },
                    async confirmAction() {
                        switch(this.confirmModalActionType) {
                            case 'enable':
                                await this.bulkEnable();
                                break;
                            case 'disable':
                                await this.bulkDisable();
                                break;
                            case 'delete':
                                await this.bulkDelete();
                                break;
                            case 'restore':
                                await this.bulkRestore();
                                break;
                            case 'delete-single':
                                if (this.confirmModalAction) {
                                    await this.confirmModalAction();
                                }
                                break;
                        }
                    },
                    cancelConfirm() {
                        this.showConfirmModal = false;
                        this.confirmModalTitle = '';
                        this.confirmModalMessage = '';
                        this.confirmModalAction = null;
                        this.confirmModalActionType = '';
                    },
                    showDeleteSingleConfirm(serviceId, serviceName) {
                        if (!serviceId || !serviceName) {
                            console.error('Missing serviceId or serviceName');
                            return;
                        }
                        console.log('Setting modal properties');
                        this.confirmModalTitle = '{{ __('Delete Service') }}';
                        this.confirmModalMessage = '{{ __('Are you sure you want to delete this service?') }}: ' + serviceName;
                        this.confirmModalActionType = 'delete-single';
                        this.confirmModalAction = async () => {
                            try {
                                const response = await fetch(`/staff/services/${serviceId}`, {
                                    method: 'DELETE',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': this.getCsrfToken(),
                                        'Accept': 'application/json',
                                        'X-Requested-With': 'XMLHttpRequest'
                                    }
                                });

                                // Check if response is ok
                                if (!response.ok) {
                                    let errorMessage = `HTTP error! status: ${response.status}`;
                                    try {
                                        const errorData = await response.json();
                                        errorMessage = errorData.error || errorMessage;
                                    } catch (e) {
                                        // If response is not JSON, use status text
                                        errorMessage = response.statusText || errorMessage;
                                    }
                                    throw new Error(errorMessage);
                                }

                                const data = await response.json();

                                if (data.success) {
                                    // Close modal
                                    this.showConfirmModal = false;
                                    // Reload the page to show updated list
                                    window.location.reload();
                                } else {
                                    alert(data.error || '{{ __('Failed to delete service. Please try again.') }}');
                                }
                            } catch (error) {
                                console.error('Delete error:', error);
                                alert(error.message || '{{ __('Failed to delete service. Please try again.') }}');
                            }
                        };
                        console.log('Before setting showConfirmModal', this.showConfirmModal);
                        this.showConfirmModal = true;
                        console.log('After setting showConfirmModal', this.showConfirmModal);
                        // Force Alpine to update
                        this.$nextTick(() => {
                            console.log('Modal state after nextTick', this.showConfirmModal);
                            var modalEl = document.querySelector('[x-show="showConfirmModal"]');
                            if (modalEl) {
                                console.log('Modal element found', modalEl.style.display, modalEl.classList);
                            } else {
                                console.error('Modal element not found');
                            }
                        });
                    },
                    toggleServiceSelection(serviceId, checked, categoryId) {
                        if (!this.selectedServices) {
                            this.selectedServices = [];
                        }
                        if (checked) {
                            if (!this.selectedServices.includes(serviceId)) {
                                this.selectedServices.push(serviceId);
                            }
                        } else {
                            this.selectedServices = this.selectedServices.filter(id => id !== serviceId);
                        }
                        this.updateCategoryCheckbox(categoryId);
                        this.updateSelectAllCheckbox();
                    },
                    updateCategoryCheckbox(categoryId) {
                        if (!this.selectedServices) {
                            this.selectedServices = [];
                        }
                        const serviceCheckboxes = document.querySelectorAll('input.service-checkbox[data-category-id="' + categoryId + '"]');
                        const allChecked = Array.from(serviceCheckboxes).every(cb => {
                            const serviceId = parseInt(cb.value);
                            return serviceId && this.selectedServices.includes(serviceId);
                        });
                        const someChecked = Array.from(serviceCheckboxes).some(cb => {
                            const serviceId = parseInt(cb.value);
                            return serviceId && this.selectedServices.includes(serviceId);
                        });
                        const categoryCheckbox = document.querySelector('input.category-checkbox[data-category-id="' + categoryId + '"]');
                        if (categoryCheckbox) {
                            categoryCheckbox.checked = allChecked;
                            categoryCheckbox.indeterminate = !allChecked && someChecked;
                        }
                    },
                openEditModal(category) {
                    this.editingCategory = Object.assign({}, category);
                    this.openEditCategoryModal = true;
                    this.$nextTick(() => {
                        const iconInput = this.$refs.editIconPickerContainer?.querySelector('input[type=hidden][name=icon]');
                        if (iconInput && category.icon) {
                            iconInput.value = category.icon || '';
                            const iconPickerElement = iconInput.closest('[x-data*="iconPickerComponent"]');
                            if (iconPickerElement && window.Alpine) {
                                const iconPickerData = Alpine.$data(iconPickerElement);
                                if (iconPickerData) {
                                    iconPickerData.selectedValue = category.icon || '';
                                }
                            }
                        }
                    });
                },
                closeEditModal() {
                    this.openEditCategoryModal = false;
                    this.editingCategory = null;
                },
                    handleSearchChanged(detail) {
                        const form = document.getElementById('filter-form');
                        if (form) {
                            const searchInput = form.querySelector('input[name="search"]');
                            const searchByInput = form.querySelector('input[name="search_by"]');
                            if (searchInput) searchInput.value = detail.search || '';
                            if (searchByInput) searchByInput.value = detail.search_by || 'all';
                        }
                        this.getActiveFiltersCount();
                        this.$nextTick(() => this.performAjaxSearch());
                    },
                    handleFormSubmit(event) {
                        event.preventDefault();
                        // Update filter count after form values are set
                        setTimeout(() => {
                            this.getActiveFiltersCount();
                        }, 0);
                        // Close filter dropdown
                        const filterDropdown = document.querySelector('[x-data*="open"]');
                        if (filterDropdown && window.Alpine) {
                            const dropdownData = Alpine.$data(filterDropdown);
                            if (dropdownData?.open !== undefined) {
                                dropdownData.open = false;
                            }
                        }
                        this.performAjaxSearch();
                    },
                updateURL(filters) {
                    // Build URL parameters
                    const params = new URLSearchParams();

                    if (filters.search) {
                        params.set('search', filters.search);
                    }
                    if (filters.search_by && filters.search_by !== 'all') {
                        params.set('search_by', filters.search_by);
                    }
                    if (filters.status && filters.status !== 'all') {
                        params.set('status', filters.status);
                    }
                    if (filters.category_id) {
                        params.set('category_id', filters.category_id);
                    }
                    if (filters.sort && filters.sort !== 'id') {
                        params.set('sort', filters.sort);
                    }
                    if (filters.dir && filters.dir !== 'asc') {
                        params.set('dir', filters.dir);
                    }

                    // Update URL without page reload
                    const newURL = params.toString()
                        ? window.location.pathname + '?' + params.toString()
                        : window.location.pathname;

                    window.history.pushState({ path: newURL }, '', newURL);
                },
                performAjaxSearch() {
                    const formData = this.getFormData();
                    if (!formData) return;

                    const searchValue = formData.get('search') || '';
                    const searchBy = formData.get('search_by') || 'all';
                    const min = formData.get('min') || '';
                    const max = formData.get('max') || '';
                    const status = formData.get('status') || 'all';
                    const categoryId = formData.get('category_id') || '';
                    const sort = formData.get('sort') || this.currentSort || 'id';
                    const dir = formData.get('dir') || this.currentDir || 'asc';

                    // Update filter count before search
                    this.getActiveFiltersCount();

                    // Update URL with current filters
                    this.updateURL({
                        search: searchValue,
                        search_by: searchBy,
                        min: min,
                        max: max,
                        status: status,
                        category_id: categoryId,
                        sort: sort,
                        dir: dir
                    });

                    // Show loading state
                    const container = document.getElementById('services-list-container');
                    if (container) {
                        container.innerHTML = '<div class="text-center p-8"><div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div><p class="mt-2 text-gray-500">Searching...</p></div>';
                    }

                    // Make AJAX request
                    fetch('{{ route("staff.services.search") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.getCsrfToken()
                        },
                        body: JSON.stringify({
                            search: searchValue,
                            search_by: searchBy,
                            status: status,
                            category_id: categoryId,
                            sort: sort,
                            dir: dir
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (container) {
                            container.innerHTML = data.html;
                            // Re-initialize Alpine.js for the new content
                            if (window.Alpine) {
                                Alpine.initTree(container);
                            }
                            // Update filter count after AJAX search
                            this.getActiveFiltersCount();
                            // Re-initialize scroll sync and checkbox state after AJAX update
                            this.$nextTick(() => {
                                this.initScrollSync();
                                this.updateSelectAllCheckbox();
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Search error:', error);
                        if (container) {
                            container.innerHTML = '<div class="text-center p-8 text-red-500">Error loading results. Please refresh the page.</div>';
                        }
                    });
                }
            };
        }
    </script>
</x-app-layout>

