<x-settings-layout>
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 text-gray-900">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-medium text-gray-900">{{ __('External Providers') }}</h3>
                <a href="{{ route('staff.settings.external-providers.create') }}"
                   class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                    {{ __('Add Provider') }}
                </a>
            </div>

            @if(session('success'))
                <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                    <p class="text-sm text-green-800">{{ session('success') }}</p>
                </div>
            @endif

            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6">
                <p class="text-sm text-gray-600">
                    <strong>{{ __('How it works:') }}</strong>
                    {{ __('Add external SMM panels here. Then when creating a service, select "Provider" mode and choose which panel should fulfill orders for that service.') }}
                </p>
            </div>

            @if($providers->isEmpty())
                <div class="text-center py-8 text-gray-500">
                    <svg class="mx-auto h-12 w-12 text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                    </svg>
                    <p class="text-sm">{{ __('No external providers configured yet.') }}</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Name') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Code') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('URL') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Status') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($providers as $provider)
                                <tr>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $provider->name }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">
                                        <code class="bg-gray-100 px-1.5 py-0.5 rounded text-xs">{{ $provider->code }}</code>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500 max-w-xs truncate">{{ $provider->base_url }}</td>
                                    <td class="px-4 py-3">
                                        @if($provider->is_active)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">{{ __('Active') }}</span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">{{ __('Disabled') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm space-x-2" x-data="{ testing: false, result: null }">
                                        <button
                                            type="button"
                                            @click="testing = true; result = null; fetch('{{ route('staff.settings.external-providers.test', $provider) }}', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.json()).then(d => { result = d; testing = false; }).catch(() => { result = { ok: false, error: 'Network error' }; testing = false; })"
                                            class="text-blue-600 hover:text-blue-800 text-xs font-medium"
                                            :disabled="testing"
                                        >
                                            <span x-show="!testing">{{ __('Test') }}</span>
                                            <span x-show="testing" x-cloak>...</span>
                                        </button>
                                        <template x-if="result">
                                            <span x-show="result" class="text-xs" :class="result?.ok ? 'text-green-600' : 'text-red-600'">
                                                <span x-show="result?.ok" x-text="'$' + result?.balance"></span>
                                                <span x-show="!result?.ok" x-text="result?.error?.substring(0, 30)"></span>
                                            </span>
                                        </template>

                                        <a href="{{ route('staff.settings.external-providers.edit', $provider) }}"
                                           class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">{{ __('Edit') }}</a>

                                        <form method="POST" action="{{ route('staff.settings.external-providers.toggle', $provider) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="text-xs font-medium {{ $provider->is_active ? 'text-yellow-600 hover:text-yellow-800' : 'text-green-600 hover:text-green-800' }}">
                                                {{ $provider->is_active ? __('Disable') : __('Enable') }}
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('staff.settings.external-providers.destroy', $provider) }}" class="inline"
                                              onsubmit="return confirm('{{ __('Delete this provider?') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-800 text-xs font-medium">{{ __('Delete') }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-settings-layout>
