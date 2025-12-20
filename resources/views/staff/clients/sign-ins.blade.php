<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Sign-in History') }}: {{ $client->name }}
            </h2>
            <div class="flex items-center gap-2">
                <a href="{{ route('staff.clients.sign-ins.matching-ips', $client) }}" class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-md hover:bg-indigo-100 hover:text-indigo-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors">
                    <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    {{ __('Find Matching IPs') }}
                </a>
                <a href="{{ route('staff.clients.edit', $client) }}" class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-white bg-gray-700 border border-gray-300 rounded-md hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 uppercase transition-colors shadow-sm">
                    <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    {{ __('Back to Edit') }}
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
                    <!-- Filters -->
                    <form method="GET" action="{{ route('staff.clients.sign-ins', $client) }}" class="mb-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
                            <div>
                                <label for="ip" class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ __('IP Address') }}
                                </label>
                                <input
                                    type="text"
                                    id="ip"
                                    name="ip"
                                    value="{{ $filters['ip'] ?? '' }}"
                                    placeholder="{{ __('Filter by IP') }}"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                />
                            </div>

                            <div>
                                <label for="device_type" class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ __('Device Type') }}
                                </label>
                                <select
                                    id="device_type"
                                    name="device_type"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                >
                                    <option value="">{{ __('All') }}</option>
                                    @foreach($deviceTypes as $type)
                                        <option value="{{ $type }}" {{ ($filters['device_type'] ?? '') === $type ? 'selected' : '' }}>
                                            {{ ucfirst($type) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="os" class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ __('OS') }}
                                </label>
                                <select
                                    id="os"
                                    name="os"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                >
                                    <option value="">{{ __('All') }}</option>
                                    @foreach($oses as $os)
                                        <option value="{{ $os }}" {{ ($filters['os'] ?? '') === $os ? 'selected' : '' }}>
                                            {{ $os }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="browser" class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ __('Browser') }}
                                </label>
                                <select
                                    id="browser"
                                    name="browser"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                >
                                    <option value="">{{ __('All') }}</option>
                                    @foreach($browsers as $browser)
                                        <option value="{{ $browser }}" {{ ($filters['browser'] ?? '') === $browser ? 'selected' : '' }}>
                                            {{ $browser }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="country" class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ __('Country') }}
                                </label>
                                <select
                                    id="country"
                                    name="country"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                >
                                    <option value="">{{ __('All') }}</option>
                                    @foreach($countries as $country)
                                        <option value="{{ $country }}" {{ ($filters['country'] ?? '') === $country ? 'selected' : '' }}>
                                            {{ $country }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="city" class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ __('City') }}
                                </label>
                                <input
                                    type="text"
                                    id="city"
                                    name="city"
                                    value="{{ $filters['city'] ?? '' }}"
                                    placeholder="{{ __('Filter by city') }}"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                />
                            </div>
                        </div>

                        <div class="mt-4 flex gap-3">
                            <button
                                type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            >
                                {{ __('Apply Filters') }}
                            </button>
                            <a
                                href="{{ route('staff.clients.sign-ins', $client) }}"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            >
                                {{ __('Clear') }}
                            </a>
                        </div>
                    </form>

                    <!-- Note about IP matching -->
                    <div class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-md">
                        <p class="text-sm text-yellow-800">
                            <strong>{{ __('Note:') }}</strong> {{ __('Matching IP addresses do not necessarily mean the same person. Users on public networks, VPNs, or shared connections may share the same IP address.') }}
                        </p>
                    </div>

                    <!-- Logs Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ __('Date/Time') }}
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ __('IP Address') }}
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ __('Location') }}
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ __('Device Summary') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($logs as $log)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $log->signed_in_at->format('M d, Y H:i:s') }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-mono">
                                            {{ $log->ip }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            @if($log->city || $log->country)
                                                {{ $log->city }}{{ $log->city && $log->country ? ', ' : '' }}{{ $log->country }}
                                            @else
                                                <span class="text-gray-400">{{ __('N/A') }}</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500">
                                            <div>
                                                <span class="font-medium">{{ ucfirst($log->device_type ?? 'unknown') }}</span>
                                                @if($log->os || $log->browser)
                                                    <span class="text-gray-400"> • </span>
                                                    <span>{{ $log->os }}</span>
                                                    @if($log->browser)
                                                        <span class="text-gray-400"> / </span>
                                                        <span>{{ $log->browser }}</span>
                                                    @endif
                                                @endif
                                                @if($log->device_name)
                                                    <div class="text-xs text-gray-400 mt-1">{{ $log->device_name }}</div>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">
                                            {{ __('No sign-in logs found.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    @if($logs->hasPages())
                        <div class="mt-6">
                            {{ $logs->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
