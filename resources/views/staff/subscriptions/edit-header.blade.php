<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Edit Client Subscriptions Header Text') }}
            </h2>
            <a href="{{ route('staff.subscriptions.index') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition">
                {{ __('Back to Plans') }}
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                    <p class="text-sm font-medium text-red-800 mb-2">{{ __('Please fix the following errors:') }}</p>
                    <ul class="text-sm text-red-800 list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('staff.subscriptions.update-header') }}">
                        @csrf

                        <div class="mb-6">
                            <label for="value" class="block text-sm font-medium text-gray-700 mb-2">
                                {{ __('Header Text') }}
                            </label>
                            <textarea
                                id="value"
                                name="value"
                                rows="4"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                placeholder="{{ __('Enter the text to display above subscription cards on the client subscriptions page...') }}"
                            >{{ old('value', $uiText?->value ?? '') }}</textarea>
                            @error('value')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-2 text-sm text-gray-500">
                                {{ __('This text will be displayed above the subscription plan cards on the client-facing subscriptions page.') }}
                            </p>
                        </div>

                        <div class="mb-6">
                            <label class="inline-flex items-center">
                                <input
                                    type="checkbox"
                                    name="is_active"
                                    value="1"
                                    {{ old('is_active', $uiText?->is_active ?? false) ? 'checked' : '' }}
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                <span class="ml-2 text-sm text-gray-700">
                                    {{ __('Enable this header text') }}
                                </span>
                            </label>
                            <p class="mt-2 text-sm text-gray-500">
                                {{ __('When enabled, the header text will be displayed on the client subscriptions page. When disabled, no header text will be shown.') }}
                            </p>
                        </div>

                        <div class="flex items-center justify-end gap-4">
                            <a href="{{ route('staff.subscriptions.index') }}"
                               class="px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition">
                                {{ __('Cancel') }}
                            </a>
                            <button
                                type="submit"
                                class="px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition"
                            >
                                {{ __('Save') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
