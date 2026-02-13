<x-app-layout>
    <style>[x-cloak]{display:none!important}</style>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Provider Order Statistics') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <p class="mb-4 text-sm text-gray-600">
                {{ __('Default date range: current day. Use tabs to switch between completed, all-without-failed, and partial.') }}
            </p>

            {{-- Filters --}}
            <form method="get" action="{{ route('staff.provider-order-stats.index') }}" class="mb-6 flex flex-wrap items-end gap-4">
                <div>
                    <label for="date_from" class="block text-xs font-medium text-gray-500 uppercase mb-1">{{ __('Date from') }}</label>
                    <input type="date" name="date_from" id="date_from" value="{{ $inputDateFrom ?? request('date_from') }}"
                           class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                </div>
                <div>
                    <label for="date_to" class="block text-xs font-medium text-gray-500 uppercase mb-1">{{ __('Date to') }}</label>
                    <input type="date" name="date_to" id="date_to" value="{{ $inputDateTo ?? request('date_to') }}"
                           class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                </div>
                <div>
                    <label for="provider_code" class="block text-xs font-medium text-gray-500 uppercase mb-1">{{ __('Provider') }}</label>
                    <select name="provider_code" id="provider_code" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-36">
                        <option value="">{{ __('All') }}</option>
                        @foreach($distinctProviderCodes ?? [] as $code => $label)
                            <option value="{{ $code }}" {{ request('provider_code') === (string) $code ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="remote_service_id" class="block text-xs font-medium text-gray-500 uppercase mb-1">{{ __('Service') }}</label>
                    <select name="remote_service_id" id="remote_service_id" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-36">
                        <option value="">{{ __('All') }}</option>
                        @foreach($distinctServiceIds ?? [] as $id => $label)
                            <option value="{{ $id }}" {{ request('remote_service_id') === (string) $id ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="user_login" class="block text-xs font-medium text-gray-500 uppercase mb-1">{{ __('User login') }}</label>
                    <input type="text" name="user_login" id="user_login" value="{{ request('user_login') }}"
                           placeholder="{{ __('Filter by login') }}"
                           class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-40">
                </div>
                <button type="submit" class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    {{ __('Apply') }}
                </button>
                <a href="{{ route('staff.provider-order-stats.index') }}" class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-600 bg-white hover:bg-gray-50">
                    {{ __('Reset') }}
                </a>
            </form>

            {{-- Sub-tabs: Completed | All without failed | Partial --}}
            @php
                $currentTab = request('tab', 'without_failed');
                if (!in_array($currentTab, ['completed', 'without_failed', 'partial'], true)) {
                    $currentTab = 'without_failed';
                }
            @endphp
            <div id="provider-order-stats-content" x-data="{ tab: '{{ $currentTab }}' }" class="mb-6" data-index-url="{{ route('staff.provider-order-stats.index') }}" data-export-url="{{ route('staff.provider-order-stats.export') }}">
                <div class="border-b border-gray-200 flex flex-wrap items-center justify-between gap-2">
                    <nav class="-mb-px flex gap-1" aria-label="Tabs">
                        <button type="button"
                                @click="tab = 'completed'; const u = new URL(location.href); u.searchParams.set('tab', 'completed'); history.replaceState(null, '', u)"
                                :class="tab === 'completed' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'"
                                class="px-4 py-2 text-sm font-medium border-b-2 rounded-t transition">
                            {{ __('Completed') }}
                        </button>
                        <button type="button"
                                @click="tab = 'without_failed'; const u = new URL(location.href); u.searchParams.set('tab', 'without_failed'); history.replaceState(null, '', u)"
                                :class="tab === 'without_failed' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'"
                                class="px-4 py-2 text-sm font-medium border-b-2 rounded-t transition">
                            {{ __('All without failed') }}
                        </button>
                        <button type="button"
                                @click="tab = 'partial'; const u = new URL(location.href); u.searchParams.set('tab', 'partial'); history.replaceState(null, '', u)"
                                :class="tab === 'partial' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'"
                                class="px-4 py-2 text-sm font-medium border-b-2 rounded-t transition">
                            {{ __('Partial') }}
                        </button>
                    </nav>
                    <a :href="(function(){
                        var base = document.getElementById('provider-order-stats-content')?.dataset?.exportUrl || '';
                        var p = { tab: tab };
                        var dateFrom = document.getElementById('date_from')?.value; if (dateFrom) p.date_from = dateFrom;
                        var dateTo = document.getElementById('date_to')?.value; if (dateTo) p.date_to = dateTo;
                        var pc = document.getElementById('provider_code')?.value; if (pc) p.provider_code = pc;
                        var rs = document.getElementById('remote_service_id')?.value; if (rs) p.remote_service_id = rs;
                        var ul = document.getElementById('user_login')?.value; if (ul) p.user_login = ul;
                        return base + '?' + new URLSearchParams(p).toString();
                    })()"
                       class="inline-flex items-center px-3 py-2 border border-green-600 text-sm font-medium rounded-md text-green-700 bg-white hover:bg-green-50">
                        {{ __('Export (Excel)') }}
                    </a>
                </div>

                {{-- Tab: Completed --}}
                <div x-show="tab === 'completed'" x-cloak class="pt-4">
                    <div class="mb-4 flex flex-wrap gap-x-6 gap-y-1 text-base font-medium text-gray-800">
                        <span>{{ __('Total revenue (completed)') }}: <span class="font-semibold">{{ number_format($totalCharge ?? 0, 2) }}</span></span>
                        <span>{{ __('Total quantity') }}: <span class="font-semibold">{{ number_format($totalQuantity ?? 0) }}</span></span>
                        <span>{{ __('Total remains') }}: <span class="font-semibold">{{ number_format($totalRemains ?? 0) }}</span></span>
                        <span>{{ __('Total difference') }}: <span class="font-semibold">{{ number_format($totalDifference ?? 0) }}</span></span>
                    </div>
            {{-- Charts --}}
{{--            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">--}}
{{--                <div class="bg-white shadow-sm rounded-lg p-4">--}}
{{--                    <h3 class="text-sm font-medium text-gray-700 mb-3">{{ __('Top users by revenue (completed)') }}</h3>--}}
{{--                    <canvas id="chartTopUsers" height="220"></canvas>--}}
{{--                </div>--}}
{{--                <div class="bg-white shadow-sm rounded-lg p-4">--}}
{{--                    <h3 class="text-sm font-medium text-gray-700 mb-3">{{ __('Top services by revenue (completed)') }}</h3>--}}
{{--                    <canvas id="chartTopServices" height="220"></canvas>--}}
{{--                </div>--}}
{{--            </div>--}}

            {{-- Table: By User --}}
            <div class="bg-white shadow-sm rounded-lg overflow-hidden mb-8">
                <h3 class="px-4 py-3 text-sm font-medium text-gray-800 border-b">{{ __('By user') }}</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('User (login / remote id)') }}</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Orders') }}</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Quantity') }}</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Remains') }}</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Difference') }}</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Total charge') }}</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Avg charge') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($byUser as $row)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 text-sm text-gray-900">{{ $row->user_login ?: $row->user_remote_id ?: '—' }}</td>
                                    <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format($row->orders_count) }}</td>
                                    <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format($row->total_quantity ?? 0) }}</td>
                                    <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format($row->total_remains ?? 0) }}</td>
                                    <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format($row->total_difference ?? 0) }}</td>
                                    <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format((float) $row->total_charge, 2) }}</td>
                                    <td class="px-4 py-2 text-sm text-right text-gray-500">{{ number_format((float) $row->avg_charge, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">{{ __('No data') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 border-t border-gray-200">
                    {{ $byUser->appends(['tab' => 'completed'])->links() }}
                </div>
            </div>

            {{-- Table: By Service --}}
            <div class="bg-white shadow-sm rounded-lg overflow-hidden mb-8">
                <h3 class="px-4 py-3 text-sm font-medium text-gray-800 border-b">{{ __('By service') }}</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Service') }}</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Orders') }}</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Quantity') }}</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Remains') }}</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Difference') }}</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Total charge') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($byService as $row)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 text-sm text-gray-900">
                                        {{ $row->remote_service_id ?? '—' }}
                                        @if(!empty($serviceNames[$row->remote_service_id ?? '']))
                                            <span class="text-gray-500">({{ $serviceNames[$row->remote_service_id] }})</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format($row->orders_count) }}</td>
                                    <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format($row->total_quantity ?? 0) }}</td>
                                    <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format($row->total_remains ?? 0) }}</td>
                                    <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format($row->total_difference ?? 0) }}</td>
                                    <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format((float) $row->total_charge, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">{{ __('No data') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 border-t border-gray-200">
                    {{ $byService->appends(['tab' => 'completed'])->links() }}
                </div>
            </div>

            {{-- By User / By Service breakdown (collapsed list) --}}
            @if($byUserByService->isNotEmpty())
                <div class="bg-white shadow-sm rounded-lg overflow-hidden mb-8">
                    <h3 class="px-4 py-3 text-sm font-medium text-gray-800 border-b">{{ __('User × Service breakdown') }}</h3>
                    <div class="overflow-x-auto max-h-96 overflow-y-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50 sticky top-0">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('User') }}</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Service') }}</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Orders') }}</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Quantity') }}</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Remains') }}</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Difference') }}</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Total charge') }}</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($byUserByService as $row)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-2 text-sm text-gray-900">{{ $row->user_login ?: $row->user_remote_id ?: '—' }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-900">{{ $row->remote_service_id ?? '—' }}</td>
                                        <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format($row->orders_count) }}</td>
                                        <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format($row->total_quantity ?? 0) }}</td>
                                        <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format($row->total_remains ?? 0) }}</td>
                                        <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format($row->total_difference ?? 0) }}</td>
                                        <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format((float) $row->total_charge, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
                </div>

                {{-- Tab: All without failed --}}
                <div x-show="tab === 'without_failed'" x-cloak class="pt-4">
                    <div class="mb-4 flex flex-wrap gap-x-6 gap-y-1 text-base font-medium text-gray-800">
                        <span>{{ __('Total revenue (all without failed)') }}: <span class="font-semibold">{{ number_format($totalChargeWithoutFailed ?? 0, 2) }}</span></span>
                        <span>{{ __('Total quantity') }}: <span class="font-semibold">{{ number_format($totalQuantityWithoutFailed ?? 0) }}</span></span>
                        <span>{{ __('Total remains') }}: <span class="font-semibold">{{ number_format($totalRemainsWithoutFailed ?? 0) }}</span></span>
                        <span>{{ __('Total difference') }}: <span class="font-semibold">{{ number_format($totalDifferenceWithoutFailed ?? 0) }}</span></span>
                    </div>

                    <div class="bg-white shadow-sm rounded-lg overflow-hidden mb-8">
                        <h3 class="px-4 py-3 text-sm font-medium text-gray-800 border-b">{{ __('By user') }}</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('User (login / remote id)') }}</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Orders') }}</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Quantity') }}</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Remains') }}</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Difference') }}</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Total charge') }}</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Avg charge') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @forelse($byUserWithoutFailed ?? [] as $row)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 text-sm text-gray-900">{{ $row->user_login ?: $row->user_remote_id ?: '—' }}</td>
                                            <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format($row->orders_count) }}</td>
                                            <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format($row->total_quantity ?? 0) }}</td>
                                            <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format($row->total_remains ?? 0) }}</td>
                                            <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format($row->total_difference ?? 0) }}</td>
                                            <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format((float) $row->total_charge, 2) }}</td>
                                            <td class="px-4 py-2 text-sm text-right text-gray-500">{{ number_format((float) $row->avg_charge, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">{{ __('No data') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="px-4 py-3 border-t border-gray-200">
                            {{ $byUserWithoutFailed->appends(['tab' => 'without_failed'])->links() }}
                        </div>
                    </div>

                    <div class="bg-white shadow-sm rounded-lg overflow-hidden mb-8">
                        <h3 class="px-4 py-3 text-sm font-medium text-gray-800 border-b">{{ __('By service') }}</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Service') }}</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Orders') }}</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Quantity') }}</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Remains') }}</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Difference') }}</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Total charge') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @forelse($byServiceWithoutFailed ?? [] as $row)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 text-sm text-gray-900">
                                                {{ $row->remote_service_id ?? '—' }}
                                                @if(!empty($serviceNames[$row->remote_service_id ?? '']))
                                                    <span class="text-gray-500">({{ $serviceNames[$row->remote_service_id] }})</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format($row->orders_count) }}</td>
                                            <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format($row->total_quantity ?? 0) }}</td>
                                            <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format($row->total_remains ?? 0) }}</td>
                                            <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format($row->total_difference ?? 0) }}</td>
                                            <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format((float) $row->total_charge, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">{{ __('No data') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="px-4 py-3 border-t border-gray-200">
                            {{ $byServiceWithoutFailed->appends(['tab' => 'without_failed'])->links() }}
                        </div>
                    </div>

                    {{-- User × Service breakdown (All without failed) --}}
                    @if(($byUserByServiceWithoutFailed ?? collect())->isNotEmpty())
                        <div class="bg-white shadow-sm rounded-lg overflow-hidden mb-8">
                            <h3 class="px-4 py-3 text-sm font-medium text-gray-800 border-b">{{ __('User × Service breakdown') }}</h3>
                            <div class="overflow-x-auto max-h-96 overflow-y-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50 sticky top-0">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('User') }}</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Service') }}</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Orders') }}</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Quantity') }}</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Remains') }}</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Difference') }}</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Total charge') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach($byUserByServiceWithoutFailed as $row)
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-2 text-sm text-gray-900">{{ $row->user_login ?: $row->user_remote_id ?: '—' }}</td>
                                                <td class="px-4 py-2 text-sm text-gray-900">{{ $row->remote_service_id ?? '—' }}</td>
                                                <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format($row->orders_count) }}</td>
                                                <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format($row->total_quantity ?? 0) }}</td>
                                                <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format($row->total_remains ?? 0) }}</td>
                                                <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format($row->total_difference ?? 0) }}</td>
                                                <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format((float) $row->total_charge, 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Tab: Partial --}}
                <div x-show="tab === 'partial'" x-cloak class="pt-4">
                    <div class="mb-4 flex flex-wrap gap-x-6 gap-y-1 text-base font-medium text-gray-800">
                        <span>{{ __('Total charge (partial)') }}: <span class="font-semibold">{{ number_format($totalChargePartial ?? 0, 2) }}</span></span>
                        <span>{{ __('Total quantity') }}: <span class="font-semibold">{{ number_format($totalQuantityPartial ?? 0) }}</span></span>
                        <span>{{ __('Total remains') }}: <span class="font-semibold">{{ number_format($totalRemainsPartial ?? 0) }}</span></span>
                        <span>{{ __('Total difference') }}: <span class="font-semibold">{{ number_format($totalDifferencePartial ?? 0) }}</span></span>
                    </div>

                    <div class="bg-white shadow-sm rounded-lg overflow-hidden mb-8">
                        <h3 class="px-4 py-3 text-sm font-medium text-gray-800 border-b">{{ __('By user') }}</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('User (login / remote id)') }}</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Orders') }}</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Quantity') }}</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Remains') }}</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Difference') }}</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Total charge') }}</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Avg charge') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @forelse($byUserPartial ?? [] as $row)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 text-sm text-gray-900">{{ $row->user_login ?: $row->user_remote_id ?: '—' }}</td>
                                            <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format($row->orders_count) }}</td>
                                            <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format($row->total_quantity ?? 0) }}</td>
                                            <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format($row->total_remains ?? 0) }}</td>
                                            <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format($row->total_difference ?? 0) }}</td>
                                            <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format((float) $row->total_charge, 2) }}</td>
                                            <td class="px-4 py-2 text-sm text-right text-gray-500">{{ number_format((float) $row->avg_charge, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">{{ __('No data') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="px-4 py-3 border-t border-gray-200">
                            {{ $byUserPartial->appends(['tab' => 'partial'])->links() }}
                        </div>
                    </div>

                    <div class="bg-white shadow-sm rounded-lg overflow-hidden mb-8">
                        <h3 class="px-4 py-3 text-sm font-medium text-gray-800 border-b">{{ __('By service') }}</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Service') }}</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Orders') }}</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Quantity') }}</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Remains') }}</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Difference') }}</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Total charge') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @forelse($byServicePartial ?? [] as $row)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 text-sm text-gray-900">
                                                {{ $row->remote_service_id ?? '—' }}
                                                @if(!empty($serviceNames[$row->remote_service_id ?? '']))
                                                    <span class="text-gray-500">({{ $serviceNames[$row->remote_service_id] }})</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format($row->orders_count) }}</td>
                                            <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format($row->total_quantity ?? 0) }}</td>
                                            <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format($row->total_remains ?? 0) }}</td>
                                            <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format($row->total_difference ?? 0) }}</td>
                                            <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format((float) $row->total_charge, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">{{ __('No data') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="px-4 py-3 border-t border-gray-200">
                            {{ $byServicePartial->appends(['tab' => 'partial'])->links() }}
                        </div>
                    </div>

                    {{-- User × Service breakdown (Partial) --}}
                    @if(($byUserByServicePartial ?? collect())->isNotEmpty())
                        <div class="bg-white shadow-sm rounded-lg overflow-hidden mb-8">
                            <h3 class="px-4 py-3 text-sm font-medium text-gray-800 border-b">{{ __('User × Service breakdown') }}</h3>
                            <div class="overflow-x-auto max-h-96 overflow-y-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50 sticky top-0">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('User') }}</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Service') }}</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Orders') }}</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Quantity') }}</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Remains') }}</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Difference') }}</th>
                                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Total charge') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach($byUserByServicePartial as $row)
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-2 text-sm text-gray-900">{{ $row->user_login ?: $row->user_remote_id ?: '—' }}</td>
                                                <td class="px-4 py-2 text-sm text-gray-900">{{ $row->remote_service_id ?? '—' }}</td>
                                                <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format($row->orders_count) }}</td>
                                                <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format($row->total_quantity ?? 0) }}</td>
                                                <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format($row->total_remains ?? 0) }}</td>
                                                <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format($row->total_difference ?? 0) }}</td>
                                                <td class="px-4 py-2 text-sm text-right text-gray-900">{{ number_format((float) $row->total_charge, 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

{{--    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>--}}
{{--    <script>--}}
{{--        (function() {--}}
{{--            const topUsers = @json($chartTopUsersData ?? []);--}}
{{--            const topServices = @json($chartTopServicesData ?? []);--}}
{{--            const barOptions = { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true } } };--}}

{{--            if (document.getElementById('chartTopUsers') && topUsers.length) {--}}
{{--                new Chart(document.getElementById('chartTopUsers'), {--}}
{{--                    type: 'bar',--}}
{{--                    data: {--}}
{{--                        labels: topUsers.map(u => u.label),--}}
{{--                        datasets: [{ label: '{{ __("Revenue") }}', data: topUsers.map(u => u.total_charge), backgroundColor: 'rgba(99, 102, 241, 0.6)' }]--}}
{{--                    },--}}
{{--                    options: barOptions--}}
{{--                });--}}
{{--            }--}}
{{--            if (document.getElementById('chartTopServices') && topServices.length) {--}}
{{--                new Chart(document.getElementById('chartTopServices'), {--}}
{{--                    type: 'bar',--}}
{{--                    data: {--}}
{{--                        labels: topServices.map(s => s.label),--}}
{{--                        datasets: [{ label: '{{ __("Revenue") }}', data: topServices.map(s => s.total_charge), backgroundColor: 'rgba(34, 197, 94, 0.6)' }]--}}
{{--                    },--}}
{{--                    options: barOptions--}}
{{--                });--}}
{{--            }--}}
{{--        })();--}}
{{--    </script>--}}

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.addEventListener('click', function(e) {
            var link = e.target.closest('a[href]');
            if (!link || link.target === '_blank') return;
            var container = link.closest('#provider-order-stats-content');
            if (!container) return;
            var href = link.getAttribute('href');
            if (!href || href.charAt(0) === '#') return;
            var fullUrl = link.href;
            var indexUrl = container.getAttribute('data-index-url');
            if (!indexUrl) return;
            var baseUrl = indexUrl.indexOf('http') === 0 ? indexUrl : (window.location.origin + (indexUrl.charAt(0) === '/' ? indexUrl : '/' + indexUrl));
            if (fullUrl.indexOf(baseUrl) !== 0 || fullUrl.indexOf('/export') !== -1) return;
            e.preventDefault();
            container.classList.add('opacity-60', 'pointer-events-none');
            fetch(fullUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } })
                .then(function(r) { return r.text(); })
                .then(function(html) {
                    var parser = new DOMParser();
                    var doc = parser.parseFromString(html, 'text/html');
                    var newContent = doc.getElementById('provider-order-stats-content');
                    if (!newContent) { location.href = fullUrl; return; }
                    var parent = container.parentNode;
                    var next = container.nextSibling;
                    container.remove();
                    parent.insertBefore(newContent, next);
                    if (window.Alpine) Alpine.initTree(newContent);
                    history.pushState({}, '', fullUrl);
                })
                .catch(function() { location.href = fullUrl; })
                .finally(function() {
                    var c = document.getElementById('provider-order-stats-content');
                    if (c) { c.classList.remove('opacity-60', 'pointer-events-none'); }
                });
        });
    });
    </script>
</x-app-layout>
