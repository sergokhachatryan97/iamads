<x-app-layout>
    {{-- Staff orders: header shows title only; no create button (orders are created by clients) --}}
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Orders') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="m-auto" style="width: 95%">
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
                    class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-full text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                    <svg class="mr-2 h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    {{ __('Export to Excel') }}
                </a>
            </div>

            <!-- Filter Bar -->
            <div class="mb-4 rounded-xl bg-white border border-gray-200 shadow-sm p-4"
                 x-data="staffOrdersFilters({
                     filtersOpen: {{ ($filterCategoryId || $filterServiceId || $filterDateFrom || $filterDateTo || ($filterSearch ?? null)) ? 'true' : 'false' }},
                     searchValue: @js($filterSearch ?? ''),
                     currentStatus: @js($currentStatus ?? 'all'),
                     indexUrl: '{{ route('staff.orders.index') }}',
                 })"
                 @submit.capture.prevent="fetchOrdersFromForm($event.target)">
                {{-- Row 1: Burger + Status Tabs + Search --}}
                <form method="GET" action="{{ route('staff.orders.index') }}" class="staff-orders-filter-form flex flex-wrap items-center gap-3">
                    @if(request()->has('status') && request('status') !== 'all')
                        <input type="hidden" name="status" value="{{ request('status') }}">
                    @endif
                        <div style="width: 100%; display: flex; justify-content: end">
                            <div class="relative">
                                <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                                <input type="text" name="search" x-model="searchValue"
                                       @input.debounce.400ms="fetchOrdersFromForm($event.target.closest('form'))"
                                       placeholder="{{ __('URL or order id') }}"
                                       class="w-full rounded-full border border-gray-300 bg-gray-50 py-2 pl-10 pr-10 text-sm text-gray-900 placeholder-gray-500 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                                <button type="button" x-show="searchValue"
                                        @click="searchValue = ''; fetchOrdersFromForm($event.target.closest('form'))"
                                        x-cloak
                                        class="absolute right-3 top-1/2 -translate-y-1/2 rounded-full p-0.5 text-gray-400 hover:bg-gray-200 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-1.5">
                        @php
                            $statusButtons = [
                                'all' => __('All'),
                                \App\Models\Order::STATUS_VALIDATING => __('Validating'),
                                \App\Models\Order::STATUS_INVALID_LINK => __('Invalid Link'),
                                \App\Models\Order::STATUS_AWAITING => __('Awaiting'),
                                \App\Models\Order::STATUS_IN_PROGRESS => __('In Progress'),
                                \App\Models\Order::STATUS_PARTIAL => __('Partial'),
                                \App\Models\Order::STATUS_COMPLETED => __('Completed'),
                                \App\Models\Order::STATUS_CANCELED => __('Canceled'),
                                \App\Models\Order::STATUS_FAIL => __('Failed'),
                            ];
                        @endphp
                            <button type="button"
                                    @click="filtersOpen = !filtersOpen"
                                    :class="filtersOpen ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                                </svg>
                            </button>
                        @foreach($statusButtons as $statusValue => $statusLabel)
                            @php
                                $urlParams = request()->except(['status', 'page']);
                                if ($statusValue !== 'all') $urlParams['status'] = $statusValue;
                                $url = route('staff.orders.index', $urlParams);
                                $count = $statusValue === 'all' ? array_sum($statusCounts ?? []) : ($statusCounts[$statusValue] ?? 0);
                            @endphp
                            <a href="{{ $url }}"
                               class="staff-orders-ajax-link inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-medium transition-colors"
                               :class="currentStatus === @js($statusValue) ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:text-gray-900'"
                               @click.prevent="fetchOrdersByUrl('{{ $url }}')">
                                {{ $statusLabel }}
                                @if($count > 0)
                                    <span class="rounded-full px-1.5 py-0.5 text-xs font-bold"
                                          :class="currentStatus === @js($statusValue) ? 'bg-white/25 text-white' : 'bg-gray-200 text-gray-700'">{{ number_format($count) }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>

                </form>

                <div x-show="filtersOpen"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 -translate-y-2"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100 translate-y-0"
                     x-transition:leave-end="opacity-0 -translate-y-2"
                     x-cloak
                     class="mt-4 border-t border-gray-200 pt-4">
                    <form method="GET" action="{{ route('staff.orders.index') }}" class="staff-orders-filter-form flex flex-wrap">
                        @if(request()->has('status') && request('status') !== 'all')
                            <input type="hidden" name="status" value="{{ request('status') }}">
                        @endif
                        <div>
                            <label for="filter-category" class="mb-1 block text-xs font-medium text-gray-500">{{ __('Category') }}</label>
                            <select name="category_id" id="filter-category" class="rounded-full border border-gray-300 bg-white px-4 py-2 text-sm text-gray-900 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                                <option value="">{{ __('All') }}</option>
                                @foreach($categories ?? [] as $cat)
                                    <option value="{{ $cat->id }}" {{ ($filterCategoryId ?? '') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div style="padding: 0 10px 0 10px">
                            <label for="filter-service" class="mb-1 block text-xs font-medium text-gray-500">{{ __('Service') }}</label>
                            <select name="service_id" id="filter-service" class="rounded-full border border-gray-300 bg-white px-4 py-2 text-sm text-gray-900 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                                <option value="">{{ __('All') }}</option>
                                @foreach($services ?? [] as $svc)
                                    <option value="{{ $svc->id }}" {{ ($filterServiceId ?? '') == $svc->id ? 'selected' : '' }}>ID{{ $svc->id }} - {{ Str::limit($svc->name, 40) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="filter-date-from" class="mb-1 block text-xs font-medium text-gray-500">{{ __('Date') }}</label>
                            <div class="flex gap-2">
                                <input type="date" name="date_from" id="filter-date-from" value="{{ $filterDateFrom ?? '' }}"
                                       class="rounded-full border border-gray-300 bg-white px-4 py-2 text-sm text-gray-900 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                                <input type="date" name="date_to" id="filter-date-to" value="{{ $filterDateTo ?? '' }}"
                                       class="rounded-full border border-gray-300 bg-white px-4 py-2 text-sm text-gray-900 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                            </div>
                        </div>
                        <div class="gap-2" style="margin: auto; margin-top: 16px">
                            <button type="submit" class="rounded-full bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                {{ __('Apply') }}
                            </button>
                            <a href="{{ route('staff.orders.index', array_filter(['status' => request('status') !== 'all' ? request('status') : null])) }}"
                               class="inline-flex items-center gap-2 rounded-full border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                                {{ __('Reset filter') }}
                            </a>
                        </div>
                    </form>
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

                <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
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
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('Order') }}
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('User') }}
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('Service') }}
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('Start') }}
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('Progress') }}
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('Price') }}
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('Date') }}
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
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @php
                                                $sourceLabel = $order->source === \App\Models\Order::SOURCE_API ? 'API' : 'WEB';
                                                $sourceClasses = $order->source === \App\Models\Order::SOURCE_API
                                                    ? 'bg-gray-100 text-gray-700'
                                                    : 'bg-indigo-50 text-indigo-700';
                                            @endphp
                                            <div class="flex flex-col gap-1">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-sm font-semibold text-gray-900">#{{ $order->id }}</span>
                                                    <button
                                                        type="button"
                                                        class="text-gray-400 hover:text-gray-700"
                                                        title="{{ __('Copy') }}"
                                                        onclick="navigator.clipboard?.writeText('{{ $order->id }}')"
                                                    >
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m-3 4H10m0 0l-2-2m2 2l2-2" />
                                                        </svg>
                                                    </button>
                                                </div>
                                                <span class="inline-flex w-fit items-center rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $sourceClasses }}">
                                                    {{ $sourceLabel }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <a href="{{ route('staff.clients.edit', $order->client_id) }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-900">
                                                {{ $order->client->name ?? "Client #{$order->client_id}" }}
                                            </a>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3 min-w-[240px]">
                                                @php
                                                    $delivered = (int) ($order->delivered ?? 0);
                                                    $quantity = (int) ($order->quantity ?? 0);
                                                    $progress = $quantity > 0 ? round(($delivered / $quantity) * 100, 1) : 0;
                                                @endphp
                                                @php
                                                    $categoryIcon = $order->service->icon
                                                        ?: ($order->category->icon ?? null)
                                                        ?: ($order->service->category->icon ?? null);
                                                @endphp
                                                <div
                                                    class="order-service-ring relative h-12 w-12 rounded-full shrink-0"
                                                    data-order-id="{{ $order->id }}"
                                                    data-progress="{{ number_format(min(100, max(0, $progress)), 1, '.', '') }}"
                                                    style="background: conic-gradient(#0ea5f5 calc({{ number_format(min(100, max(0, $progress)), 1, '.', '') }} * 1%), rgba(255,255,255,.25) 0);"
                                                >
                                                    <div class="absolute inset-[3px] rounded-full bg-[#fff] flex items-center justify-center">
                                                        <div class="h-9 w-9 rounded-full bg-[#fff] border border-white/10 flex items-center justify-center text-sky-400">
                                                            @if($categoryIcon)
                                                                @if(\Illuminate\Support\Str::startsWith($categoryIcon, '<svg'))
                                                                    <span class="h-5 w-5 [&_svg]:h-5 [&_svg]:w-5 [&_svg]:text-sky-400">{!! $categoryIcon !!}</span>
                                                                @elseif(\Illuminate\Support\Str::startsWith($categoryIcon, 'data:'))
                                                                    <img src="{{ $categoryIcon }}" alt="icon" class="h-5 w-5 object-contain" />
                                                                @elseif(\Illuminate\Support\Str::startsWith($categoryIcon, 'fas ') || \Illuminate\Support\Str::startsWith($categoryIcon, 'far ') || \Illuminate\Support\Str::startsWith($categoryIcon, 'fab ') || \Illuminate\Support\Str::startsWith($categoryIcon, 'fal ') || \Illuminate\Support\Str::startsWith($categoryIcon, 'fad '))
                                                                    <i class="{{ $categoryIcon }}"></i>
                                                                @else
                                                                    <span class="text-sm font-semibold">{{ $categoryIcon }}</span>
                                                                @endif
                                                            @else
                                                                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor" aria-hidden="true">
                                                                    <path d="M9.993 15.674l-.425 5.987c.608 0 .872-.261 1.19-.573l2.856-2.744 5.92 4.33c1.085.598 1.85.284 2.137-.999L24 1.255 0 10.246c.707.222 1.94.608 1.94.608l6.845 2.136 15.9-10.037c.75-.452 1.437-.2 .873.252"/>
                                                                </svg>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="min-w-0">
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-sm font-semibold text-gray-900">{{ $order->service_id }}</span>
                                                        <span class="text-sm text-gray-700 truncate">{{ $order->service->name ?? '—' }}</span>
                                                    </div>
                                                    @if($order->link)
                                                        <a href="{{ $order->link }}" target="_blank" rel="noopener noreferrer" class="text-xs text-indigo-600 hover:underline truncate block" title="{{ $order->link }}">
                                                            {{ \Illuminate\Support\Str::limit($order->link, 40) }}
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $order->start_count !== null ? number_format($order->start_count) : '—' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @php
                                                $statusDots = [
                                                    'validating' => 'bg-cyan-500',
                                                    'invalid_link' => 'bg-red-500',
                                                    'awaiting' => 'bg-yellow-500',
                                                    'pending' => 'bg-blue-500',
                                                    'in_progress' => 'bg-indigo-500',
                                                    'processing' => 'bg-purple-500',
                                                    'partial' => 'bg-orange-500',
                                                    'completed' => 'bg-green-500',
                                                    'canceled' => 'bg-red-500',
                                                    'fail' => 'bg-gray-500',
                                                ];
                                                $dotClass = $statusDots[$order->status] ?? 'bg-gray-500';
                                                $statusLabel = ucfirst(str_replace('_', ' ', $order->status));
                                                $delivered = (int) ($order->delivered ?? 0);
                                                $quantity = (int) ($order->quantity ?? 0);
                                            @endphp
                                            <div class="flex flex-col gap-1">
                                                <div class="flex items-center gap-2 text-sm font-semibold text-gray-900 order-status-badge" data-order-id="{{ $order->id }}" data-status="{{ $order->status }}">
                                                    <span class="h-2.5 w-2.5 rounded-full {{ $dotClass }}"></span>
                                                    <span>{{ $statusLabel }}</span>
                                                </div>
                                                <div class="text-xs text-gray-500 order-progress-detail" data-order-id="{{ $order->id }}">
                                                    {{ number_format($delivered) }} {{ __('of') }} {{ number_format($quantity) }}
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @php
                                                $charge = (float) ($order->charge ?? 0);
                                                $profit = $order->cost !== null ? ($charge - (float) $order->cost) : null;
                                            @endphp
                                            <div class="flex flex-col gap-1">
                                                <div class="text-sm font-semibold text-gray-900">${{ number_format($charge, 4) }}</div>
                                                @if($profit !== null)
                                                    <div class="text-xs font-semibold {{ $profit >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                                        {{ __('Profit') }} ${{ number_format($profit, 4) }}
                                                    </div>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex flex-col gap-1">
                                                <div class="text-sm font-semibold text-gray-900">{{ $order->created_at->format('d M') }}</div>
                                                <div class="text-xs text-gray-500">{{ $order->created_at->format('H:i') }}</div>
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
                                                                :message="__('Are you sure you want to cancel this order? A full refund of $:amount will be processed.', ['amount' => $order->charge])"
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
                </div>

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
                                        'validating' => 'bg-cyan-100 text-cyan-800',
                                        'invalid_link' => 'bg-red-100 text-red-800',
                                        'restricted' => 'bg-orange-100 text-orange-800',
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

                    <!-- Link(s) -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="text-xs font-medium text-gray-500 uppercase mb-1">{{ __('Link') }}</div>
                        @if($order->link || $order->link_2)
                            <div class="space-y-3">
                                @if($order->link)
                                    @php
                                        $telegramUrl = $order->link;
                                        if (preg_match('/^(https?:\/\/)?(t\.me|telegram\.me|telegram\.dog)\/([A-Za-z0-9_+\/\-]+)/i', $order->link, $matches)) {
                                            $username = explode('?', explode('/', $matches[3])[0])[0];
                                            $telegramUrl = 'tg://resolve?domain=' . $username;
                                        } elseif (preg_match('/^@([A-Za-z0-9_]{3,32})$/i', $order->link, $matches)) {
                                            $telegramUrl = 'tg://resolve?domain=' . $matches[1];
                                        }
                                    @endphp
                                    <div>
                                        @if($order->link_2)<div class="text-xs text-gray-500 mb-0.5">{{ __('Target') }}</div>@endif
                                        <a href="{{ $telegramUrl }}" target="_blank" rel="noopener noreferrer" class="text-sm font-mono text-indigo-600 hover:text-indigo-900 hover:underline break-all">{{ $order->link}}</a>
                                    </div>
                                @endif
                                @if($order->link_2)
                                    @php
                                        $telegramUrl2 = $order->link_2;
                                        if (preg_match('/^(https?:\/\/)?(t\.me|telegram\.me|telegram\.dog)\/([A-Za-z0-9_+\/\-]+)/i', $order->link_2, $matches)) {
                                            $username = explode('?', explode('/', $matches[3])[0])[0];
                                            $telegramUrl2 = 'tg://resolve?domain=' . $username;
                                        } elseif (preg_match('/^@([A-Za-z0-9_]{3,32})$/i', $order->link_2, $matches)) {
                                            $telegramUrl2 = 'tg://resolve?domain=' . $matches[1];
                                        }
                                    @endphp
                                    <div>
                                        <div class="text-xs text-gray-500 mb-0.5">{{ __('Source') }}</div>
                                        <a href="{{ $telegramUrl2 }}" target="_blank" rel="noopener noreferrer" class="text-sm font-mono text-indigo-600 hover:text-indigo-900 hover:underline break-all">{{\Illuminate\Support\Str::limit( $order->link_2, 30) }}</a>
                                    </div>
                                @endif
                            </div>
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
                                @php
                                    $orderStartCounts = $order->provider_payload['start_counts'] ?? null;
                                    $startCountLabels = ['subscribe' => __('Subscribers'), 'view' => __('Views'), 'like' => __('Likes'), 'comment' => __('Comments')];
                                @endphp
                                @if($orderStartCounts && is_array($orderStartCounts))
                                    @foreach($orderStartCounts as $key => $val)
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">{{ $startCountLabels[$key] ?? ucfirst($key) }}:</span>
                                            <span class="font-medium text-gray-900">{{ number_format($val) }}</span>
                                        </div>
                                    @endforeach
                                @elseif($order->start_count !== null)
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
                                $p = min(100, max(0, $progress));
                                $r = 14;
                                $circ = 2 * pi() * $r;
                                $dashOffset = $circ * (1 - ($p / 100));
                            @endphp
                            <div class="flex flex-col items-center">
                                <div class="relative h-11 w-11 shrink-0">
                                    <svg class="h-10 w-10 -rotate-90" viewBox="0 0 36 36" aria-hidden="true">
                                        <circle cx="18" cy="18" r="{{ $r }}" fill="none" stroke="#e5e7eb" stroke-width="4"></circle>
                                        <circle
                                            cx="18" cy="18" r="{{ $r }}"
                                            fill="none"
                                            stroke="#4f46e5"
                                            stroke-width="4"
                                            stroke-linecap="round"
                                            stroke-dasharray="{{ number_format($circ, 3, '.', '') }}"
                                            stroke-dashoffset="{{ number_format($dashOffset, 3, '.', '') }}"
                                        ></circle>
                                    </svg>
                                    <div class="absolute inset-0 grid place-items-center">
                                        <span class="text-[11px] font-semibold text-gray-900">{{ number_format($progress, 0) }}%</span>
                                    </div>
                                </div>
                                <div class="mt-1 text-xs text-gray-600 whitespace-nowrap">
                                    {{ number_format($order->remains) }} / {{ number_format($quantity) }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Financial Info -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="text-xs font-medium text-gray-500 uppercase mb-2">{{ __('Financial Information') }}</div>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <span class="text-gray-600">{{ __('Charge') }}:</span>
                                <span class="font-medium text-gray-900 ml-2">${{ $order->charge }}</span>
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

                    <!-- Error Info -->
                    @if($order->provider_last_error)
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <svg class="w-4 h-4 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                            </svg>
                            <span class="text-xs font-semibold text-red-700 uppercase">{{ __('Provider Error') }}</span>
                        </div>
                        <p class="text-sm text-red-800 font-mono break-all">{{ $order->provider_last_error }}</p>
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
                        search: urlParams.get('search') || '',
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

        document.addEventListener('alpine:init', () => {
            Alpine.data('staffOrdersFilters', (config) => ({
                filtersOpen: config.filtersOpen,
                searchValue: config.searchValue,
                currentStatus: config.currentStatus,
                indexUrl: config.indexUrl,

                fetchOrdersFromForm(form) {
                    const params = new URLSearchParams();
                    document.querySelectorAll('.staff-orders-filter-form').forEach(f => {
                        new FormData(f).forEach((val, key) => {
                            if (key !== 'search' && val) params.set(key, val);
                        });
                    });
                    if (this.searchValue) params.set('search', this.searchValue);
                    const url = this.indexUrl + (params.toString() ? '?' + params.toString() : '');
                    this.fetchOrdersByUrl(url);
                },

                fetchOrdersByUrl(url) {
                    const status = new URL(url, window.location.origin).searchParams.get('status');
                    this.currentStatus = status || 'all';
                    fetchOrdersPage(1, null, null, url);
                }
            }));
        });

        function fetchOrdersPage(page, sortByParam = null, sortDirParam = null, customUrl = null) {
            // Get Alpine instance (we need it to re-sync checkboxes after DOM swap)
            const tableContainer = document.querySelector('[x-data*="bulkOrderSelection"]');
            const alpineData = tableContainer?.__x?.$data;

            // resolve sorting
            let sortBy = sortByParam || alpineData?.sortBy || null;
            let sortDir = sortDirParam || alpineData?.sortDir || null;

            const url = customUrl ? new URL(customUrl, window.location.origin) : new URL(window.location);
            url.searchParams.set('page', page);

            // preserve params except page/sort (from url when custom, else from location)
            const currentParams = new URLSearchParams(url.search);
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

                    // 1) replace tbody (fallback to full reload if structure differs, e.g. 0 orders vs some orders)
                    const newTbody = doc.querySelector('#orders-table tbody');
                    const currentTbody = document.querySelector('#orders-table tbody');

                    if (!newTbody || !currentTbody) {
                        window.location.href = url.toString();
                        return;
                    }

                    currentTbody.innerHTML = newTbody.innerHTML;

                    // IMPORTANT: re-init Alpine bindings on replaced DOM
                    if (window.Alpine && typeof window.Alpine.initTree === 'function') {
                        window.Alpine.initTree(currentTbody);
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

        // Real-time status updates
        (function() {
            const statusColors = {
                'validating': 'bg-cyan-100 text-cyan-800',
                'invalid_link': 'bg-red-100 text-red-800',
                'restricted': 'bg-orange-100 text-orange-800',
                'awaiting': 'bg-yellow-100 text-yellow-800',
                'pending': 'bg-blue-100 text-blue-800',
                'in_progress': 'bg-indigo-100 text-indigo-800',
                'processing': 'bg-purple-100 text-purple-800',
                'partial': 'bg-orange-100 text-orange-800',
                'completed': 'bg-green-100 text-green-800',
                'canceled': 'bg-red-100 text-red-800',
                'fail': 'bg-gray-100 text-gray-800',
            };

            function formatStatusLabel(status) {
                return status.split('_').map(word =>
                    word.charAt(0).toUpperCase() + word.slice(1)
                ).join(' ');
            }

            function updateOrderStatus(orderId, statusData) {
                // Update status badge
                const statusBadge = document.querySelector(`.order-status-badge[data-order-id="${orderId}"]`);
                if (statusBadge) {
                    const currentStatus = statusBadge.getAttribute('data-status');
                    if (currentStatus !== statusData.status) {
                        statusBadge.setAttribute('data-status', statusData.status);
                        const dot = statusBadge.querySelector('span.rounded-full');
                        const label = statusBadge.querySelectorAll('span')[1];

                        const dotClasses = {
                            validating: 'bg-cyan-500',
                            invalid_link: 'bg-red-500',
                            awaiting: 'bg-yellow-500',
                            pending: 'bg-blue-500',
                            in_progress: 'bg-indigo-500',
                            processing: 'bg-purple-500',
                            partial: 'bg-orange-500',
                            completed: 'bg-green-500',
                            canceled: 'bg-red-500',
                            fail: 'bg-gray-500',
                        };

                        if (dot) {
                            dot.className = `h-2.5 w-2.5 rounded-full ${dotClasses[statusData.status] || 'bg-gray-500'}`;
                        }
                        if (label) {
                            label.textContent = formatStatusLabel(statusData.status);
                        }

                        // Add animation effect
                        statusBadge.classList.add('animate-pulse');
                        setTimeout(() => {
                            statusBadge.classList.remove('animate-pulse');
                        }, 1000);
                    }
                }

                // Update progress detail (delivered of quantity)
                const progressDetail = document.querySelector(`.order-progress-detail[data-order-id="${orderId}"]`);
                if (progressDetail) {
                    progressDetail.textContent = `${new Intl.NumberFormat().format(statusData.delivered)} ${'{{ __('of') }}'} ${new Intl.NumberFormat().format(statusData.quantity)}`;
                }

                // Update service icon progress ring
                const serviceRing = document.querySelector(`.order-service-ring[data-order-id="${orderId}"]`);
                if (serviceRing) {
                    const p = Math.min(100, Math.max(0, statusData.progress));
                    serviceRing.style.background = `conic-gradient(#0ea5f5 calc(${p} * 1%), rgba(255,255,255,.25) 0)`;
                    serviceRing.setAttribute('data-progress', p.toFixed(1));
                }
            }

            function pollOrderStatuses() {
                // Get all order IDs from the current page
                const orderBadges = document.querySelectorAll('.order-status-badge');
                const orderIds = Array.from(orderBadges).map(badge =>
                    parseInt(badge.getAttribute('data-order-id'), 10)
                ).filter(id => !isNaN(id));

                if (orderIds.length === 0) {
                    return;
                }

                const url = new URL('{{ route("staff.orders.statuses") }}', window.location.origin);
                orderIds.forEach(id => {
                    url.searchParams.append('order_ids[]', id);
                });

                fetch(url.toString(), {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin',
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.statuses) {
                        Object.entries(data.statuses).forEach(([orderId, statusData]) => {
                            updateOrderStatus(parseInt(orderId, 10), statusData);
                        });
                    }
                })
                .catch(error => {
                    console.error('Error polling order statuses:', error);
                });
            }

            // Start polling when page loads
            let pollInterval;

            function startPolling() {
                // Poll immediately
                pollOrderStatuses();

                // Then poll every 3 seconds
                pollInterval = setInterval(pollOrderStatuses, 3000);
            }

            function stopPolling() {
                if (pollInterval) {
                    clearInterval(pollInterval);
                    pollInterval = null;
                }
            }

            // Start polling when page is visible
            if (document.visibilityState === 'visible') {
                startPolling();
            }

            // Handle visibility change (pause when tab is hidden)
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    startPolling();
                } else {
                    stopPolling();
                }
            });

            // Clean up on page unload
            window.addEventListener('beforeunload', stopPolling);
        })();
    </script>

</x-app-layout>

