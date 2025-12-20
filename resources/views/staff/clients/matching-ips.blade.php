<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Matching IPs') }}: {{ $client->name }}
            </h2>
            <div class="flex items-center gap-2">
                <a href="{{ route('staff.clients.sign-ins', $client) }}" class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-white bg-gray-700 border border-gray-300 rounded-md hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 uppercase transition-colors shadow-sm">
                    <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    {{ __('Back to Sign-in History') }}
                </a>
                <a href="{{ route('staff.clients.index') }}" class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-white bg-gray-700 border border-gray-300 rounded-md hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 uppercase transition-colors shadow-sm">
                    <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    {{ __('Back to Clients') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-[90rem] mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <!-- Info Box -->
                    <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-md">
                        <p class="text-sm text-blue-800">
                            <strong>{{ __('Client IPs:') }}</strong>
                            @if(!empty($clientIps))
                                {{ implode(', ', $clientIps) }}
                            @else
                                {{ __('No IPs found for this client.') }}
                            @endif
                        </p>
                        <p class="text-sm text-blue-700 mt-2">
                            {{ __('Below are other clients who have logged in from any of these IP addresses.') }}
                        </p>
                    </div>

                    <!-- Warning -->
                    <div class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-md">
                        <p class="text-sm text-yellow-800">
                            <strong>{{ __('Warning:') }}</strong> {{ __('Matching IP addresses do not necessarily mean the same person. Users on public networks, VPNs, or shared connections may share the same IP address. Use this information as a reference only.') }}
                        </p>
                    </div>

                    <!-- Matches Table -->
                    @if($matches->isEmpty())
                        <div class="text-center py-8">
                            <p class="text-sm text-gray-500">{{ __('No other clients found with matching IP addresses.') }}</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            {{ __('Client') }}
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            {{ __('Matched IPs') }}
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            {{ __('Last Seen') }}
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            {{ __('Actions') }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($matches as $match)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">{{ $match['client']->name }}</div>
                                                <div class="text-sm text-gray-500">{{ $match['client']->email }}</div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-gray-900 font-mono">
                                                    @foreach($match['matched_ips'] as $ip)
                                                        <div>{{ $ip }}</div>
                                                    @endforeach
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $match['last_seen']->format('M d, Y H:i:s') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <a href="{{ route('staff.clients.edit', $match['client']) }}" class="text-indigo-600 hover:text-indigo-900">
                                                    {{ __('View Client') }}
                                                </a>
                                                <span class="mx-2 text-gray-300">|</span>
                                                <a href="{{ route('staff.clients.sign-ins', $match['client']) }}" class="text-indigo-600 hover:text-indigo-900">
                                                    {{ __('View Logs') }}
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
