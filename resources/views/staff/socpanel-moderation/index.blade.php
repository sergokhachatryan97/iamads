<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Socpanel Moderation') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <p class="mb-4 text-sm text-gray-600">
                {{ __('Provider orders ingested from Socpanel. Super admin only.') }}
                @if(!$orders->isEmpty())
                    <span class="font-medium text-gray-800">{{ __('Total') }}: {{ number_format($orders->total()) }} {{ __('provider orders') }}</span>
                @endif
            </p>

            {{-- Filters & Search --}}
            <form method="get" action="{{ route('staff.socpanel-moderation.index') }}" class="mb-4 flex flex-wrap items-end gap-4">
                <div class="min-w-[200px]">
                    <label for="search" class="block text-xs font-medium text-gray-500 uppercase mb-1">{{ __('Search') }}</label>
                    <input type="text" name="search" id="search" value="{{ request('search') }}"
                           placeholder="{{ __('Link, order ID, service ID, user, error…') }}"
                           class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-full">
                </div>
                <div>
                    <label for="status" class="block text-xs font-medium text-gray-500 uppercase mb-1">{{ __('Status') }}</label>
                    <select name="status" id="status" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                        @foreach($statuses as $value => $label)
                            <option value="{{ $value }}" {{ request('status', 'all') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="remote_status" class="block text-xs font-medium text-gray-500 uppercase mb-1">{{ __('Remote status') }}</label>
                    <select name="remote_status" id="remote_status" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-36">
                        <option value="">{{ __('All') }}</option>
                        <option value="__null__" {{ request('remote_status') === '__null__' ? 'selected' : '' }}>{{ __('(empty)') }}</option>
                        @foreach($remoteStatuses ?? [] as $value => $label)
                            <option value="{{ $value }}" {{ request('remote_status') === (string) $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="provider_code" class="block text-xs font-medium text-gray-500 uppercase mb-1">{{ __('Provider') }}</label>
                    <input type="text" name="provider_code" id="provider_code" value="{{ request('provider_code') }}"
                           placeholder="e.g. adtag"
                           class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-32">
                </div>
                <div>
                    <label for="service_id" class="block text-xs font-medium text-gray-500 uppercase mb-1">{{ __('Service') }}</label>
                    <select name="service_id" id="service_id" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-36">
                        <option value="">{{ __('All services') }}</option>
                        @foreach($serviceIds ?? [] as $id => $label)
                            <option value="{{ $id }}" {{ request('service_id') === (string) $id ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="user_login" class="block text-xs font-medium text-gray-500 uppercase mb-1">{{ __('User login') }}</label>
                    <input type="text" name="user_login" id="user_login" value="{{ request('user_login') }}"
                           placeholder="{{ __('Login') }}"
                           class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-36">
                </div>
                <div>
                    <label for="user_remote_id" class="block text-xs font-medium text-gray-500 uppercase mb-1">{{ __('User remote ID') }}</label>
                    <input type="text" name="user_remote_id" id="user_remote_id" value="{{ request('user_remote_id') }}"
                           placeholder="{{ __('Remote user ID') }}"
                           class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm w-32">
                </div>
                <div>
                    <label for="date_from" class="block text-xs font-medium text-gray-500 uppercase mb-1">{{ __('Date from') }}</label>
                    <input type="date" name="date_from" id="date_from" value="{{ request('date_from') }}"
                           class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                </div>
                <div>
                    <label for="date_to" class="block text-xs font-medium text-gray-500 uppercase mb-1">{{ __('Date to') }}</label>
                    <input type="date" name="date_to" id="date_to" value="{{ request('date_to') }}"
                           class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                </div>
                <button type="submit" class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    {{ __('Filter') }}
                </button>
                <a href="{{ route('staff.socpanel-moderation.index') }}" class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-600 bg-white hover:bg-gray-50">
                    {{ __('Reset') }}
                </a>
            </form>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                @if($orders->isEmpty())
                    <div class="p-8 text-center text-gray-500">
                        {{ __('No provider orders found.') }}
                    </div>
                @else
                    <p class="px-4 py-2 text-sm text-gray-600">
                        {{ __('Showing') }} {{ $orders->firstItem() }}–{{ $orders->lastItem() }} {{ __('of') }} {{ number_format($orders->total()) }}
                    </p>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    @php
                                        $currentSort = $sort ?? 'id';
                                        $currentDir = $dir ?? 'desc';
                                        $sortLink = function ($col, $label, $defaultDir = 'asc') use ($currentSort, $currentDir) {
                                            $nextDir = ($currentSort === $col) ? ($currentDir === 'asc' ? 'desc' : 'asc') : $defaultDir;
                                            $url = request()->fullUrlWithQuery(['sort' => $col, 'dir' => $nextDir]);
                                            $arrow = $currentSort === $col ? ($currentDir === 'asc' ? ' ↑' : ' ↓') : '';
                                            return '<a href="' . e($url) . '" class="text-gray-600 hover:text-gray-900">' . e($label) . $arrow . '</a>';
                                        };
                                    @endphp
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{!! $sortLink('id', 'ID', 'desc') !!}</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{!! $sortLink('provider_code', __('Provider')) !!}</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{!! $sortLink('remote_order_id', __('Remote ID')) !!}</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{!! $sortLink('remote_service_id', __('Service ID')) !!}</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{!! $sortLink('status', __('Status')) !!}</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Remote status') }}</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Link') }}</th>
                                    <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{!! $sortLink('charge', __('Charge'), 'desc') !!}</th>
                                    <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{!! $sortLink('remains', __('Remains'), 'desc') !!}</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{!! $sortLink('fetched_at', __('Fetched'), 'desc') !!}</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{!! $sortLink('created_at', __('Created At'), 'desc') !!}</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{!! $sortLink('updated_at', __('Updated At'), 'desc') !!}</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Last error') }}</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($orders as $order)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">{{ $order->id }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">{{ $order->provider_code }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">{{ $order->remote_order_id }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">{{ $order->remote_service_id }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            <span class="px-2 py-0.5 text-xs font-medium rounded-full
                                                @if($order->status === 'validating') bg-yellow-100 text-yellow-800
                                                @elseif(in_array($order->status, ['ok', 'completed'])) bg-green-100 text-green-800
                                                @elseif(in_array($order->status, ['fail', 'failed'])) bg-red-100 text-red-800
                                                @else bg-gray-100 text-gray-800
                                                @endif">
                                                {{ $order->status }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">{{ $order->remote_status ?? '—' }}</td>
                                        <td class="px-4 py-2 text-sm max-w-xs truncate">
                                            @if($order->link)
                                                @php
                                                    $href = $order->link;
                                                    if (!preg_match('#^https?://#i', $href)) {
                                                        $href = 'https://' . $href;
                                                    }
                                                @endphp
                                                <a href="{{ $href }}" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:text-indigo-800 hover:underline truncate block" title="{{ $order->link }}">{{ $order->link }}</a>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-right text-gray-900">{{ $order->charge !== null ? number_format($order->charge, 2) : '—' }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-right text-gray-900">{{ $order->remains ?? '—' }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">{{ $order->fetched_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">{{ $order->created_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">{{ $order->updated_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-500 max-w-xs truncate" title="{{ $order->provider_last_error }}">{{ $order->provider_last_error ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="px-4 py-3 border-t border-gray-200">
                        {{ $orders->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
