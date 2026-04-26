<x-app-layout>
    <style>[x-cloak]{display:none!important}</style>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-6 bg-white min-h-[calc(100vh-4rem)]">
        <div class="max-w-8xl mx-auto sm:px-6 lg:px-8">

            {{-- Filter bar --}}
            <div class="bg-gray-50 rounded-lg border border-gray-200 p-4 mb-6 flex flex-wrap items-end gap-4">
                <form method="get" action="{{ route('staff.dashboard') }}" class="flex flex-wrap items-end gap-4">
                    {{-- Network filter --}}
                    <div class="flex items-center gap-2">
                        <label class="text-gray-600 text-sm">{{ __('Network') }}</label>
                        <select name="network" class="rounded border-gray-300 text-gray-900 text-sm px-3 py-2 w-36">
                            <option value="all" {{ ($network ?? 'all') === 'all' ? 'selected' : '' }}>{{ __('All') }}</option>
                            <option value="telegram" {{ ($network ?? '') === 'telegram' ? 'selected' : '' }}>Telegram</option>
                            <option value="max" {{ ($network ?? '') === 'max' ? 'selected' : '' }}>Max</option>
                        </select>
                    </div>

                    {{-- Service filter --}}
                    <div class="flex items-center gap-2">
                        <label class="text-gray-600 text-sm">{{ __('Service') }}</label>
                        <select name="service_id" class="rounded border-gray-300 text-gray-900 text-sm px-3 py-2 w-56">
                            <option value="">{{ __('All services') }}</option>
                            @foreach($allServices as $s)
                                <option value="{{ $s->id }}" {{ ($serviceId ?? '') == $s->id ? 'selected' : '' }}>
                                    {{ Str::limit($s->name, 35) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Date range --}}
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

                    @if(!$hasDateFilter)
                        <input type="hidden" name="period" value="{{ $period }}">
                    @endif

                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded">
                        {{ __('Apply') }}
                    </button>
                </form>
                <div class="ms-auto flex items-center gap-3">
                    @if($hasDateFilter || !empty($serviceId) || ($network ?? 'all') !== 'all')
                        <a href="{{ route('staff.dashboard') }}" class="px-3 py-2 text-gray-600 hover:text-gray-900 text-sm">{{ __('Reset') }}</a>
                    @endif
                    @if(!$hasDateFilter)
                        <div class="flex gap-1 bg-gray-100 rounded-lg p-1">
                            <a href="{{ route('staff.dashboard', array_merge(request()->except('period'), ['period' => 'days'])) }}"
                               class="px-4 py-1.5 text-sm font-medium rounded-md transition {{ $period === 'days' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                                {{ __('Days') }}
                            </a>
                            <a href="{{ route('staff.dashboard', array_merge(request()->except('period'), ['period' => 'monthly'])) }}"
                               class="px-4 py-1.5 text-sm font-medium rounded-md transition {{ $period === 'monthly' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                                {{ __('Monthly') }}
                            </a>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Top metrics: Completions + Income --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                {{-- Completions --}}
                <div class="bg-gray-900 rounded-xl p-6">
                    <h3 class="text-gray-400 text-sm font-medium mb-1">{{ __('Completions') }} <span class="text-gray-600 text-xs">({{ __('clean') }})</span></h3>
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
                        @if($hasDateFilter) {{ $dateFrom ?? '...' }} &mdash; {{ $dateTo ?? '...' }}
                        @else {{ $period === 'monthly' ? __('This month') : __('Today') }}
                        @endif
                    </div>
                    <div class="h-20">
                        <canvas id="chartCompletions"></canvas>
                    </div>
                </div>

                {{-- Income --}}
                <div class="bg-gray-900 rounded-xl p-6">
                    <h3 class="text-gray-400 text-sm font-medium mb-1">{{ __('Income') }} <span class="text-gray-600 text-xs">({{ __('clean') }})</span></h3>
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
                        @if($hasDateFilter) {{ $dateFrom ?? '...' }} &mdash; {{ $dateTo ?? '...' }}
                        @else {{ $period === 'monthly' ? __('This month') : __('Today') }}
                        @endif
                    </div>
                    <div class="h-20">
                        <canvas id="chartIncome"></canvas>
                    </div>
                </div>
            </div>

            {{-- Secondary metrics row --}}
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
                {{-- Daily Payments --}}
                <div class="bg-white rounded-xl border border-gray-200 p-5">
                    <h3 class="text-gray-500 text-sm font-medium mb-1">{{ __('Payments') }}</h3>
                    <span class="text-2xl font-bold text-gray-900">${{ number_format($chartPaymentsData->sum(), 0, '.', ' ') }}</span>
                    <div class="h-16 mt-3">
                        <canvas id="chartPayments"></canvas>
                    </div>
                </div>

                {{-- Daily New Users --}}
                <div class="bg-white rounded-xl border border-gray-200 p-5">
                    <h3 class="text-gray-500 text-sm font-medium mb-1">{{ __('New Users') }}</h3>
                    <span class="text-2xl font-bold text-gray-900">{{ number_format($chartNewUsersData->sum(), 0, '.', ' ') }}</span>
                    <div class="h-16 mt-3">
                        <canvas id="chartNewUsers"></canvas>
                    </div>
                </div>

                {{-- Daily Refill Completions --}}
                <div class="bg-white rounded-xl border border-gray-200 p-5">
                    <h3 class="text-gray-500 text-sm font-medium mb-1">{{ __('Refill Completions') }}</h3>
                    <span class="text-2xl font-bold text-gray-900">{{ number_format($chartRefillData->sum(), 0, '.', ' ') }}</span>
                    <div class="h-16 mt-3">
                        <canvas id="chartRefills"></canvas>
                    </div>
                </div>

                {{-- Daily Test Money --}}
                <div class="bg-white rounded-xl border border-gray-200 p-5">
                    <h3 class="text-gray-500 text-sm font-medium mb-1">{{ __('Test Orders (money)') }}</h3>
                    <span class="text-2xl font-bold text-gray-900">${{ number_format($chartTestMoneyData->sum(), 2, '.', ' ') }}</span>
                    <div class="h-16 mt-3">
                        <canvas id="chartTestMoney"></canvas>
                    </div>
                </div>
            </div>

            {{-- Results by Admin --}}
            @if($adminStats->isNotEmpty())
            <div class="mb-8">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">{{ __('Results by Admin') }}</h3>
                <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Admin') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Completions') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Payments') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Refills') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Tests') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('New Users') }}</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($adminStats as $admin)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $admin->name }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-900 font-medium">{{ number_format($admin->completions) }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-emerald-600 font-medium">${{ number_format($admin->payments, 2) }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-blue-600">{{ number_format($admin->refills) }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-yellow-600">{{ number_format($admin->tests) }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-900">{{ number_format($admin->new_users) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            {{-- Services table --}}
            <div>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">{{ __('Services') }}</h3>
                </div>

                <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Service') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $completionsColumnLabel }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Accounts') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Delay') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Price') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Orders') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Volume') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $hasDateFilter ? __('Income') : __('Income today') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Completed') }}</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($displayedServices as $service)
                                    @php
                                        $stats = $serviceStats[$service->id] ?? null;
                                        $accounts = $accountsPerService[$service->id] ?? 0;
                                        $delaySec = $delayPerService[$service->id] ?? null;
                                        $completionsTodayService = $completionsTodayPerService[$service->id] ?? 0;
                                        $serviceIncome = $service->rate_per_1000 ? $completionsTodayService * (float) $service->rate_per_1000 / 1000 : 0;
                                        $inProgressOrders = $stats->in_progress_orders ?? 0;
                                        $totalVolume = $stats->total_volume ?? 0;
                                        $completedOrders = $stats->completed_orders ?? 0;
                                        $completedBalance = $stats->completed_balance ?? 0;
                                        $price = $service->rate_per_1000 ? '$' . rtrim(rtrim(number_format((float)$service->rate_per_1000, 4), '0'), '.') : '-';
                                        $isTelegram = in_array($service->id, $telegramServiceIds);
                                    @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 text-sm text-gray-900">
                                            <div class="flex items-center gap-2">
                                                <span class="{{ $isTelegram ? 'text-blue-500' : 'text-purple-500' }}">
                                                    @if($isTelegram)
                                                        <i class="fab fa-telegram"></i>
                                                    @else
                                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a8 8 0 100 16 8 8 0 000-16zM8 13l-3-3 1.4-1.4L8 10.2l5.6-5.6L15 6l-7 7z"/></svg>
                                                    @endif
                                                </span>
                                                <div>
                                                    <div class="font-medium">{{ Str::limit($service->name, 40) }}</div>
                                                    <div class="text-xs text-gray-400">{{ $service->id }} &middot; {{ $isTelegram ? 'TG' : 'Max' }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-900 font-medium">{{ number_format($completionsTodayService) }}</td>
                                        <td class="px-4 py-3 text-sm text-right {{ $accounts > 0 ? 'text-emerald-600 font-medium' : 'text-gray-400' }}">{{ number_format($accounts) }}</td>
                                        <td class="px-4 py-3 text-sm text-right {{ $delaySec !== null && $delaySec <= 60 ? 'text-emerald-600' : ($delaySec !== null && $delaySec <= 300 ? 'text-yellow-600' : 'text-gray-400') }}">
                                            @if($delaySec !== null)
                                                @if($delaySec >= 60)
                                                    {{ floor($delaySec / 60) }}m {{ $delaySec % 60 }}s
                                                @else
                                                    {{ $delaySec }}s
                                                @endif
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-900">{{ $price }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-900">
                                            <div class="font-medium">{{ number_format($inProgressOrders) }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-900">{{ number_format($totalVolume) }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-emerald-600 font-medium">${{ rtrim(rtrim(number_format((float) $serviceIncome, 4), '0'), '.') }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-900">
                                            <div class="font-medium">{{ number_format($completedOrders) }}</div>
                                            <div class="text-xs text-emerald-600">${{ rtrim(rtrim(number_format((float) $completedBalance, 4), '0'), '.') }}</div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="px-4 py-8 text-center text-gray-500">{{ __('No services found') }}</td>
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
            const tgCompletions = @json($chartTgCompletions);
            const maxCompletions = @json($chartMaxCompletions);
            const paymentsData = @json($chartPaymentsData);
            const newUsersData = @json($chartNewUsersData);
            const refillData = @json($chartRefillData);
            const testMoneyData = @json($chartTestMoneyData);
            const isMonthly = @json($period === 'monthly');
            const network = @json($network);

            const tooltipLabels = labels.map(d => {
                if (isMonthly) {
                    const [y, m] = d.split('-');
                    return new Date(y, m - 1).toLocaleDateString('en', { month: 'long', year: 'numeric' });
                }
                return new Date(d + 'T00:00:00').toLocaleDateString('en', { day: 'numeric', month: 'long' });
            });

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

            function makeOpts(tooltipLabel, prefix) {
                return {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            enabled: true,
                            backgroundColor: 'rgba(255,255,255,0.95)',
                            titleColor: '#111827', bodyColor: '#111827',
                            borderColor: '#e5e7eb', borderWidth: 1, cornerRadius: 8, padding: 12,
                            displayColors: true, boxWidth: 8, boxHeight: 8, boxPadding: 4,
                            titleFont: { size: 12, weight: 'normal' },
                            bodyFont: { size: 14, weight: 'bold' },
                            callbacks: {
                                title: items => tooltipLabels[items[0].dataIndex],
                                label: ctx => '  ' + ctx.dataset.label + ':  ' + (prefix || '') + fmt(ctx.parsed.y)
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

            function makeSmallOpts(tooltipLabel, prefix) {
                const opts = makeOpts(tooltipLabel, prefix);
                opts.plugins.tooltip.bodyFont = { size: 12, weight: 'bold' };
                return opts;
            }

            // Completions chart — show separate lines for TG/Max when viewing "all"
            if (document.getElementById('chartCompletions') && labels.length) {
                const datasets = [];
                if (network === 'all') {
                    datasets.push({
                        label: 'Telegram', data: tgCompletions,
                        borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.10)',
                        pointBackgroundColor: '#3b82f6', pointBorderColor: '#fff',
                        fill: true, tension: 0.4, borderWidth: 2
                    });
                    datasets.push({
                        label: 'Max', data: maxCompletions,
                        borderColor: '#a855f7', backgroundColor: 'rgba(168,85,247,0.10)',
                        pointBackgroundColor: '#a855f7', pointBorderColor: '#fff',
                        fill: true, tension: 0.4, borderWidth: 2
                    });
                } else {
                    datasets.push({
                        label: 'Completions', data: completionsData,
                        borderColor: network === 'telegram' ? '#3b82f6' : '#a855f7',
                        backgroundColor: network === 'telegram' ? 'rgba(59,130,246,0.15)' : 'rgba(168,85,247,0.15)',
                        pointBackgroundColor: network === 'telegram' ? '#3b82f6' : '#a855f7',
                        pointBorderColor: '#fff', fill: true, tension: 0.4, borderWidth: 2
                    });
                }
                const opts = makeOpts('Completions', '');
                if (network === 'all') opts.plugins.legend = { display: true, labels: { boxWidth: 10, usePointStyle: true, pointStyle: 'circle', padding: 12, color: '#9ca3af', font: { size: 11 } } };
                new Chart(document.getElementById('chartCompletions'), { type: 'line', data: { labels: shortLabels, datasets }, options: opts });
            }

            // Income chart
            if (document.getElementById('chartIncome') && labels.length) {
                new Chart(document.getElementById('chartIncome'), {
                    type: 'line',
                    data: { labels: shortLabels, datasets: [{ label: 'Income', data: incomeData, borderColor: '#22c55e', backgroundColor: 'rgba(34,197,94,0.15)', pointBackgroundColor: '#22c55e', pointBorderColor: '#fff', fill: true, tension: 0.4, borderWidth: 2 }] },
                    options: makeOpts('Income', '$')
                });
            }

            // Small charts
            function smallChart(id, label, data, color, prefix) {
                const el = document.getElementById(id);
                if (!el || !labels.length) return;
                new Chart(el, {
                    type: 'line',
                    data: { labels: shortLabels, datasets: [{ label, data, borderColor: color, backgroundColor: color + '22', pointBackgroundColor: color, pointBorderColor: '#fff', fill: true, tension: 0.4, borderWidth: 1.5 }] },
                    options: makeSmallOpts(label, prefix)
                });
            }

            smallChart('chartPayments', 'Payments', paymentsData, '#f59e0b', '$');
            smallChart('chartNewUsers', 'New Users', newUsersData, '#6366f1', '');
            smallChart('chartRefills', 'Refill Completions', refillData, '#3b82f6', '');
            smallChart('chartTestMoney', 'Test Money', testMoneyData, '#ef4444', '$');
        });
    </script>
</x-app-layout>
