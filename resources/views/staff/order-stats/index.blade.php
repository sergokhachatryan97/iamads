<x-app-layout>
    <style>[x-cloak]{display:none!important}</style>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Order Statistics') }}
        </h2>
    </x-slot>

    <div class="py-6 bg-white min-h-[calc(100vh-4rem)]">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{-- Time range + filters bar --}}
            <div class="bg-gray-50 rounded-lg border border-gray-200 p-4 mb-6 flex flex-wrap items-end gap-4">
                <form method="get" action="{{ route('staff.order-stats.index') }}" class="flex flex-wrap items-end gap-4">
                    <div class="flex items-center gap-2">
                        <label class="text-gray-600 text-sm">{{ __('From') }}</label>
                        <input type="date" name="date_from" id="date_from" value="{{ $inputDateFrom ?? request('date_from') }}"
                               class="rounded border-gray-300 text-gray-900 text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="text-gray-600 text-sm">{{ __('To') }}</label>
                        <input type="date" name="date_to" id="date_to" value="{{ $inputDateTo ?? request('date_to') }}"
                               class="rounded border-gray-300 text-gray-900 text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <select name="status" id="status" class="rounded border-gray-300 text-gray-900 text-sm px-3 py-2 w-36">
                        @foreach($statuses ?? [] as $val => $label)
                            <option value="{{ $val }}" {{ request('status') === (string) $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    <select name="service_id" id="service_id" class="rounded border-gray-300 text-gray-900 text-sm px-3 py-2 w-48">
                        <option value="">{{ __('All services') }}</option>
                        @foreach($allServices ?? [] as $s)
                            <option value="{{ $s->id }}" {{ request('service_id') == $s->id ? 'selected' : '' }}>{{ Str::limit($s->name, 30) }}</option>
                        @endforeach
                    </select>
                    <input type="number" name="client_id" id="client_id" value="{{ request('client_id') }}"
                           placeholder="{{ __('Client ID') }}" class="rounded border-gray-300 text-gray-900 text-sm px-3 py-2 w-24">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded">
                        {{ __('Apply') }}
                    </button>
                </form>
                <div class="ms-auto flex items-center gap-2">
                    <a href="{{ route('staff.order-stats.index') }}" class="px-3 py-2 text-gray-600 hover:text-gray-900 text-sm">{{ __('Reset') }}</a>
                    <a href="{{ route('staff.order-stats.export') }}?{{ http_build_query(array_filter(request()->only(['date_from','date_to','status','service_id','client_id']) + ['tab' => 'all'])) }}"
                       class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm font-medium rounded">
                        {{ __('Export CSV') }}
                    </a>
                </div>
            </div>

            {{-- Top metrics: Orders (with area chart) + Tasks Received/Completed --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
                    <h3 class="text-gray-600 text-sm font-medium mb-2">{{ __('Orders (Time Range)') }}</h3>
                    <div class="text-3xl font-bold text-indigo-600 mb-3">{{ number_format($totalsAll->orders_count ?? 0) }}</div>
                    <div class="h-24">
                        <canvas id="chartOrdersArea"></canvas>
                    </div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
                    <h3 class="text-gray-600 text-sm font-medium mb-2">{{ __('Tasks') }}</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <div class="text-xs text-gray-500">{{ __('Received') }}</div>
                            <div class="text-2xl font-bold text-indigo-600">{{ number_format($totalsAll->orders_count ?? 0) }}</div>
                            <div class="h-16 mt-1"><canvas id="chartReceivedArea"></canvas></div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500">{{ __('Completed') }}</div>
                            <div class="text-2xl font-bold text-emerald-600">{{ number_format($totalsCompleted->orders_count ?? 0) }}</div>
                            <div class="h-16 mt-1"><canvas id="chartCompletedArea"></canvas></div>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
                    <h3 class="text-gray-600 text-sm font-medium mb-2">{{ __('Summary') }}</h3>
                    <div class="space-y-2">
                        <div class="flex justify-between"><span class="text-gray-600">{{ __('Total revenue (all without failed)') }}</span><span class="font-bold text-gray-900">{{ number_format((float) ($totalsWithoutFailed->total_charge ?? 0), 2) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-600">{{ __('Total quantity') }}</span><span class="font-bold text-gray-900">{{ number_format($totalsWithoutFailed->total_quantity ?? 0) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-600">{{ __('Total remains') }}</span><span class="font-bold text-gray-900">{{ number_format($totalsWithoutFailed->total_remains ?? 0) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-600">{{ __('Total difference') }}</span><span class="font-bold text-gray-900">{{ number_format(($totalsWithoutFailed->total_quantity ?? 0) - ($totalsWithoutFailed->total_remains ?? 0)) }}</span></div>
                        <div class="flex justify-between pt-1 border-t border-gray-100"><span class="text-gray-600">{{ __('Failed') }}</span><span class="font-bold text-red-600">{{ number_format($totalsFailed->orders_count ?? 0) }}</span></div>
                    </div>
                </div>
            </div>

            {{-- Line charts: Orders by service + Errors over time --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
                    <h3 class="text-gray-600 text-sm font-medium mb-3">{{ __('Orders Received (Time Range)') }}</h3>
                    <div class="h-64">
                        <canvas id="chartOrdersByService"></canvas>
                    </div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
                    <h3 class="text-gray-600 text-sm font-medium mb-3">{{ __('Errors') }}</h3>
                    <div class="h-64">
                        <canvas id="chartErrorsOverTime"></canvas>
                    </div>
                </div>
            </div>

            {{-- Error pie chart + Top errors table --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
                    <h3 class="text-gray-600 text-sm font-medium mb-3">{{ __('Errors (Time Range)') }}</h3>
                    <div class="flex items-center gap-4">
                        <div class="h-48 w-48 shrink-0">
                            <canvas id="chartErrorPie"></canvas>
                        </div>
                        <div class="flex-1 text-sm max-h-48 overflow-y-auto space-y-1">
                            @foreach($chartErrorPieData ?? [] as $row)
                                <div class="flex justify-between text-gray-700">
                                    <span class="truncate max-w-[180px]" title="{{ e($row['label']) }}">{{ $row['label'] }}</span>
                                    <span class="shrink-0 ml-2">{{ number_format($row['value']) }} ({{ $row['percent'] }}%)</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-5">
                    <h3 class="text-gray-600 text-sm font-medium mb-3">{{ __('Top errors by count') }}</h3>
                    <div class="h-48 overflow-y-auto">
                        <canvas id="chartTopErrorsBar"></canvas>
                    </div>
                </div>
            </div>

            {{-- Tabs: All / Completed / Failed with tables --}}
            @php $currentTab = request('tab', 'all'); if (!in_array($currentTab, ['all','completed','failed'], true)) $currentTab = 'all'; @endphp
            <div id="order-stats-content" x-data="{ tab: '{{ $currentTab }}' }" class="mb-6">
                <div class="border-b border-gray-200 flex gap-2 mb-4">
                    <button type="button" @click="tab='all'; const u=new URL(location.href); u.searchParams.set('tab','all'); history.replaceState(null,'',u)"
                            :class="tab==='all'?'border-indigo-500 text-indigo-600':'border-transparent text-gray-500 hover:text-gray-700'"
                            class="px-4 py-2 text-sm font-medium border-b-2 transition">{{ __('All') }}</button>
                    <button type="button" @click="tab='completed'; const u=new URL(location.href); u.searchParams.set('tab','completed'); history.replaceState(null,'',u)"
                            :class="tab==='completed'?'border-indigo-500 text-indigo-600':'border-transparent text-gray-500 hover:text-gray-700'"
                            class="px-4 py-2 text-sm font-medium border-b-2 transition">{{ __('Completed') }}</button>
                    <button type="button" @click="tab='failed'; const u=new URL(location.href); u.searchParams.set('tab','failed'); history.replaceState(null,'',u)"
                            :class="tab==='failed'?'border-indigo-500 text-indigo-600':'border-transparent text-gray-500 hover:text-gray-700'"
                            class="px-4 py-2 text-sm font-medium border-b-2 transition">{{ __('Failed') }} ({{ $byError->sum('orders_count') }})</button>
                </div>
                <div x-show="tab==='all'" x-cloak>
                    @include('staff.order-stats.partials.tables', ['byClient' => $byClient, 'byService' => $byService, 'byClientByService' => $byClientByService, 'clients' => $clients, 'services' => $services, 'tab' => 'all', 'dark' => false])
                </div>
                <div x-show="tab==='completed'" x-cloak>
                    @include('staff.order-stats.partials.tables', ['byClient' => $byClientCompleted, 'byService' => $byServiceCompleted, 'byClientByService' => $byClientByServiceCompleted, 'clients' => $clients, 'services' => $services, 'tab' => 'completed', 'dark' => false])
                </div>
                <div x-show="tab==='failed'" x-cloak>
                    @include('staff.order-stats.partials.tables', ['byClient' => $byClientFailed, 'byService' => $byServiceFailed, 'byClientByService' => $byClientByServiceFailed, 'clients' => $clients, 'services' => $services, 'tab' => 'failed', 'dark' => false])
                    <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden mt-6">
                        <h3 class="px-4 py-3 text-sm font-medium text-gray-800 border-b border-gray-200">{{ __('Error details') }}</h3>
                        <div class="overflow-x-auto max-h-64 overflow-y-auto">
                            <table class="min-w-full">
                                <thead class="bg-gray-50 sticky top-0">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs text-gray-500 uppercase">{{ __('Error') }}</th>
                                        <th class="px-4 py-2 text-right text-xs text-gray-500 uppercase">{{ __('Orders') }}</th>
                                        <th class="px-4 py-2 text-right text-xs text-gray-500 uppercase">{{ __('Charge') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    @forelse($byError as $row)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 text-sm text-gray-900 break-all max-w-md">{{ Str::limit($row->provider_last_error, 80) }}</td>
                                            <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format($row->orders_count) }}</td>
                                            <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format((float) $row->total_charge, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="3" class="px-4 py-6 text-center text-gray-500">{{ __('No errors') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Chart.defaults.color = '#4b5563';
            Chart.defaults.borderColor = '#e5e7eb';
            Chart.defaults.font.family = 'system-ui';

            const periods = @json($allPeriods ?? []);
            const ordersOverTime = @json($chartOrdersOverTime ?? []);
            const completedOverTime = @json($chartCompletedOverTime ?? []);
            const ordersByService = @json($chartOrdersByServiceOverTime ?? []);
            const errorsOverTime = @json($chartErrorsOverTime ?? []);
            const errorPie = @json($chartErrorPieData ?? []);
            const topErrors = @json($chartTopErrorsData ?? []);

            const labels = periods;
            const ordersData = periods.map(p => ordersOverTime[p] || 0);
            const completedData = periods.map(p => completedOverTime[p] || 0);

            const areaOpt = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { display: !!labels.length }, y: { beginAtZero: true } } };
            const lineColors = ['#22c55e','#eab308','#3b82f6','#a855f7','#ef4444','#06b6d4'];

            if (document.getElementById('chartOrdersArea') && labels.length) {
                new Chart(document.getElementById('chartOrdersArea'), {
                    type: 'line',
                    data: { labels, datasets: [{ label: 'Orders', data: ordersData, borderColor: '#4f46e5', backgroundColor: 'rgba(79,70,229,0.2)', fill: true, tension: 0.3 }] },
                    options: areaOpt
                });
            }
            if (document.getElementById('chartReceivedArea') && labels.length) {
                new Chart(document.getElementById('chartReceivedArea'), {
                    type: 'line',
                    data: { labels, datasets: [{ label: 'Received', data: ordersData, borderColor: '#4f46e5', backgroundColor: 'rgba(79,70,229,0.2)', fill: true, tension: 0.3 }] },
                    options: areaOpt
                });
            }
            if (document.getElementById('chartCompletedArea') && labels.length) {
                new Chart(document.getElementById('chartCompletedArea'), {
                    type: 'line',
                    data: { labels, datasets: [{ label: 'Completed', data: completedData, borderColor: '#22c55e', backgroundColor: 'rgba(34,197,94,0.2)', fill: true, tension: 0.3 }] },
                    options: areaOpt
                });
            }
            if (document.getElementById('chartOrdersByService') && ordersByService.length && labels.length) {
                new Chart(document.getElementById('chartOrdersByService'), {
                    type: 'line',
                    data: {
                        labels,
                        datasets: ordersByService.map((s, i) => ({
                            label: s.label,
                            data: periods.map(p => s.data[p] || 0),
                            borderColor: lineColors[i % lineColors.length],
                            backgroundColor: 'transparent',
                            tension: 0.3
                        }))
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, scales: { x: { display: true }, y: { beginAtZero: true } } }
                });
            }
            if (document.getElementById('chartErrorsOverTime') && errorsOverTime.length && labels.length) {
                new Chart(document.getElementById('chartErrorsOverTime'), {
                    type: 'line',
                    data: {
                        labels,
                        datasets: errorsOverTime.map((e, i) => ({
                            label: e.label,
                            data: periods.map(p => (e.data || {})[p] || 0),
                            borderColor: lineColors[i % lineColors.length],
                            backgroundColor: 'transparent',
                            tension: 0.3
                        }))
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, scales: { x: { display: true }, y: { beginAtZero: true } } }
                });
            }
            if (document.getElementById('chartErrorPie') && errorPie.length) {
                new Chart(document.getElementById('chartErrorPie'), {
                    type: 'doughnut',
                    data: {
                        labels: errorPie.map(e => e.label),
                        datasets: [{ data: errorPie.map(e => e.value), backgroundColor: ['#ef4444','#f97316','#eab308','#22c55e','#06b6d4','#3b82f6','#8b5cf6','#ec4899','#64748b','#94a3b8'] }]
                    },
                    options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { display: false } } }
                });
            }
            if (document.getElementById('chartTopErrorsBar') && topErrors.length) {
                new Chart(document.getElementById('chartTopErrorsBar'), {
                    type: 'bar',
                    data: {
                        labels: topErrors.map(e => e.label),
                        datasets: [{ label: '{{ __("Orders") }}', data: topErrors.map(e => e.orders_count), backgroundColor: 'rgba(239,68,68,0.6)' }]
                    },
                    options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true } } }
                });
            }
        });
    </script>
</x-app-layout>
