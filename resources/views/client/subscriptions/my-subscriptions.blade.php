<x-client-layout :title="__('My Subscriptions')">
    <style>
        .smm-my-subscriptions-page .smm-ms-empty {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius, 12px);
            padding: 3rem 1.5rem;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
        }
        [data-theme="light"] .smm-my-subscriptions-page .smm-ms-empty {
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
        }
        .smm-my-subscriptions-page .smm-ms-empty p {
            color: var(--text3);
            font-size: 1.125rem;
            margin: 0;
        }
        .smm-my-subscriptions-page .smm-ms-empty a {
            display: inline-block;
            margin-top: 1rem;
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--purple-light);
            text-decoration: none;
        }
        .smm-my-subscriptions-page .smm-ms-empty a:hover {
            text-decoration: underline;
        }
        .smm-my-subscriptions-page .smm-ms-table-wrap {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius, 12px);
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
        }
        [data-theme="light"] .smm-my-subscriptions-page .smm-ms-table-wrap {
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
        }
        .smm-my-subscriptions-page .smm-ms-table-wrap .divide-y.divide-gray-200 > :not([hidden]) ~ :not([hidden]) {
            border-color: var(--border) !important;
        }
        .smm-my-subscriptions-page .smm-ms-table-wrap thead.bg-gray-50 {
            background: rgba(0, 0, 0, 0.32) !important;
        }
        [data-theme="light"] .smm-my-subscriptions-page .smm-ms-table-wrap thead.bg-gray-50 {
            background: var(--card2) !important;
        }
        .smm-my-subscriptions-page .smm-ms-table-wrap thead th {
            color: var(--text3) !important;
        }
        .smm-my-subscriptions-page .smm-ms-table-wrap tbody {
            background: var(--card) !important;
        }
        .smm-my-subscriptions-page .smm-ms-table-wrap tbody.bg-white {
            background: var(--card) !important;
        }
        .smm-my-subscriptions-page .smm-ms-table-wrap .text-gray-900 {
            color: var(--text) !important;
        }
        .smm-my-subscriptions-page .smm-ms-table-wrap .text-gray-500 {
            color: var(--text3) !important;
        }
        .smm-my-subscriptions-page .smm-ms-table-wrap .bg-green-100 {
            background: rgba(0, 184, 148, 0.2) !important;
            color: #55efc4 !important;
        }
        [data-theme="light"] .smm-my-subscriptions-page .smm-ms-table-wrap .bg-green-100 {
            background: rgba(0, 184, 148, 0.15) !important;
            color: #00a085 !important;
        }
        .smm-my-subscriptions-page .smm-ms-table-wrap .text-green-800 {
            color: inherit !important;
        }
        .smm-my-subscriptions-page .smm-ms-bar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 9999px;
            height: 0.5rem;
            overflow: hidden;
        }
        [data-theme="light"] .smm-my-subscriptions-page .smm-ms-bar-track {
            background: rgba(0, 0, 0, 0.08);
        }
        .smm-my-subscriptions-page .smm-ms-bar-fill {
            height: 100%;
            border-radius: 9999px;
            background: linear-gradient(90deg, var(--purple), var(--teal));
            transition: width 0.3s ease;
        }
        .smm-my-subscriptions-page .smm-ms-btn {
            display: inline-flex;
            align-items: center;
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 9999px;
            border: 1px solid var(--purple);
            background: rgba(108, 92, 231, 0.15);
            color: var(--purple-light) !important;
            cursor: pointer;
            transition: background 0.15s, filter 0.15s;
        }
        .smm-my-subscriptions-page .smm-ms-btn:hover {
            background: rgba(108, 92, 231, 0.25);
            filter: brightness(1.05);
        }

        .smm-client-modals-root .client-smm-modal-backdrop {
            background: rgba(0, 0, 0, 0.72) !important;
        }
        [data-theme="light"] .smm-client-modals-root .client-smm-modal-backdrop {
            background: rgba(15, 23, 42, 0.45) !important;
        }
        .smm-client-modals-root .client-smm-modal-panel {
            background: var(--card) !important;
            color: var(--text);
        }
        .smm-client-modals-root .client-smm-modal-panel .text-gray-900,
        .smm-client-modals-root .client-smm-modal-panel .text-gray-700,
        .smm-client-modals-root .client-smm-modal-panel .text-gray-600,
        .smm-client-modals-root .client-smm-modal-panel .text-gray-500 {
            color: var(--text) !important;
        }
        .smm-client-modals-root .client-smm-modal-panel .border-gray-200,
        .smm-client-modals-root .client-smm-modal-panel .border-gray-300 {
            border-color: var(--border) !important;
        }
        .smm-client-modals-root .client-smm-modal-panel .bg-gray-50 {
            background: rgba(0, 0, 0, 0.22) !important;
        }
        [data-theme="light"] .smm-client-modals-root .client-smm-modal-panel .bg-gray-50 {
            background: var(--card2) !important;
        }
        .smm-client-modals-root .client-smm-modal-panel .bg-white {
            background: rgba(0, 0, 0, 0.15) !important;
        }
        [data-theme="light"] .smm-client-modals-root .client-smm-modal-panel .bg-white {
            background: #fff !important;
        }
        .smm-client-modals-root .client-smm-modal-panel .text-indigo-600 {
            color: var(--purple-light) !important;
        }
        .smm-client-modals-root .client-smm-modal-panel .bg-indigo-600 {
            background: var(--purple) !important;
        }
        .smm-client-modals-root .client-smm-modal-panel .text-gray-400 {
            color: var(--text3) !important;
        }
        .smm-client-modals-root .client-smm-modal-panel thead th.text-gray-500 {
            color: var(--text3) !important;
        }
        .smm-client-modals-root .client-smm-modal-panel .hover\:text-gray-500:hover {
            color: var(--text2) !important;
        }
        .smm-client-modals-root .client-smm-modal-panel .bg-green-100 {
            background: rgba(0, 184, 148, 0.2) !important;
        }
        .smm-client-modals-root .client-smm-modal-panel .text-green-800 {
            color: #55efc4 !important;
        }
        .smm-client-modals-root .client-smm-modal-panel .smm-ms-bar-track-modal {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 9999px;
            height: 0.75rem;
            overflow: hidden;
        }
        [data-theme="light"] .smm-client-modals-root .client-smm-modal-panel .smm-ms-bar-track-modal {
            background: rgba(0, 0, 0, 0.08);
        }
        .smm-client-modals-root .client-smm-modal-panel .smm-ms-bar-fill-modal {
            height: 100%;
            border-radius: 9999px;
            background: linear-gradient(90deg, var(--purple), var(--teal));
        }
    </style>

    <div class="smm-my-subscriptions-page px-4 pb-12 pt-2 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-7xl">
            @if(empty($subscriptions))
                <div class="smm-ms-empty">
                    <p>{{ __('You have no active subscriptions.') }}</p>
                    <a href="{{ route('client.subscriptions.index') }}">{{ __('Browse Subscription Plans') }}</a>
                </div>
            @else
                <div class="smm-ms-table-wrap overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                    {{ __('Plan') }}
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                    {{ __('Link') }}
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                    {{ __('Expires') }}
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                    {{ __('Progress') }}
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">
                                    {{ __('Actions') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach($subscriptions as $subscription)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-semibold text-gray-900">{{ $subscription['plan_name'] }}</div>
                                        @if($subscription['auto_renew'])
                                            <span class="mt-1 inline-flex items-center rounded px-2 py-0.5 text-xs font-medium bg-green-100 text-green-800">
                                                {{ __('Auto-renew') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-mono text-sm text-gray-900">{{ Str::limit($subscription['link'], 50) }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">{{ $subscription['expires_at']->format('M d, Y') }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            <div class="smm-ms-bar-track w-24 shrink-0">
                                                <div
                                                    class="smm-ms-bar-fill"
                                                    style="width: {{ min(100, max(0, $subscription['overall_percentage'])) }}%"
                                                ></div>
                                            </div>
                                            <span class="text-sm font-medium text-gray-900">
                                                {{ number_format($subscription['overall_percentage'], 1) }}%
                                            </span>
                                        </div>
                                        <div class="mt-1 text-xs text-gray-500">
                                            {{ number_format($subscription['total_used']) }} / {{ number_format($subscription['total_initial']) }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button
                                            type="button"
                                            onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'subscription-details-{{ $subscription['subscription_id'] }}-{{ md5($subscription['link']) }}' }))"
                                            class="smm-ms-btn focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 focus:ring-offset-[var(--card)]"
                                        >
                                            {{ __('View Details') }}
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="smm-client-modals-root">
        @foreach($subscriptions as $subscription)
            <x-modal name="subscription-details-{{ $subscription['subscription_id'] }}-{{ md5($subscription['link']) }}" maxWidth="3xl" theme="smm">
                <div class="p-6">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900">
                            {{ $subscription['plan_name'] }} — {{ __('Subscription Details') }}
                        </h3>
                        <button
                            type="button"
                            onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'subscription-details-{{ $subscription['subscription_id'] }}-{{ md5($subscription['link']) }}' }))"
                            class="text-gray-400 hover:text-gray-500 focus:outline-none"
                        >
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <div class="space-y-4">
                        <div class="rounded-lg bg-gray-50 p-4">
                            <div class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <span class="text-gray-600">{{ __('Link') }}:</span>
                                    <span class="ml-2 font-mono text-gray-900 break-all">{{ $subscription['link'] }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-600">{{ __('Expires') }}:</span>
                                    <span class="ml-2 font-medium text-gray-900">{{ $subscription['expires_at']->format('M d, Y') }}</span>
                                </div>
                                @if($subscription['auto_renew'])
                                    <div class="sm:col-span-2">
                                        <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-800">
                                            {{ __('Auto-renew enabled') }}
                                        </span>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div>
                            <div class="mb-2 flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-700">{{ __('Overall Progress') }}</span>
                                <span class="text-sm font-semibold text-gray-900">
                                    {{ number_format($subscription['overall_percentage'], 1) }}%
                                </span>
                            </div>
                            <div class="smm-ms-bar-track-modal w-full">
                                <div
                                    class="smm-ms-bar-fill-modal"
                                    style="width: {{ min(100, max(0, $subscription['overall_percentage'])) }}%"
                                ></div>
                            </div>
                            <div class="mt-2 flex items-center justify-between text-xs text-gray-600">
                                <span>
                                    {{ __('Used') }}: {{ number_format($subscription['total_used']) }} / {{ number_format($subscription['total_initial']) }}
                                </span>
                                <span>
                                    {{ __('Remaining') }}: {{ number_format($subscription['total_remaining']) }}
                                </span>
                            </div>
                        </div>

                        <div>
                            <h4 class="mb-3 text-sm font-medium text-gray-700">{{ __('Services Breakdown') }}</h4>
                            <div class="overflow-x-auto rounded-lg border border-gray-200">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                                {{ __('Service') }}
                                            </th>
                                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                                {{ __('Initial Quantity') }}
                                            </th>
                                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                                {{ __('Used') }}
                                            </th>
                                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                                {{ __('Remaining') }}
                                            </th>
                                            <th scope="col" class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                                {{ __('Progress') }}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 bg-white">
                                        @foreach($subscription['services'] as $service)
                                            <tr>
                                                <td class="whitespace-nowrap px-4 py-3">
                                                    <div class="text-sm font-medium text-gray-900">{{ $service['service_name'] }}</div>
                                                </td>
                                                <td class="whitespace-nowrap px-4 py-3">
                                                    <div class="text-sm text-gray-900">{{ number_format($service['initial_quantity']) }}</div>
                                                </td>
                                                <td class="whitespace-nowrap px-4 py-3">
                                                    <div class="text-sm text-gray-900">{{ number_format($service['used_quantity']) }}</div>
                                                </td>
                                                <td class="whitespace-nowrap px-4 py-3">
                                                    <div class="text-sm text-gray-900">{{ number_format($service['remaining_quantity']) }}</div>
                                                </td>
                                                <td class="whitespace-nowrap px-4 py-3">
                                                    <div class="flex items-center gap-2">
                                                        <div class="smm-ms-bar-track w-24 shrink-0">
                                                            <div
                                                                class="smm-ms-bar-fill"
                                                                style="width: {{ min(100, max(0, $service['percentage'])) }}%"
                                                            ></div>
                                                        </div>
                                                        <span class="text-sm font-medium text-gray-900">
                                                            {{ number_format($service['percentage'], 1) }}%
                                                        </span>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button
                            type="button"
                            onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'subscription-details-{{ $subscription['subscription_id'] }}-{{ md5($subscription['link']) }}' }))"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        >
                            {{ __('Close') }}
                        </button>
                    </div>
                </div>
            </x-modal>
        @endforeach
    </div>
</x-client-layout>
