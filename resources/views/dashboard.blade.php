@auth('client')
    <x-client-layout>
        <x-slot name="header">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Client Dashboard') }}
            </h2>
        </x-slot>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        {{ __("You're logged in as a client!") }}
                    </div>
                </div>
            </div>
        </div>
    </x-client-layout>
@else
    <x-app-layout>
        <x-slot name="header">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Staff Dashboard') }}
            </h2>
        </x-slot>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        {{ __("You're logged in as staff!") }}
                    </div>
                </div>

                @if(isset($partialOrdersStats) && is_array($partialOrdersStats))
                <div class="mt-6 bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-sm font-medium text-gray-800">{{ __('Partial orders (hourly job)') }}</h3>
                    </div>
                    <div class="p-6 grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                        <div>
                            <span class="text-gray-500">{{ __('Orders count') }}:</span>
                            <span class="font-semibold text-gray-900 ml-2">{{ number_format($partialOrdersStats['count'] ?? 0) }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">{{ __('Total charge') }}:</span>
                            <span class="font-semibold text-gray-900 ml-2">{{ number_format((float) ($partialOrdersStats['total_charge'] ?? 0), 2) }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">{{ __('Total remains') }}:</span>
                            <span class="font-semibold text-gray-900 ml-2">{{ number_format($partialOrdersStats['total_remains'] ?? 0) }}</span>
                        </div>
                        <div class="sm:col-span-3 text-gray-500 text-xs mt-2">
                            {{ __('Last run') }}: {{ $partialOrdersStats['last_run_at'] ?? '—' }}
                        </div>
                    </div>
                </div>
                @else
                <div class="mt-6 bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-sm text-gray-500">
                        {{ __('Partial orders stats will appear here after the hourly job runs.') }}
                    </div>
                </div>
                @endif
            </div>
        </div>
    </x-app-layout>
@endauth
