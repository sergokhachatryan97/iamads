<x-app-layout>
    <style>[x-cloak]{display:none!important}</style>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Telegram Statistics') }}
        </h2>
    </x-slot>

    <div class="py-6 bg-white min-h-[calc(100vh-4rem)]">
        <div class="max-w-8xl mx-auto sm:px-6 lg:px-8">

            {{-- Scope tabs --}}
            <div class="flex gap-1 bg-gray-100 rounded-lg p-1 mb-4 w-fit">
                <a href="{{ route('staff.telegram-stats.index', array_merge(request()->except('scope'), ['scope' => 'default'])) }}"
                   class="px-4 py-1.5 text-sm font-medium rounded-md transition {{ ($scope ?? 'default') === 'default' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                    Telegram
                </a>
                <a href="{{ route('staff.telegram-stats.index', array_merge(request()->except('scope'), ['scope' => 'premium'])) }}"
                   class="px-4 py-1.5 text-sm font-medium rounded-md transition {{ ($scope ?? 'default') === 'premium' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                    Telegram Premium ⭐
                </a>
            </div>

            {{-- Filter bar --}}
            <div class="bg-gray-50 rounded-lg border border-gray-200 p-4 mb-6 flex flex-wrap items-end gap-4">
                <form method="get" action="{{ route('staff.telegram-stats.index') }}" class="flex flex-wrap items-end gap-4">
                    <input type="hidden" name="scope" value="{{ $scope ?? 'default' }}">
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
                        <a href="{{ route('staff.telegram-stats.index', ['scope' => $scope ?? 'default']) }}"
                           class="px-3 py-2 text-gray-600 hover:text-gray-900 text-sm">{{ __('Reset') }}</a>
                    @endif
                    @if(!$hasDateFilter)
                        <div class="flex gap-1 bg-gray-100 rounded-lg p-1">
                            <a href="{{ route('staff.telegram-stats.index', ['period' => 'days', 'scope' => $scope ?? 'default']) }}"
                               class="px-4 py-1.5 text-sm font-medium rounded-md transition {{ $period === 'days' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                                {{ __('Days') }}
                            </a>
                            <a href="{{ route('staff.telegram-stats.index', ['period' => 'monthly', 'scope' => $scope ?? 'default']) }}"
                               class="px-4 py-1.5 text-sm font-medium rounded-md transition {{ $period === 'monthly' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                                {{ __('Monthly') }}
                            </a>
                        </div>
                    @endif
                </div>
            </div>

            {{-- 4 metric cards --}}
            @php
                $totalActiveAccounts = $accountsPerService->sum();
                $totalInProgress     = $serviceStats->sum('in_progress_orders');
                $totalVolume         = $serviceStats->sum('total_volume');
                $periodLabel = $hasDateFilter
                    ? (($dateFrom ?? '...') . ' — ' . ($dateTo ?? '...'))
                    : ($period === 'monthly' ? __('This month') : __('Today'));
            @endphp
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

                {{-- Income --}}
                <div class="bg-gray-900 rounded-xl p-5">
                    <div class="text-gray-400 text-xs font-medium mb-1 uppercase tracking-wide">{{ __('Income') }}</div>
                    <div class="text-2xl font-bold text-white leading-tight">
                        ${{ number_format((float) $incomeToday, 0, '.', ' ') }}
                    </div>
                    @if(!$hasDateFilter && $incomeYesterday > 0)
                        @php $d = $incomeToday - $incomeYesterday; @endphp
                        <div class="text-xs mt-1 {{ $d >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                            {{ $d >= 0 ? '▲' : '▼' }} ${{ number_format(abs($d), 0, '.', ' ') }} vs {{ $period === 'monthly' ? __('prev month') : __('yesterday') }}
                        </div>
                    @endif
                    <div class="text-gray-600 text-xs mt-1">{{ $periodLabel }}</div>
                </div>

                {{-- Completions --}}
                <div class="bg-gray-900 rounded-xl p-5">
                    <div class="text-gray-400 text-xs font-medium mb-1 uppercase tracking-wide">{{ __('Completions') }}</div>
                    <div class="text-2xl font-bold text-white leading-tight">
                        {{ number_format($completionsToday) }}
                    </div>
                    @if(!$hasDateFilter && $completionsYesterday > 0)
                        @php $d = $completionsToday - $completionsYesterday; @endphp
                        <div class="text-xs mt-1 {{ $d >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                            {{ $d >= 0 ? '▲' : '▼' }} {{ number_format(abs($d)) }} vs {{ $period === 'monthly' ? __('prev month') : __('yesterday') }}
                        </div>
                    @endif
                    <div class="text-gray-600 text-xs mt-1">{{ $periodLabel }}</div>
                </div>

                {{-- Active accounts --}}
                <div class="bg-gray-900 rounded-xl p-5">
                    <div class="text-gray-400 text-xs font-medium mb-1 uppercase tracking-wide">{{ __('Active Accounts') }}</div>
                    <div class="text-2xl font-bold {{ $totalActiveAccounts > 0 ? 'text-emerald-400' : 'text-white' }} leading-tight">
                        {{ number_format($totalActiveAccounts) }}
                    </div>
                    <div class="text-gray-600 text-xs mt-1">{{ __('Last 2 hours') }}</div>
                </div>

                {{-- In-progress orders + volume --}}
                <div class="bg-gray-900 rounded-xl p-5">
                    <div class="text-gray-400 text-xs font-medium mb-1 uppercase tracking-wide">{{ __('Active Orders') }}</div>
                    <div class="text-2xl font-bold text-white leading-tight">
                        {{ number_format($totalInProgress) }}
                    </div>
                    <div class="text-gray-600 text-xs mt-1">
                        {{ number_format($totalVolume) }} {{ __('left to deliver') }}
                    </div>
                </div>

            </div>

            {{-- Chart --}}
            @if($chartLabels->isNotEmpty())
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">{{ __('Performance') }}</h3>
                    <div class="flex items-center gap-4 text-xs text-gray-500">
                        <span class="flex items-center gap-1"><span class="inline-block w-3 h-0.5 bg-emerald-500 rounded"></span> {{ __('Income') }}</span>
                        <span class="flex items-center gap-1"><span class="inline-block w-3 h-0.5 bg-blue-500 rounded"></span> {{ __('Completions') }}</span>
                    </div>
                </div>
                <div class="h-64">
                    <canvas id="chartMain"></canvas>
                </div>
            </div>
            @endif

            {{-- Services table --}}
            @php
                // Pre-compute totals from ALL displayed services (not just current page)
                $totComp = 0; $totAccounts = 0; $totInProg = 0; $totVol = 0; $totInc = 0.0; $totCompOrd = 0; $totCompBal = 0.0;
                foreach ($allDisplayedServices as $s) {
                    $st = $serviceStats[$s->id] ?? null;
                    $c  = (int) ($completionsTodayPerService[$s->id] ?? 0);
                    $totComp     += $c;
                    $totAccounts += (int) ($accountsPerService[$s->id] ?? 0);
                    $totInProg   += (int) ($st->in_progress_orders ?? 0);
                    $totVol      += (int) ($st->total_volume ?? 0);
                    $totInc      += $s->rate_per_1000 ? $c * (float) $s->rate_per_1000 / 1000 : 0;
                    $totCompOrd  += (int) ($st->completed_orders ?? 0);
                    $totCompBal  += (float) ($st->completed_balance ?? 0);
                }
            @endphp
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-700">{{ __('Services') }}</h3>
                    @if($displayedServices->total() > $displayedServices->perPage())
                        <span class="text-xs text-gray-400">
                            {{ $displayedServices->firstItem() }}–{{ $displayedServices->lastItem() }}
                            {{ __('of') }} {{ $displayedServices->total() }}
                        </span>
                    @endif
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Service') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $completionsColumnLabel }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Accounts') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Delay') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Price / 1K') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Active Orders') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Volume left') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $hasDateFilter ? __('Income') : __('Income today') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Completed orders') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($displayedServices as $service)
                                @php
                                    $stats     = $serviceStats[$service->id] ?? null;
                                    $accounts  = (int) ($accountsPerService[$service->id] ?? 0);
                                    $delaySec  = $delayPerService[$service->id] ?? null;
                                    $compToday = (int) ($completionsTodayPerService[$service->id] ?? 0);
                                    $incToday  = $service->rate_per_1000 ? $compToday * (float) $service->rate_per_1000 / 1000 : 0;
                                    $inProg    = (int) ($stats->in_progress_orders ?? 0);
                                    $vol       = (int) ($stats->total_volume ?? 0);
                                    $compOrd   = (int) ($stats->completed_orders ?? 0);
                                    $compBal   = (float) ($stats->completed_balance ?? 0);
                                    $price     = $service->rate_per_1000 ? '$'.rtrim(rtrim(number_format((float)$service->rate_per_1000, 4),'0'),'.') : '-';
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-gray-900">
                                        <div class="flex items-center gap-2">
                                            <span class="text-blue-500 text-xs"><i class="fab fa-telegram"></i></span>
                                            <div>
                                                <div class="font-medium">{{ Str::limit($service->name, 40) }}</div>
                                                <div class="text-xs text-gray-400">#{{ $service->id }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right font-medium text-gray-900">{{ number_format($compToday) }}</td>
                                    <td class="px-4 py-3 text-right {{ $accounts > 0 ? 'text-emerald-600 font-medium' : 'text-gray-400' }}">{{ number_format($accounts) }}</td>
                                    <td class="px-4 py-3 text-right {{ $delaySec !== null && $delaySec <= 60 ? 'text-emerald-600' : ($delaySec !== null && $delaySec <= 300 ? 'text-yellow-600' : 'text-gray-400') }}">
                                        @if($delaySec !== null)
                                            @if($delaySec >= 60){{ floor($delaySec/60) }}m {{ $delaySec%60 }}s
                                            @else{{ $delaySec }}s
                                            @endif
                                        @else-@endif
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-900">{{ $price }}</td>
                                    <td class="px-4 py-3 text-right text-gray-900">{{ number_format($inProg) }}</td>
                                    <td class="px-4 py-3 text-right text-gray-900">{{ number_format($vol) }}</td>
                                    <td class="px-4 py-3 text-right text-emerald-600 font-medium">${{ rtrim(rtrim(number_format($incToday, 4),'0'),'.') }}</td>
                                    <td class="px-4 py-3 text-right text-gray-900">
                                        <div class="font-medium">{{ number_format($compOrd) }}</div>
                                        <div class="text-xs text-emerald-600">${{ rtrim(rtrim(number_format($compBal, 4),'0'),'.') }}</div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-4 py-8 text-center text-gray-500">{{ __('No Telegram services found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                        {{-- Totals always over ALL services, not just current page --}}
                        @if($allDisplayedServices->isNotEmpty())
                        <tfoot class="bg-gray-50 border-t-2 border-gray-300">
                            <tr class="font-semibold text-gray-700">
                                <td class="px-4 py-3 text-xs uppercase tracking-wide text-gray-500">{{ __('Total') }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($totComp) }}</td>
                                <td class="px-4 py-3 text-right {{ $totAccounts > 0 ? 'text-emerald-600' : '' }}">{{ number_format($totAccounts) }}</td>
                                <td class="px-4 py-3 text-right text-gray-400">—</td>
                                <td class="px-4 py-3 text-right text-gray-400">—</td>
                                <td class="px-4 py-3 text-right">{{ number_format($totInProg) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($totVol) }}</td>
                                <td class="px-4 py-3 text-right text-emerald-600">${{ rtrim(rtrim(number_format($totInc, 4),'0'),'.') }}</td>
                                <td class="px-4 py-3 text-right">
                                    <div>{{ number_format($totCompOrd) }}</div>
                                    <div class="text-xs text-emerald-600">${{ rtrim(rtrim(number_format($totCompBal, 4),'0'),'.') }}</div>
                                </td>
                            </tr>
                        </tfoot>
                        @endif
                    </table>
                </div>

                {{-- Pagination links --}}
                @if($displayedServices->hasPages())
                    <div class="px-4 py-3 border-t border-gray-100">
                        {{ $displayedServices->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const labels         = @json($chartLabels->values());
            const incomeData     = @json($chartIncomeData);
            const completionsData= @json($chartCompletionsData);
            const isMonthly      = @json($period === 'monthly');

            if (!labels.length) return;

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

            function fmt(v) { return v.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' '); }

            const ctx = document.getElementById('chartMain');
            if (!ctx) return;

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: shortLabels,
                    datasets: [
                        {
                            label: 'Income',
                            data: incomeData,
                            borderColor: '#22c55e',
                            backgroundColor: 'rgba(34,197,94,0.08)',
                            pointBackgroundColor: '#22c55e',
                            pointBorderColor: '#fff',
                            fill: true,
                            tension: 0.4,
                            borderWidth: 2,
                            yAxisID: 'yIncome',
                        },
                        {
                            label: 'Completions',
                            data: completionsData,
                            borderColor: '#3b82f6',
                            backgroundColor: 'rgba(59,130,246,0.08)',
                            pointBackgroundColor: '#3b82f6',
                            pointBorderColor: '#fff',
                            fill: true,
                            tension: 0.4,
                            borderWidth: 2,
                            yAxisID: 'yComp',
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(255,255,255,0.97)',
                            titleColor: '#111827',
                            bodyColor: '#374151',
                            borderColor: '#e5e7eb',
                            borderWidth: 1,
                            cornerRadius: 8,
                            padding: 12,
                            displayColors: true,
                            boxWidth: 8, boxHeight: 8, boxPadding: 4,
                            titleFont: { size: 12, weight: '500' },
                            bodyFont:  { size: 13, weight: '600' },
                            callbacks: {
                                title: items => tooltipLabels[items[0].dataIndex],
                                label: ctx => {
                                    const prefix = ctx.datasetIndex === 0 ? '$ ' : '';
                                    return '  ' + ctx.dataset.label + ':  ' + prefix + fmt(ctx.parsed.y);
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { color: 'rgba(0,0,0,0.04)' },
                            ticks: { color: '#9ca3af', font: { size: 11 }, maxRotation: 0 }
                        },
                        yIncome: {
                            position: 'left',
                            beginAtZero: true,
                            grid: { color: 'rgba(0,0,0,0.04)' },
                            ticks: { color: '#22c55e', font: { size: 11 }, callback: v => '$' + fmt(v) }
                        },
                        yComp: {
                            position: 'right',
                            beginAtZero: true,
                            grid: { drawOnChartArea: false },
                            ticks: { color: '#3b82f6', font: { size: 11 }, callback: v => fmt(v) }
                        }
                    },
                    elements: {
                        point: { radius: 2, hoverRadius: 6, hoverBorderWidth: 2, hitRadius: 20 }
                    }
                }
            });
        });
    </script>
</x-app-layout>
