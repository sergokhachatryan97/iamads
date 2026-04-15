<x-client-layout :title="__('Order Created')">
    <style>
        .order-success-page {
            max-width: 540px;
            margin: 0 auto;
            padding: 32px 16px 48px;
        }
        .order-success-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 32px 22px 28px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
            text-align: center;
        }
        [data-theme="light"] .order-success-card { box-shadow: 0 12px 40px rgba(0, 0, 0, 0.06); }

        .order-success-icon {
            width: 64px; height: 64px;
            margin: 0 auto 16px;
        }
        .order-success-icon svg { width: 100%; height: 100%; }

        .order-success-title {
            font-size: 22px;
            font-weight: 800;
            color: var(--text1);
            margin-bottom: 8px;
        }
        .order-success-charge {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 20px;
            background: rgba(46, 213, 115, 0.12);
            color: #2ed573;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 24px;
        }

        .order-success-details {
            background: var(--bg2);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 24px;
        }
        .order-success-order-id {
            font-size: 28px;
            font-weight: 800;
            color: var(--text1);
            margin-bottom: 4px;
        }
        .order-success-service {
            font-size: 13px;
            color: var(--text3);
            margin-bottom: 10px;
            line-height: 1.5;
        }
        .order-success-link {
            font-size: 13px;
            color: var(--purple-light);
            word-break: break-all;
        }
        .order-success-link a {
            color: var(--purple-light);
            text-decoration: none;
        }
        .order-success-link a:hover { text-decoration: underline; }

        /* Multi-order table — desktop */
        .order-success-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .order-success-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            text-align: left;
        }
        .order-success-table th {
            color: var(--text3);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 8px 6px;
            border-bottom: 1px solid var(--border);
            font-size: 10px;
            white-space: nowrap;
        }
        .order-success-table td {
            color: var(--text2);
            padding: 8px 6px;
            border-bottom: 1px solid var(--border);
            vertical-align: top;
        }
        .order-success-table tr:last-child td { border-bottom: none; }
        .order-success-table .cell-id { font-weight: 700; color: var(--text1); }
        .order-success-table .cell-link {
            color: var(--purple-light);
            word-break: break-all;
            max-width: 140px;
        }
        .order-success-table .cell-link a { color: var(--purple-light); text-decoration: none; }
        .order-success-table .cell-link a:hover { text-decoration: underline; }
        .order-success-table .cell-amount { font-weight: 600; }

        .order-success-total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 6px 0;
            font-size: 13px;
            font-weight: 700;
            color: var(--text1);
        }

        /* Multi-order cards — mobile only */
        .order-success-cards { display: none; }
        .order-success-card-item {
            background: var(--bg2);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 8px;
        }
        .order-success-card-item:last-child { margin-bottom: 0; }
        .order-success-card-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            padding: 3px 0;
            font-size: 12px;
        }
        .order-success-card-label {
            color: var(--text3);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 10px;
            letter-spacing: 0.04em;
            flex-shrink: 0;
            margin-right: 8px;
        }
        .order-success-card-value {
            color: var(--text2);
            text-align: right;
            word-break: break-all;
        }
        .order-success-card-value a { color: var(--purple-light); text-decoration: none; }
        .order-success-card-value a:hover { text-decoration: underline; }
        .order-success-card-value.val-id { font-weight: 700; color: var(--text1); }
        .order-success-card-value.val-amount { font-weight: 600; }

        .order-success-buttons {
            display: flex;
            gap: 12px;
        }
        .order-success-btn {
            flex: 1;
            padding: 14px 20px;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
            transition: filter 0.15s;
            border: none;
        }
        .order-success-btn:hover { filter: brightness(1.06); }
        .order-success-btn-primary {
            background: var(--purple);
            color: #fff !important;
            box-shadow: 0 6px 24px rgba(108, 92, 231, 0.4);
        }
        .order-success-btn-secondary {
            background: transparent;
            color: var(--text2) !important;
            border: 1px solid var(--border);
        }
        .order-success-btn-secondary:hover { color: var(--text1) !important; border-color: var(--text3); }

        /* ---- Responsive ---- */
        @media (max-width: 580px) {
            .order-success-page { padding: 16px 10px 32px; }
            .order-success-card { padding: 24px 16px 22px; border-radius: 16px; }
            .order-success-icon { width: 52px; height: 52px; margin-bottom: 12px; }
            .order-success-title { font-size: 18px; }
            .order-success-order-id { font-size: 24px; }
            .order-success-details { padding: 14px; }
            .order-success-btn { padding: 12px 14px; font-size: 12px; border-radius: 12px; }
        }

        @media (max-width: 480px) {
            /* Hide table, show cards on small screens */
            .order-success-table-wrap { display: none; }
            .order-success-cards { display: block; }
            .order-success-buttons { flex-direction: column; }
            .order-success-btn { width: 100%; }
        }
    </style>

    <div class="order-success-page">
        <div class="order-success-card">
            <!-- Success Icon -->
            <div class="order-success-icon">
                <svg viewBox="0 0 64 64" fill="none">
                    <circle cx="32" cy="32" r="32" fill="#2ed573" fill-opacity="0.15"/>
                    <path d="M32 8C18.745 8 8 18.745 8 32s10.745 24 24 24 24-10.745 24-24S45.255 8 32 8z" fill="#2ed573"/>
                    <path d="M27 33l4 4 8-10" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M32 4l2.5 4.5L32 8l-2.5.5L32 4zM32 56l2.5-4.5L32 56l-2.5-.5L32 60zM4 32l4.5-2.5L4 32l.5 2.5L4 32zM60 32l-4.5 2.5L60 32l-.5-2.5L60 32z" fill="#2ed573" fill-opacity="0.4"/>
                </svg>
            </div>

            @if($isSingle)
                <!-- Single Order -->
                <div class="order-success-title">{{ __('Order Created') }}</div>
                <div class="order-success-charge">${{ number_format($totalCharge, 4) }} {{ __('payment success') }}</div>

                <div class="order-success-details">
                    <div class="order-success-order-id">{{ $orders[0]->id }}</div>
                    <div class="order-success-service">
                        {{ $orders[0]->service_id }} - {{ $orders[0]->service->name ?? '' }}
                    </div>
                    <div class="order-success-link">
                        <a href="{{ $orders[0]->link }}" target="_blank" rel="noopener">{{ Str::limit($orders[0]->link, 60) }}</a>
                    </div>
                </div>
            @else
                <!-- Multiple Orders -->
                <div class="order-success-title">{{ __('Orders Created') }}</div>
                <div class="order-success-charge">${{ number_format($totalCharge, 4) }} {{ __('payment success') }}</div>

                <div class="order-success-details" style="text-align: left;">
                    <!-- Desktop: table -->
                    <div class="order-success-table-wrap">
                        <table class="order-success-table">
                            <thead>
                                <tr>
                                    <th>{{ __('ID') }}</th>
                                    <th>{{ __('Service') }}</th>
                                    <th>{{ __('Link') }}</th>
                                    <th style="text-align:right;">{{ __('Qty') }}</th>
                                    <th style="text-align:right;">{{ __('Amount') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($orders as $order)
                                    <tr>
                                        <td class="cell-id">{{ $order->id }}</td>
                                        <td>{{ $order->service_id }} - {{ Str::limit($order->service->name ?? '', 30) }}</td>
                                        <td class="cell-link">
                                            <a href="{{ $order->link }}" target="_blank" rel="noopener">{{ Str::limit($order->link, 25) }}</a>
                                        </td>
                                        <td style="text-align:right;">{{ number_format($order->quantity) }}</td>
                                        <td style="text-align:right;" class="cell-amount">${{ number_format((float)$order->charge, 4) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile: card list -->
                    <div class="order-success-cards">
                        @foreach($orders as $order)
                            <div class="order-success-card-item">
                                <div class="order-success-card-row">
                                    <span class="order-success-card-label">{{ __('ID') }}</span>
                                    <span class="order-success-card-value val-id">{{ $order->id }}</span>
                                </div>
                                <div class="order-success-card-row">
                                    <span class="order-success-card-label">{{ __('Service') }}</span>
                                    <span class="order-success-card-value">{{ $order->service_id }} - {{ Str::limit($order->service->name ?? '', 25) }}</span>
                                </div>
                                <div class="order-success-card-row">
                                    <span class="order-success-card-label">{{ __('Link') }}</span>
                                    <span class="order-success-card-value">
                                        <a href="{{ $order->link }}" target="_blank" rel="noopener">{{ Str::limit($order->link, 30) }}</a>
                                    </span>
                                </div>
                                <div class="order-success-card-row">
                                    <span class="order-success-card-label">{{ __('Qty') }}</span>
                                    <span class="order-success-card-value">{{ number_format($order->quantity) }}</span>
                                </div>
                                <div class="order-success-card-row">
                                    <span class="order-success-card-label">{{ __('Amount') }}</span>
                                    <span class="order-success-card-value val-amount">${{ number_format((float)$order->charge, 4) }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="order-success-total-row">
                        <span>{{ __('Total') }}</span>
                        <span>${{ number_format($totalCharge, 4) }}</span>
                    </div>
                </div>
            @endif

            <!-- Buttons -->
            <div class="order-success-buttons">
                <a href="{{ route('client.orders.index') }}" class="order-success-btn order-success-btn-primary">
                    {{ __('My Orders') }}
                </a>
                <a href="{{ route('client.orders.create') }}" class="order-success-btn order-success-btn-secondary">
                    {{ __('Next Order') }}
                </a>
            </div>
        </div>
    </div>
</x-client-layout>
