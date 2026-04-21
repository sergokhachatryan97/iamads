<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <!-- Referral Link -->
            @php
                $staffUser = auth()->guard('staff')->user();
                $referralUrl = $staffUser->referral_url;
                $referredCount = \App\Models\Client::where('referred_by_staff_id', $staffUser->id)->count();
            @endphp
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <h2 class="text-lg font-medium text-gray-900">{{ __('Referral Link') }}</h2>
                    <p class="mt-1 text-sm text-gray-600">{{ __('Share this link with potential clients. They will be automatically assigned to you upon registration.') }}</p>

                    <div class="mt-4 flex items-center gap-3">
                        <input type="text" value="{{ $referralUrl }}" readonly id="adminReferralUrl"
                            class="flex-1 px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-sm font-mono text-gray-700">
                        <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('adminReferralUrl').value).then(() => { this.textContent = '{{ __('Copied!') }}'; setTimeout(() => { this.textContent = '{{ __('Copy') }}'; }, 2000); })"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            {{ __('Copy') }}
                        </button>
                    </div>

                    <div class="mt-3 text-sm text-gray-600">
                        {{ __('Referred clients') }}: <span class="font-semibold text-gray-900">{{ $referredCount }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
