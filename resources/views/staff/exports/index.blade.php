<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Export Files') }} - {{ ucfirst($module) }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div
                    x-data="{ show: true }"
                    x-init="setTimeout(() => show = false, 3000)"
                    x-show="show"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative"
                    role="alert"
                >
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

            @if(session('error'))
                <div
                    x-data="{ show: true }"
                    x-init="setTimeout(() => show = false, 3000)"
                    x-show="show"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative"
                    role="alert"
                >
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            <div
                x-data="exportForm()"
                x-cloak
            >
                <!-- Filter Bar -->
                <div class="mb-6 bg-white rounded-lg shadow-sm p-4">
                    <form id="export-form" action="{{ route('staff.exports.store') }}" method="POST">
                        @csrf
                        <input type="hidden" name="module" value="{{ $module }}">
                        <input type="hidden" name="columns" id="selected-columns" value="">

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            @if($module === 'orders')
                                <!-- Date From -->
                                <div>
                                    <label for="filters_date_from" class="block text-xs font-medium text-gray-700 mb-1">
                                        {{ __('Date From') }}
                                    </label>
                                    <input
                                        type="date"
                                        id="filters_date_from"
                                        name="filters[date_from]"
                                        x-model="filters.date_from"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    >
                                </div>

                                <!-- Date To -->
                                <div>
                                    <label for="filters_date_to" class="block text-xs font-medium text-gray-700 mb-1">
                                        {{ __('Date To') }}
                                    </label>
                                    <input
                                        type="date"
                                        id="filters_date_to"
                                        name="filters[date_to]"
                                        x-model="filters.date_to"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    >
                                </div>

                                <!-- Client -->
                                <div>
                                    <label for="filters_client_id" class="block text-xs font-medium text-gray-700 mb-1">
                                        {{ __('Client') }}
                                    </label>
                                    <select
                                        id="filters_client_id"
                                        name="filters[client_id]"
                                        x-model="filters.client_id"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    >
                                        <option value="">{{ __('All Clients') }}</option>
                                        @foreach(\App\Models\Client::orderBy('name')->get() as $client)
                                            <option value="{{ $client->id }}">{{ $client->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Manager/Staff -->
                                <div>
                                    <label for="filters_staff_id" class="block text-xs font-medium text-gray-700 mb-1">
                                        {{ __('Manager') }}
                                    </label>
                                    <select
                                        id="filters_staff_id"
                                        name="filters[staff_id]"
                                        x-model="filters.staff_id"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    >
                                        <option value="">{{ __('All Managers') }}</option>
                                        @php
                                            $staffUsers = \App\Models\User::whereHas('roles', function($q) { $q->where('name', 'staff'); })->orderBy('name')->get();
                                        @endphp
                                        @foreach($staffUsers as $staff)
                                            <option value="{{ $staff->id }}">{{ $staff->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Service -->
                                <div>
                                    <label for="filters_service_id" class="block text-xs font-medium text-gray-700 mb-1">
                                        {{ __('Service') }}
                                    </label>
                                    <select
                                        id="filters_service_id"
                                        name="filters[service_id]"
                                        x-model="filters.service_id"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    >
                                        <option value="">{{ __('All Services') }}</option>
                                        @foreach(\App\Models\Service::orderBy('name')->get() as $service)
                                            <option value="{{ $service->id }}">{{ $service->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Status -->
                                <div>
                                    <label for="filters_status" class="block text-xs font-medium text-gray-700 mb-1">
                                        {{ __('Status') }}
                                    </label>
                                    <select
                                        id="filters_status"
                                        name="filters[status]"
                                        x-model="filters.status"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    >
                                        <option value="all">{{ __('All Statuses') }}</option>
                                        @foreach([
                                            \App\Models\Order::STATUS_AWAITING => __('Awaiting'),
                                            \App\Models\Order::STATUS_PENDING => __('Pending'),
                                            \App\Models\Order::STATUS_IN_PROGRESS => __('In Progress'),
                                            \App\Models\Order::STATUS_PROCESSING => __('Processing'),
                                            \App\Models\Order::STATUS_PARTIAL => __('Partial'),
                                            \App\Models\Order::STATUS_COMPLETED => __('Completed'),
                                            \App\Models\Order::STATUS_CANCELED => __('Canceled'),
                                            \App\Models\Order::STATUS_FAIL => __('Failed'),
                                        ] as $statusValue => $statusLabel)
                                            <option value="{{ $statusValue }}">{{ $statusLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            @if($module === 'subscriptions')
                                <!-- Date From -->
                                <div>
                                    <label for="filters_date_from" class="block text-xs font-medium text-gray-700 mb-1">
                                        {{ __('Created From') }}
                                    </label>
                                    <input
                                        type="date"
                                        id="filters_date_from"
                                        name="filters[date_from]"
                                        x-model="filters.date_from"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    >
                                </div>

                                <!-- Date To -->
                                <div>
                                    <label for="filters_date_to" class="block text-xs font-medium text-gray-700 mb-1">
                                        {{ __('Created To') }}
                                    </label>
                                    <input
                                        type="date"
                                        id="filters_date_to"
                                        name="filters[date_to]"
                                        x-model="filters.date_to"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    >
                                </div>

                                <!-- Expires From -->
                                <div>
                                    <label for="filters_expires_from" class="block text-xs font-medium text-gray-700 mb-1">
                                        {{ __('Expires From') }}
                                    </label>
                                    <input
                                        type="date"
                                        id="filters_expires_from"
                                        name="filters[expires_from]"
                                        x-model="filters.expires_from"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    >
                                </div>

                                <!-- Expires To -->
                                <div>
                                    <label for="filters_expires_to" class="block text-xs font-medium text-gray-700 mb-1">
                                        {{ __('Expires To') }}
                                    </label>
                                    <input
                                        type="date"
                                        id="filters_expires_to"
                                        name="filters[expires_to]"
                                        x-model="filters.expires_to"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    >
                                </div>

                                <!-- Client -->
                                <div>
                                    <label for="filters_client_id" class="block text-xs font-medium text-gray-700 mb-1">
                                        {{ __('Client') }}
                                    </label>
                                    <select
                                        id="filters_client_id"
                                        name="filters[client_id]"
                                        x-model="filters.client_id"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    >
                                        <option value="">{{ __('All Clients') }}</option>
                                        @foreach(\App\Models\Client::orderBy('name')->get() as $client)
                                            <option value="{{ $client->id }}">{{ $client->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Manager/Staff -->
                                <div>
                                    <label for="filters_staff_id" class="block text-xs font-medium text-gray-700 mb-1">
                                        {{ __('Manager') }}
                                    </label>
                                    <select
                                        id="filters_staff_id"
                                        name="filters[staff_id]"
                                        x-model="filters.staff_id"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    >
                                        <option value="">{{ __('All Managers') }}</option>
                                        @php
                                            $staffUsers = \App\Models\User::whereHas('roles', function($q) { $q->where('name', 'staff'); })->orderBy('name')->get();
                                        @endphp
                                        @foreach($staffUsers as $staff)
                                            <option value="{{ $staff->id }}">{{ $staff->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Subscription Plan -->
                                <div>
                                    <label for="filters_subscription_id" class="block text-xs font-medium text-gray-700 mb-1">
                                        {{ __('Subscription Plan') }}
                                    </label>
                                    <select
                                        id="filters_subscription_id"
                                        name="filters[subscription_id]"
                                        x-model="filters.subscription_id"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    >
                                        <option value="">{{ __('All Plans') }}</option>
                                        @foreach(\App\Models\SubscriptionPlan::orderBy('name')->get() as $plan)
                                            <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Service -->
                                <div>
                                    <label for="filters_service_id" class="block text-xs font-medium text-gray-700 mb-1">
                                        {{ __('Service') }}
                                    </label>
                                    <select
                                        id="filters_service_id"
                                        name="filters[service_id]"
                                        x-model="filters.service_id"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    >
                                        <option value="">{{ __('All Services') }}</option>
                                        @foreach(\App\Models\Service::orderBy('name')->get() as $service)
                                            <option value="{{ $service->id }}">{{ $service->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Auto Renew -->
                                <div>
                                    <label for="filters_auto_renew" class="block text-xs font-medium text-gray-700 mb-1">
                                        {{ __('Auto Renew') }}
                                    </label>
                                    <select
                                        id="filters_auto_renew"
                                        name="filters[auto_renew]"
                                        x-model="filters.auto_renew"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    >
                                        <option value="">{{ __('All') }}</option>
                                        <option value="1">{{ __('Yes') }}</option>
                                        <option value="0">{{ __('No') }}</option>
                                    </select>
                                </div>

                                <!-- Status -->
                                <div>
                                    <label for="filters_status" class="block text-xs font-medium text-gray-700 mb-1">
                                        {{ __('Status') }}
                                    </label>
                                    <select
                                        id="filters_status"
                                        name="filters[status]"
                                        x-model="filters.status"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    >
                                        <option value="">{{ __('All Statuses') }}</option>
                                        <option value="active">{{ __('Active') }}</option>
                                        <option value="expired">{{ __('Expired') }}</option>
                                    </select>
                                </div>
                            @endif

                            <!-- Format -->
                            <div>
                                <label for="format" class="block text-xs font-medium text-gray-700 mb-1">
                                    {{ __('Format') }}
                                </label>
                                <select
                                    id="format"
                                    name="format"
                                    x-model="format"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                >
                                    <option value="csv">CSV</option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-4 flex items-center justify-between">
                            <button
                                type="button"
                                @click="openColumnPicker()"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            >
                                <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path>
                                </svg>
                                {{ __('Customize columns') }}
                            </button>

                            <button
                                type="submit"
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            >
                                {{ __('Create file') }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Column Picker Modal -->
                <x-modal name="column-picker" maxWidth="md">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('Customize Columns') }}</h3>

                        <div class="mb-4 flex items-center justify-between">
                            <button
                                type="button"
                                @click="selectAllColumns()"
                                class="text-sm text-indigo-600 hover:text-indigo-900"
                            >
                                {{ __('Select All') }}
                            </button>
                            <button
                                type="button"
                                @click="clearAllColumns()"
                                class="text-sm text-indigo-600 hover:text-indigo-900"
                            >
                                {{ __('Clear All') }}
                            </button>
                        </div>
                        <div class="space-y-2 max-h-96 overflow-y-auto">
                            @foreach($moduleConfig['allowed_columns'] ?? [] as $columnKey => $columnLabel)
                                <label class="flex items-center p-2 hover:bg-gray-50 rounded">
                                    <input
                                        type="checkbox"
                                        value="{{ $columnKey }}"
                                        x-model="selectedColumns"
                                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    >
                                    <span class="ml-2 text-sm text-gray-700">{{ $columnLabel }}</span>
                                </label>
                            @endforeach
                        </div>

                        <div class="mt-6 flex justify-end gap-2">
                            <button
                                type="button"
                                @click="$dispatch('close-modal', 'column-picker')"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
                            >
                                {{ __('Cancel') }}
                            </button>
                            <button
                                type="button"
                                @click="saveColumns()"
                                class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700"
                            >
                                {{ __('Save') }}
                            </button>
                        </div>
                    </div>
                </x-modal>

                <!-- Exports History Table -->
                <div
                    x-data="exportsTable('{{ $module }}')"
                    x-init="
                        const self = this;
                        fetchExports();
                        startPolling();
                        window.addEventListener('pagination-change', (e) => {
                            if (e.detail.componentId === 'exports-pagination') {
                                fetchExports(e.detail.page);
                            }
                        });
                        window.addEventListener('exports-table-sort', function(e) {
                            const column = e.detail.column;
                            const currentSortBy = self.sortBy || '{{ $sortBy }}';
                            const currentSortDir = self.sortDir || '{{ $sortDir }}';
                            const newSortDir = (currentSortBy === column) ? (currentSortDir === 'asc' ? 'desc' : 'asc') : 'asc';
                            self.sortBy = column;
                            self.sortDir = newSortDir;
                            self.fetchExports(1, column, newSortDir);
                        });
                    "
                >
                    <x-table id="exports-table">
                        <x-slot name="header">
                            <tr>
                                <x-table-sortable-header
                                    column="created_at"
                                    :label="__('Created')"
                                    :currentSortBy="$sortBy"
                                    :currentSortDir="$sortDir"
                                    route="staff.exports.index"
                                    :params="['module' => $module]"
                                    :ajax="true"
                                    sort-event="exports-table-sort"
                                />
                                <x-table-sortable-header
                                    column="format"
                                    :label="__('Format')"
                                    :currentSortBy="$sortBy"
                                    :currentSortDir="$sortDir"
                                    route="staff.exports.index"
                                    :params="['module' => $module]"
                                    :ajax="true"
                                    sort-event="exports-table-sort"
                                />
                                <x-table-sortable-header
                                    column="rows_count"
                                    :label="__('Rows')"
                                    :currentSortBy="$sortBy"
                                    :currentSortDir="$sortDir"
                                    route="staff.exports.index"
                                    :params="['module' => $module]"
                                    :ajax="true"
                                    sort-event="exports-table-sort"
                                />
                                <x-table-sortable-header
                                    column="status"
                                    :label="__('Status')"
                                    :currentSortBy="$sortBy"
                                    :currentSortDir="$sortDir"
                                    route="staff.exports.index"
                                    :params="['module' => $module]"
                                    :ajax="true"
                                    sort-event="exports-table-sort"
                                />
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ __('Actions') }}
                                </th>
                            </tr>
                        </x-slot>
                        <x-slot name="body">
                            <template x-for="exp in exports" :key="exp.id">
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="exp.created_at"></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="exp.format"></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="exp.rows_count"></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span
                                            class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                            :class="{
                                                'bg-yellow-100 text-yellow-800': exp.status === 'pending',
                                                'bg-blue-100 text-blue-800': exp.status === 'processing',
                                                'bg-green-100 text-green-800': exp.status === 'ready',
                                                'bg-red-100 text-red-800': exp.status === 'failed',
                                            }"
                                            x-text="exp.status_label"
                                        ></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <template x-if="exp.is_ready">
                                            <a
                                                :href="exp.download_url"
                                                class="text-indigo-600 hover:text-indigo-900"
                                            >
                                                {{ __('Download') }}
                                            </a>
                                        </template>
                                        <template x-if="exp.status === 'failed'">
                                            <span class="text-red-600 text-xs" :title="exp.error">
                                                {{ __('Failed') }}
                                            </span>
                                        </template>
                                        <template x-if="!exp.is_ready && exp.status !== 'failed'">
                                            <span class="text-gray-400">{{ __('Processing...') }}</span>
                                        </template>
                                    </td>
                                </tr>
                            </template>
                            <template x-if="exports.length === 0">
                                <tr>
                                    <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">
                                        {{ __('No exports found.') }}
                                    </td>
                                </tr>
                            </template>
                        </x-slot>
                        <x-slot name="pagination">
                            <x-pagination
                                :currentPage="$exports->currentPage()"
                                :lastPage="$exports->lastPage()"
                                :hasPages="$exports->hasPages()"
                                id="exports-pagination"
                            />
                        </x-slot>
                    </x-table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function exportForm() {
            return {
                filters: {
                    date_from: '',
                    date_to: '',
                    expires_from: '',
                    expires_to: '',
                    client_id: '',
                    staff_id: '',
                    service_id: '',
                    subscription_id: '',
                    auto_renew: '',
                    status: @js($module === 'orders' ? 'all' : ''),
                },
                format: 'csv',
                selectedColumns: @js($moduleConfig['default_columns'] ?? []),

                init() {
                    // Initialize with default columns
                    this.updateSelectedColumnsInput();
                },

                openColumnPicker() {
                    window.dispatchEvent(new CustomEvent('open-modal', { detail: 'column-picker' }));
                },

                selectAllColumns() {
                    this.selectedColumns = @js(array_keys($moduleConfig['allowed_columns'] ?? []));
                },

                clearAllColumns() {
                    this.selectedColumns = [];
                },

                saveColumns() {
                    this.updateSelectedColumnsInput();
                    window.dispatchEvent(new CustomEvent('close-modal', { detail: 'column-picker' }));
                },

                updateSelectedColumnsInput() {
                    const input = document.getElementById('selected-columns');

                    if (input) {
                        input.value = JSON.stringify(this.selectedColumns);
                    }
                },
            }
        }

        function exportsTable(module) {
            return {
                exports: @js($exports->map(function($export) {
                    return [
                        'id' => $export->id,
                        'created_at' => $export->created_at->format('Y-m-d H:i:s'),
                        'format' => strtoupper($export->format),
                        'rows_count' => $export->rows_count ? number_format($export->rows_count) : '—',
                        'status' => $export->status,
                        'status_label' => ucfirst($export->status),
                        'is_ready' => $export->isReady(),
                        'download_url' => $export->isReady() ? $export->getDownloadUrl() : null,
                        'error' => $export->error,
                    ];
                })->values()),
                pollingInterval: null,
                module: module || this.getModuleFromUrl(),
                currentPage: {{ $exports->currentPage() }},
                lastPage: {{ $exports->lastPage() }},
                hasPages: {{ $exports->hasPages() ? 'true' : 'false' }},
                sortBy: '{{ $sortBy }}',
                sortDir: '{{ $sortDir }}',

                getModuleFromUrl() {
                    // Get module from URL query string
                    const urlParams = new URLSearchParams(window.location.search);
                    return urlParams.get('module') || 'orders';
                },

                updateUrl(page) {
                    const url = new URL(window.location);
                    url.searchParams.set('page', page);
                    url.searchParams.set('module', this.module);
                    // Preserve sort parameters from state
                    if (this.sortBy) {
                        url.searchParams.set('sort_by', this.sortBy);
                    }
                    if (this.sortDir) {
                        url.searchParams.set('sort_dir', this.sortDir);
                    }
                    window.history.pushState({}, '', url);
                },

                updateUrlWithSort(page) {
                    const url = new URL(window.location);
                    url.searchParams.set('page', page);
                    url.searchParams.set('module', this.module);
                    url.searchParams.set('sort_by', this.sortBy);
                    url.searchParams.set('sort_dir', this.sortDir);
                    window.history.pushState({}, '', url);
                },


                fetchExports(page = null, sortByParam = null, sortDirParam = null) {
                    // Always get module from URL to ensure it's current
                    const currentModule = this.getModuleFromUrl();
                    this.module = currentModule;
                    const pageToFetch = page || this.currentPage;
                    this.currentPage = pageToFetch;
                    this.updateUrl(pageToFetch);

                    // Build URL with all query parameters
                    const url = new URL('{{ route("staff.exports.index.json") }}', window.location.origin);
                    url.searchParams.set('module', currentModule);
                    url.searchParams.set('page', pageToFetch);
                    
                    // Use sort parameters from function params first, then state
                    const sortBy = sortByParam || this.sortBy;
                    const sortDir = sortDirParam || this.sortDir;
                    
                    if (sortBy) {
                        url.searchParams.set('sort_by', sortBy);
                    }
                    if (sortDir) {
                        url.searchParams.set('sort_dir', sortDir);
                    }

                    fetch(url.toString(), {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                        credentials: 'same-origin',
                    })
                    .then(response => response.json())
                    .then(data => {
                        this.exports = data.exports;
                        this.currentPage = data.current_page;
                        this.lastPage = data.last_page;
                        this.hasPages = data.has_pages;
                        
                        // Update sort state
                        if (sortBy) {
                            this.sortBy = sortBy;
                        }
                        if (sortDir) {
                            this.sortDir = sortDir;
                        }
                        
                        // Update sort icons in headers
                        if (window.updateSortIcons) {
                            window.updateSortIcons('exports-table', sortBy, sortDir);
                        }
                        
                        // Stop polling if all exports are ready or failed
                        const allFinished = this.exports.every(exp => exp.status === 'ready' || exp.status === 'failed');
                        if (allFinished && this.exports.length > 0) {
                            this.stopPolling();
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching exports:', error);
                    });
                },


                startPolling() {
                    const hasActiveExports = this.exports.some(exp => exp.status === 'pending' || exp.status === 'processing');
                    if (hasActiveExports) {
                        this.pollingInterval = setInterval(() => {
                            this.fetchExports();
                        }, 3000);
                    }
                },

                stopPolling() {
                    if (this.pollingInterval) {
                        clearInterval(this.pollingInterval);
                        this.pollingInterval = null;
                    }
                },
            }
        }
    </script>
</x-app-layout>

