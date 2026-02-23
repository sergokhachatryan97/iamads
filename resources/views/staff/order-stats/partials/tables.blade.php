@php $dark = $dark ?? false; $byClientByService = $byClientByService ?? collect(); @endphp
<div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
    {{-- By client --}}
    <div class="{{ $dark ? 'bg-zinc-800' : 'bg-white shadow-sm' }} rounded-lg overflow-hidden">
        <h3 class="px-4 py-3 text-sm font-medium {{ $dark ? 'text-zinc-300 border-zinc-700' : 'text-gray-800 border-gray-200' }} border-b">{{ __('By client') }}</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full {{ $dark ? 'divide-zinc-700' : 'divide-gray-200' }} divide-y">
                <thead class="{{ $dark ? 'bg-zinc-700/50' : 'bg-gray-50' }}">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium {{ $dark ? 'text-zinc-400' : 'text-gray-500' }} uppercase">{{ __('Client') }}</th>
                        <th class="px-4 py-2 text-right text-xs font-medium {{ $dark ? 'text-zinc-400' : 'text-gray-500' }} uppercase">{{ __('Orders') }}</th>
                        <th class="px-4 py-2 text-right text-xs font-medium {{ $dark ? 'text-zinc-400' : 'text-gray-500' }} uppercase">{{ __('Quantity') }}</th>
                        <th class="px-4 py-2 text-right text-xs font-medium {{ $dark ? 'text-zinc-400' : 'text-gray-500' }} uppercase">{{ __('Remains') }}</th>
                        <th class="px-4 py-2 text-right text-xs font-medium {{ $dark ? 'text-zinc-400' : 'text-gray-500' }} uppercase">{{ __('Delivered') }}</th>
                        <th class="px-4 py-2 text-right text-xs font-medium {{ $dark ? 'text-zinc-400' : 'text-gray-500' }} uppercase">{{ __('Total charge') }}</th>
                    </tr>
                </thead>
                <tbody class="{{ $dark ? 'divide-zinc-700' : 'divide-gray-200' }} divide-y {{ $dark ? 'bg-zinc-800' : 'bg-white' }}">
                    @forelse($byClient as $row)
                        @php $client = $clients[$row->client_id] ?? null; @endphp
                        <tr class="{{ $dark ? 'hover:bg-zinc-700/30' : 'hover:bg-gray-50' }}">
                            <td class="px-4 py-2 text-sm {{ $dark ? 'text-zinc-300' : 'text-gray-900' }}">
                                {{ $client ? ($client->email ?? $client->name ?? 'client#' . $row->client_id) : ('client#' . $row->client_id) }}
                            </td>
                            <td class="px-4 py-2 text-sm text-right {{ $dark ? 'text-white' : 'text-gray-900' }}">{{ number_format($row->orders_count) }}</td>
                            <td class="px-4 py-2 text-sm text-right {{ $dark ? 'text-white' : 'text-gray-900' }}">{{ number_format($row->total_quantity ?? 0) }}</td>
                            <td class="px-4 py-2 text-sm text-right {{ $dark ? 'text-white' : 'text-gray-900' }}">{{ number_format($row->total_remains ?? 0) }}</td>
                            <td class="px-4 py-2 text-sm text-right {{ $dark ? 'text-white' : 'text-gray-900' }}">{{ number_format($row->total_delivered ?? 0) }}</td>
                            <td class="px-4 py-2 text-sm text-right {{ $dark ? 'text-white' : 'text-gray-900' }}">{{ number_format((float) $row->total_charge, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-6 text-center {{ $dark ? 'text-zinc-500' : 'text-gray-500' }}">{{ __('No data') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t {{ $dark ? 'border-zinc-700' : 'border-gray-200' }}">
            {{ $byClient->appends(['tab' => $tab])->links() }}
        </div>
    </div>

    {{-- By service --}}
    <div class="{{ $dark ? 'bg-zinc-800' : 'bg-white shadow-sm' }} rounded-lg overflow-hidden">
        <h3 class="px-4 py-3 text-sm font-medium {{ $dark ? 'text-zinc-300 border-zinc-700' : 'text-gray-800 border-gray-200' }} border-b">{{ __('By service') }}</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full {{ $dark ? 'divide-zinc-700' : 'divide-gray-200' }} divide-y">
                <thead class="{{ $dark ? 'bg-zinc-700/50' : 'bg-gray-50' }}">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium {{ $dark ? 'text-zinc-400' : 'text-gray-500' }} uppercase">{{ __('Service') }}</th>
                        <th class="px-4 py-2 text-right text-xs font-medium {{ $dark ? 'text-zinc-400' : 'text-gray-500' }} uppercase">{{ __('Orders') }}</th>
                        <th class="px-4 py-2 text-right text-xs font-medium {{ $dark ? 'text-zinc-400' : 'text-gray-500' }} uppercase">{{ __('Quantity') }}</th>
                        <th class="px-4 py-2 text-right text-xs font-medium {{ $dark ? 'text-zinc-400' : 'text-gray-500' }} uppercase">{{ __('Remains') }}</th>
                        <th class="px-4 py-2 text-right text-xs font-medium {{ $dark ? 'text-zinc-400' : 'text-gray-500' }} uppercase">{{ __('Delivered') }}</th>
                        <th class="px-4 py-2 text-right text-xs font-medium {{ $dark ? 'text-zinc-400' : 'text-gray-500' }} uppercase">{{ __('Total charge') }}</th>
                    </tr>
                </thead>
                <tbody class="{{ $dark ? 'divide-zinc-700' : 'divide-gray-200' }} divide-y {{ $dark ? 'bg-zinc-800' : 'bg-white' }}">
                    @forelse($byService as $row)
                        @php $service = $services[$row->service_id] ?? null; @endphp
                        <tr class="{{ $dark ? 'hover:bg-zinc-700/30' : 'hover:bg-gray-50' }}">
                            <td class="px-4 py-2 text-sm {{ $dark ? 'text-zinc-300' : 'text-gray-900' }}">
                                {{ $service ? $service->name : ('service#' . $row->service_id) }}
                            </td>
                            <td class="px-4 py-2 text-sm text-right {{ $dark ? 'text-white' : 'text-gray-900' }}">{{ number_format($row->orders_count) }}</td>
                            <td class="px-4 py-2 text-sm text-right {{ $dark ? 'text-white' : 'text-gray-900' }}">{{ number_format($row->total_quantity ?? 0) }}</td>
                            <td class="px-4 py-2 text-sm text-right {{ $dark ? 'text-white' : 'text-gray-900' }}">{{ number_format($row->total_remains ?? 0) }}</td>
                            <td class="px-4 py-2 text-sm text-right {{ $dark ? 'text-white' : 'text-gray-900' }}">{{ number_format($row->total_delivered ?? 0) }}</td>
                            <td class="px-4 py-2 text-sm text-right {{ $dark ? 'text-white' : 'text-gray-900' }}">{{ number_format((float) $row->total_charge, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-6 text-center {{ $dark ? 'text-zinc-500' : 'text-gray-500' }}">{{ __('No data') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t {{ $dark ? 'border-zinc-700' : 'border-gray-200' }}">
            {{ $byService->appends(['tab' => $tab])->links() }}
        </div>
    </div>
</div>

{{-- Client × Service breakdown --}}
@if($byClientByService->isNotEmpty())
<div class="{{ $dark ? 'bg-zinc-800' : 'bg-white shadow-sm' }} rounded-lg overflow-hidden mt-8">
    <h3 class="px-4 py-3 text-sm font-medium {{ $dark ? 'text-zinc-300 border-zinc-700' : 'text-gray-800 border-gray-200' }} border-b">{{ __('Client × Service breakdown') }}</h3>
    <div class="overflow-x-auto max-h-96 overflow-y-auto">
        <table class="min-w-full {{ $dark ? 'divide-zinc-700' : 'divide-gray-200' }} divide-y">
            <thead class="{{ $dark ? 'bg-zinc-700/50' : 'bg-gray-50' }} sticky top-0">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium {{ $dark ? 'text-zinc-400' : 'text-gray-500' }} uppercase">{{ __('Client') }}</th>
                    <th class="px-4 py-2 text-left text-xs font-medium {{ $dark ? 'text-zinc-400' : 'text-gray-500' }} uppercase">{{ __('Service') }}</th>
                    <th class="px-4 py-2 text-right text-xs font-medium {{ $dark ? 'text-zinc-400' : 'text-gray-500' }} uppercase">{{ __('Orders') }}</th>
                    <th class="px-4 py-2 text-right text-xs font-medium {{ $dark ? 'text-zinc-400' : 'text-gray-500' }} uppercase">{{ __('Quantity') }}</th>
                    <th class="px-4 py-2 text-right text-xs font-medium {{ $dark ? 'text-zinc-400' : 'text-gray-500' }} uppercase">{{ __('Remains') }}</th>
                    <th class="px-4 py-2 text-right text-xs font-medium {{ $dark ? 'text-zinc-400' : 'text-gray-500' }} uppercase">{{ __('Delivered') }}</th>
                    <th class="px-4 py-2 text-right text-xs font-medium {{ $dark ? 'text-zinc-400' : 'text-gray-500' }} uppercase">{{ __('Total charge') }}</th>
                </tr>
            </thead>
            <tbody class="{{ $dark ? 'divide-zinc-700 bg-zinc-800' : 'divide-gray-200 bg-white' }} divide-y">
                @foreach($byClientByService as $row)
                    @php
                        $client = $clients[$row->client_id] ?? null;
                        $service = $services[$row->service_id] ?? null;
                    @endphp
                    <tr class="{{ $dark ? 'hover:bg-zinc-700/30' : 'hover:bg-gray-50' }}">
                        <td class="px-4 py-2 text-sm {{ $dark ? 'text-zinc-300' : 'text-gray-900' }}">
                            {{ $client ? ($client->email ?? $client->name ?? 'client#'.$row->client_id) : 'client#'.$row->client_id }}
                        </td>
                        <td class="px-4 py-2 text-sm {{ $dark ? 'text-zinc-300' : 'text-gray-900' }}">
                            {{ $service ? $service->name : 'service#'.$row->service_id }}
                        </td>
                        <td class="px-4 py-2 text-sm text-right {{ $dark ? 'text-white' : 'text-gray-900' }}">{{ number_format($row->orders_count) }}</td>
                        <td class="px-4 py-2 text-sm text-right {{ $dark ? 'text-white' : 'text-gray-900' }}">{{ number_format($row->total_quantity ?? 0) }}</td>
                        <td class="px-4 py-2 text-sm text-right {{ $dark ? 'text-white' : 'text-gray-900' }}">{{ number_format($row->total_remains ?? 0) }}</td>
                        <td class="px-4 py-2 text-sm text-right {{ $dark ? 'text-white' : 'text-gray-900' }}">{{ number_format($row->total_delivered ?? 0) }}</td>
                        <td class="px-4 py-2 text-sm text-right {{ $dark ? 'text-white' : 'text-gray-900' }}">{{ number_format((float) $row->total_charge, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
