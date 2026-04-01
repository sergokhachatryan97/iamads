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
        <div class="m-auto sm:px-6 lg:px-8" style="width: 95%">
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
                    <form method="GET" action="{{ route('client.orders.index') }}" class="client-orders-filter-form flex">
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
                        <div style="padding: 0 10px 0 10px">
                            <label for="filter-service" class="mb-1 block text-xs font-medium text-gray-500">{{ __('Service') }}</label>
                            <select name="service_id" id="filter-service" class="rounded-full border border-gray-300 bg-white px-4 py-2 text-sm text-gray-900 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500">
                                <option value="">{{ __('All') }}</option>
                                @foreach($services ?? [] as $svc)
                                    <option value="{{ $svc->id }}" {{ ($filterServiceId ?? '') == $svc->id ? 'selected' : '' }}>ID{{ $svc->id }} - {{ Str::limit($svc->name, 30) }}</option>
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
                        <div class="flex items-center gap-2" style="margin-top: 19px; padding-left: 10px">
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
                <div
                    id="client-orders-live-region"
                    x-data="{ sortBy: @js($sortBy), sortDir: @js($sortDir) }"
                >
                    @include('client.orders.orders-table-inner', ['orders' => $orders])
                </div>
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

            <div id="client-order-modals-root">
                @if($orders->count() > 0)
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
                                        <a href="{{ $telegramUrl }}" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline">{{ Str::limit($order->link, 30) }}</a>
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
                                        <a href="{{ $telegramUrl2 }}" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline">{{ Str::limit($order->link_2, 30) }}</a>
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
                                        $dp = min(100, max(0, $dProgress));
                                        $dr = 14;
                                        $dcirc = 2 * pi() * $dr;
                                        $ddashOffset = $dcirc * (1 - ($dp / 100));
                                    @endphp
                                    <div class="flex flex-col items-center">
                                        <div class="relative h-11 w-11 shrink-0">
                                            <svg class="h-10 w-10 -rotate-90" viewBox="0 0 36 36" aria-hidden="true">
                                                <circle cx="18" cy="18" r="{{ $dr }}" fill="none" stroke="#e5e7eb" stroke-width="4"></circle>
                                                <circle
                                                    cx="18" cy="18" r="{{ $dr }}"
                                                    fill="none"
                                                    stroke="#4f46e5"
                                                    stroke-width="4"
                                                    stroke-linecap="round"
                                                    stroke-dasharray="{{ number_format($dcirc, 3, '.', '') }}"
                                                    stroke-dashoffset="{{ number_format($ddashOffset, 3, '.', '') }}"
                                                ></circle>
                                            </svg>
                                            <div class="absolute inset-0 grid place-items-center">
                                                <span class="text-[11px] font-semibold text-gray-900">{{ number_format($dProgress, 0) }}%</span>
                                            </div>
                                        </div>
                                        <div class="mt-1 text-xs text-gray-600 whitespace-nowrap">
                                            {{ number_format($order->remains) }} / {{ number_format($dQuantity) }}
                                        </div>
                                    </div>
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
                    const modalsRoot = document.getElementById('client-order-modals-root');
                    if (!container) return;
                    container.style.opacity = '0.6';
                    container.style.pointerEvents = 'none';
                    if (modalsRoot) {
                        modalsRoot.style.opacity = '0.6';
                        modalsRoot.style.pointerEvents = 'none';
                    }
                    try {
                        const resp = await fetch(url, {
                            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' }
                        });
                        const html = await resp.text();
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        const newContainer = doc.getElementById('client-orders-container');
                        const newModals = doc.getElementById('client-order-modals-root');
                        if (newContainer) {
                            container.innerHTML = newContainer.innerHTML;
                            if (modalsRoot && newModals) {
                                modalsRoot.innerHTML = newModals.innerHTML;
                            }
                            if (window.Alpine && typeof window.Alpine.initTree === 'function') {
                                container.querySelectorAll('[x-data]').forEach((el) => Alpine.initTree(el));
                                if (modalsRoot) {
                                    modalsRoot.querySelectorAll('[x-data]').forEach((el) => Alpine.initTree(el));
                                }
                            }
                            history.pushState({}, '', url);
                        }
                    } catch (e) {
                        console.error(e);
                        window.location.href = url;
                    } finally {
                        container.style.opacity = '';
                        container.style.pointerEvents = '';
                        if (modalsRoot) {
                            modalsRoot.style.opacity = '';
                            modalsRoot.style.pointerEvents = '';
                        }
                    }
                }
            }));
        });

        window.addEventListener('pagination-change', (e) => {
            if (e.detail.componentId === 'client-orders-pagination' && typeof window.fetchClientOrdersPage === 'function') {
                window.fetchClientOrdersPage(e.detail.page);
            }
        });

        window.fetchClientOrdersPage = function fetchClientOrdersPage(page, sortByParam = null, sortDirParam = null, customUrl = null) {
            const live = document.getElementById('client-orders-live-region');
            const alpineData = live?.__x?.$data;
            const url = customUrl ? new URL(customUrl, window.location.origin) : new URL(window.location.href);
            url.searchParams.set('page', String(page));

            let sortBy = sortByParam ?? alpineData?.sortBy ?? url.searchParams.get('sort_by');
            let sortDir = sortDirParam ?? alpineData?.sortDir ?? url.searchParams.get('sort_dir');
            if (sortBy) {
                url.searchParams.set('sort_by', sortBy);
            }
            if (sortDir) {
                url.searchParams.set('sort_dir', sortDir);
            }

            fetch(url.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'text/html',
                },
                credentials: 'same-origin',
            })
                .then((r) => r.text())
                .then((html) => {
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    const newTbody = doc.querySelector('#client-orders-table tbody');
                    const currentTbody = document.querySelector('#client-orders-table tbody');

                    if (!newTbody || !currentTbody) {
                        window.location.href = url.toString();
                        return;
                    }

                    currentTbody.innerHTML = newTbody.innerHTML;
                    if (window.Alpine && typeof window.Alpine.initTree === 'function') {
                        window.Alpine.initTree(currentTbody);
                    }

                    const newPagination = doc.querySelector('#client-orders-table [data-pagination-root]');
                    const currentPagination = document.querySelector('#client-orders-table [data-pagination-root]');
                    if (newPagination && currentPagination) {
                        currentPagination.innerHTML = newPagination.innerHTML;
                        if (window.Alpine && typeof window.Alpine.initTree === 'function') {
                            window.Alpine.initTree(currentPagination);
                        }
                    }

                    const newModals = doc.getElementById('client-order-modals-root');
                    const curModals = document.getElementById('client-order-modals-root');
                    if (newModals && curModals && window.Alpine && typeof window.Alpine.initTree === 'function') {
                        curModals.innerHTML = newModals.innerHTML;
                        curModals.querySelectorAll('[x-data]').forEach((el) => window.Alpine.initTree(el));
                    }

                    window.history.pushState({}, '', url);

                    if (alpineData) {
                        alpineData.sortBy = url.searchParams.get('sort_by') || alpineData.sortBy;
                        alpineData.sortDir = url.searchParams.get('sort_dir') || alpineData.sortDir;
                    }
                })
                .catch(() => {
                    window.location.href = url.toString();
                });
        };

        (function clientOrderStatusPolling() {
            const ofLabel = @json(__('of'));

            function formatStatusLabel(status) {
                return status.split('_').map((word) => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
            }

            function updateOrderStatus(orderId, statusData) {
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
                            restricted: 'bg-orange-500',
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

                        statusBadge.classList.add('animate-pulse');
                        setTimeout(() => statusBadge.classList.remove('animate-pulse'), 1000);
                    }
                }

                const progressDetail = document.querySelector(`.order-progress-detail[data-order-id="${orderId}"]`);
                if (progressDetail) {
                    progressDetail.textContent = `${new Intl.NumberFormat().format(statusData.delivered)} ${ofLabel} ${new Intl.NumberFormat().format(statusData.quantity)}`;
                }

                const serviceRing = document.querySelector(`.order-service-ring[data-order-id="${orderId}"]`);
                if (serviceRing) {
                    const p = Math.min(100, Math.max(0, statusData.progress));
                    serviceRing.style.background = `conic-gradient(#0ea5f5 calc(${p} * 1%), rgba(255,255,255,.25) 0)`;
                    serviceRing.setAttribute('data-progress', p.toFixed(1));
                }
            }

            function pollOrderStatuses() {
                const orderBadges = document.querySelectorAll('.order-status-badge');
                const orderIds = Array.from(orderBadges)
                    .map((badge) => parseInt(badge.getAttribute('data-order-id'), 10))
                    .filter((id) => !Number.isNaN(id));

                if (orderIds.length === 0) {
                    return;
                }

                const pollUrl = new URL(@json(route('client.orders.statuses')), window.location.origin);
                orderIds.forEach((id) => pollUrl.searchParams.append('order_ids[]', id));

                fetch(pollUrl.toString(), {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                    },
                    credentials: 'same-origin',
                })
                    .then((response) => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then((data) => {
                        if (data.statuses) {
                            Object.entries(data.statuses).forEach(([orderId, statusData]) => {
                                updateOrderStatus(parseInt(orderId, 10), statusData);
                            });
                        }
                    })
                    .catch((error) => console.error('Error polling order statuses:', error));
            }

            let pollInterval;

            function startPolling() {
                stopPolling();
                pollOrderStatuses();
                pollInterval = setInterval(pollOrderStatuses, 3000);
            }

            function stopPolling() {
                if (pollInterval) {
                    clearInterval(pollInterval);
                    pollInterval = null;
                }
            }

            if (document.visibilityState === 'visible') {
                startPolling();
            }

            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    startPolling();
                } else {
                    stopPolling();
                }
            });

            window.addEventListener('beforeunload', stopPolling);
        })();
    </script>
</x-client-layout>

