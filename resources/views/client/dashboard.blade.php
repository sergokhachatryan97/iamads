<x-client-smm-layout :title="__('Dashboard')" :client="$client" :contactTelegram="$contactTelegram">
    <style>
        .smm-dash-stats { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 14px; margin-bottom: 28px; }
        .smm-dash-stat {
            background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 18px 18px;
            position: relative; overflow: hidden;
        }
        .smm-dash-stat::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
            background: linear-gradient(90deg, var(--purple), var(--teal));
        }
        .smm-dash-stat-val { font-size: 26px; font-weight: 800; background: linear-gradient(135deg, var(--purple), var(--teal)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .smm-dash-stat-label { font-size: 12px; color: var(--text3); margin-top: 4px; }
        .smm-dash-stat-trend { font-size: 11px; margin-top: 8px; font-weight: 600; }
        .smm-dash-stat-trend.up { color: #00b894; }
        .smm-dash-stat-trend.down { color: #e17055; }
        .smm-dash-stat-trend.neutral { color: var(--text3); }
        .smm-dash-grid2 { display: grid; grid-template-columns: 1fr; gap: 18px; margin-bottom: 28px; }
        @media (min-width: 1024px) { .smm-dash-grid2 { grid-template-columns: 1.4fr 1fr; } }
        .smm-dash-panel { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 18px 20px; }
        .smm-dash-panel h3 { font-size: 14px; font-weight: 700; margin: 0 0 16px; }
        .smm-dash-bars { display: flex; align-items: flex-end; gap: 8px; height: 140px; padding-top: 8px; }
        .smm-dash-bar-wrap { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 8px; height: 100%; justify-content: flex-end; }
        .smm-dash-bar {
            width: 100%; max-width: 36px; border-radius: 6px 6px 0 0;
            background: linear-gradient(180deg, var(--purple-light), var(--purple-dark));
            min-height: 4px; transition: height 0.3s ease;
        }
        .smm-dash-bar-label { font-size: 10px; color: var(--text3); }
        .smm-dash-quick { display: flex; flex-direction: column; gap: 10px; }
        .smm-dash-quick-row { display: flex; justify-content: space-between; align-items: center; font-size: 13px; padding: 8px 0; border-bottom: 1px solid var(--border); }
        .smm-dash-quick-row:last-child { border-bottom: 0; }
        .smm-dash-quick-row span:first-child { color: var(--text2); }
        .smm-dash-quick-row strong { color: var(--teal); font-weight: 700; }
        .smm-dash-table-wrap { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; }
        .smm-dash-table-wrap h3 { padding: 16px 18px; margin: 0; font-size: 14px; font-weight: 700; border-bottom: 1px solid var(--border); }
        .smm-dash-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .smm-dash-table th { text-align: left; padding: 10px 14px; color: var(--text3); font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; font-size: 10px; border-bottom: 1px solid var(--border); background: var(--card2); }
        .smm-dash-table td { padding: 12px 14px; border-bottom: 1px solid var(--border); color: var(--text2); vertical-align: middle; }
        .smm-dash-table tr:last-child td { border-bottom: 0; }
        .smm-dash-table a { color: var(--purple-light); text-decoration: none; font-weight: 600; }
        .smm-dash-table a:hover { text-decoration: underline; }
        .smm-dash-badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 100px; font-size: 11px; font-weight: 600; }
        .smm-dash-badge.api { background: rgba(108,92,231,0.15); color: var(--purple-light); }
        .smm-dash-badge.web { background: rgba(0,210,211,0.12); color: var(--teal); }
        .smm-dash-viewall { display: inline-block; margin-top: 12px; font-size: 13px; font-weight: 600; color: var(--purple-light); text-decoration: none; }
        .smm-dash-viewall:hover { text-decoration: underline; }
        .smm-dash-empty { text-align: center; padding: 32px 16px; color: var(--text3); font-size: 14px; }
    </style>

    <div class="smm-dash-page">
        <div class="smm-dash-stats">
            <div class="smm-dash-stat">
                <div class="smm-dash-stat-val">{{ number_format($totalOrders) }}</div>
                <div class="smm-dash-stat-label">{{ __('Total Orders') }}</div>
                @if($ordersTrendPct !== null)
                    <div class="smm-dash-stat-trend {{ $ordersTrendPct >= 0 ? 'up' : 'down' }}">
                        {{ $ordersTrendPct >= 0 ? '+' : '' }}{{ $ordersTrendPct }}% {{ __('vs last month') }}
                    </div>
                @else
                    <div class="smm-dash-stat-trend neutral">—</div>
                @endif
            </div>
            <div class="smm-dash-stat">
                <div class="smm-dash-stat-val">${{ number_format($spentThisMonth, 2) }}</div>
                <div class="smm-dash-stat-label">{{ __('This month') }}</div>
                @if($spentTrendPct !== null)
                    <div class="smm-dash-stat-trend {{ $spentTrendPct >= 0 ? 'up' : 'down' }}">
                        {{ $spentTrendPct >= 0 ? '+' : '' }}{{ $spentTrendPct }}% {{ __('vs last month') }}
                    </div>
                @else
                    <div class="smm-dash-stat-trend neutral">—</div>
                @endif
            </div>
            <div class="smm-dash-stat">
                <div class="smm-dash-stat-val">{{ number_format($activeOrdersCount) }}</div>
                <div class="smm-dash-stat-label">{{ __('Active orders') }}</div>
                <div class="smm-dash-stat-trend neutral">{{ __('In progress') }}</div>
            </div>
        </div>

        <div class="smm-dash-grid2">
            <div class="smm-dash-panel">
                <h3>{{ __('Orders overview') }}</h3>
                <div class="smm-dash-bars" role="img" aria-label="{{ __('Orders per month') }}">
                    @foreach($chartMonths as $m)
                        @php $h = $chartMax > 0 ? round(($m['count'] / $chartMax) * 100) : 0; @endphp
                        <div class="smm-dash-bar-wrap">
                            <div class="smm-dash-bar" style="height: {{ max(4, $h) }}%"></div>
                            <span class="smm-dash-bar-label">{{ $m['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="smm-dash-panel">
                <h3>{{ __('Quick stats') }}</h3>
                <div class="smm-dash-quick">
                    @forelse($platformStats as $row)
                        <div class="smm-dash-quick-row">
                            <span>{{ $row->platform_name }}</span>
                            <strong>{{ number_format($row->cnt) }}</strong>
                        </div>
                    @empty
                        <p class="smm-dash-empty" style="padding:16px 0">{{ __('No platform data yet.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="smm-dash-table-wrap">
            <h3>{{ __('Recent orders') }}</h3>
            @if($recentOrders->isEmpty())
                <div class="smm-dash-empty">{{ __('No orders yet.') }} <a href="{{ route('client.orders.create') }}">{{ __('Create your first order') }}</a></div>
            @else
                <div style="overflow-x:auto">
                    <table class="smm-dash-table">
                        <thead>
                            <tr>
                                <th>{{ __('Order ID') }}</th>
                                <th>{{ __('Service') }}</th>
                                <th>{{ __('Progress') }}</th>
                                <th>{{ __('Price') }}</th>
                                <th>{{ __('Date') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentOrders as $order)
                                @php
                                    $qty = (int) ($order->quantity ?? 0);
                                    $del = (int) ($order->delivered ?? 0);
                                @endphp
                                <tr>
                                    <td>
                                        <strong style="color:var(--text)">#{{ $order->id }}</strong>
                                        <span class="smm-dash-badge {{ $order->source === \App\Models\Order::SOURCE_API ? 'api' : 'web' }}">
                                            {{ $order->source === \App\Models\Order::SOURCE_API ? 'API' : 'WEB' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span style="color:var(--text)">{{ \Illuminate\Support\Str::limit($order->service?->name ?? '—', 42) }}</span>
                                        @if($order->link)
                                            <div style="font-size:11px;margin-top:4px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                                <a href="{{ $order->link }}" target="_blank" rel="noopener">{{ \Illuminate\Support\Str::limit($order->link, 36) }}</a>
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                                        <div style="font-size:11px;margin-top:2px">{{ $del }} / {{ $qty }}</div>
                                    </td>
                                    <td>${{ rtrim(rtrim(number_format((float) $order->charge, 4), '0'), '.') }}</td>
                                    <td>{{ $order->created_at->format('M j, H:i') }}</td>
                                    <td><a href="{{ route('client.orders.index') }}">{{ __('Details') }}</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div style="padding: 0 18px 16px">
                    <a href="{{ route('client.orders.index') }}" class="smm-dash-viewall">{{ __('View all orders') }} →</a>
                </div>
            @endif
        </div>
    </div>
</x-client-smm-layout>
