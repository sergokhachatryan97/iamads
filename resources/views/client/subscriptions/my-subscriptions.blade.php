<x-client-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('My Subscriptions') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(empty($subscriptions))
                <div class="bg-white rounded-lg shadow-sm p-12 text-center">
                    <p class="text-gray-500 text-lg">{{ __('You have no active subscriptions.') }}</p>
                    <a href="{{ route('client.subscriptions.index') }}" class="mt-4 inline-block text-indigo-600 hover:text-indigo-900">
                        {{ __('Browse Subscription Plans') }}
                    </a>
                </div>
            @else
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ __('Plan') }}
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ __('Link') }}
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ __('Expires') }}
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ __('Progress') }}
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ __('Actions') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($subscriptions as $subscription)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-semibold text-gray-900">{{ $subscription['plan_name'] }}</div>
                                            @if($subscription['auto_renew'])
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 mt-1">
                                                    {{ __('Auto-renew') }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-900 font-mono">{{ Str::limit($subscription['link'], 50) }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">{{ $subscription['expires_at']->format('M d, Y') }}</div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center">
                                                <div class="w-24 bg-gray-200 rounded-full h-2 mr-2">
                                                    <div 
                                                        class="bg-indigo-600 h-2 rounded-full transition-all duration-300"
                                                        style="width: {{ min(100, max(0, $subscription['overall_percentage'])) }}%"
                                                    ></div>
                                                </div>
                                                <span class="text-sm font-medium text-gray-900">
                                                    {{ number_format($subscription['overall_percentage'], 1) }}%
                                                </span>
                                            </div>
                                            <div class="text-xs text-gray-500 mt-1">
                                                {{ number_format($subscription['total_used']) }} / {{ number_format($subscription['total_initial']) }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <button 
                                                type="button"
                                                onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'subscription-details-{{ $subscription['subscription_id'] }}-{{ md5($subscription['link']) }}' }))"
                                                class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                            >
                                                {{ __('View Details') }}
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @foreach($subscriptions as $subscription)
        <x-modal name="subscription-details-{{ $subscription['subscription_id'] }}-{{ md5($subscription['link']) }}" maxWidth="3xl">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">
                        {{ $subscription['plan_name'] }} - {{ __('Subscription Details') }}
                    </h3>
                    <button
                        type="button"
                        onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'subscription-details-{{ $subscription['subscription_id'] }}-{{ md5($subscription['link']) }}' }))"
                        class="text-gray-400 hover:text-gray-500"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div class="space-y-4">
                    <!-- Subscription Info -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <span class="text-gray-600">{{ __('Link') }}:</span>
                                <span class="font-mono text-gray-900 ml-2">{{ $subscription['link'] }}</span>
                            </div>
                            <div>
                                <span class="text-gray-600">{{ __('Expires') }}:</span>
                                <span class="font-medium text-gray-900 ml-2">{{ $subscription['expires_at']->format('M d, Y') }}</span>
                            </div>
                            @if($subscription['auto_renew'])
                                <div class="col-span-2">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        {{ __('Auto-renew enabled') }}
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Overall Progress -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-gray-700">{{ __('Overall Progress') }}</span>
                            <span class="text-sm font-semibold text-gray-900">
                                {{ number_format($subscription['overall_percentage'], 1) }}%
                            </span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-3">
                            <div 
                                class="bg-indigo-600 h-3 rounded-full transition-all duration-300"
                                style="width: {{ min(100, max(0, $subscription['overall_percentage'])) }}%"
                            ></div>
                        </div>
                        <div class="flex items-center justify-between mt-2 text-xs text-gray-600">
                            <span>
                                {{ __('Used') }}: {{ number_format($subscription['total_used']) }} / {{ number_format($subscription['total_initial']) }}
                            </span>
                            <span>
                                {{ __('Remaining') }}: {{ number_format($subscription['total_remaining']) }}
                            </span>
                        </div>
                    </div>

                    <!-- Services Table -->
                    <div>
                        <h4 class="text-sm font-medium text-gray-700 mb-3">{{ __('Services Breakdown') }}</h4>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            {{ __('Service') }}
                                        </th>
                                        <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            {{ __('Initial Quantity') }}
                                        </th>
                                        <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            {{ __('Used') }}
                                        </th>
                                        <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            {{ __('Remaining') }}
                                        </th>
                                        <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            {{ __('Progress') }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($subscription['services'] as $service)
                                        <tr>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">{{ $service['service_name'] }}</div>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">{{ number_format($service['initial_quantity']) }}</div>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">{{ number_format($service['used_quantity']) }}</div>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">{{ number_format($service['remaining_quantity']) }}</div>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="w-24 bg-gray-200 rounded-full h-2 mr-2">
                                                        <div 
                                                            class="bg-indigo-600 h-2 rounded-full transition-all duration-300"
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
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    >
                        {{ __('Close') }}
                    </button>
                </div>
            </div>
        </x-modal>
    @endforeach
</x-client-layout>

