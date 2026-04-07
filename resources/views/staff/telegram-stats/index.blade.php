<x-app-layout>
    <style>[x-cloak]{display:none!important}</style>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Telegram Statistics') }}
        </h2>
    </x-slot>

    <div class="py-6 bg-white min-h-[calc(100vh-4rem)]">
        <div class="max-w-8xl mx-auto sm:px-6 lg:px-8">

            {{-- Filter bar --}}
            <div class="bg-gray-50 rounded-lg border border-gray-200 p-4 mb-6 flex flex-wrap items-end gap-4">
                <form method="get" action="{{ route('staff.telegram-stats.index') }}" class="flex flex-wrap items-end gap-4">
                    <div class="flex items-center gap-2">
                        <label class="text-gray-600 text-sm">{{ __('From') }}</label>
                        <input type="date" name="date_from" value="{{ $dateFrom ?? '' }}"
                               class="rounded border-gray-300 text-gray-900 text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="text-gray-600 text-sm">{{ __('To') }}</label>
                        <input type="date" name="date_to" value="{{ $dateTo ?? '' }}"
                               class="rounded border-gray-300 text-gray-900 text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <select name="service_id" class="rounded border-gray-300 text-gray-900 text-sm px-3 py-2 w-56">
                        <option value="">{{ __('All services') }}</option>
                        @foreach($telegramServices as $s)
                            <option value="{{ $s->id }}" {{ ($serviceId ?? '') == $s->id ? 'selected' : '' }}>{{ Str::limit($s->name, 35) }}</option>
                        @endforeach
                    </select>
                    @if(!$hasDateFilter)
                        <input type="hidden" name="period" value="{{ $period }}">
                    @endif
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded">
                        {{ __('Apply') }}
                    </button>
                </form>
                <div class="ms-auto flex items-center gap-3">
                    @if($hasDateFilter || !empty($serviceId))
                        <a href="{{ route('staff.telegram-stats.index') }}" class="px-3 py-2 text-gray-600 hover:text-gray-900 text-sm">{{ __('Reset') }}</a>
                    @endif
                    @if(!$hasDateFilter)
                        <div class="flex gap-1 bg-gray-100 rounded-lg p-1">
                            <a href="{{ route('staff.telegram-stats.index', ['period' => 'days']) }}"
                               class="px-4 py-1.5 text-sm font-medium rounded-md transition {{ $period === 'days' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                                {{ __('Days') }}
                            </a>
                            <a href="{{ route('staff.telegram-stats.index', ['period' => 'monthly']) }}"
                               class="px-4 py-1.5 text-sm font-medium rounded-md transition {{ $period === 'monthly' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                                {{ __('Monthly') }}
                            </a>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Top metrics: Income + Completions --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                {{-- Income --}}
                <div class="bg-gray-900 rounded-xl p-6">
                    <h3 class="text-gray-400 text-sm font-medium mb-1">{{ __('Income') }}</h3>
                    <div class="flex items-baseline gap-3 mb-1">
                        <span class="text-3xl font-bold text-white">{{ number_format((float) $incomeToday, 0, '.', ' ') }} $</span>
                        @php $incomeDiff = $incomeToday - $incomeYesterday; @endphp
                        @if($incomeYesterday > 0)
                            <span class="text-sm {{ $incomeDiff >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                                {{ ($incomeDiff >= 0 ? "\xe2\x96\xb2" : "\xe2\x96\xbc") }} {{ number_format(abs($incomeDiff), 0, '.', ' ') }} $
                            </span>
                        @endif
                    </div>
                    <div class="text-gray-500 text-xs mb-3">
                        @if($hasDateFilter)
                            {{ $dateFrom ?? '...' }} — {{ $dateTo ?? '...' }}
                        @else
                            {{ $period === 'monthly' ? __('This month') : __('Today') }}
                        @endif
                    </div>
                    <div class="h-20">
                        <canvas id="chartIncome"></canvas>
                    </div>
                </div>

                {{-- Completions --}}
                <div class="bg-gray-900 rounded-xl p-6">
                    <h3 class="text-gray-400 text-sm font-medium mb-1">{{ __('Completions') }}</h3>
                    <div class="flex items-baseline gap-3 mb-1">
                        <span class="text-3xl font-bold text-white">{{ number_format($completionsToday, 0, '.', ' ') }}</span>
                        @php $compDiff = $completionsToday - $completionsYesterday; @endphp
                        @if($completionsYesterday > 0)
                            <span class="text-sm {{ $compDiff >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                                {{ ($compDiff >= 0 ? "\xe2\x96\xb2" : "\xe2\x96\xbc") }} {{ number_format(abs($compDiff), 0, '.', ' ') }}
                            </span>
                        @endif
                    </div>
                    <div class="text-gray-500 text-xs mb-3">
                        @if($hasDateFilter)
                            {{ $dateFrom ?? '...' }} — {{ $dateTo ?? '...' }}
                        @else
                            {{ $period === 'monthly' ? __('This month') : __('Today') }}
                        @endif
                    </div>
                    <div class="h-20">
                        <canvas id="chartCompletions"></canvas>
                    </div>
                </div>
            </div>

            {{-- Services --}}
            <div>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">{{ __('My services') }}</h3>
                </div>

                {{-- Services table --}}
                <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Service') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $completionsColumnLabel }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Accounts') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Price') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Orders') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Volume') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $hasDateFilter ? __('Income') : __('Income today') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Completed') }}</th>
{{--                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Balance') }}</th>--}}
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($displayedServices as $service)
                                    @php
                                        $stats = $serviceStats[$service->id] ?? null;
                                        $accounts = $accountsPerService[$service->id] ?? 0;
                                        $completionsTodayService = $completionsTodayPerService[$service->id] ?? 0;
                                        $serviceIncome = $service->rate_per_1000 ? $completionsTodayService * (float) $service->rate_per_1000 / 1000 : 0;
                                        $totalOrders = $stats->total_orders ?? 0;
                                        $totalVolume = $stats->total_volume ?? 0;
                                        $totalBalance = $stats->total_balance ?? 0;
                                        $completedOrders = $stats->completed_orders ?? 0;
                                        $completedBalance = $stats->completed_balance ?? 0;
                                        $price = $service->rate_per_1000 ? '$' . (float)$service->rate_per_1000  : '-';
                                    @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 text-sm text-gray-900">
                                            <div class="flex items-center gap-2">
                                                <span class="text-blue-500">
                                                    <i class="fab fa-telegram"></i>
                                                </span>
                                                <div>
                                                    <div class="font-medium">{{ Str::limit($service->name, 40) }}</div>
                                                    <div class="text-xs text-gray-400">{{ $service->id }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-900 font-medium">
                                            {{ number_format($completionsTodayService) }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-right {{ $accounts > 0 ? 'text-emerald-600 font-medium' : 'text-gray-400' }}">
                                            {{ number_format($accounts) }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-900">{{ $price }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-900">{{ number_format($totalOrders) }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-900">{{ number_format($totalVolume) }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-emerald-600 font-medium">${{ (float) $serviceIncome }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-900">
                                            <div class="font-medium">{{ number_format($completedOrders) }}</div>
                                            <div class="text-xs text-emerald-600">${{ number_format((float) $completedBalance, 2, '.', ' ') }}</div>
                                        </td>
{{--                                        <td class="px-4 py-3 text-sm text-right text-gray-900 font-medium">${{ number_format((float) $totalBalance, 0, '.', ' ') }}</td>--}}
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="px-4 py-8 text-center text-gray-500">{{ __('No Telegram services found') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const labels = @json($chartLabels->values());
            const incomeData = @json($chartIncomeData);
            const completionsData = @json($chartCompletionsData);
            const isMonthly = @json($period === 'monthly');

            // Full date labels for tooltip title (e.g. "31 March" or "March 2025")
            const tooltipLabels = labels.map(d => {
                if (isMonthly) {
                    const [y, m] = d.split('-');
                    return new Date(y, m - 1).toLocaleDateString('en', { day: undefined, month: 'long', year: 'numeric' });
                }
                return new Date(d + 'T00:00:00').toLocaleDateString('en', { day: 'numeric', month: 'long' });
            });

            // Short labels for x-axis (not displayed, but used internally)
            const shortLabels = labels.map(d => {
                if (isMonthly) {
                    const [y, m] = d.split('-');
                    return new Date(y, m - 1).toLocaleDateString('en', { month: 'short', year: '2-digit' });
                }
                return new Date(d + 'T00:00:00').toLocaleDateString('en', { month: 'short', day: 'numeric' });
            });

            function fmt(val) {
                return val.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            }

            function makeOpts(label, prefix) {
                return {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            enabled: true,
                            backgroundColor: 'rgba(255,255,255,0.95)',
                            titleColor: '#111827',
                            bodyColor: '#111827',
                            borderColor: '#e5e7eb',
                            borderWidth: 1,
                            cornerRadius: 8,
                            padding: 12,
                            displayColors: true,
                            boxWidth: 8,
                            boxHeight: 8,
                            boxPadding: 4,
                            titleFont: { size: 12, weight: 'normal' },
                            bodyFont: { size: 14, weight: 'bold' },
                            callbacks: {
                                title: function(items) {
                                    return tooltipLabels[items[0].dataIndex];
                                },
                                label: function(ctx) {
                                    return '  ' + label + ':  ' + (prefix || '') + fmt(ctx.parsed.y);
                                }
                            }
                        }
                    },
                    scales: {
                        x: { display: false },
                        y: { display: false, beginAtZero: true }
                    },
                    elements: {
                        point: { radius: 0, hoverRadius: 6, hoverBorderWidth: 2, hitRadius: 20 }
                    }
                };
            }

            if (document.getElementById('chartIncome') && labels.length) {
                new Chart(document.getElementById('chartIncome'), {
                    type: 'line',
                    data: {
                        labels: shortLabels,
                        datasets: [{
                            label: 'Income',
                            data: incomeData,
                            borderColor: '#22c55e',
                            backgroundColor: 'rgba(34,197,94,0.15)',
                            pointBackgroundColor: '#22c55e',
                            pointBorderColor: '#fff',
                            fill: true,
                            tension: 0.4,
                            borderWidth: 2
                        }]
                    },
                    options: makeOpts('Income', '$')
                });
            }

            if (document.getElementById('chartCompletions') && labels.length) {
                new Chart(document.getElementById('chartCompletions'), {
                    type: 'line',
                    data: {
                        labels: shortLabels,
                        datasets: [{
                            label: 'Completions',
                            data: completionsData,
                            borderColor: '#3b82f6',
                            backgroundColor: 'rgba(59,130,246,0.15)',
                            pointBackgroundColor: '#3b82f6',
                            pointBorderColor: '#fff',
                            fill: true,
                            tension: 0.4,
                            borderWidth: 2
                        }]
                    },
                    options: makeOpts('Completions', '')
                });
            }
        });
    </script>
</x-app-layout>
