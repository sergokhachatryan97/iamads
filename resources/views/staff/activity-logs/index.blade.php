<x-app-layout>
    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="mb-6 flex items-center justify-between">
                <h2 class="text-2xl font-bold text-gray-900">{{ __('Manager Activity Log') }}</h2>
            </div>

            <!-- Filters -->
            <div class="bg-white shadow-sm sm:rounded-lg mb-6">
                <form method="GET" action="{{ route('staff.activity-logs.index') }}" class="p-4 flex flex-wrap items-end gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Manager') }}</label>
                        <select name="user_id" class="rounded-md border-gray-300 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">{{ __('All') }}</option>
                            @foreach($managers as $manager)
                                <option value="{{ $manager->id }}" {{ ($filters['user_id'] ?? '') == $manager->id ? 'selected' : '' }}>
                                    {{ $manager->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Action') }}</label>
                        <select name="action" class="rounded-md border-gray-300 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">{{ __('All') }}</option>
                            @foreach($actions as $action)
                                <option value="{{ $action }}" {{ ($filters['action'] ?? '') === $action ? 'selected' : '' }}>
                                    {{ ucfirst(str_replace('_', ' ', $action)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Search') }}</label>
                        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('Description...') }}"
                               class="rounded-md border-gray-300 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500 w-48">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('From') }}</label>
                        <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"
                               class="rounded-md border-gray-300 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('To') }}</label>
                        <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"
                               class="rounded-md border-gray-300 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
                            {{ __('Filter') }}
                        </button>
                        @if(collect($filters)->filter(fn($v) => $v !== null && $v !== '')->isNotEmpty())
                            <a href="{{ route('staff.activity-logs.index') }}" class="px-4 py-2 text-gray-600 hover:text-gray-900 text-sm">
                                {{ __('Reset') }}
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            <!-- Table -->
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Time') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Manager') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Action') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Description') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('IP') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Details') }}</th>
                            </tr>
                        </thead>
                            @forelse($logs as $log)
                                <tbody x-data="{ open: false }" class="divide-y divide-gray-200">
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-500">
                                        {{ $log->created_at->format('M d, Y H:i:s') }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                        {{ $log->user->name ?? __('Deleted') }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        @php
                                            $actionColors = [
                                                'create' => 'bg-green-100 text-green-800',
                                                'update' => 'bg-blue-100 text-blue-800',
                                                'delete' => 'bg-red-100 text-red-800',
                                                'login' => 'bg-purple-100 text-purple-800',
                                                'toggle' => 'bg-yellow-100 text-yellow-800',
                                                'cancel' => 'bg-orange-100 text-orange-800',
                                                'balance' => 'bg-emerald-100 text-emerald-800',
                                            ];
                                            $color = $actionColors[$log->action] ?? 'bg-gray-100 text-gray-800';
                                        @endphp
                                        <span class="px-2 py-0.5 text-xs font-medium rounded-full {{ $color }}">
                                            {{ ucfirst(str_replace('_', ' ', $log->action)) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 max-w-md truncate">
                                        {{ $log->description }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-400 font-mono">
                                        {{ $log->ip_address }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-500">
                                        @if($log->properties)
                                            <button type="button"
                                                    @click="open = !open"
                                                    class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">
                                                <span x-text="open ? '{{ __('Hide') }}' : '{{ __('View') }}'"></span>
                                            </button>
                                        @else
                                            <span class="text-gray-300">—</span>
                                        @endif
                                    </td>
                                </tr>
                                @if($log->properties)
                                    <tr x-show="open" x-cloak class="bg-gray-50">
                                        <td colspan="6" class="px-4 py-3">
                                            <pre class="text-xs text-gray-600 bg-gray-100 rounded-md p-3 overflow-x-auto max-h-48 overflow-y-auto">{{ json_encode($log->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </td>
                                    </tr>
                                @endif
                                </tbody>
                            @empty
                                <tbody>
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                        {{ __('No activity logs found.') }}
                                    </td>
                                </tr>
                                </tbody>
                            @endforelse
                    </table>
                </div>

                @if($logs->hasPages())
                    <div class="px-4 py-3 border-t border-gray-200">
                        {{ $logs->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
