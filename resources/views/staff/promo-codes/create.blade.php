<x-settings-layout>
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6">
            <div class="flex items-center gap-4 mb-6">
                <a href="{{ route('staff.promo-codes.index') }}" class="text-gray-400 hover:text-gray-600">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
                <h3 class="text-lg font-medium text-gray-900">{{ __('Create Promo Code') }}</h3>
            </div>

            <form method="POST" action="{{ route('staff.promo-codes.store') }}" class="space-y-5 max-w-lg">
                @csrf

                <div>
                    <label for="code" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Code') }} <span class="text-gray-400">({{ __('leave blank to auto-generate') }})</span></label>
                    <input type="text" name="code" id="code" value="{{ old('code') }}"
                           class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm uppercase"
                           placeholder="e.g. WELCOME50" maxlength="32" autocomplete="off">
                    @error('code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="reward_value" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Reward Amount ($)') }} <span class="text-red-500">*</span></label>
                    <input type="number" name="reward_value" id="reward_value" value="{{ old('reward_value') }}"
                           class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                           step="0.01" min="0.01" max="999999.99" required placeholder="10.00">
                    @error('reward_value') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="max_uses" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Max Total Uses') }} <span class="text-gray-400">({{ __('blank = unlimited') }})</span></label>
                        <input type="number" name="max_uses" id="max_uses" value="{{ old('max_uses') }}"
                               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                               min="1" placeholder="∞">
                        @error('max_uses') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="max_uses_per_client" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Max Per User') }}</label>
                        <input type="number" name="max_uses_per_client" id="max_uses_per_client" value="{{ old('max_uses_per_client', 1) }}"
                               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                               min="1" required>
                        @error('max_uses_per_client') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label for="expires_at" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Expires At') }} <span class="text-gray-400">({{ __('optional') }})</span></label>
                    <input type="datetime-local" name="expires_at" id="expires_at" value="{{ old('expires_at') }}"
                           class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    @error('expires_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center gap-2">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}
                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <label for="is_active" class="text-sm font-medium text-gray-700">{{ __('Active immediately') }}</label>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition-colors">
                        <i class="fa-solid fa-plus"></i>
                        {{ __('Create Promo Code') }}
                    </button>
                    <a href="{{ route('staff.promo-codes.index') }}" class="text-sm text-gray-500 hover:text-gray-700">{{ __('Cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
</x-settings-layout>
