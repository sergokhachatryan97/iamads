<x-settings-layout>
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 text-gray-900">
            <h3 class="text-lg font-medium text-gray-900 mb-6">{{ __('Referral Program Settings') }}</h3>

            @if(session('success'))
                <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                    <p class="text-sm text-green-800">{{ session('success') }}</p>
                </div>
            @endif

            <form method="POST" action="{{ route('staff.settings.referral.update') }}">
                @csrf
                @method('PUT')

                <div class="space-y-6">
                    <div>
                        <label for="referral_bonus_percent" class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('Referral Bonus Percentage') }}
                        </label>
                        <div class="flex items-center gap-2">
                            <input type="number"
                                   id="referral_bonus_percent"
                                   name="referral_bonus_percent"
                                   value="{{ old('referral_bonus_percent', $bonusPercent) }}"
                                   min="0"
                                   max="100"
                                   step="0.01"
                                   class="w-32 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                   required>
                            <span class="text-sm text-gray-500">%</span>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">
                            {{ __('Percentage of each payment that the referrer receives as a bonus.') }}
                        </p>
                        @error('referral_bonus_percent')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox"
                                   name="is_active"
                                   value="1"
                                   class="sr-only peer"
                                   {{ $isActive ? 'checked' : '' }}>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                            <span class="ml-3 text-sm font-medium text-gray-700">{{ __('Referral program enabled') }}</span>
                        </label>
                        <p class="mt-1 text-xs text-gray-500">
                            {{ __('When disabled, no referral bonuses will be credited for new payments.') }}
                        </p>
                    </div>

                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                        <p class="text-sm text-gray-600">
                            <strong>{{ __('How it works:') }}</strong>
                            {{ __('Clients share their referral link. When a referred user makes a payment, the referrer receives the configured percentage as a bonus added to their balance. Funds cannot be withdrawn.') }}
                        </p>
                    </div>

                    <div>
                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            {{ __('Save') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-settings-layout>
