<x-settings-layout>
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 text-gray-900">
            <div class="flex items-center gap-3 mb-6">
                <a href="{{ route('staff.settings.external-providers.index') }}" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </a>
                <h3 class="text-lg font-medium text-gray-900">
                    {{ isset($provider) ? __('Edit Provider') : __('Add External Provider') }}
                </h3>
            </div>

            @if($errors->any())
                <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                    <ul class="list-disc list-inside text-sm text-red-800">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST"
                  action="{{ isset($provider) ? route('staff.settings.external-providers.update', $provider) : route('staff.settings.external-providers.store') }}">
                @csrf
                @if(isset($provider))
                    @method('PUT')
                @endif

                <div class="space-y-5 max-w-lg">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('Display Name') }} <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="name" id="name"
                               value="{{ old('name', $provider->name ?? '') }}"
                               placeholder="{{ __('e.g. Perfect Panel') }}"
                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                               required>
                    </div>

                    <div>
                        <label for="code" class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('Code') }} <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="code" id="code"
                               value="{{ old('code', $provider->code ?? '') }}"
                               placeholder="{{ __('e.g. perfectpanel') }}"
                               pattern="[a-z0-9_]+"
                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                               required>
                        <p class="mt-1 text-xs text-gray-500">{{ __('Lowercase letters, numbers, underscores only. Used as internal identifier.') }}</p>
                    </div>

                    <div>
                        <label for="base_url" class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('API URL') }} <span class="text-red-500">*</span>
                        </label>
                        <input type="url" name="base_url" id="base_url"
                               value="{{ old('base_url', $provider->base_url ?? '') }}"
                               placeholder="https://example.com/api/v2"
                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                               required>
                    </div>

                    <div>
                        <label for="api_key" class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('API Key') }}
                            @if(!isset($provider)) <span class="text-red-500">*</span> @endif
                        </label>
                        <input type="password" name="api_key" id="api_key"
                               value="{{ old('api_key') }}"
                               placeholder="{{ isset($provider) ? __('Leave empty to keep current key') : '' }}"
                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                               {{ isset($provider) ? '' : 'required' }}>
                    </div>

                    <div>
                        <label for="timeout" class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('Timeout (seconds)') }}
                        </label>
                        <input type="number" name="timeout" id="timeout"
                               value="{{ old('timeout', $provider->timeout ?? 30) }}"
                               min="5" max="120"
                               class="block w-32 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                            {{ isset($provider) ? __('Update') : __('Add Provider') }}
                        </button>
                        <a href="{{ route('staff.settings.external-providers.index') }}"
                           class="text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-settings-layout>
