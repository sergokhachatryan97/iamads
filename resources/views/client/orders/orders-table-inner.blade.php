{{-- Staff-style order table (client: no User column, no bulk checkboxes) --}}
<div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
    <x-table id="client-orders-table">
        <x-slot name="header">
            <tr>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    {{ __('Order') }}
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
                    $sourceLabel = $order->source === \App\Models\Order::SOURCE_API ? 'API' : 'WEB';
                    $sourceClasses = $order->source === \App\Models\Order::SOURCE_API
                        ? 'bg-gray-100 text-gray-700'
                        : 'bg-indigo-50 text-indigo-700';
                @endphp
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap">
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
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3 min-w-[240px]" style="max-width: 300px">
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
                        @php
                            $displayStart = $order->start_count;
                            if (is_array($payload) && isset($payload['youtube']['parsed']['start_counts'])) {
                                $scArr = $payload['youtube']['parsed']['start_counts'];
                                if (is_array($scArr)) {
                                    $displayStart = $scArr['view'] ?? $scArr['subscribe'] ?? $displayStart;
                                }
                            }
                        @endphp
                        {{ $displayStart !== null ? number_format((int) $displayStart) : '—' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
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
                        <div class="text-sm font-semibold text-gray-900">${{ number_format($charge, 4) }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex flex-col gap-1">
                            <div class="text-sm font-semibold text-gray-900">{{ $order->created_at->format('d M') }}</div>
                            <div class="text-xs text-gray-500">{{ $order->created_at->format('H:i') }}</div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
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
                                    <div
                                        x-data="{
                                            openModal() { $dispatch('open-modal', 'cancel-full-{{ $order->id }}'); },
                                            submitForm() { document.getElementById('cancel-full-form-{{ $order->id }}').submit(); }
                                        }"
                                        x-on:confirm-action.window="if ($event.detail === 'cancel-full-{{ $order->id }}') { submitForm(); }"
                                    >
                                        <button
                                            type="button"
                                            @click="openModal()"
                                            class="block w-full px-4 py-2 text-start text-sm leading-5 text-red-700 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 transition duration-150 ease-in-out"
                                        >
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
                                            <form id="cancel-full-form-{{ $order->id }}" action="{{ route('client.orders.cancelFull', $order) }}" method="POST" class="hidden">
                                                @csrf
                                            </form>
                                        </x-confirm-modal>
                                    </div>
                                @elseif($canCancel && $isInProgressOrProcessing)
                                    <div
                                        x-data="{
                                            openModal() { $dispatch('open-modal', 'cancel-partial-{{ $order->id }}'); },
                                            submitForm() { document.getElementById('cancel-partial-form-{{ $order->id }}').submit(); }
                                        }"
                                        x-on:confirm-action.window="if ($event.detail === 'cancel-partial-{{ $order->id }}') { submitForm(); }"
                                    >
                                        <button
                                            type="button"
                                            @click="openModal()"
                                            class="block w-full px-4 py-2 text-start text-sm leading-5 text-orange-700 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 transition duration-150 ease-in-out"
                                        >
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
                                            <form id="cancel-partial-form-{{ $order->id }}" action="{{ route('client.orders.cancelPartial', $order) }}" method="POST" class="hidden">
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
                    id="client-orders-pagination"
                />
            </div>
        </x-slot>
    </x-table>
</div>
