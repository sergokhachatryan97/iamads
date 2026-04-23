<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Failed Telegram Tasks') }}</h2>
            <form method="POST" action="{{ route('staff.failed-telegram-tasks.delete-completed') }}" onsubmit="return confirm('Delete ALL failed tasks & memberships for COMPLETED orders?')">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 text-white text-sm font-semibold rounded-lg hover:bg-red-700 transition-colors">
                    <i class="fa-solid fa-broom"></i>
                    {{ __('Clean Completed Orders') }}
                </button>
            </form>
        </div>
    </x-slot>

    <style>
        .ft-summary { display: flex; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; }
        .ft-summary-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px 18px; flex: 1; min-width: 120px; }
        .ft-summary-val { font-size: 22px; font-weight: 800; color: #111827; }
        .ft-summary-sub { font-size: 11px; color: #9ca3af; }
        .ft-summary-label { font-size: 12px; color: #6b7280; margin-top: 2px; }
        .ft-tabs { display: flex; gap: 4px; margin-bottom: 16px; }
        .ft-tab { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 8px; font-size: 14px; font-weight: 600; text-decoration: none; transition: all 0.15s; }
        .ft-tab-idle { color: #6b7280; background: #f3f4f6; }
        .ft-tab-idle:hover { background: #e5e7eb; color: #374151; }
        .ft-tab-active { color: #fff; background: #6366f1; }
        .ft-badge { min-width: 20px; padding: 1px 6px; border-radius: 9999px; font-size: 11px; font-weight: 700; text-align: center; }
        .ft-badge-idle { background: #d1d5db; color: #374151; }
        .ft-badge-active { background: rgba(255,255,255,0.25); color: #fff; }
        .ft-filters { display: flex; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; align-items: end; }
        .ft-filter-group { display: flex; flex-direction: column; gap: 4px; }
        .ft-filter-group label { font-size: 12px; font-weight: 600; color: #6b7280; }
        .ft-filter-group input, .ft-filter-group select { padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; color: #374151; background: #fff; }
        .ft-filter-group input:focus, .ft-filter-group select:focus { border-color: #6366f1; outline: none; box-shadow: 0 0 0 2px rgba(99,102,241,0.2); }
        .ft-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; margin-bottom: 10px; overflow: hidden; transition: border-color 0.15s; }
        .ft-card:hover { border-color: #a5b4fc; }
        .ft-card-header { display: flex; align-items: center; gap: 12px; padding: 14px 16px; flex-wrap: wrap; }
        .ft-card-order { font-size: 15px; font-weight: 700; color: #111827; }
        .ft-card-order a { color: #6366f1; text-decoration: none; }
        .ft-card-order a:hover { text-decoration: underline; }
        .ft-card-svc { font-size: 13px; color: #6b7280; flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .ft-card-count { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 9999px; font-size: 12px; font-weight: 700; background: #fef2f2; color: #dc2626; }
        .ft-card-status { padding: 2px 8px; border-radius: 9999px; font-size: 11px; font-weight: 600; }
        .ft-status-completed { background: #f0fdf4; color: #16a34a; }
        .ft-status-in_progress { background: #eef2ff; color: #4f46e5; }
        .ft-status-awaiting { background: #fffbeb; color: #d97706; }
        .ft-status-canceled, .ft-status-fail { background: #fef2f2; color: #dc2626; }
        .ft-status-default { background: #f3f4f6; color: #6b7280; }
        .ft-card-meta { font-size: 12px; color: #9ca3af; }
        .ft-card-actions { margin-left: auto; flex-shrink: 0; }
        .ft-delete-order-btn { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 8px; border: 1px solid #fecaca; background: #fff; color: #ef4444; font-size: 12px; font-weight: 600; cursor: pointer; transition: all 0.15s; font-family: inherit; }
        .ft-delete-order-btn:hover { background: #fef2f2; border-color: #f87171; }
        .ft-card-errors { padding: 0 16px 14px; }
        .ft-error-item { display: flex; gap: 8px; padding: 8px 10px; background: #fef2f2; border-radius: 8px; margin-bottom: 4px; font-size: 12px; align-items: flex-start; }
        .ft-error-msg { color: #991b1b; flex: 1; min-width: 0; word-break: break-word; line-height: 1.4; }
        .ft-error-meta { color: #9ca3af; font-size: 11px; flex-shrink: 0; white-space: nowrap; }
        .ft-error-action { color: #6b7280; font-size: 11px; font-weight: 600; flex-shrink: 0; background: #f3f4f6; padding: 1px 6px; border-radius: 4px; }
        .ft-more-errors { font-size: 11px; color: #9ca3af; padding: 4px 10px; }
        .ft-error-summary { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; margin-bottom: 16px; overflow: hidden; }
        .ft-error-summary-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 12px 16px; cursor: pointer; user-select: none;
            font-size: 14px; font-weight: 700; color: #374151;
        }
        .ft-error-summary-header:hover { background: #f9fafb; }
        .ft-error-summary-header i { color: #9ca3af; transition: transform 0.2s; }
        .ft-error-summary-body { border-top: 1px solid #e5e7eb; }
        .ft-error-summary-row {
            display: flex; align-items: flex-start; gap: 12px;
            padding: 10px 16px; border-bottom: 1px solid #f3f4f6;
            font-size: 13px;
        }
        .ft-error-summary-row:last-child { border-bottom: none; }
        .ft-error-summary-row:hover { background: #fef2f2; }
        .ft-error-summary-text { flex: 1; min-width: 0; color: #111827; word-break: break-word; line-height: 1.4; font-size: 13px; font-weight: 600; }
        .ft-error-summary-counts { display: flex; gap: 8px; flex-shrink: 0; align-items: center; }
        .ft-error-summary-pill { padding: 2px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; white-space: nowrap; }
        .ft-error-summary-pill-count { background: #fee2e2; color: #dc2626; }
        .ft-error-summary-pill-orders { background: #f3f4f6; color: #6b7280; }
        .ft-empty { text-align: center; padding: 48px 20px; color: #9ca3af; font-size: 14px; }
        .ft-empty i { font-size: 36px; color: #10b981; margin-bottom: 12px; display: block; }
        @media (max-width: 768px) {
            .ft-filters { flex-direction: column; }
            .ft-filter-group { width: 100%; }
            .ft-filter-group input, .ft-filter-group select { width: 100%; font-size: 16px; }
            .ft-card-header { flex-direction: column; align-items: flex-start; gap: 8px; }
            .ft-card-actions { align-self: stretch; }
            .ft-delete-order-btn { width: 100%; justify-content: center; }
            .ft-summary-card { min-width: 0; }
        }
    </style>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">{{ session('success') }}</div>
            @endif

            {{-- Summary --}}
            <div class="ft-summary">
                <div class="ft-summary-card">
                    <div class="ft-summary-val">{{ number_format($taskCount) }}</div>
                    <div class="ft-summary-label">{{ __('Failed Tasks') }}</div>
                    <div class="ft-summary-sub">{{ number_format($taskOrderCount) }} {{ __('orders') }}</div>
                </div>
                <div class="ft-summary-card">
                    <div class="ft-summary-val">{{ number_format($membershipCount) }}</div>
                    <div class="ft-summary-label">{{ __('Failed Memberships') }}</div>
                    <div class="ft-summary-sub">{{ number_format($membershipOrderCount) }} {{ __('orders') }}</div>
                </div>
                <div class="ft-summary-card">
                    <div class="ft-summary-val">{{ number_format($taskCount + $membershipCount) }}</div>
                    <div class="ft-summary-label">{{ __('Total Failed') }}</div>
                </div>
            </div>

            {{-- Tabs --}}
            <div class="ft-tabs">
                <a href="{{ route('staff.failed-telegram-tasks.index', array_merge(request()->except('tab', 'page'), ['tab' => 'tasks'])) }}"
                   class="ft-tab {{ $tab === 'tasks' ? 'ft-tab-active' : 'ft-tab-idle' }}">
                    {{ __('Tasks') }}
                    <span class="ft-badge {{ $tab === 'tasks' ? 'ft-badge-active' : 'ft-badge-idle' }}">{{ number_format($taskCount) }}</span>
                </a>
                <a href="{{ route('staff.failed-telegram-tasks.index', array_merge(request()->except('tab', 'page'), ['tab' => 'memberships'])) }}"
                   class="ft-tab {{ $tab === 'memberships' ? 'ft-tab-active' : 'ft-tab-idle' }}">
                    {{ __('Memberships') }}
                    <span class="ft-badge {{ $tab === 'memberships' ? 'ft-badge-active' : 'ft-badge-idle' }}">{{ number_format($membershipCount) }}</span>
                </a>
            </div>

            {{-- Filters --}}
            <form method="GET" action="{{ route('staff.failed-telegram-tasks.index') }}" class="ft-filters">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <div class="ft-filter-group">
                    <label>{{ __('Order ID') }}</label>
                    <input type="number" name="order_id" value="{{ $filterOrderId }}" placeholder="{{ __('All') }}" min="1">
                </div>
                <div class="ft-filter-group">
                    <label>{{ __('Order Status') }}</label>
                    <select name="order_status">
                        <option value="all" {{ $filterStatus === 'all' ? 'selected' : '' }}>{{ __('All') }}</option>
                        <option value="completed" {{ $filterStatus === 'completed' ? 'selected' : '' }}>{{ __('Completed') }}</option>
                        <option value="in_progress" {{ $filterStatus === 'in_progress' ? 'selected' : '' }}>{{ __('In Progress') }}</option>
                        <option value="awaiting" {{ $filterStatus === 'awaiting' ? 'selected' : '' }}>{{ __('Awaiting') }}</option>
                        <option value="canceled" {{ $filterStatus === 'canceled' ? 'selected' : '' }}>{{ __('Canceled') }}</option>
                        <option value="fail" {{ $filterStatus === 'fail' ? 'selected' : '' }}>{{ __('Failed') }}</option>
                    </select>
                </div>
                <div class="ft-filter-group" style="justify-content:flex-end;">
                    <label>&nbsp;</label>
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700">{{ __('Filter') }}</button>
                </div>
            </form>

            {{-- Error Summary --}}
            @if($errorSummary->isNotEmpty())
                <div class="ft-error-summary" x-data="{ open: false }">
                    <div class="ft-error-summary-header" @click="open = !open">
                        <span><i class="fa-solid fa-chart-bar" style="margin-right:6px;color:#ef4444;"></i> {{ $tab === 'tasks' ? __('Failed by Action') : __('Failed by Error') }} ({{ $errorSummary->count() }})</span>
                        <i class="fa-solid fa-chevron-down" :class="open ? 'rotate-180' : ''" style="transition:transform 0.2s;"></i>
                    </div>
                    <div class="ft-error-summary-body" x-show="open" x-collapse x-cloak>
                        @foreach($errorSummary as $es)
                            <div class="ft-error-summary-row">
                                <div class="ft-error-summary-text">{{ $es->error_key }}</div>
                                <div class="ft-error-summary-counts">
                                    <span class="ft-error-summary-pill ft-error-summary-pill-count">{{ number_format($es->error_count) }}</span>
                                    <span class="ft-error-summary-pill ft-error-summary-pill-orders">{{ number_format($es->order_count) }} {{ __('orders') }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Grouped Items --}}
            @if($grouped->isEmpty())
                <div class="ft-empty">
                    <i class="fa-solid fa-check-circle"></i>
                    {{ __('No failed records found.') }}
                </div>
            @else
                @foreach($grouped as $row)
                    @php
                        $order = $orders[$row->order_id] ?? null;
                        $statusKey = $order->status ?? 'unknown';
                        $statusClass = match($statusKey) {
                            'completed' => 'ft-status-completed',
                            'in_progress', 'processing' => 'ft-status-in_progress',
                            'awaiting', 'pending' => 'ft-status-awaiting',
                            'canceled', 'fail' => 'ft-status-canceled',
                            default => 'ft-status-default',
                        };
                    @endphp
                    <div class="ft-card">
                        <div class="ft-card-header">
                            <span class="ft-card-order">
                                <a href="{{ route('staff.orders.index', ['search' => $row->order_id]) }}">#{{ $row->order_id }}</a>
                            </span>
                            <span class="ft-card-status {{ $statusClass }}">{{ ucfirst(str_replace('_', ' ', $statusKey)) }}</span>
                            <span class="ft-card-svc">{{ $order?->service?->name ?? '—' }}</span>
                            <span class="ft-card-count">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                {{ number_format($row->failed_count) }} {{ __('failed') }}
                            </span>
                            <span class="ft-card-meta">{{ \Carbon\Carbon::parse($row->last_failed_at)->format('M d, H:i') }}</span>
                            <div class="ft-card-actions">
                                <form method="POST" action="{{ route('staff.failed-telegram-tasks.delete-by-order') }}" onsubmit="return confirm('Delete {{ $row->failed_count }} failed records for order #{{ $row->order_id }}?')">
                                    @csrf
                                    <input type="hidden" name="order_id" value="{{ $row->order_id }}">
                                    <button type="submit" class="ft-delete-order-btn">
                                        <i class="fa-solid fa-trash-can"></i>
                                        {{ __('Delete') }} ({{ $row->failed_count }})
                                    </button>
                                </form>
                            </div>
                        </div>
                        @if(isset($errors[$row->order_id]) && $errors[$row->order_id]->isNotEmpty())
                            <div class="ft-card-errors">
                                @foreach($errors[$row->order_id] as $err)
                                    <div class="ft-error-item">
                                        @if($tab === 'tasks')
                                            @php
                                                $errResult = is_array($err->result) ? $err->result : [];
                                                $errMsg = $errResult['error'] ?? $errResult['message'] ?? $errResult['reason'] ?? json_encode($errResult);
                                            @endphp
                                            <span class="ft-error-action">{{ $err->action ?? '—' }}</span>
                                            <span class="ft-error-msg">{{ Str::limit($errMsg, 150) }}</span>
                                        @else
                                            <span class="ft-error-action">{{ $err->account_phone ?? '—' }}</span>
                                            <span class="ft-error-msg">{{ Str::limit($err->last_error, 150) }}</span>
                                        @endif
                                        <span class="ft-error-meta">{{ $err->updated_at?->format('M d H:i') }}</span>
                                    </div>
                                @endforeach
                                @if($row->failed_count > 3)
                                    <div class="ft-more-errors">+ {{ $row->failed_count - 3 }} {{ __('more errors') }}</div>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach

                <div class="mt-4">
                    {{ $grouped->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
