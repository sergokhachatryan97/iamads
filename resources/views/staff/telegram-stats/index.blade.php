<x-app-layout>
    <style>[x-cloak]{display:none!important}</style>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Telegram Statistics') }}
        </h2>
    </x-slot>

    <div class="py-6 bg-white min-h-[calc(100vh-4rem)]"
         x-data="telegramStats(@js($dataUrl), @js($serviceId))">

        <div class="max-w-8xl mx-auto sm:px-6 lg:px-8">

            {{-- Scope tabs --}}
            <div class="flex gap-1 bg-gray-100 rounded-lg p-1 mb-4 w-fit">
                <a href="{{ route('staff.telegram-stats.index', array_merge(request()->except('scope'), ['scope' => 'default'])) }}"
                   class="px-4 py-1.5 text-sm font-medium rounded-md transition {{ $scope === 'default' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                    Telegram
                </a>
                <a href="{{ route('staff.telegram-stats.index', array_merge(request()->except('scope'), ['scope' => 'premium'])) }}"
                   class="px-4 py-1.5 text-sm font-medium rounded-md transition {{ $scope === 'premium' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                    Telegram Premium ⭐
                </a>
            </div>

            {{-- Filter bar --}}
            <div class="bg-gray-50 rounded-lg border border-gray-200 p-4 mb-6 flex flex-wrap items-end gap-4">
                <form method="get" action="{{ route('staff.telegram-stats.index') }}" class="flex flex-wrap items-end gap-4">
                    <input type="hidden" name="scope" value="{{ $scope }}">
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
                        <template x-for="s in (data.telegramServices || [])" :key="s.id">
                            <option :value="s.id" :selected="s.id == selectedServiceId"
                                    x-text="s.name.length > 35 ? s.name.substring(0, 35) + '...' : s.name"></option>
                        </template>
                    </select>
                    @if(!$dateFrom && !$dateTo)
                        <input type="hidden" name="period" value="{{ $period }}">
                    @endif
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded">
                        {{ __('Apply') }}
                    </button>
                </form>
                <div class="ms-auto flex items-center gap-3">
                    @if($dateFrom || $dateTo || $serviceId)
                        <a href="{{ route('staff.telegram-stats.index', ['scope' => $scope]) }}"
                           class="px-3 py-2 text-gray-600 hover:text-gray-900 text-sm">{{ __('Reset') }}</a>
                    @endif
                    @if(!$dateFrom && !$dateTo)
                        <div class="flex gap-1 bg-gray-100 rounded-lg p-1">
                            <a href="{{ route('staff.telegram-stats.index', ['period' => 'days', 'scope' => $scope]) }}"
                               class="px-4 py-1.5 text-sm font-medium rounded-md transition {{ $period === 'days' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                                {{ __('Days') }}
                            </a>
                            <a href="{{ route('staff.telegram-stats.index', ['period' => 'monthly', 'scope' => $scope]) }}"
                               class="px-4 py-1.5 text-sm font-medium rounded-md transition {{ $period === 'monthly' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                                {{ __('Monthly') }}
                            </a>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Error state --}}
            <div x-show="error" x-cloak class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6 flex items-center gap-3 text-red-700 text-sm">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                <span x-text="error"></span>
                <button @click="fetchData()" class="ml-auto underline font-medium hover:no-underline">{{ __('Retry') }}</button>
            </div>

            {{-- 4 metric cards --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

                {{-- Income --}}
                <div class="bg-gray-900 rounded-xl p-5">
                    <div class="text-gray-400 text-xs font-medium mb-1 uppercase tracking-wide">{{ __('Income') }}</div>
                    <div x-show="loading" class="animate-pulse h-8 bg-gray-700 rounded w-3/4 mb-2"></div>
                    <div x-show="!loading" x-cloak class="text-2xl font-bold text-white leading-tight"
                         x-text="'$' + fmtNum(data.income_today || 0)"></div>
                    <div x-show="!loading && !data.has_date_filter && data.income_yesterday > 0" x-cloak
                         class="text-xs mt-1"
                         :class="(data.income_today - data.income_yesterday) >= 0 ? 'text-emerald-400' : 'text-red-400'"
                         x-text="((data.income_today - data.income_yesterday) >= 0 ? '▲' : '▼') + ' $' + fmtNum(Math.abs(data.income_today - data.income_yesterday)) + ' vs ' + (data.period === 'monthly' ? '{{ __('prev month') }}' : '{{ __('yesterday') }}')">
                    </div>
                    <div class="text-gray-600 text-xs mt-1" x-text="periodLabel"></div>
                </div>

                {{-- Completions --}}
                <div class="bg-gray-900 rounded-xl p-5">
                    <div class="text-gray-400 text-xs font-medium mb-1 uppercase tracking-wide">{{ __('Completions') }}</div>
                    <div x-show="loading" class="animate-pulse h-8 bg-gray-700 rounded w-3/4 mb-2"></div>
                    <div x-show="!loading" x-cloak class="text-2xl font-bold text-white leading-tight"
                         x-text="fmtNum(data.completions_today || 0)"></div>
                    <div x-show="!loading && !data.has_date_filter && data.completions_yesterday > 0" x-cloak
                         class="text-xs mt-1"
                         :class="(data.completions_today - data.completions_yesterday) >= 0 ? 'text-emerald-400' : 'text-red-400'"
                         x-text="((data.completions_today - data.completions_yesterday) >= 0 ? '▲' : '▼') + ' ' + fmtNum(Math.abs(data.completions_today - data.completions_yesterday)) + ' vs ' + (data.period === 'monthly' ? '{{ __('prev month') }}' : '{{ __('yesterday') }}')">
                    </div>
                    <div class="text-gray-600 text-xs mt-1" x-text="periodLabel"></div>
                </div>

                {{-- Active accounts --}}
                <div class="bg-gray-900 rounded-xl p-5">
                    <div class="text-gray-400 text-xs font-medium mb-1 uppercase tracking-wide">{{ __('Active Accounts') }}</div>
                    <div x-show="loading" class="animate-pulse h-8 bg-gray-700 rounded w-3/4 mb-2"></div>
                    <div x-show="!loading" x-cloak class="text-2xl font-bold leading-tight"
                         :class="data.totals && data.totals.accounts > 0 ? 'text-emerald-400' : 'text-white'"
                         x-text="fmtNum(data.totals ? data.totals.accounts : 0)"></div>
                    <div class="text-gray-600 text-xs mt-1">{{ __('Last 2 hours') }}</div>
                </div>

                {{-- Active orders --}}
                <div class="bg-gray-900 rounded-xl p-5">
                    <div class="text-gray-400 text-xs font-medium mb-1 uppercase tracking-wide">{{ __('Active Orders') }}</div>
                    <div x-show="loading" class="animate-pulse h-8 bg-gray-700 rounded w-3/4 mb-2"></div>
                    <div x-show="!loading" x-cloak class="text-2xl font-bold text-white leading-tight"
                         x-text="fmtNum(data.totals ? data.totals.in_progress_orders : 0)"></div>
                    <div x-show="!loading" x-cloak class="text-gray-600 text-xs mt-1"
                         x-text="fmtNum(data.totals ? data.totals.total_volume : 0) + ' {{ __('left to deliver') }}'"></div>
                </div>

            </div>

            {{-- Chart --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">{{ __('Performance') }}</h3>
                    <div class="flex items-center gap-4 text-xs text-gray-500">
                        <span class="flex items-center gap-1"><span class="inline-block w-3 h-0.5 bg-emerald-500 rounded"></span> {{ __('Income') }}</span>
                        <span class="flex items-center gap-1"><span class="inline-block w-3 h-0.5 bg-blue-500 rounded"></span> {{ __('Completions') }}</span>
                    </div>
                </div>
                <div class="h-64 relative">
                    <div x-show="loading" class="absolute inset-0 flex items-end gap-1 px-2 pb-2">
                        @foreach([45,62,38,71,55,80,48,65,72,58,83,67] as $h)
                            <div class="flex-1 bg-gray-100 animate-pulse rounded-t" style="height:{{ $h }}%"></div>
                        @endforeach
                    </div>
                    <canvas id="chartMain" x-show="!loading" x-cloak></canvas>
                </div>
            </div>

            {{-- Services table --}}
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-700">{{ __('Services') }}</h3>
                    <template x-if="!loading && data.pagination && data.pagination.total > data.pagination.per_page">
                        <span class="text-xs text-gray-400"
                              x-text="((data.pagination.current_page - 1) * data.pagination.per_page + 1) + '–' + Math.min(data.pagination.current_page * data.pagination.per_page, data.pagination.total) + ' {{ __('of') }} ' + data.pagination.total">
                        </span>
                    </template>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Service') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider"
                                    x-text="data.completions_column_label || '{{ __('Completions today') }}'"></th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Accounts') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Delay') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Price / 1K') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Active Orders') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Volume left') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Income today') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Completed orders') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">

                            {{-- Skeleton rows while loading --}}
                            <template x-if="loading">
                                <template x-for="i in 5" :key="i">
                                    <tr>
                                        <td class="px-4 py-3"><div class="animate-pulse h-4 bg-gray-100 rounded w-48"></div></td>
                                        <td class="px-4 py-3 text-right"><div class="animate-pulse h-4 bg-gray-100 rounded w-16 ml-auto"></div></td>
                                        <td class="px-4 py-3 text-right"><div class="animate-pulse h-4 bg-gray-100 rounded w-12 ml-auto"></div></td>
                                        <td class="px-4 py-3 text-right"><div class="animate-pulse h-4 bg-gray-100 rounded w-12 ml-auto"></div></td>
                                        <td class="px-4 py-3 text-right"><div class="animate-pulse h-4 bg-gray-100 rounded w-16 ml-auto"></div></td>
                                        <td class="px-4 py-3 text-right"><div class="animate-pulse h-4 bg-gray-100 rounded w-12 ml-auto"></div></td>
                                        <td class="px-4 py-3 text-right"><div class="animate-pulse h-4 bg-gray-100 rounded w-12 ml-auto"></div></td>
                                        <td class="px-4 py-3 text-right"><div class="animate-pulse h-4 bg-gray-100 rounded w-16 ml-auto"></div></td>
                                        <td class="px-4 py-3 text-right"><div class="animate-pulse h-4 bg-gray-100 rounded w-16 ml-auto"></div></td>
                                    </tr>
                                </template>
                            </template>

                            {{-- Actual data rows --}}
                            <template x-if="!loading">
                                <template x-for="service in (data.services || [])" :key="service.id">
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 text-gray-900">
                                            <div class="flex items-center gap-2">
                                                <span class="text-blue-500 text-xs"><i class="fab fa-telegram"></i></span>
                                                <div>
                                                    <div class="font-medium"
                                                         x-text="service.name.length > 40 ? service.name.substring(0, 40) + '...' : service.name"></div>
                                                    <div class="text-xs text-gray-400" x-text="'#' + service.id"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-right font-medium text-gray-900"
                                            x-text="fmtNum(service.completions_today)"></td>
                                        <td class="px-4 py-3 text-right font-medium"
                                            :class="service.accounts > 0 ? 'text-emerald-600' : 'text-gray-400'"
                                            x-text="fmtNum(service.accounts)"></td>
                                        <td class="px-4 py-3 text-right"
                                            :class="service.delay_sec !== null && service.delay_sec !== undefined && service.delay_sec <= 60
                                                ? 'text-emerald-600'
                                                : (service.delay_sec !== null && service.delay_sec !== undefined && service.delay_sec <= 300 ? 'text-yellow-600' : 'text-gray-400')"
                                            x-text="fmtDelay(service.delay_sec)"></td>
                                        <td class="px-4 py-3 text-right text-gray-900"
                                            x-text="service.rate_per_1000 ? '$' + fmtPrice(service.rate_per_1000) : '-'"></td>
                                        <td class="px-4 py-3 text-right text-gray-900"
                                            x-text="fmtNum(service.in_progress_orders)"></td>
                                        <td class="px-4 py-3 text-right text-gray-900"
                                            x-text="fmtNum(service.total_volume)"></td>
                                        <td class="px-4 py-3 text-right text-emerald-600 font-medium"
                                            x-text="'$' + fmtPrice(service.income_today)"></td>
                                        <td class="px-4 py-3 text-right text-gray-900">
                                            <div x-text="fmtNum(service.completed_orders)"></div>
                                            <div class="text-xs text-emerald-600" x-text="'$' + fmtPrice(service.completed_balance)"></div>
                                        </td>
                                    </tr>
                                </template>
                            </template>

                            {{-- Empty state --}}
                            <template x-if="!loading && (!data.services || data.services.length === 0)">
                                <tr>
                                    <td colspan="9" class="px-4 py-8 text-center text-gray-500">{{ __('No Telegram services found') }}</td>
                                </tr>
                            </template>

                        </tbody>

                        {{-- Totals row --}}
                        <template x-if="!loading && data.totals && data.services && data.services.length > 0">
                            <tfoot class="bg-gray-50 border-t-2 border-gray-300">
                                <tr class="font-semibold text-gray-700">
                                    <td class="px-4 py-3 text-xs uppercase tracking-wide text-gray-500">{{ __('Total') }}</td>
                                    <td class="px-4 py-3 text-right" x-text="fmtNum(data.totals.completions_today)"></td>
                                    <td class="px-4 py-3 text-right"
                                        :class="data.totals.accounts > 0 ? 'text-emerald-600' : ''"
                                        x-text="fmtNum(data.totals.accounts)"></td>
                                    <td class="px-4 py-3 text-right text-gray-400">—</td>
                                    <td class="px-4 py-3 text-right text-gray-400">—</td>
                                    <td class="px-4 py-3 text-right" x-text="fmtNum(data.totals.in_progress_orders)"></td>
                                    <td class="px-4 py-3 text-right" x-text="fmtNum(data.totals.total_volume)"></td>
                                    <td class="px-4 py-3 text-right text-emerald-600" x-text="'$' + fmtPrice(data.totals.income_today)"></td>
                                    <td class="px-4 py-3 text-right">
                                        <div x-text="fmtNum(data.totals.completed_orders)"></div>
                                        <div class="text-xs text-emerald-600" x-text="'$' + fmtPrice(data.totals.completed_balance)"></div>
                                    </td>
                                </tr>
                            </tfoot>
                        </template>

                    </table>
                </div>

                {{-- Pagination: x-if (not x-show) so inner bindings never run before data.pagination exists --}}
                <template x-if="!loading && data.pagination && data.pagination.last_page > 1">
                <div class="px-4 py-3 border-t border-gray-100 flex items-center gap-1 flex-wrap">
                    <button @click="changePage(data.pagination.current_page - 1)"
                            :disabled="data.pagination.current_page <= 1"
                            class="px-3 py-1.5 text-sm rounded border border-gray-200 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-gray-50">
                        &laquo;
                    </button>
                    <template x-for="p in pageNumbers()" :key="p + '-' + Math.random()">
                        <button @click="p !== '...' && changePage(p)"
                                :disabled="p === '...'"
                                :class="p === data.pagination.current_page
                                    ? 'bg-indigo-600 text-white border-indigo-600'
                                    : (p === '...' ? 'cursor-default border-transparent text-gray-400' : 'hover:bg-gray-50 border-gray-200')"
                                class="px-3 py-1.5 text-sm rounded border min-w-[2rem] text-center"
                                x-text="p">
                        </button>
                    </template>
                    <button @click="changePage(data.pagination.current_page + 1)"
                            :disabled="data.pagination.current_page >= data.pagination.last_page"
                            class="px-3 py-1.5 text-sm rounded border border-gray-200 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-gray-50">
                        &raquo;
                    </button>
                </div>
                </template>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        function telegramStats(dataUrl, selectedServiceId) {
            // Chart instance lives outside Alpine's reactive state: a Proxy-wrapped
            // Chart.js object breaks its internal redraw after destroy().
            let chart = null;

            return {
                dataUrl,
                selectedServiceId,
                loading: true,
                error: null,
                data: {},

                get periodLabel() {
                    if (!this.data || this.data.has_date_filter) return '';
                    return this.data.period === 'monthly' ? '{{ __('This month') }}' : '{{ __('Today') }}';
                },

                async init() {
                    await this.fetchData();
                },

                async fetchData() {
                    this.loading = true;
                    this.error = null;
                    try {
                        const res = await fetch(this.dataUrl, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            }
                        });
                        if (!res.ok) throw new Error('HTTP ' + res.status);
                        this.data = await res.json();
                        this.$nextTick(() => this.renderChart());
                    } catch (e) {
                        this.error = '{{ __('Failed to load statistics. Please try again.') }}';
                    } finally {
                        this.loading = false;
                    }
                },

                changePage(page) {
                    if (!page || page === '...') return;
                    const url = new URL(this.dataUrl, window.location.origin);
                    url.searchParams.set('page', page);
                    this.dataUrl = url.toString();
                    this.fetchData();
                },

                pageNumbers() {
                    const cur  = this.data.pagination?.current_page || 1;
                    const last = this.data.pagination?.last_page    || 1;
                    if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1);
                    const pages = [1];
                    if (cur > 3) pages.push('...');
                    for (let i = Math.max(2, cur - 1); i <= Math.min(last - 1, cur + 1); i++) pages.push(i);
                    if (cur < last - 2) pages.push('...');
                    pages.push(last);
                    return pages;
                },

                fmtNum(v) {
                    return Math.round(v || 0).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '\u00a0');
                },

                fmtPrice(v) {
                    return parseFloat(v || 0).toFixed(4).replace(/\.?0+$/, '');
                },

                fmtDelay(sec) {
                    if (sec === null || sec === undefined) return '-';
                    if (sec >= 60) return Math.floor(sec / 60) + 'm ' + (sec % 60) + 's';
                    return sec + 's';
                },

                renderChart() {
                    const labels     = this.data.chart_labels      || [];
                    const incomeData = this.data.chart_income      || [];
                    const compData   = this.data.chart_completions || [];
                    const isMonthly  = this.data.period === 'monthly';

                    const ctx = document.getElementById('chartMain');
                    if (!ctx) return;
                    if (chart) { chart.destroy(); chart = null; }
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

                    const fmt = v => v.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '\u00a0');

                    chart = new Chart(ctx, {
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
                                    fill: true, tension: 0.4, borderWidth: 2,
                                    yAxisID: 'yIncome',
                                },
                                {
                                    label: 'Completions',
                                    data: compData,
                                    borderColor: '#3b82f6',
                                    backgroundColor: 'rgba(59,130,246,0.08)',
                                    pointBackgroundColor: '#3b82f6',
                                    pointBorderColor: '#fff',
                                    fill: true, tension: 0.4, borderWidth: 2,
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
                                    titleColor: '#111827', bodyColor: '#374151',
                                    borderColor: '#e5e7eb', borderWidth: 1,
                                    cornerRadius: 8, padding: 12,
                                    displayColors: true,
                                    boxWidth: 8, boxHeight: 8, boxPadding: 4,
                                    titleFont: { size: 12, weight: '500' },
                                    bodyFont: { size: 13, weight: '600' },
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
                                    position: 'left', beginAtZero: true,
                                    grid: { color: 'rgba(0,0,0,0.04)' },
                                    ticks: { color: '#22c55e', font: { size: 11 }, callback: v => '$' + fmt(v) }
                                },
                                yComp: {
                                    position: 'right', beginAtZero: true,
                                    grid: { drawOnChartArea: false },
                                    ticks: { color: '#3b82f6', font: { size: 11 }, callback: v => fmt(v) }
                                }
                            },
                            elements: {
                                point: { radius: 2, hoverRadius: 6, hoverBorderWidth: 2, hitRadius: 20 }
                            }
                        }
                    });
                }
            };
        }
    </script>
</x-app-layout>
