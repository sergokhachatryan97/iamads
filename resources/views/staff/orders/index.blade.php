<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Orders') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div
                    x-data="{ show: true }"
                    x-init="setTimeout(() => show = false, 3000)"
                    x-show="show"
                    x-transition:leave="transition ease-in duration-300"
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
                    x-transition:leave="transition ease-in duration-300"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative"
                    role="alert"
                >
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            @if($errors->any())
                <div
                    x-data="{ show: true }"
                    x-init="setTimeout(() => show = false, 3000)"
                    x-show="show"
                    x-transition:leave="transition ease-in duration-300"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative"
                    role="alert"
                >
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(session('bulk_result'))
                @php
                    $result = session('bulk_result');
                @endphp
                <div
                    x-data="{ show: true }"
                    x-init="setTimeout(() => show = false, 3000)"
                    x-show="show"
                    x-transition:leave="transition ease-in duration-300"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="mb-4 bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative"
                    role="alert"
                >
                    <div class="font-semibold mb-2">{{ __('Bulk Action Results') }}</div>
                    <div class="text-sm">
                        <p><strong>{{ __('Total Matched') }}:</strong> {{ $result['total_matched'] ?? 0 }}</p>
                        <p><strong>{{ __('Processed') }}:</strong> {{ $result['processed_count'] ?? 0 }}</p>
                        <p><strong>{{ __('Succeeded') }}:</strong> {{ $result['succeeded_count'] ?? 0 }}</p>
                        @if(($result['failed_count'] ?? 0) > 0)
                            <p class="text-red-600"><strong>{{ __('Failed') }}:</strong> {{ $result['failed_count'] }}</p>
                            @if(!empty($result['failures']))
                                <details class="mt-2">
                                    <summary class="cursor-pointer font-semibold">{{ __('View Failures') }}</summary>
                                    <ul class="list-disc list-inside mt-2 ml-4">
                                        @foreach(array_slice($result['failures'], 0, 10) as $failure)
                                            <li>Order #{{ $failure['order_id'] }}: {{ $failure['reason'] }}</li>
                                        @endforeach
                                        @if(count($result['failures']) > 10)
                                            <li class="text-gray-600">... and {{ count($result['failures']) - 10 }} more</li>
                                        @endif
                                    </ul>
                                </details>
                            @endif
                        @endif
                    </div>
                </div>
            @endif

            <!-- Export Button -->
            <div class="mb-4 flex justify-end">
                <a
                    href="{{ route('staff.exports.index', ['module' => 'orders']) }}"
                    class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                    <svg class="mr-2 h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    {{ __('Export to Excel') }}
                </a>
            </div>

            <!-- Status Filter Tabs -->
            <div class="mb-4">
                <div class="flex flex-wrap gap-2 border-b border-indigo-200 pb-2">
                    @php
                        $statusButtons = [
                            'all' => __('All'),
                            \App\Models\Order::STATUS_AWAITING => __('Awaiting'),
                            \App\Models\Order::STATUS_PENDING => __('Pending'),
                            \App\Models\Order::STATUS_IN_PROGRESS => __('In Progress'),
                            \App\Models\Order::STATUS_PROCESSING => __('Processing'),
                            \App\Models\Order::STATUS_PARTIAL => __('Partial'),
                            \App\Models\Order::STATUS_COMPLETED => __('Completed'),
                            \App\Models\Order::STATUS_CANCELED => __('Canceled'),
                            \App\Models\Order::STATUS_FAIL => __('Failed'),
                        ];
                    @endphp
                    @foreach($statusButtons as $statusValue => $statusLabel)
                        @php
                            $isActive = $currentStatus === $statusValue;
                            $urlParams = request()->except(['status', 'page']);
                            if ($statusValue !== 'all') {
                                $urlParams['status'] = $statusValue;
                            }
                            $url = route('staff.orders.index', $urlParams);
                        @endphp
                        <a
                            href="{{ $url }}"
                            class="inline-flex items-center px-4 py-2 rounded-md text-sm font-semibold transition-colors duration-200 {{ $isActive ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-indigo-600 hover:bg-gray-200' }}"
                        >
                            {{ $statusLabel }}
                        </a>
                    @endforeach
                </div>
            </div>

            @if($orders->count() > 0)
                <div
                    x-data="bulkOrderSelection()"
                    x-cloak
                    x-init="
                        const self = this;
                        window.addEventListener('pagination-change', (e) => {
                            if (e.detail.componentId === 'orders-pagination') {
                                fetchOrdersPage(e.detail.page);
                            }
                        });
                        window.addEventListener('orders-table-sort', function(e) {
                            const column = e.detail.column;
                            const currentSortBy = self.sortBy || '{{ $sortBy }}';
                            const currentSortDir = self.sortDir || '{{ $sortDir }}';
                            const newSortDir = (currentSortBy === column) ? (currentSortDir === 'asc' ? 'desc' : 'asc') : 'asc';
                            self.sortBy = column;
                            self.sortDir = newSortDir;
                            fetchOrdersPage(1, column, newSortDir);
                        });
                    "
                >
                    <!-- Bulk Actions Bar -->
                    <div class="mb-4 bg-white rounded-lg shadow-sm p-4" x-show="hasSelection()">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-700">
                                <span x-text="getSelectionText()"></span>
                            </div>
                            <div class="flex gap-2">
                                <x-dropdown align="right" width="48">
                                    <x-slot name="trigger">
                                        <button class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                            {{ __('Bulk Actions') }}
                                            <svg class="ml-2 -mr-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </x-slot>

                                    <x-slot name="content">
                                        <button
                                            type="button"
                                            @click="openConfirmModal('cancel_full')"
                                            class="block w-full px-4 py-2 text-start text-sm leading-5 text-red-700 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 transition duration-150 ease-in-out"
                                        >
                                            {{ __('Cancel (Full Refund)') }}
                                        </button>
                                        <button
                                            type="button"
                                            @click="openConfirmModal('cancel_partial')"
                                            class="block w-full px-4 py-2 text-start text-sm leading-5 text-orange-700 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 transition duration-150 ease-in-out"
                                        >
                                            {{ __('Cancel (Partial Refund)') }}
                                        </button>
                                        <button
                                            type="button"
                                            @click="clearSelection()"
                                            class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 transition duration-150 ease-in-out"
                                        >
                                            {{ __('Clear Selection') }}
                                        </button>
                                    </x-slot>
                                </x-dropdown>
                            </div>
                        </div>
                    </div>

                    <!-- Selection Controls -->
                    <div class="mb-4 flex items-center gap-2">
                        <button
                            type="button"
                            @click="selectPage()"
                            class="text-sm text-indigo-600 hover:text-indigo-900"
                        >
                            {{ __('Select page') }}
                        </button>
                        <span class="text-gray-300">|</span>
                        <button
                            type="button"
                            @click="selectAllMatching()"
                            class="text-sm text-indigo-600 hover:text-indigo-900"
                        >
                            {{ __('Select all matching filters') }}
                        </button>
                    </div>

                <x-table id="orders-table">
                    <x-slot name="header">
                        <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-12">
                            <input
                                type="checkbox"
                                @change="toggleSelectAll($event.target.checked)"
                                x-bind:checked="isAllSelected()"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            />
                        </th>
                        <x-table-sortable-header
                            column="id"
                            :label="__('ID')"
                            :currentSortBy="$sortBy"
                            :currentSortDir="$sortDir"
                            route="staff.orders.index"
                            :params="['status' => $currentStatus !== 'all' ? $currentStatus : null]"
                            :ajax="true"
                            sort-event="orders-table-sort"
                        />
                        <x-table-sortable-header
                            column="created_at"
                            :label="__('Date')"
                            :currentSortBy="$sortBy"
                            :currentSortDir="$sortDir"
                            route="staff.orders.index"
                            :params="['status' => $currentStatus !== 'all' ? $currentStatus : null]"
                            :ajax="true"
                            sort-event="orders-table-sort"
                        />
                        @if($isSuperAdmin)
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('Client') }}
                        </th>
                        @endif
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('Link') }}
                        </th>
                        <x-table-sortable-header
                            column="charge"
                            :label="__('Charge')"
                            :currentSortBy="$sortBy"
                            :currentSortDir="$sortDir"
                            route="staff.orders.index"
                            :params="['status' => $currentStatus !== 'all' ? $currentStatus : null]"
                            :ajax="true"
                            sort-event="orders-table-sort"
                        />
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('Start count') }}
                        </th>
                        <x-table-sortable-header
                            column="quantity"
                            :label="__('Quantity')"
                            :currentSortBy="$sortBy"
                            :currentSortDir="$sortDir"
                            route="staff.orders.index"
                            :params="['status' => $currentStatus !== 'all' ? $currentStatus : null]"
                            :ajax="true"
                            sort-event="orders-table-sort"
                        />
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('Service') }}
                        </th>
                        <x-table-sortable-header
                            column="status"
                            :label="__('Status')"
                            :currentSortBy="$sortBy"
                            :currentSortDir="$sortDir"
                            route="staff.orders.index"
                            :params="['status' => $currentStatus !== 'all' ? $currentStatus : null]"
                            :ajax="true"
                            sort-event="orders-table-sort"
                        />
                        <x-table-sortable-header
                            column="remains"
                            :label="__('Remains')"
                            :currentSortBy="$sortBy"
                            :currentSortDir="$sortDir"
                            route="staff.orders.index"
                            :params="['status' => $currentStatus !== 'all' ? $currentStatus : null]"
                            :ajax="true"
                            sort-event="orders-table-sort"
                        />
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('Progress') }}
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('Actions') }}
                        </th>
                        </tr>
                    </x-slot>
                    <x-slot name="body">
                        @foreach($orders as $order)
                                    @php
                                        $service = $order->service;
                                        $canCancel = $service && $service->user_can_cancel;
                                        // Full cancel eligible: awaiting, pending, processing
                                        $isAwaitingOrPending = in_array($order->status, [\App\Models\Order::STATUS_AWAITING, \App\Models\Order::STATUS_PENDING, \App\Models\Order::STATUS_PROCESSING], true);
                                        // Partial cancel eligible: in_progress, processing
                                        $isInProgressOrProcessing = in_array($order->status, [\App\Models\Order::STATUS_IN_PROGRESS, \App\Models\Order::STATUS_PROCESSING], true);
                                    @endphp
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @php
                                                $canSelect = $canCancel && ($isAwaitingOrPending || $isInProgressOrProcessing);
                                            @endphp
                                            @if($canSelect)
                                                <input
                                                    type="checkbox"
                                                    name="order_ids[]"
                                                    value="{{ $order->id }}"
                                                    @change="toggleOrder({{ $order->id }}, $event.target.checked)"
                                                    x-bind:checked="isOrderSelected({{ $order->id }})"
                                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                            @else
                                                <span class="text-gray-400">—</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $order->id }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $order->created_at->format('Y-m-d H:i:s') }}
                                        </td>
                                        @if($isSuperAdmin)
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            <a href="{{ route('staff.clients.edit', $order->client_id) }}" class="text-indigo-600 hover:text-indigo-900">
                                                {{ $order->client->name ?? "Client #{$order->client_id}" }}
                                            </a>
                                        </td>
                                        @endif
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            @if($order->link)
                                                @php
                                                    $telegramUrl = $order->link;
                                                    // Convert Telegram links to tg:// protocol
                                                    if (preg_match('/^(https?:\/\/)?(t\.me|telegram\.me|telegram\.dog)\/([A-Za-z0-9_+\/\-]+)/i', $order->link, $matches)) {
                                                        $username = $matches[3];
                                                        // Remove query parameters if any
                                                        $username = explode('?', $username)[0];
                                                        $username = explode('/', $username)[0];
                                                        $telegramUrl = 'tg://resolve?domain=' . $username;
                                                    } elseif (preg_match('/^@([A-Za-z0-9_]{5,32})$/i', $order->link, $matches)) {
                                                        $telegramUrl = 'tg://resolve?domain=' . $matches[1];
                                                    }
                                                @endphp
                                                <a
                                                    href="{{ $telegramUrl }}"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="text-indigo-600 hover:text-indigo-900 hover:underline truncate block max-w-xs"
                                                    title="{{ $order->link }}"
                                                >
                                                    {{ $order->link }}
                                                </a>
                                            @else
                                                <span class="text-gray-400">—</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            ${{ number_format($order->charge, 2) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $order->start_count ?? '—' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ number_format($order->quantity) }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            {{ $order->service->name ?? 'N/A' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @php
                                                $statusColors = [
                                                    'awaiting' => 'bg-yellow-100 text-yellow-800',
                                                    'pending' => 'bg-blue-100 text-blue-800',
                                                    'in_progress' => 'bg-indigo-100 text-indigo-800',
                                                    'processing' => 'bg-purple-100 text-purple-800',
                                                    'partial' => 'bg-orange-100 text-orange-800',
                                                    'completed' => 'bg-green-100 text-green-800',
                                                    'canceled' => 'bg-red-100 text-red-800',
                                                    'fail' => 'bg-gray-100 text-gray-800',
                                                ];
                                                $statusColor = $statusColors[$order->status] ?? 'bg-gray-100 text-gray-800';
                                                $statusLabel = ucfirst(str_replace('_', ' ', $order->status));
                                            @endphp
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $statusColor }}">
                                                {{ $statusLabel }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ number_format($order->remains) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @php
                                                $delivered = $order->delivered ?? 0;
                                                $quantity = $order->quantity ?? 1;
                                                $progress = $quantity > 0 ? round(($delivered / $quantity) * 100, 1) : 0;
                                            @endphp
                                            <div class="flex items-center">
                                                <div class="w-24 bg-gray-200 rounded-full h-2 mr-2">
                                                    <div
                                                        class="bg-indigo-600 h-2 rounded-full transition-all duration-300"
                                                        style="width: {{ min(100, max(0, $progress)) }}%"
                                                    ></div>
                                                </div>
                                                <span class="text-sm font-medium text-gray-900">
                                                    {{ number_format($progress, 1) }}%
                                                </span>
                                            </div>
                                            <div class="text-xs text-gray-500 mt-1">
                                                {{ number_format($delivered) }} / {{ number_format($quantity) }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            @php
                                                $service = $order->service;
                                                $canCancel = $service && $service->user_can_cancel;
                                                // Full cancel eligible: awaiting, pending, processing
                                                $isAwaitingOrPending = in_array($order->status, [\App\Models\Order::STATUS_AWAITING, \App\Models\Order::STATUS_PENDING, \App\Models\Order::STATUS_PROCESSING], true);
                                                // Partial cancel eligible: in_progress, processing
                                                $isInProgressOrProcessing = in_array($order->status, [\App\Models\Order::STATUS_IN_PROGRESS, \App\Models\Order::STATUS_PROCESSING], true);
                                            @endphp

                                            <x-dropdown align="right" width="48">
                                                <x-slot name="trigger">
                                                    <button class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                                        {{ __('Actions') }}
                                                        <svg class="ml-2 -mr-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                        </svg>
                                                    </button>
                                                </x-slot>

                                                <x-slot name="content">
                                                    <button
                                                        type="button"
                                                        onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'order-details-{{ $order->id }}' }))"
                                                        class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 transition duration-150 ease-in-out"
                                                    >
                                                        {{ __('View Details') }}
                                                    </button>

                                                    @if($canCancel && $isAwaitingOrPending)
                                                        <div x-data="{
                                                            openModal() {
                                                                $dispatch('open-modal', 'cancel-full-{{ $order->id }}');
                                                            },
                                                            submitForm() {
                                                                document.getElementById('cancel-full-form-{{ $order->id }}').submit();
                                                            }
                                                        }"
                                                        x-on:confirm-action.window="if ($event.detail === 'cancel-full-{{ $order->id }}') { submitForm(); }">
                                                            <button
                                                                type="button"
                                                                @click="openModal()"
                                                                class="block w-full px-4 py-2 text-start text-sm leading-5 text-red-700 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 transition duration-150 ease-in-out">
                                                                {{ __('Cancel') }}
                                                            </button>

                                                            <x-confirm-modal
                                                                name="cancel-full-{{ $order->id }}"
                                                                title="{{ __('Cancel Order') }}"
                                                                :message="__('Are you sure you want to cancel this order? A full refund of $:amount will be processed.', ['amount' => number_format($order->charge, 2)])"
                                                                confirm-text="{{ __('Yes, Cancel Order') }}"
                                                                cancel-text="{{ __('No, Keep Order') }}"
                                                                confirm-button-class="bg-red-600 hover:bg-red-700"
                                                            >
                                                                <form
                                                                    id="cancel-full-form-{{ $order->id }}"
                                                                    action="{{ route('staff.orders.cancelFull', $order) }}"
                                                                    method="POST"
                                                                    style="display: none;"
                                                                >
                                                                    @csrf
                                                                </form>
                                                            </x-confirm-modal>
                                                        </div>
                                                    @elseif($canCancel && $isInProgressOrProcessing)
                                                        <div x-data="{
                                                            openModal() {
                                                                $dispatch('open-modal', 'cancel-partial-{{ $order->id }}');
                                                            },
                                                            submitForm() {
                                                                document.getElementById('cancel-partial-form-{{ $order->id }}').submit();
                                                            }
                                                        }"
                                                        x-on:confirm-action.window="if ($event.detail === 'cancel-partial-{{ $order->id }}') { submitForm(); }">
                                                            <button
                                                                type="button"
                                                                @click="openModal()"
                                                                class="block w-full px-4 py-2 text-start text-sm leading-5 text-orange-700 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 transition duration-150 ease-in-out">
                                                                {{ __('Cancel (Partial)') }}
                                                            </button>

                                                            <x-confirm-modal
                                                                name="cancel-partial-{{ $order->id }}"
                                                                title="{{ __('Partial Cancel Order') }}"
                                                                :message="__('Are you sure you want to cancel the remaining part of this order? A refund for undelivered quantity will be processed.')"
                                                                confirm-text="{{ __('Yes, Cancel Partial') }}"
                                                                cancel-text="{{ __('No, Keep Order') }}"
                                                                confirm-button-class="bg-orange-600 hover:bg-orange-700"
                                                            >
                                                                <form
                                                                    id="cancel-partial-form-{{ $order->id }}"
                                                                    action="{{ route('staff.orders.cancelPartial', $order) }}"
                                                                    method="POST"
                                                                    style="display: none;"
                                                                >
                                                                    @csrf
                                                                </form>
                                                            </x-confirm-modal>
                                                        </div>
                                                    @endif
                                                </x-slot>
                                            </x-dropdown>
                                        </td>
                                    </tr>
                        @endforeach
                    </x-slot>
                    <x-slot name="pagination">
                        <div data-pagination-root>
                            <x-pagination
                                :currentPage="$orders->currentPage()"
                                :lastPage="$orders->lastPage()"
                                :hasPages="$orders->hasPages()"
                                id="orders-pagination"
                            />
                        </div>
                    </x-slot>
                </x-table>

                    <!-- Bulk Action Form -->
                    <form id="bulk-action-form" action="{{ route('staff.orders.bulk-action') }}" method="POST" style="display: none;">
                        @csrf
                        <input type="hidden" name="action" id="bulk-action-type">
                        <input type="hidden" name="select_all" id="bulk-select-all">
                        <input type="hidden" name="selected_ids" id="bulk-selected-ids">
                        <input type="hidden" name="excluded_ids" id="bulk-excluded-ids">
                        <input type="hidden" name="filters" id="bulk-filters">
                    </form>

                    <!-- Bulk Action Confirmation Modal -->
                    <x-modal name="bulk-action-confirm" maxWidth="lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4" x-text="confirmModalTitle"></h3>
                            <p class="text-sm text-gray-600 mb-4" x-text="confirmModalMessage"></p>
                            <div class="flex justify-end gap-2">
                                <button
                                    type="button"
                                    @click="$dispatch('close-modal', 'bulk-action-confirm')"
                                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
                                >
                                    {{ __('Cancel') }}
                                </button>
                                <button
                                    type="button"
                                    @click="submitBulkAction()"
                                    class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700"
                                >
                                    {{ __('Confirm') }}
                                </button>
                            </div>
                        </div>
                    </x-modal>
                </div>
            @else
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <p class="text-center text-gray-500">{{ __('No orders found.') }}</p>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @foreach($orders as $order)
        <x-modal name="order-details-{{ $order->id }}" maxWidth="3xl">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">
                        {{ __('Order Details') }} #{{ $order->id }}
                    </h3>
                    <button
                        type="button"
                        onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'order-details-{{ $order->id }}' }))"
                        class="text-gray-400 hover:text-gray-500"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div class="space-y-4">
                    <!-- Order Info Grid -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="text-xs font-medium text-gray-500 uppercase mb-1">{{ __('Order ID') }}</div>
                            <div class="text-sm font-semibold text-gray-900">#{{ $order->id }}</div>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="text-xs font-medium text-gray-500 uppercase mb-1">{{ __('Date') }}</div>
                            <div class="text-sm font-semibold text-gray-900">{{ $order->created_at->format('M d, Y H:i:s') }}</div>
                        </div>
                        @if($isSuperAdmin)
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="text-xs font-medium text-gray-500 uppercase mb-1">{{ __('Client') }}</div>
                            <div class="text-sm font-semibold text-gray-900">
                                <a href="{{ route('staff.clients.edit', $order->client_id) }}" class="text-indigo-600 hover:text-indigo-900">
                                    {{ $order->client->name ?? "Client #{$order->client_id}" }}
                                </a>
                            </div>
                        </div>
                        @endif
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="text-xs font-medium text-gray-500 uppercase mb-1">{{ __('Status') }}</div>
                            <div class="text-sm font-semibold text-gray-900">
                                @php
                                    $statusColors = [
                                        'awaiting' => 'bg-yellow-100 text-yellow-800',
                                        'pending' => 'bg-blue-100 text-blue-800',
                                        'in_progress' => 'bg-indigo-100 text-indigo-800',
                                        'processing' => 'bg-purple-100 text-purple-800',
                                        'partial' => 'bg-orange-100 text-orange-800',
                                        'completed' => 'bg-green-100 text-green-800',
                                        'canceled' => 'bg-red-100 text-red-800',
                                        'fail' => 'bg-gray-100 text-gray-800',
                                    ];
                                    $statusColor = $statusColors[$order->status] ?? 'bg-gray-100 text-gray-800';
                                    $statusLabel = ucfirst(str_replace('_', ' ', $order->status));
                                @endphp
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $statusColor }}">
                                    {{ $statusLabel }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Service & Category -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <span class="text-gray-600">{{ __('Category') }}:</span>
                                <span class="font-medium text-gray-900 ml-2">{{ $order->category->name ?? 'N/A' }}</span>
                            </div>
                            <div>
                                <span class="text-gray-600">{{ __('Service') }}:</span>
                                <span class="font-medium text-gray-900 ml-2">{{ $order->service->name ?? 'N/A' }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Link -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="text-xs font-medium text-gray-500 uppercase mb-1">{{ __('Link') }}</div>
                        @if($order->link)
                            @php
                                $telegramUrl = $order->link;
                                // Convert Telegram links to tg:// protocol
                                if (preg_match('/^(https?:\/\/)?(t\.me|telegram\.me|telegram\.dog)\/([A-Za-z0-9_+\/\-]+)/i', $order->link, $matches)) {
                                    $username = $matches[3];
                                    // Remove query parameters if any
                                    $username = explode('?', $username)[0];
                                    $username = explode('/', $username)[0];
                                    $telegramUrl = 'tg://resolve?domain=' . $username;
                                } elseif (preg_match('/^@([A-Za-z0-9_]{5,32})$/i', $order->link, $matches)) {
                                    $telegramUrl = 'tg://resolve?domain=' . $matches[1];
                                }
                            @endphp
                            <a
                                href="{{ $telegramUrl }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-sm font-mono text-indigo-600 hover:text-indigo-900 hover:underline break-all"
                            >
                                {{ $order->link }}
                            </a>
                        @else
                            <div class="text-sm font-mono text-gray-900">—</div>
                        @endif
                    </div>

                    <!-- Quantity & Progress -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="text-xs font-medium text-gray-500 uppercase mb-2">{{ __('Quantity Details') }}</div>
                            <div class="space-y-1 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ __('Total Quantity') }}:</span>
                                    <span class="font-medium text-gray-900">{{ number_format($order->quantity) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ __('Delivered') }}:</span>
                                    <span class="font-medium text-gray-900">{{ number_format($order->delivered ?? 0) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ __('Remaining') }}:</span>
                                    <span class="font-medium text-gray-900">{{ number_format($order->remains) }}</span>
                                </div>
                                @if($order->start_count)
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ __('Start Count') }}:</span>
                                    <span class="font-medium text-gray-900">{{ number_format($order->start_count) }}</span>
                                </div>
                                @endif
                            </div>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="text-xs font-medium text-gray-500 uppercase mb-2">{{ __('Progress') }}</div>
                            @php
                                $delivered = $order->delivered ?? 0;
                                $quantity = $order->quantity ?? 1;
                                $progress = $quantity > 0 ? round(($delivered / $quantity) * 100, 1) : 0;
                            @endphp
                            <div class="flex items-center mb-2">
                                <div class="w-full bg-gray-200 rounded-full h-3 mr-2">
                                    <div
                                        class="bg-indigo-600 h-3 rounded-full transition-all duration-300"
                                        style="width: {{ min(100, max(0, $progress)) }}%"
                                    ></div>
                                </div>
                                <span class="text-sm font-semibold text-gray-900">
                                    {{ number_format($progress, 1) }}%
                                </span>
                            </div>
                            <div class="text-xs text-gray-600">
                                {{ number_format($delivered) }} / {{ number_format($quantity) }}
                            </div>
                        </div>
                    </div>

                    <!-- Financial Info -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="text-xs font-medium text-gray-500 uppercase mb-2">{{ __('Financial Information') }}</div>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <span class="text-gray-600">{{ __('Charge') }}:</span>
                                <span class="font-medium text-gray-900 ml-2">${{ number_format($order->charge, 2) }}</span>
                            </div>
                            @if($order->cost !== null)
                            <div>
                                <span class="text-gray-600">{{ __('Cost') }}:</span>
                                <span class="font-medium text-gray-900 ml-2">${{ number_format($order->cost, 2) }}</span>
                            </div>
                            @endif
                            <div>
                                <span class="text-gray-600">{{ __('Payment Source') }}:</span>
                                <span class="font-medium text-gray-900 ml-2">
                                    @if($order->payment_source === \App\Models\Order::PAYMENT_SOURCE_SUBSCRIPTION)
                                        <span class="text-indigo-600">{{ __('Subscription') }}</span>
                                        @if($order->subscription)
                                            ({{ $order->subscription->name }})
                                        @endif
                                    @else
                                        {{ __('Balance') }}
                                    @endif
                                </span>
                            </div>
                            <div>
                                <span class="text-gray-600">{{ __('Mode') }}:</span>
                                <span class="font-medium text-gray-900 ml-2">{{ ucfirst($order->mode ?? 'manual') }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Info -->
                    @if($order->batch_id || $order->created_by)
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="text-xs font-medium text-gray-500 uppercase mb-2">{{ __('Additional Information') }}</div>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            @if($order->batch_id)
                            <div>
                                <span class="text-gray-600">{{ __('Batch ID') }}:</span>
                                <span class="font-mono text-gray-900 ml-2 text-xs">{{ $order->batch_id }}</span>
                            </div>
                            @endif
                            @if($order->created_by && $order->creator)
                            <div>
                                <span class="text-gray-600">{{ __('Created By') }}:</span>
                                <span class="font-medium text-gray-900 ml-2">{{ $order->creator->name }}</span>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>

                <div class="mt-6 flex justify-end">
                    <button
                        type="button"
                        onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'order-details-{{ $order->id }}' }))"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    >
                        {{ __('Close') }}
                    </button>
                </div>
            </div>
        </x-modal>
    @endforeach

    <script>
        function bulkOrderSelection() {
            return {
                selectedIds: new Set(),
                excludedIds: new Set(),
                selectAll: false,
                orderIds: @js($ordersIds),

                pendingAction: null,
                confirmModalTitle: '',
                confirmModalMessage: '',

                sortBy: '{{ $sortBy }}',
                sortDir: '{{ $sortDir }}',

                // --- Helpers ---
                getCurrentPageIds() {
                    // only selectable ones that exist as checkboxes in DOM (current page)
                    return Array.from(document.querySelectorAll('input[name="order_ids[]"]'))
                        .map(cb => String(cb.value));
                },

                syncPageCheckboxes() {
                    // reflect state in current page UI
                    const checkboxes = Array.from(document.querySelectorAll('input[name="order_ids[]"]'));
                    checkboxes.forEach(cb => {
                        cb.checked = this.isOrderSelected(cb.value);
                    });
                },

                // --- Core selection logic ---
                toggleOrder(orderId, checked) {
                    const id = String(orderId);
                    if (this.selectAll) {
                        // selectAll=true => checked means "not excluded"
                        checked ? this.excludedIds.delete(id) : this.excludedIds.add(id);
                    } else {
                        checked ? this.selectedIds.add(id) : this.selectedIds.delete(id);
                    }
                },

                isOrderSelected(orderId) {
                    const id = String(orderId);
                    if (this.selectAll) return !this.excludedIds.has(id);
                    return this.selectedIds.has(id);
                },

                toggleSelectAll(checked) {
                    this.selectAllMatching()
                },

                isAllSelected() {
                    const pageIds = this.getCurrentPageIds();
                    if (pageIds.length === 0) return false;
                    return pageIds.every(id => this.isOrderSelected(id));
                },

                // "Select page" button
                selectPage() {
                    this.selectAll = false;
                    this.excludedIds.clear();

                    const pageIds = this.getCurrentPageIds();
                    pageIds.forEach(id => this.selectedIds.add(id));

                    this.syncPageCheckboxes();
                },

                // "Select all matching filters" button (բոլոր էջերը՝ state-ով)
                selectAllMatching() {
                    this.selectAll = true;
                    this.selectedIds.clear();
                    this.excludedIds.clear();
                    this.selectedIds = Object.values(this.orderIds);
                },

                clearSelection() {
                    this.selectedIds.clear();
                    this.excludedIds.clear();
                    this.selectAll = false;

                    this.syncPageCheckboxes();
                },

                getCurrentFilters() {
                    const urlParams = new URLSearchParams(window.location.search);
                    return {
                        status: urlParams.get('status') || 'all',
                        search: urlParams.get('search') || '',
                        service_id: urlParams.get('service_id') || '',
                        category_id: urlParams.get('category_id') || '',
                        date_from: urlParams.get('date_from') || '',
                        date_to: urlParams.get('date_to') || '',
                    };
                },

                hasSelection() {
                    return this.selectAll || this.selectedIds.size > 0;
                },

                getSelectionText() {
                    if (this.selectAll) {
                        const excluded = this.excludedIds.size;
                        return excluded > 0
                            ? `All matching orders selected (${excluded} excluded)`
                            : 'All matching orders selected';
                    }
                    return `${this.selectedIds.size} order(s) selected`;
                },

                openConfirmModal(action) {
                    this.pendingAction = action;

                    const actionLabels = {
                        cancel_full: 'Cancel (Full Refund)',
                        cancel_partial: 'Cancel (Partial Refund)',
                        refund: 'Refund',
                    };

                    this.confirmModalTitle = actionLabels[action] || 'Bulk Action';
                    this.confirmModalMessage = `Are you sure you want to ${String(actionLabels[action] || 'do this').toLowerCase()} ${this.getSelectionText()}?`;

                    window.dispatchEvent(new CustomEvent('open-modal', { detail: 'bulk-action-confirm' }));
                },

                submitBulkAction() {
                    if (!this.pendingAction) return;

                    const form = document.getElementById('bulk-action-form');
                    form.querySelector('#bulk-action-type').value = this.pendingAction;
                    form.querySelector('#bulk-select-all').value = this.selectAll ? '1' : '0';

                    const selectedIdsArray = Array.from(this.selectedIds).map(id => parseInt(id, 10));
                    const excludedIdsArray = Array.from(this.excludedIds).map(id => parseInt(id, 10));

                    form.querySelector('#bulk-selected-ids').value = JSON.stringify(selectedIdsArray);
                    form.querySelector('#bulk-excluded-ids').value = JSON.stringify(excludedIdsArray);
                    form.querySelector('#bulk-filters').value = JSON.stringify(this.getCurrentFilters());
                    form.submit();
                },
            }
        }

        function fetchOrdersPage(page, sortByParam = null, sortDirParam = null) {
            // Get Alpine instance (we need it to re-sync checkboxes after DOM swap)
            const tableContainer = document.querySelector('[x-data*="bulkOrderSelection"]');
            const alpineData = tableContainer?.__x?.$data;

            // resolve sorting
            let sortBy = sortByParam || alpineData?.sortBy || null;
            let sortDir = sortDirParam || alpineData?.sortDir || null;

            const url = new URL(window.location);
            url.searchParams.set('page', page);

            // preserve params except page/sort
            const currentParams = new URLSearchParams(window.location.search);
            for (const [key, value] of currentParams.entries()) {
                if (!['page', 'sort_by', 'sort_dir'].includes(key)) {
                    url.searchParams.set(key, value);
                }
            }

            if (sortBy) url.searchParams.set('sort_by', sortBy);
            if (sortDir) url.searchParams.set('sort_dir', sortDir);

            fetch(url.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/html',
                },
                credentials: 'same-origin',
            })
                .then(r => r.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');

                    // 1) replace tbody
                    const newTbody = doc.querySelector('#orders-table tbody');
                    const currentTbody = document.querySelector('#orders-table tbody');

                    if (newTbody && currentTbody) {
                        currentTbody.innerHTML = newTbody.innerHTML;

                        // IMPORTANT: re-init Alpine bindings on replaced DOM
                        if (window.Alpine && typeof window.Alpine.initTree === 'function') {
                            window.Alpine.initTree(currentTbody);
                        }
                    }

                    // 2) replace pagination slot (optional but recommended)
                    const newPagination = doc.querySelector('#orders-table [data-pagination-root]');
                    const currentPagination = document.querySelector('#orders-table [data-pagination-root]');
                    if (newPagination && currentPagination) {
                        currentPagination.innerHTML = newPagination.innerHTML;

                        if (window.Alpine && typeof window.Alpine.initTree === 'function') {
                            window.Alpine.initTree(currentPagination);
                        }
                    }

                    // update URL
                    window.history.pushState({}, '', url);

                    // update Alpine sort state
                    if (alpineData) {
                        alpineData.sortBy = url.searchParams.get('sort_by') || alpineData.sortBy;
                        alpineData.sortDir = url.searchParams.get('sort_dir') || alpineData.sortDir;

                        // re-sync checkbox UI according to selection state
                        alpineData.syncPageCheckboxes();
                    }

                    // update sort icons if you have it
                    if (window.updateSortIcons) {
                        window.updateSortIcons('orders-table', url.searchParams.get('sort_by'), url.searchParams.get('sort_dir'));
                    }
                })
                .catch(err => {
                    console.error('Error fetching page:', err);
                    window.location.href = url.toString();
                });
        }
    </script>

</x-app-layout>

