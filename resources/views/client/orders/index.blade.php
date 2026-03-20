<x-client-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Orders') }}
            </h2>
            <a href="{{ route('client.orders.create') }}"
               class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                {{ __('New Order') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div x-data="{ show: true }"
                 x-init="setTimeout(() => show = false, 3000)"
                 x-show="show"
                 x-transition.opacity.duration.300ms>
                @if(session('success'))
                    <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                        <span class="block sm:inline">{{ session('success') }}</span>
                    </div>
                @endif

                @if(session('error'))
                    <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                        <span class="block sm:inline">{{ session('error') }}</span>
                    </div>
                @endif

                @if($errors->any())
                    <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                        <ul class="list-disc list-inside">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <!-- Filter Bar -->
            <div class="mb-4 rounded-xl bg-white border border-gray-200 shadow-sm p-4"
                 x-data="clientOrdersFilters({
                     filtersOpen: {{ ($filterCategoryId || $filterServiceId || $filterDateFrom || $filterDateTo || ($filterSearch ?? null)) ? 'true' : 'false' }},
                     searchValue: @js($filterSearch ?? ''),
                     currentStatus: @js($currentStatus ?? 'all'),
                     indexUrl: '{{ route('client.orders.index') }}',
                 })"
                 @fetch-client-orders.window="fetchOrdersByUrl($event.detail.url)"
                 @submit.capture.prevent="fetchOrdersFromForm($event.target)">
                {{-- Row 1: Burger + Status Tabs + Search --}}
                <form method="GET" action="{{ route('client.orders.index') }}" class="client-orders-filter-form flex flex-wrap items-center gap-3">
                    @if(request()->has('status') && request('status') !== 'all')
                        <input type="hidden" name="status" value="{{ request('status') }}">
                    @endif
                    @if(request()->has('source') && in_array(request('source'), ['web', 'api']))
                        <input type="hidden" name="source" value="{{ request('source') }}">
                    @endif
                    {{-- Burger / Filter toggle --}}
                    <button type="button"
                            @click="filtersOpen = !filtersOpen"
                            :class="filtersOpen ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                        </svg>
                    </button>

                    {{-- Status Tabs --}}
                    <div class="flex flex-wrap items-center gap-1.5">
                        @php
                            $statusButtons = [
                                'all' => __('All'),
                                \App\Models\Order::STATUS_AWAITING => __('Awaiting'),
                                \App\Models\Order::STATUS_IN_PROGRESS => __('In Progress'),
                                \App\Models\Order::STATUS_PARTIAL => __('Partial'),
                                \App\Models\Order::STATUS_COMPLETED => __('Completed'),
                                \App\Models\Order::STATUS_CANCELED => __('Canceled'),
                                \App\Models\Order::STATUS_INVALID_LINK => __('Invalid Link'),
                                \App\Models\Order::STATUS_FAIL => __('Failed'),
                            ];
                        @endphp
                        @foreach($statusButtons as $statusValue => $statusLabel)
                            @php
                                $urlParams = request()->except(['status', 'page']);
                                if ($statusValue !== 'all') $urlParams['status'] = $statusValue;
                                $url = route('client.orders.index', $urlParams);
                                $count = $statusValue === 'all' ? array_sum($statusCounts ?? []) : ($statusCounts[$statusValue] ?? 0);
                            @endphp
                            <a href="{{ $url }}"
                               class="orders-ajax-link inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-medium transition-colors"
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

                    {{-- Search bar with clear button --}}
                    <div class="ml-auto flex-1 min-w-[200px] max-w-md">
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
                </form>

                {{-- Row 2: Advanced filters (expandable) --}}
                <div x-show="filtersOpen"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 -translate-y-2"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100 translate-y-0"
                     x-transition:leave-end="opacity-0 -translate-y-2"
                     x-cloak
                     class="mt-4 border-t border-gray-200 pt-4">
                    <form method="GET" action="{{ route('client.orders.index') }}" class="client-orders-filter-form flex flex-wrap items-end gap-4">
                        @if(request()->has('status') && request('status') !== 'all')
                            <input type="hidden" name="status" value="{{ request('status') }}">
                        @endif
                        @if(request()->has('source') && in_array(request('source'), ['web', 'api']))
                            <input type="hidden" name="source" value="{{ request('source') }}">
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
                        <div>
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
                                       placeholder="{{ __('From') }}"
                                       class="rounded-full border border-gray-300 bg-white px-4 py-2 text-sm text-gray-900 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                                <input type="date" name="date_to" id="filter-date-to" value="{{ $filterDateTo ?? '' }}"
                                       placeholder="{{ __('To') }}"
                                       class="rounded-full border border-gray-300 bg-white px-4 py-2 text-sm text-gray-900 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="submit" class="rounded-full bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                {{ __('Apply') }}
                            </button>
                            <a href="{{ route('client.orders.index', array_filter(['status' => request('status') !== 'all' ? request('status') : null, 'source' => in_array(request('source'), ['web', 'api']) ? request('source') : null])) }}"
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

            <div id="client-orders-container">
            @if($orders->count() > 0)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    @php
                                        if (!function_exists('getSortUrl')) {
                                            function getSortUrl($column, $currentSortBy, $currentSortDir, $currentStatus, $currentSource = 'all') {
                                                $params = request()->except(['sort_by', 'sort_dir', 'page']);
                                                $params['sort_by'] = $column;

                                                if ($currentSortBy === $column) {
                                                    $params['sort_dir'] = $currentSortDir === 'asc' ? 'desc' : 'asc';
                                                } else {
                                                    $params['sort_dir'] = 'asc';
                                                }

                                                if ($currentStatus !== 'all') {
                                                    $params['status'] = $currentStatus;
                                                }
                                                if ($currentSource !== 'all') {
                                                    $params['source'] = $currentSource;
                                                }

                                                return route('client.orders.index', $params);
                                            }
                                        }

                                        if (!function_exists('getSortIcon')) {
                                            function getSortIcon($column, $currentSortBy, $currentSortDir) {
                                                if ($currentSortBy !== $column) {
                                                    return '<svg class="w-4 h-4 inline ml-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path></svg>';
                                                }

                                                if ($currentSortDir === 'asc') {
                                                    return '<svg class="w-4 h-4 inline ml-1 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path></svg>';
                                                } else {
                                                    return '<svg class="w-4 h-4 inline ml-1 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>';
                                                }
                                            }
                                        }
                                    @endphp
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <a href="{{ getSortUrl('id', $sortBy, $sortDir, $currentStatus, $currentSource ?? 'all') }}" class="group inline-flex items-center hover:text-gray-700">
                                            {{ __('ID') }}
                                            {!! getSortIcon('id', $sortBy, $sortDir) !!}
                                        </a>
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <a href="{{ getSortUrl('created_at', $sortBy, $sortDir, $currentStatus, $currentSource ?? 'all') }}" class="group inline-flex items-center hover:text-gray-700">
                                            {{ __('Date') }}
                                            {!! getSortIcon('created_at', $sortBy, $sortDir) !!}
                                        </a>
                                    </th>
{{--                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">--}}
{{--                                        {{ __('Source') }}--}}
{{--                                    </th>--}}
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ __('Link') }}
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <a href="{{ getSortUrl('charge', $sortBy, $sortDir, $currentStatus, $currentSource ?? 'all') }}" class="group inline-flex items-center hover:text-gray-700">
                                            {{ __('Charge') }}
                                            {!! getSortIcon('charge', $sortBy, $sortDir) !!}
                                        </a>
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ __('Start count') }}
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <a href="{{ getSortUrl('quantity', $sortBy, $sortDir, $currentStatus) }}" class="group inline-flex items-center hover:text-gray-700">
                                            {{ __('Quantity') }}
                                            {!! getSortIcon('quantity', $sortBy, $sortDir) !!}
                                        </a>
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ __('Service') }}
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <a href="{{ getSortUrl('status', $sortBy, $sortDir, $currentStatus, $currentSource ?? 'all') }}" class="group inline-flex items-center hover:text-gray-700">
                                            {{ __('Status') }}
                                            {!! getSortIcon('status', $sortBy, $sortDir) !!}
                                        </a>
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <a href="{{ getSortUrl('remains', $sortBy, $sortDir, $currentStatus, $currentSource ?? 'all') }}" class="group inline-flex items-center hover:text-gray-700">
                                            {{ __('Remains') }}
                                            {!! getSortIcon('remains', $sortBy, $sortDir) !!}
                                        </a>
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ __('Progress') }}
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ __('Actions') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($orders as $order)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $order->id }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $order->created_at->format('Y-m-d H:i:s') }}
                                        </td>
{{--                                        <td class="px-6 py-4 whitespace-nowrap">--}}
{{--                                            @php--}}
{{--                                                $orderSource = $order->source ?? 'web';--}}
{{--                                            @endphp--}}
{{--                                            <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium {{ $orderSource === 'api' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-700' }}">--}}
{{--                                                {{ $orderSource === 'api' ? __('API') : __('Web') }}--}}
{{--                                            </span>--}}
{{--                                        </td>--}}
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            @if($order->link || $order->link_2)
                                                <div class="space-y-1 max-w-xs">
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
                                                        <a href="{{ $telegramUrl }}" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:text-indigo-900 hover:underline truncate block" title="{{ $order->link }}">{{ $order->link }}</a>
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
                                                        <div class="text-xs text-gray-500">{{ __('Source') }}:</div>
                                                        <a href="{{ $telegramUrl2 }}" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:text-indigo-900 hover:underline truncate block text-xs" title="{{ $order->link_2 }}">{{ $order->link_2 }}</a>
                                                    @endif
                                                </div>
                                            @else
                                                <span class="text-gray-400">—</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            ${{ $order->charge }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $order->start_count ?? '—' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ number_format($order->quantity) }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            {{ $order?->service?->name }}
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
                                                $isAwaitingOrPending = in_array($order->status, ['awaiting', 'validating']);
                                                $isInProgressOrProcessing = in_array($order->status, ['in_progress', 'processing']);
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
                                                        onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'client-order-details-{{ $order->id }}' }))"
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
                                                                {{ __('Cancel & Refund') }}
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
                                                                    action="{{ route('client.orders.cancelFull', $order) }}"
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
                                                                title="{{ __('Cancel Remaining Part') }}"
                                                                :message="__('Are you sure you want to cancel the remaining part of this order? A partial refund will be processed for the undelivered quantity.')"
                                                                confirm-text="{{ __('Yes, Cancel Remaining') }}"
                                                                cancel-text="{{ __('No, Keep Order') }}"
                                                                confirm-button-class="bg-orange-600 hover:bg-orange-700"
                                                            >
                                                                <form
                                                                    id="cancel-partial-form-{{ $order->id }}"
                                                                    action="{{ route('client.orders.cancelPartial', $order) }}"
                                                                    method="POST"
                                                                    style="display: none;"
                                                                >
                                                                    @csrf
                                                                </form>
                                                            </x-confirm-modal>
                                                        </div>
                                                    @else
                                                        <span class="block px-4 py-2 text-sm text-gray-400">{{ __('No actions available') }}</span>
                                                    @endif
                                                </x-slot>
                                            </x-dropdown>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-4">
                    {{ $orders->links() }}
                </div>

                {{-- Order details modals --}}
                @foreach($orders as $order)
                <x-modal name="client-order-details-{{ $order->id }}" maxWidth="2xl">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">{{ __('Order Details') }} #{{ $order->id }}</h3>
                            <button
                                type="button"
                                onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'client-order-details-{{ $order->id }}' }))"
                                class="text-gray-400 hover:text-gray-500"
                            >
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>

                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <div class="text-xs font-medium text-gray-500 uppercase mb-1">{{ __('Date') }}</div>
                                    <div class="text-sm font-semibold text-gray-900">{{ $order->created_at->format('M d, Y H:i') }}</div>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <div class="text-xs font-medium text-gray-500 uppercase mb-1">{{ __('Status') }}</div>
                                    @php
                                        $detailStatusColors = [
                                            'awaiting' => 'bg-yellow-100 text-yellow-800',
                                            'pending' => 'bg-blue-100 text-blue-800',
                                            'in_progress' => 'bg-indigo-100 text-indigo-800',
                                            'processing' => 'bg-purple-100 text-purple-800',
                                            'partial' => 'bg-orange-100 text-orange-800',
                                            'completed' => 'bg-green-100 text-green-800',
                                            'canceled' => 'bg-red-100 text-red-800',
                                            'fail' => 'bg-gray-100 text-gray-800',
                                        ];
                                    @endphp
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $detailStatusColors[$order->status] ?? 'bg-gray-100 text-gray-800' }}">
                                        {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                                    </span>
                                </div>
                            </div>

                            <div class="bg-gray-50 rounded-lg p-4">
                                <div class="text-xs font-medium text-gray-500 uppercase mb-1">{{ __('Service') }}</div>
                                <div class="text-sm font-semibold text-gray-900">{{ $order->service->name ?? 'N/A' }}</div>
                            </div>

                            @if($order->link || $order->link_2)
                            <div class="bg-gray-50 rounded-lg p-4">
                                <div class="text-xs font-medium text-gray-500 uppercase mb-1">{{ __('Link') }}</div>
                                <div class="space-y-2 text-sm font-mono break-all">
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
                                        @if($order->link_2)<div class="text-xs text-gray-500">{{ __('Target') }}</div>@endif
                                        <a href="{{ $telegramUrl }}" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline">{{ $order->link }}</a>
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
                                        <div class="text-xs text-gray-500">{{ __('Source') }}</div>
                                        <a href="{{ $telegramUrl2 }}" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline">{{ $order->link_2 }}</a>
                                    @endif
                                </div>
                            </div>
                            @endif

                            <div class="grid grid-cols-2 gap-4">
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <div class="text-xs font-medium text-gray-500 uppercase mb-2">{{ __('Quantity') }}</div>
                                    <div class="space-y-1 text-sm">
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">{{ __('Ordered') }}:</span>
                                            <span class="font-medium">{{ number_format($order->quantity) }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">{{ __('Delivered') }}:</span>
                                            <span class="font-medium">{{ number_format($order->delivered ?? 0) }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">{{ __('Remaining') }}:</span>
                                            <span class="font-medium">{{ number_format($order->remains) }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <div class="text-xs font-medium text-gray-500 uppercase mb-2">{{ __('Progress') }}</div>
                                    @php
                                        $dDelivered = $order->delivered ?? 0;
                                        $dQuantity = $order->quantity ?? 1;
                                        $dProgress = $dQuantity > 0 ? round(($dDelivered / $dQuantity) * 100, 1) : 0;
                                    @endphp
                                    <div class="flex items-center mb-2">
                                        <div class="w-full bg-gray-200 rounded-full h-3 mr-2">
                                            <div class="bg-indigo-600 h-3 rounded-full" style="width: {{ min(100, max(0, $dProgress)) }}%"></div>
                                        </div>
                                        <span class="text-sm font-semibold text-gray-900 whitespace-nowrap">{{ number_format($dProgress, 1) }}%</span>
                                    </div>
                                    <div class="text-xs text-gray-600">{{ number_format($dDelivered) }} / {{ number_format($dQuantity) }}</div>
                                </div>
                            </div>

                            <div class="bg-gray-50 rounded-lg p-4">
                                <div class="text-xs font-medium text-gray-500 uppercase mb-1">{{ __('Charge') }}</div>
                                <div class="text-sm font-semibold text-gray-900">${{ $order->charge }}</div>
                            </div>

                            @if($order->provider_last_error)
                            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <svg class="w-4 h-4 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                                    </svg>
                                    <span class="text-xs font-semibold text-red-700 uppercase">{{ __('Error') }}</span>
                                </div>
                                <p class="text-sm text-red-800 break-all">{{ $order->provider_last_error }}</p>
                            </div>
                            @endif
                        </div>

                        <div class="mt-6 flex justify-end">
                            <button
                                type="button"
                                onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'client-order-details-{{ $order->id }}' }))"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            >
                                {{ __('Close') }}
                            </button>
                        </div>
                    </div>
                </x-modal>
                @endforeach
            @else
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 text-center">
                        <p class="text-gray-500">
                            @if($currentStatus !== 'all' || $filterCategoryId || $filterServiceId || $filterDateFrom || $filterDateTo || ($filterSearch ?? null))
                                {{ __('No orders found matching your filters.') }}
                            @else
                                {{ __('No orders found.') }}
                            @endif
                        </p>
                        @if($currentStatus !== 'all')
                            <a href="{{ route('client.orders.index') }}"
                               class="mt-4 inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                {{ __('Clear Filter') }}
                            </a>
                        @else
                            <a href="{{ route('client.orders.create') }}"
                               class="mt-4 inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                {{ __('Create Your First Order') }}
                            </a>
                        @endif
                    </div>
                </div>
            @endif
            </div>
        </div>
    </div>

    <script>
        (function() {
            document.addEventListener('click', function(e) {
                const container = document.getElementById('client-orders-container');
                if (!container || !container.contains(e.target)) return;
                const link = e.target.closest('a');
                if (!link || link.classList.contains('orders-ajax-link')) return;
                const href = link.getAttribute('href') || link.href;
                if (href && (href.includes('orders') || href.includes(window.location.pathname))) {
                    e.preventDefault();
                    const url = href.startsWith('http') ? href : (window.location.origin + (href.startsWith('/') ? '' : '/') + href);
                    window.dispatchEvent(new CustomEvent('fetch-client-orders', { detail: { url } }));
                }
            });
        })();
        document.addEventListener('alpine:init', () => {
            Alpine.data('clientOrdersFilters', (config) => ({
                filtersOpen: config.filtersOpen,
                searchValue: config.searchValue,
                currentStatus: config.currentStatus ?? 'all',
                indexUrl: config.indexUrl,

                fetchOrdersFromForm(form) {
                    const params = new URLSearchParams();
                    const forms = document.querySelectorAll('.client-orders-filter-form');
                    forms.forEach(f => {
                        new FormData(f).forEach((val, key) => {
                            if (key !== 'search' && val) params.set(key, val);
                        });
                    });
                    if (this.searchValue) params.set('search', this.searchValue);
                    const url = this.indexUrl + (params.toString() ? '?' + params.toString() : '');
                    this.fetchOrdersByUrl(url);
                },

                async fetchOrdersByUrl(url) {
                    const status = new URL(url, window.location.origin).searchParams.get('status');
                    this.currentStatus = status || 'all';
                    const container = document.getElementById('client-orders-container');
                    if (!container) return;
                    container.style.opacity = '0.6';
                    container.style.pointerEvents = 'none';
                    try {
                        const resp = await fetch(url, {
                            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' }
                        });
                        const html = await resp.text();
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        const newContainer = doc.getElementById('client-orders-container');
                        if (newContainer) {
                            container.innerHTML = newContainer.innerHTML;
                            if (window.Alpine) container.querySelectorAll('[x-data]').forEach(el => Alpine.initTree(el));
                            history.pushState({}, '', url);
                        }
                    } catch (e) {
                        console.error(e);
                        window.location.href = url;
                    } finally {
                        container.style.opacity = '';
                        container.style.pointerEvents = '';
                    }
                }
            }));
        });
    </script>
</x-client-layout>

