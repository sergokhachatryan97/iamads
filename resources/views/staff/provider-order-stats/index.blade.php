<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Provider order statistics') }}
            </h2>
        </div>
    </x-slot>

    @php
        $tab = $tab ?? request('tab', 'without_failed');

        if (!in_array($tab, ['completed', 'without_failed', 'partial'], true)) {
            $tab = 'without_failed';
        }

        $totals = $totals ?? [
            'orders_count' => 0,
            'total_quantity' => 0,
            'total_remains' => 0,
            'total_difference' => 0,
            'total_charge' => 0,
        ];

        $serviceNames = $serviceNames ?? [];
        $distinctProviderCodes = $distinctProviderCodes ?? [];
        $distinctServiceIds = $distinctServiceIds ?? [];
    @endphp

    <div class="py-6">
        <div class="max-w-[1600px] mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm rounded-lg p-6 mb-6">
                <form method="GET"
                      action="{{ route('staff.provider-order-stats.index') }}"
                      class="flex flex-wrap items-end gap-3">
                    <input type="hidden" name="tab" value="{{ $tab }}">

                    <div>
                        <label for="date_from" class="block text-xs font-medium text-gray-500 uppercase mb-1">
                            {{ __('Date from') }}
                        </label>
                        <input
                            type="date"
                            name="date_from"
                            id="date_from"
                            value="{{ $inputDateFrom ?? request('date_from') }}"
                            class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        >
                    </div>

                    <div>
                        <label for="date_to" class="block text-xs font-medium text-gray-500 uppercase mb-1">
                            {{ __('Date to') }}
                        </label>
                        <input
                            type="date"
                            name="date_to"
                            id="date_to"
                            value="{{ $inputDateTo ?? request('date_to') }}"
                            class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        >
                    </div>

                    <div>
                        <label for="provider_code" class="block text-xs font-medium text-gray-500 uppercase mb-1">
                            {{ __('Provider') }}
                        </label>
                        <select
                            name="provider_code"
                            id="provider_code"
                            class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-40"
                        >
                            <option value="">{{ __('All') }}</option>
                            @foreach($distinctProviderCodes as $code => $label)
                                <option value="{{ $code }}" {{ request('provider_code') === (string) $code ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="remote_service_id" class="block text-xs font-medium text-gray-500 uppercase mb-1">
                            {{ __('Service') }}
                        </label>
                        <select
                            name="remote_service_id"
                            id="remote_service_id"
                            class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-40"
                        >
                            <option value="">{{ __('All') }}</option>
                            @foreach($distinctServiceIds as $id => $label)
                                <option value="{{ $id }}" {{ request('remote_service_id') === (string) $id ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="user_login" class="block text-xs font-medium text-gray-500 uppercase mb-1">
                            {{ __('User login') }}
                        </label>
                        <input
                            type="text"
                            name="user_login"
                            id="user_login"
                            value="{{ request('user_login') }}"
                            placeholder="{{ __('Filter by login') }}"
                            class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-44"
                        >
                    </div>

                    <div>
                        <label for="user_remote_id" class="block text-xs font-medium text-gray-500 uppercase mb-1">
                            {{ __('User remote id') }}
                        </label>
                        <input
                            type="text"
                            name="user_remote_id"
                            id="user_remote_id"
                            value="{{ request('user_remote_id') }}"
                            placeholder="{{ __('Filter by user remote id') }}"
                            class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-44"
                        >
                    </div>

                    <button
                        type="submit"
                        class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                    >
                        {{ __('Apply') }}
                    </button>

                    <a
                        href="{{ route('staff.provider-order-stats.index', ['tab' => $tab]) }}"
                        class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-600 bg-white hover:bg-gray-50"
                    >
                        {{ __('Reset') }}
                    </a>
                </form>
            </div>

            <div id="provider-order-stats-content"
                 class="bg-white shadow-sm rounded-lg"
                 data-index-url="{{ route('staff.provider-order-stats.index') }}"
                 data-export-url="{{ route('staff.provider-order-stats.export') }}">

                <div class="border-b border-gray-200 flex flex-wrap items-center justify-between gap-3 px-6 pt-4">
                    <nav class="-mb-px flex gap-1" aria-label="Tabs">
                        <a href="{{ route('staff.provider-order-stats.index', array_merge(request()->query(), ['tab' => 'completed'])) }}"
                           class="px-4 py-2 text-sm font-medium border-b-2 rounded-t transition {{ $tab === 'completed' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }}">
                            {{ __('Completed') }}
                        </a>

                        <a href="{{ route('staff.provider-order-stats.index', array_merge(request()->query(), ['tab' => 'without_failed'])) }}"
                           class="px-4 py-2 text-sm font-medium border-b-2 rounded-t transition {{ $tab === 'without_failed' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }}">
                            {{ __('All without failed') }}
                        </a>

                        <a href="{{ route('staff.provider-order-stats.index', array_merge(request()->query(), ['tab' => 'partial'])) }}"
                           class="px-4 py-2 text-sm font-medium border-b-2 rounded-t transition {{ $tab === 'partial' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }}">
                            {{ __('Partial') }}
                        </a>
                    </nav>

                    <a href="{{ route('staff.provider-order-stats.export', array_merge(request()->query(), ['tab' => $tab])) }}"
                       class="inline-flex items-center px-3 py-2 border border-green-600 text-sm font-medium rounded-md text-green-700 bg-white hover:bg-green-50">
                        {{ __('Export (CSV)') }}
                    </a>
                </div>

                <div class="p-6">
                    <div class="mb-6 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
                        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                            <div class="text-xs uppercase tracking-wide text-gray-500">{{ __('Tab') }}</div>
                            <div class="mt-1 text-sm font-semibold text-gray-900">
                                @if($tab === 'completed')
                                    {{ __('Completed') }}
                                @elseif($tab === 'partial')
                                    {{ __('Partial') }}
                                @else
                                    {{ __('All without failed') }}
                                @endif
                            </div>
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                            <div class="text-xs uppercase tracking-wide text-gray-500">{{ __('Orders') }}</div>
                            <div class="mt-1 text-lg font-semibold text-gray-900">
                                {{ number_format((int) ($totals['orders_count'] ?? 0)) }}
                            </div>
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                            <div class="text-xs uppercase tracking-wide text-gray-500">{{ __('Quantity') }}</div>
                            <div class="mt-1 text-lg font-semibold text-gray-900">
                                {{ number_format((int) ($totals['total_quantity'] ?? 0)) }}
                            </div>
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                            <div class="text-xs uppercase tracking-wide text-gray-500">{{ __('Remains') }}</div>
                            <div class="mt-1 text-lg font-semibold text-gray-900">
                                {{ number_format((int) ($totals['total_remains'] ?? 0)) }}
                            </div>
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                            <div class="text-xs uppercase tracking-wide text-gray-500">{{ __('Total revenue') }}</div>
                            <div class="mt-1 text-lg font-semibold text-gray-900">
                                {{ number_format((float) ($totals['total_charge'] ?? 0), 2) }}
                            </div>
                        </div>
                    </div>

                    <div class="mb-6 rounded-lg border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                        <span class="font-medium">{{ __('Difference') }}:</span>
                        {{ number_format((int) ($totals['total_difference'] ?? 0)) }}
                    </div>

                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                        <div class="rounded-lg border border-gray-200 overflow-hidden">
                            <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                                <h3 class="text-sm font-semibold text-gray-900">
                                    {{ __('Revenue by user') }}
                                </h3>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-white">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">{{ __('User') }}</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">{{ __('Orders') }}</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">{{ __('Quantity') }}</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">{{ __('Remains') }}</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">{{ __('Difference') }}</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">{{ __('Avg') }}</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">{{ __('Total') }}</th>
                                    </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 bg-white">
                                    @forelse($byUser as $row)
                                        <tr>
                                            <td class="px-4 py-2 text-sm text-gray-900">
                                                {{ $row->user_login ?: ($row->user_remote_id ?: '—') }}
                                            </td>
                                            <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format((int) $row->orders_count) }}</td>
                                            <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format((int) $row->total_quantity) }}</td>
                                            <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format((int) $row->total_remains) }}</td>
                                            <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format((int) $row->total_difference) }}</td>
                                            <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format((float) $row->avg_charge, 2) }}</td>
                                            <td class="px-4 py-2 text-sm text-right font-medium text-gray-900">{{ number_format((float) $row->total_charge, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="px-4 py-6 text-sm text-center text-gray-500">
                                                {{ __('No data found') }}
                                            </td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>

                            @if(method_exists($byUser, 'links'))
                                <div class="px-4 py-3 border-t border-gray-200 bg-gray-50">
                                    {{ $byUser->links() }}
                                </div>
                            @endif
                        </div>

                        <div class="rounded-lg border border-gray-200 overflow-hidden">
                            <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                                <h3 class="text-sm font-semibold text-gray-900">
                                    {{ __('Revenue by service') }}
                                </h3>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-white">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">{{ __('Service') }}</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">{{ __('Orders') }}</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">{{ __('Quantity') }}</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">{{ __('Remains') }}</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">{{ __('Difference') }}</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">{{ __('Total') }}</th>
                                    </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 bg-white">
                                    @forelse($byService as $row)
                                        <tr>
                                            <td class="px-4 py-2 text-sm text-gray-900">
                                                {{ $serviceNames[$row->remote_service_id] ?? $row->remote_service_id ?? '—' }}
                                            </td>
                                            <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format((int) $row->orders_count) }}</td>
                                            <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format((int) $row->total_quantity) }}</td>
                                            <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format((int) $row->total_remains) }}</td>
                                            <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format((int) $row->total_difference) }}</td>
                                            <td class="px-4 py-2 text-sm text-right font-medium text-gray-900">{{ number_format((float) $row->total_charge, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="px-4 py-6 text-sm text-center text-gray-500">
                                                {{ __('No data found') }}
                                            </td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>

                            @if(method_exists($byService, 'links'))
                                <div class="px-4 py-3 border-t border-gray-200 bg-gray-50">
                                    {{ $byService->links() }}
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="mt-6 rounded-lg border border-gray-200 overflow-hidden">
                        <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                            <h3 class="text-sm font-semibold text-gray-900">
                                {{ __('Breakdown by user and service') }}
                            </h3>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-white">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">{{ __('User') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">{{ __('Service') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">{{ __('Orders') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">{{ __('Quantity') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">{{ __('Remains') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">{{ __('Difference') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">{{ __('Total') }}</th>
                                </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                @forelse($byUserByService as $row)
                                    <tr>
                                        <td class="px-4 py-2 text-sm text-gray-900">
                                            {{ $row->user_login ?: ($row->user_remote_id ?: '—') }}
                                        </td>
                                        <td class="px-4 py-2 text-sm text-gray-900">
                                            {{ $serviceNames[$row->remote_service_id] ?? $row->remote_service_id ?? '—' }}
                                        </td>
                                        <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format((int) $row->orders_count) }}</td>
                                        <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format((int) $row->total_quantity) }}</td>
                                        <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format((int) $row->total_remains) }}</td>
                                        <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format((int) $row->total_difference) }}</td>
                                        <td class="px-4 py-2 text-sm text-right font-medium text-gray-900">{{ number_format((float) $row->total_charge, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-4 py-6 text-sm text-center text-gray-500">
                                            {{ __('No data found') }}
                                        </td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if(method_exists($byUserByService, 'links'))
                            <div class="px-4 py-3 border-t border-gray-200 bg-gray-50">
                                {{ $byUserByService->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.addEventListener('click', function (e) {
                var link = e.target.closest('a[href]');
                if (!link || link.target === '_blank') return;

                var container = document.getElementById('provider-order-stats-content');
                if (!container) return;
                if (!container.contains(link)) return;

                var href = link.getAttribute('href');
                if (!href || href.charAt(0) === '#') return;
                if (href.indexOf('/export') !== -1) return;

                var fullUrl = link.href;
                var indexUrl = container.getAttribute('data-index-url');
                if (!indexUrl) return;

                var baseUrl = indexUrl.indexOf('http') === 0
                    ? indexUrl
                    : (window.location.origin + (indexUrl.charAt(0) === '/' ? indexUrl : '/' + indexUrl));

                if (fullUrl.indexOf(baseUrl) !== 0) return;

                e.preventDefault();

                container.classList.add('opacity-60', 'pointer-events-none');

                fetch(fullUrl, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'text/html'
                    }
                })
                    .then(function (r) {
                        return r.text();
                    })
                    .then(function (html) {
                        var parser = new DOMParser();
                        var doc = parser.parseFromString(html, 'text/html');
                        var newContent = doc.getElementById('provider-order-stats-content');

                        if (!newContent) {
                            location.href = fullUrl;
                            return;
                        }

                        container.replaceWith(newContent);
                        history.pushState({}, '', fullUrl);
                    })
                    .catch(function () {
                        location.href = fullUrl;
                    })
                    .finally(function () {
                        var c = document.getElementById('provider-order-stats-content');
                        if (c) {
                            c.classList.remove('opacity-60', 'pointer-events-none');
                        }
                    });
            });

            window.addEventListener('popstate', function () {
                location.reload();
            });
        });
    </script>
</x-app-layout>
