<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Edit Client') }}: {{ $client->name }}
            </h2>
            <a href="{{ route('staff.clients.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
                {{ __('← Back to Clients') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-[90rem] mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('staff.clients.update', $client) }}">
                        @csrf
                        @method('PATCH')

                        <!-- Discount -->
                        <div class="mb-6">
                            <label for="discount" class="block text-sm font-medium text-gray-700 mb-2">
                                {{ __('Discount (%)') }}
                            </label>
                            <input
                                type="number"
                                id="discount"
                                name="discount"
                                value="{{ old('discount', $client->discount) }}"
                                step="0.01"
                                min="0"
                                max="100"
                                required
                                class="block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                            />
                            @error('discount')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Rates -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                {{ __('Rates') }}
                            </label>
                            <p class="text-sm text-gray-500 mb-3">{{ __('Add custom rates as key-value pairs (e.g., "hourly_rate": 50)') }}</p>
                            <div id="rates-container" class="space-y-3">
                                @if($client->rates && is_array($client->rates) && count($client->rates) > 0)
                                    @foreach($client->rates as $key => $value)
                                        <div class="flex items-center gap-3 rate-item">
                                            <input
                                                type="text"
                                                name="rates_key[]"
                                                value="{{ $key }}"
                                                placeholder="{{ __('Key') }}"
                                                class="w-1/3 px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                            />
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                name="rates_value[]"
                                                value="{{ $value }}"
                                                placeholder="{{ __('Value') }}"
                                                class="w-1/3 px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                            />
                                            <button
                                                type="button"
                                                onclick="removeRateItem(this)"
                                                class="px-4 py-2 text-sm text-red-600 hover:text-red-800 border border-red-300 rounded-lg hover:bg-red-50"
                                            >
                                                {{ __('Remove') }}
                                            </button>
                                        </div>
                                    @endforeach
                                @endif
                            </div>
                            <button
                                type="button"
                                onclick="addRateItem()"
                                class="mt-3 px-4 py-2 text-sm font-medium text-indigo-600 bg-indigo-50 border border-indigo-300 rounded-lg hover:bg-indigo-100"
                            >
                                + {{ __('Add Rate') }}
                            </button>
                            @error('rates')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            @error('rates.*')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Submit Button -->
                        <div class="flex items-center justify-end gap-4">
                            <a
                                href="{{ route('staff.clients.index') }}"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            >
                                {{ __('Cancel') }}
                            </a>
                            <button
                                type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            >
                                {{ __('Update') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function addRateItem() {
            const container = document.getElementById('rates-container');
            const div = document.createElement('div');
            div.className = 'flex items-center gap-3 rate-item';
            div.innerHTML = `
                <input
                    type="text"
                    name="rates_key[]"
                    placeholder="{{ __('Key') }}"
                    class="w-1/3 px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                />
                <input
                    type="number"
                    step="0.01"
                    min="0"
                    name="rates_value[]"
                    placeholder="{{ __('Value') }}"
                    class="w-1/3 px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                />
                <button
                    type="button"
                    onclick="removeRateItem(this)"
                    class="px-4 py-2 text-sm text-red-600 hover:text-red-800 border border-red-300 rounded-lg hover:bg-red-50"
                >
                    {{ __('Remove') }}
                </button>
            `;
            container.appendChild(div);
        }

        function removeRateItem(button) {
            button.closest('.rate-item').remove();
        }
    </script>
</x-app-layout>

