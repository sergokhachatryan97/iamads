{{-- Shared PHP for each order (used by both table and cards) --}}
@php
    $ordersData = $orders->map(function ($order) {
        $service = $order->service;
        $canCancel = $service && $service->user_can_cancel;
        $isAwaitingOrPending = in_array($order->status, [
            \App\Models\Order::STATUS_AWAITING,
            \App\Models\Order::STATUS_PENDING,
            \App\Models\Order::STATUS_PROCESSING,
            \App\Models\Order::STATUS_VALIDATING,
        ], true);
        $isInProgressOrProcessing = in_array($order->status, [
            \App\Models\Order::STATUS_IN_PROGRESS,
            \App\Models\Order::STATUS_PROCESSING,
        ], true);
        $isBulkFullEligible = in_array($order->status, [
            \App\Models\Order::STATUS_AWAITING,
            \App\Models\Order::STATUS_PENDING,
            \App\Models\Order::STATUS_PROCESSING,
        ], true);
        $canSelectForBulk = $canCancel && ($isBulkFullEligible || $isInProgressOrProcessing);
        $delivered = (int) ($order->delivered ?? 0);
        $quantity = (int) ($order->quantity ?? 0);
        $progress = $quantity > 0 ? round(($delivered / $quantity) * 100, 1) : 0;
        $categoryIcon = $order->service?->icon
            ?: ($order->category?->icon ?? null)
            ?: ($order->service?->category?->icon ?? null);
        $payload = $order->provider_payload ?? [];
        $statusDots = [
            'validating' => 'bg-cyan-500',
            'invalid_link' => 'bg-red-500',
            'restricted' => 'bg-orange-500',
            'awaiting' => 'bg-yellow-500',
            'pending' => 'bg-blue-500',
            'pending_dependency' => 'bg-slate-500',
            'in_progress' => 'bg-indigo-500',
            'processing' => 'bg-purple-500',
            'partial' => 'bg-orange-500',
            'completed' => 'bg-green-500',
            'canceled' => 'bg-red-500',
            'fail' => 'bg-gray-500',
        ];
        $dotClass = $statusDots[$order->status] ?? 'bg-gray-500';
        $statusLabel = ucfirst(str_replace('_', ' ', $order->status));
        $charge = (float) ($order->charge ?? 0);
        $sourceLabel = match($order->source) {
            \App\Models\Order::SOURCE_API => 'API',
            \App\Models\Order::SOURCE_STAFF => 'STAFF',
            default => 'WEB',
        };
        $sourceClasses = match($order->source) {
            \App\Models\Order::SOURCE_API => 'order-source-api',
            \App\Models\Order::SOURCE_STAFF => 'order-source-staff',
            default => 'order-source-web',
        };
        $purposeLabel = match($order->order_purpose ?? 'normal') {
            'refill' => 'REFILL',
            'test' => 'TEST',
            default => null,
        };
        $displayStart = $order->start_count;
        if (is_array($payload) && isset($payload['youtube']['parsed']['start_counts'])) {
            $scArr = $payload['youtube']['parsed']['start_counts'];
            if (is_array($scArr)) {
                $displayStart = $scArr['view'] ?? $scArr['subscribe'] ?? $displayStart;
            }
        }
        return (object) compact(
            'service', 'canCancel', 'isAwaitingOrPending', 'isInProgressOrProcessing',
            'canSelectForBulk', 'delivered', 'quantity', 'progress', 'categoryIcon',
            'dotClass', 'statusLabel', 'charge', 'sourceLabel', 'sourceClasses',
            'purposeLabel', 'displayStart'
        );
    });
@endphp

{{-- ==================== DESKTOP TABLE (hidden on mobile) ==================== --}}
<div class="client-orders-table-wrap rounded-xl overflow-hidden co-desktop-table">
    <x-table id="client-orders-table">
        <x-slot name="header">
            <tr>
                <th scope="col" class="co-th co-th-checkbox px-3 py-3 text-center text-xs font-medium uppercase tracking-wider w-12">
                    <span class="sr-only">{{ __('Select') }}</span>
                    <input type="checkbox" @change="toggleSelectAll($event.target.checked)" x-bind:checked="isAllSelected()" class="co-bulk-checkbox rounded border-[var(--border)] text-[var(--purple)] focus:ring-[var(--purple)]" />
                </th>
                <th scope="col" class="co-th px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">{{ __('Order') }}</th>
                <th scope="col" class="co-th px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">{{ __('Service') }}</th>
                <th scope="col" class="co-th px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">{{ __('Start') }}</th>
                <th scope="col" class="co-th px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">{{ __('Progress') }}</th>
                <th scope="col" class="co-th px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">{{ __('Price') }}</th>
                <th scope="col" class="co-th px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">{{ __('Date') }}</th>
                <th scope="col" class="co-th co-th-actions px-3 py-3 text-center text-xs font-medium uppercase tracking-wider">
                    <span class="sr-only">{{ __('Actions') }}</span>
                    <i class="fa-solid fa-ellipsis-vertical text-base opacity-80" aria-hidden="true"></i>
                </th>
            </tr>
        </x-slot>
        <x-slot name="body">
            @foreach($orders as $idx => $order)
                @php $d = $ordersData[$idx]; @endphp
                <tr>
                    <td class="co-th-checkbox px-3 py-4 whitespace-nowrap text-center align-middle">
                        @if($d->canSelectForBulk)
                            <input type="checkbox" name="order_ids[]" value="{{ $order->id }}" @change="toggleOrder({{ $order->id }}, $event.target.checked)" x-bind:checked="isOrderSelected({{ $order->id }})" class="co-bulk-checkbox rounded border-[var(--border)] text-[var(--purple)] focus:ring-[var(--purple)]" />
                        @else
                            <span class="co-text-muted text-sm">—</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex flex-col gap-1">
                            <div class="flex items-center gap-2">
                                <span class="co-text text-sm font-semibold">ID{{ $order->id }}</span>
                                <button type="button" class="co-copy-btn" title="{{ __('Copy') }}" onclick="navigator.clipboard?.writeText('{{ $order->id }}')">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m-3 4H10m0 0l-2-2m2 2l2-2" /></svg>
                                </button>
                            </div>
                            <div class="flex flex-wrap gap-1">
                                <span class="inline-flex w-fit items-center rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $d->sourceClasses }}">{{ $d->sourceLabel }}</span>
                                @if($d->purposeLabel)
                                    <span class="inline-flex w-fit items-center rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $d->purposeLabel === 'REFILL' ? 'order-source-refill' : 'order-source-test' }}">{{ $d->purposeLabel }}</span>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3 min-w-[240px]" style="max-width: 300px">
                            @include('client.orders._service-ring', ['order' => $order, 'd' => $d])
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="co-text text-sm font-semibold">{{ $order->service_id }}</span>
                                    <span class="co-text-secondary text-sm truncate">{{ $order->service->name ?? '—' }}</span>
                                </div>
                                @if($order->link)
                                    <a href="{{ $order->link }}" target="_blank" rel="noopener noreferrer" class="co-link text-xs hover:underline truncate block" title="{{ $order->link }}">{{ \Illuminate\Support\Str::limit($order->link, 40) }}</a>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="co-text px-6 py-4 whitespace-nowrap text-sm">{{ $d->displayStart !== null ? number_format((int) $d->displayStart) : '—' }}</td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex flex-col gap-1">
                            <div class="co-text flex items-center gap-2 text-sm font-semibold order-status-badge" data-order-id="{{ $order->id }}" data-status="{{ $order->status }}">
                                <span class="h-2.5 w-2.5 rounded-full {{ $d->dotClass }}"></span>
                                <span>{{ $d->statusLabel }}</span>
                            </div>
                            <div class="co-text-muted text-xs order-progress-detail" data-order-id="{{ $order->id }}">{{ number_format($d->delivered) }} {{ __('of') }} {{ number_format($d->quantity) }}</div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap"><div class="co-text text-sm font-semibold">${{ rtrim(rtrim(number_format($d->charge, 4), '0'), '.') }}</div></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex flex-col gap-1">
                            <div class="co-text text-sm font-semibold">{{ $order->created_at->format('d M') }}</div>
                            <div class="co-text-muted text-xs">{{ $order->created_at->format('H:i') }}</div>
                        </div>
                    </td>
                    <td class="px-3 py-4 whitespace-nowrap text-sm font-medium text-center">
                        @include('client.orders._actions-dropdown', ['order' => $order, 'd' => $d])
                    </td>
                </tr>
            @endforeach
        </x-slot>
        <x-slot name="pagination">
            <div data-pagination-root>
                <x-pagination :currentPage="$orders->currentPage()" :lastPage="$orders->lastPage()" :hasPages="$orders->hasPages()" id="client-orders-pagination" />
            </div>
        </x-slot>
    </x-table>
</div>

{{-- ==================== MOBILE CARDS (hidden on desktop) ==================== --}}
<div class="co-mobile-cards" id="client-orders-table">
    @foreach($orders as $idx => $order)
        @php $d = $ordersData[$idx]; @endphp
        <div class="co-order-card" onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'client-order-details-{{ $order->id }}' }))">
            {{-- Left: service icon ring --}}
            @include('client.orders._service-ring', ['order' => $order, 'd' => $d])
            {{-- Middle: info --}}
            <div class="co-order-card-info">
                <div class="co-order-card-service">
                    <span class="co-order-card-sid">#{{ $order->id }}</span>
                    <span class="co-order-card-svc-id">{{ $order->service_id }}</span>
                    <span class="co-order-card-sname">{{ \Illuminate\Support\Str::limit($order->service->name ?? '—', 28) }}</span>
                </div>
                <div class="co-order-card-meta">
                    <span class="co-order-card-progress order-progress-detail" data-order-id="{{ $order->id }}">{{ number_format($d->delivered) }} {{ __('of') }} {{ number_format($d->quantity) }}</span>
                    <span class="co-order-card-dot">·</span>
                    <span class="co-order-card-status order-status-badge" data-order-id="{{ $order->id }}" data-status="{{ $order->status }}">
                        <span class="h-2 w-2 rounded-full {{ $d->dotClass }}" style="display:inline-block;"></span>
                        {{ $d->statusLabel }}
                    </span>
                </div>
                <div class="co-order-card-bottom">
                    <span class="co-order-card-price">${{ rtrim(rtrim(number_format($d->charge, 4), '0'), '.') }}</span>
                    <span class="co-order-card-dot">·</span>
                    <span class="co-order-card-date">{{ $order->created_at->format('d M') }}</span>
                </div>
            </div>
            {{-- Right: actions --}}
            <div class="co-order-card-actions" onclick="event.stopPropagation()">
                <button type="button"
                    onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'client-order-details-{{ $order->id }}' }))"
                    class="co-order-card-action-btn" title="{{ __('View') }}">
                    <i class="fa-solid fa-eye"></i>
                </button>
                @if($d->canCancel && $d->isAwaitingOrPending)
                    <button type="button"
                        onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'cancel-full-{{ $order->id }}' }))"
                        class="co-order-card-action-btn co-order-card-action-danger" title="{{ __('Cancel') }}">
                        <i class="fa-solid fa-ban"></i>
                    </button>
                @elseif($d->canCancel && $d->isInProgressOrProcessing)
                    <button type="button"
                        onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'cancel-partial-{{ $order->id }}' }))"
                        class="co-order-card-action-btn co-order-card-action-warn" title="{{ __('Cancel Partial') }}">
                        <i class="fa-solid fa-ban"></i>
                    </button>
                @endif
            </div>
        </div>
    @endforeach

    {{-- Pagination --}}
    <div data-pagination-root style="margin-top: 12px;">
        <x-pagination :currentPage="$orders->currentPage()" :lastPage="$orders->lastPage()" :hasPages="$orders->hasPages()" id="client-orders-pagination" />
    </div>
</div>

{{-- Cancel modals (outside both table and cards so they work everywhere) --}}
@foreach($orders as $idx => $order)
    @php $d = $ordersData[$idx]; @endphp
    @if($d->canCancel && $d->isAwaitingOrPending)
        <div x-data="{ submitForm() { document.getElementById('cancel-full-form-{{ $order->id }}').submit(); } }"
             x-on:confirm-action.window="if ($event.detail === 'cancel-full-{{ $order->id }}') { submitForm(); }">
            <x-confirm-modal
                name="cancel-full-{{ $order->id }}"
                theme="smm"
                title="{{ __('Cancel Order') }}"
                :message="__('Are you sure you want to cancel this order? A full refund of $:amount will be processed.', ['amount' => $order->charge])"
                confirm-text="{{ __('Yes, Cancel Order') }}"
                cancel-text="{{ __('No, Keep Order') }}"
                confirm-button-class="bg-red-600 hover:bg-red-700"
            >
                <form id="cancel-full-form-{{ $order->id }}" action="{{ route('client.orders.cancelFull', $order) }}" method="POST" style="display:none;">@csrf</form>
            </x-confirm-modal>
        </div>
    @elseif($d->canCancel && $d->isInProgressOrProcessing)
        <div x-data="{ submitForm() { document.getElementById('cancel-partial-form-{{ $order->id }}').submit(); } }"
             x-on:confirm-action.window="if ($event.detail === 'cancel-partial-{{ $order->id }}') { submitForm(); }">
            <x-confirm-modal
                name="cancel-partial-{{ $order->id }}"
                theme="smm"
                title="{{ __('Cancel Remaining Part') }}"
                :message="__('Are you sure you want to cancel the remaining part of this order? A partial refund will be processed for the undelivered quantity.')"
                confirm-text="{{ __('Yes, Cancel Remaining') }}"
                cancel-text="{{ __('No, Keep Order') }}"
                confirm-button-class="bg-orange-600 hover:bg-orange-700"
            >
                <form id="cancel-partial-form-{{ $order->id }}" action="{{ route('client.orders.cancelPartial', $order) }}" method="POST" style="display:none;">@csrf</form>
            </x-confirm-modal>
        </div>
    @endif
@endforeach
