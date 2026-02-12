<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ isset($service) ? __('Edit Service') : __('Create Service') }}
                </h2>
                <p class="text-xs text-gray-500 mt-1">
                    {{ isset($service) ? __('Update service settings for manual mode.') : __('Create a new service for manual mode.') }}
                </p>
            </div>

            <a href="{{ route('staff.services.index') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition">
                {{ __('Back to Services') }}
            </a>
        </div>
    </x-slot>

    @php
        $defaultMode = \App\Models\Service::MODE_DEFAULT;

        $modeOptions = [
            'manual' => 'Manual',
            'provider' => 'Provider',
        ];

        $serviceTypeOptions = [
            'default'         => 'Default',
            'custom_comments' => 'Custom comments',
            'mentions'        => 'Mentions',
            'package'         => 'Package',
        ];

        $allowOptions = ['1' => 'Allow', '0' => 'Disallow'];
        $yesNoOptions = ['1' => 'Yes', '0' => 'No'];

        $countTypeOptions = [
            'telegram_members'    => 'Telegram members',
            'instagram_likes'     => 'Instagram likes',
            'instagram_followers' => 'Instagram followers',
            'youtube_views'       => 'YouTube views',
        ];
    @endphp

    <div class="py-6">
        <div class="max-w-[95%] mx-auto sm:px-6 lg:px-8">

            {{-- Flash / Errors --}}
            @if (session('status') === 'service-created')
                <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                    <p class="text-sm text-green-800">{{ __('Service created successfully.') }}</p>
                </div>
            @endif

            @if (session('status') === 'service-updated')
                <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                    <p class="text-sm text-green-800">{{ __('Service updated successfully.') }}</p>
                </div>
            @endif

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

            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 sm:p-8">

                    <form method="POST"
                          action="{{ isset($service) ? route('staff.services.update', $service) : route('staff.services.store') }}"
                          x-data="serviceCreateForm()"
                          @change.window="
                            if ($event.target.name === 'mode' && $event.target.type === 'hidden') {
                                const modeSection = document.querySelector('[x-data*=\'mode\']');
                                if (modeSection && window.Alpine) {
                                    const modeData = Alpine.$data(modeSection);
                                    if (modeData) {
                                        modeData.mode = $event.target.value;
                                    }
                                }
                            }
                            if ($event.target.name === 'start_count_parsing_enabled' && $event.target.type === 'hidden') {
                                parsingEnabled = $event.target.value;
                            }
                          ">
                        @csrf
                        @if(isset($service))
                            @method('PUT')
                        @endif

                        <div class="space-y-6">

                            {{-- Essential Information Section --}}
                            <div class="border-l-4 border-indigo-500 bg-indigo-50/30 rounded-r-lg p-5 sm:p-6">
                                <div class="flex items-center gap-2 mb-4">
                                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <h3 class="text-lg font-semibold text-gray-900">{{ __('Essential Information') }}</h3>
                                    <span class="text-xs text-gray-500 font-normal">(Required)</span>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
                                    {{-- Service name --}}
                                    <div>
                                        <label for="name" class="block text-sm font-medium text-gray-700 mb-1.5">
                                            {{ __('Service Name') }} <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text"
                                               name="name"
                                               id="name"
                                               value="{{ old('name', $service->name ?? '') }}"
                                               required
                                               maxlength="255"
                                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('name') border-red-300 @enderror"
                                               placeholder="{{ __('e.g., Instagram Followers') }}">
                                        @error('name')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    {{-- Category --}}
                                    <div>
                                        <x-custom-select
                                            name="category_id"
                                            id="category_id"
                                            :value="old('category_id', isset($service) ? (string) $service->category_id : '')"
                                            :label="__('Category') . ' *'"
                                            placeholder="{{ __('Select a category') }}"
                                            :options="collect($categories)->mapWithKeys(fn($c) => [$c->id => $c->name])->toArray()"
                                        />
                                        @error('category_id')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                            {{-- 2) Mode & options --}}
                            <div class="border border-gray-200 rounded-lg p-5 sm:p-6 bg-white shadow-sm"
                                 x-data="{
                                     mode: @js(old('mode', isset($service) ? ($service->mode ?? $defaultMode) : $defaultMode)),
                                     init() {
                                         const modeInput = document.querySelector('input[name=mode][type=hidden]');
                                         if (modeInput) {
                                             modeInput.addEventListener('change', () => {
                                                 this.mode = modeInput.value;
                                             });
                                         }
                                     }
                                 }">
                                <h3 class="text-base font-semibold text-gray-900 mb-1 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                    {{ __('Mode & Options') }}
                                </h3>
                                <p class="text-xs text-gray-500 mb-4">{{ __('Configure how this service operates') }}</p>

                                <div class="space-y-4">
                                    {{-- Mode --}}
                                    <div>
                                        <x-custom-select
                                            name="mode"
                                            id="mode"
                                            :value="old('mode', isset($service) ? ($service->mode ?? $defaultMode) : $defaultMode)"
                                            :label="__('Service Mode') . ' *'"
                                            placeholder="{{ __('Select mode') }}"
                                            :options="$modeOptions"
                                        />
                                        @error('mode')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    {{-- Manual mode only fields --}}
                                    <div x-show="mode === 'manual'" x-cloak class="mt-4 pt-4 border-t border-gray-200">
                                        <p class="text-xs font-medium text-gray-600 mb-3">{{ __('Manual Mode Settings') }}</p>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                            {{-- Service type --}}
                                            <div>
                                                <x-custom-select
                                                    name="service_type"
                                                    id="service_type"
                                                    :value="old('service_type', isset($service) ? ($service->service_type ?? 'default') : 'default')"
                                                    :label="__('Service type')"
                                                    placeholder="{{ __('Select service type') }}"
                                                    :options="$serviceTypeOptions"
                                                />
                                                @error('service_type')
                                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            {{-- Drip-feed --}}
                                            <div>
                                                <x-custom-select
                                                    name="dripfeed_enabled"
                                                    id="dripfeed_enabled"
                                                    :value="old('dripfeed_enabled', isset($service) ? (string) (int) ($service->dripfeed_enabled ?? false) : '0')"
                                                    :label="__('Drip-feed')"
                                                    placeholder="{{ __('Select') }}"
                                                    :options="$allowOptions"
                                                />
                                                @error('dripfeed_enabled')
                                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            {{-- Cancel option --}}
                                            <div>
                                                <x-custom-select
                                                    name="user_can_cancel"
                                                    id="user_can_cancel"
                                                    :value="old('user_can_cancel', isset($service) ? (string) (int) ($service->user_can_cancel ?? false) : '0')"
                                                    :label="__('Cancel option')"
                                                    placeholder="{{ __('Select') }}"
                                                    :options="$allowOptions"
                                                />
                                                @error('user_can_cancel')
                                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div x-show="mode === 'provider'" x-cloak class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-md">
                                    <p class="text-xs text-blue-800 flex items-start gap-2">
                                        <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                        </svg>
                                        <span>{{ __('Provider mode settings will be fetched from the provider API.') }}</span>
                                    </p>
                                </div>
                            </div>

                            {{-- 3) Rate per 1000 --}}
                            <div class="border border-gray-200 rounded-lg p-5 sm:p-6 bg-white shadow-sm">
                                <h3 class="text-base font-semibold text-gray-900 mb-1 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    {{ __('Pricing') }}
                                </h3>
                                <p class="text-xs text-gray-500 mb-4">
                                    {{ __('Set the service rate per 1000 units') }}
                                </p>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="rate_per_1000" class="block text-sm font-medium text-gray-700 mb-1.5">
                                            {{ __('Service Rate') }} <span class="text-red-500">*</span>
                                            <span class="text-xs font-normal text-gray-500">(per 1000)</span>
                                        </label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <span class="text-gray-500 sm:text-sm">$</span>
                                            </div>
                                            <input type="number"
                                                   name="rate_per_1000"
                                                   id="rate_per_1000"
                                                   value="{{ old('rate_per_1000', isset($service) ? ($service->rate_per_1000 ?? $service->rate ?? 0) : '') }}"
                                                   required
                                                   min="0"
                                                   step="0.0001"
                                                   class="block w-full pl-7 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('rate_per_1000') border-red-300 @enderror"
                                                   placeholder="5.00">
                                        </div>
                                        @error('rate_per_1000')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label for="service_cost_per_1000" class="block text-sm font-medium text-gray-700 mb-1.5">
                                            {{ __('Service Cost') }}
                                            <span class="text-xs font-normal text-gray-400">(optional)</span>
                                            <span class="text-xs font-normal text-gray-500">(per 1000)</span>
                                        </label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <span class="text-gray-500 sm:text-sm">$</span>
                                            </div>
                                            <input type="number"
                                                   name="service_cost_per_1000"
                                                   id="service_cost_per_1000"
                                                   value="{{ old('service_cost_per_1000', isset($service) ? $service->service_cost_per_1000 : '') }}"
                                                   min="0"
                                                   step="0.0001"
                                                   class="block w-full pl-7 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('service_cost_per_1000') border-red-300 @enderror"
                                                   placeholder="3.50">
                                        </div>
                                        @error('service_cost_per_1000')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                        <p class="mt-1.5 text-xs text-gray-500 flex items-start gap-1">
                                            <svg class="w-3 h-3 mt-0.5 text-gray-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                            </svg>
                                            <span>{{ __("Used for profit calculation. Leave empty if not needed.") }}</span>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {{-- 4) Quantity limits --}}
                            <div class="border border-gray-200 rounded-lg p-5 sm:p-6 bg-white shadow-sm">
                                <h3 class="text-base font-semibold text-gray-900 mb-1 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                    </svg>
                                    {{ __('Quantity Limits') }}
                                </h3>
                                <p class="text-xs text-gray-500 mb-4">{{ __('Set minimum and maximum order quantities') }}</p>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label for="min_quantity" class="block text-sm font-medium text-gray-700 mb-1.5">
                                            {{ __('Minimum Order') }} <span class="text-red-500">*</span>
                                        </label>
                                        <input type="number"
                                               name="min_quantity"
                                               id="min_quantity"
                                               value="{{ old('min_quantity', isset($service) ? ($service->min_quantity ?? $service->min_order ?? 1) : '1') }}"
                                               required
                                               min="1"
                                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('min_quantity') border-red-300 @enderror"
                                               placeholder="1">
                                        @error('min_quantity')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div>
                                        <label for="max_quantity" class="block text-sm font-medium text-gray-700 mb-1.5">
                                            {{ __('Maximum Order') }} <span class="text-red-500">*</span>
                                        </label>
                                        <input type="number"
                                               name="max_quantity"
                                               id="max_quantity"
                                               value="{{ old('max_quantity', isset($service) ? ($service->max_quantity ?? $service->max_order ?? 1) : '1') }}"
                                               required
                                               min="1"
                                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('max_quantity') border-red-300 @enderror"
                                               placeholder="10000">
                                        @error('max_quantity')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            {{-- 5) Advanced Settings --}}
                            <div class="border border-gray-200 rounded-lg p-5 sm:p-6 bg-white shadow-sm lg:col-span-2">
                                <h3 class="text-base font-semibold text-gray-900 mb-1 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path>
                                    </svg>
                                    {{ __('Advanced Settings') }}
                                </h3>
                                <p class="text-xs text-gray-500 mb-4">{{ __('Optional configuration options') }}</p>

                                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                                    {{-- Deny link duplicates --}}
                                    <div>
                                            <x-custom-select
                                                name="deny_link_duplicates"
                                                id="deny_link_duplicates"
                                                :value="old('deny_link_duplicates', isset($service) ? (string) (int) ($service->deny_link_duplicates ?? false) : '0')"
                                                :label="__('Deny Link Duplicates')"
                                                placeholder="{{ __('Select') }}"
                                                :options="$yesNoOptions"
                                                x-model="denyDuplicates"
                                            />
                                            @error('deny_link_duplicates')
                                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>

                                    <div x-show="denyDuplicates === '1'" x-cloak class="transition-all">
                                        <label for="deny_duplicates_days" class="block text-sm font-medium text-gray-700 mb-1.5">
                                            {{ __('Days to Check') }}
                                        </label>
                                        <input type="number"
                                               name="deny_duplicates_days"
                                               id="deny_duplicates_days"
                                               value="{{ old('deny_duplicates_days', isset($service) ? ($service->deny_duplicates_days ?? 90) : '90') }}"
                                               min="1"
                                               max="3650"
                                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('deny_duplicates_days') border-red-300 @enderror"
                                               placeholder="90">
                                        @error('deny_duplicates_days')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                        <p class="mt-1 text-xs text-gray-500">{{ __('Check for duplicate links within this period') }}</p>
                                    </div>

                                    {{-- Increment --}}
                                    <div>
                                        <label for="increment" class="block text-sm font-medium text-gray-700 mb-1.5">
                                            {{ __('Order Increment') }}
                                        </label>
                                        <input type="number"
                                               name="increment"
                                               id="increment"
                                               value="{{ old('increment', isset($service) ? ($service->increment ?? 0) : '0') }}"
                                               min="0"
                                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('increment') border-red-300 @enderror"
                                               placeholder="0">
                                        @error('increment')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                        <p class="mt-1.5 text-xs text-gray-500 flex items-start gap-1">
                                            <svg class="w-3 h-3 mt-0.5 text-gray-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                            </svg>
                                            <span>{{ __('Orders must be multiples of this value. Set 0 to disable.') }}</span>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {{-- 6) Parsing & Automation --}}
                            <div class="border border-gray-200 rounded-lg p-5 sm:p-6 bg-white shadow-sm">
                                <h3 class="text-base font-semibold text-gray-900 mb-1 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                    </svg>
                                    {{ __('Parsing & Automation') }}
                                </h3>
                                <p class="text-xs text-gray-500 mb-4">{{ __('Configure automatic count parsing and completion') }}</p>

                                <div class="space-y-4">
                                    <div>
                                        <x-custom-select
                                            name="start_count_parsing_enabled"
                                            id="start_count_parsing_enabled"
                                            :value="old('start_count_parsing_enabled', isset($service) ? (string) (int) ($service->start_count_parsing_enabled ?? false) : '0')"
                                            :label="__('Start Count Parsing')"
                                            placeholder="{{ __('Select') }}"
                                            :options="$allowOptions"
                                        />
                                        @error('start_count_parsing_enabled')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div x-show="parsingEnabled === '1'" x-cloak class="transition-all pt-4 border-t border-gray-200">
                                        <x-custom-select
                                            name="count_type"
                                            id="count_type"
                                            :value="old('count_type', isset($service) ? $service->count_type : '')"
                                            :label="__('Count Type') . ' *'"
                                            placeholder="{{ __('Select count type') }}"
                                            :options="$countTypeOptions"
                                        />
                                        @error('count_type')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                        <p class="mt-1.5 text-xs text-gray-500">
                                            {{ __('Select the type of count to parse from the service URL') }}
                                        </p>
                                    </div>

                                    <div>
                                        <x-custom-select
                                            name="auto_complete_enabled"
                                            id="auto_complete_enabled"
                                            :value="old('auto_complete_enabled', isset($service) ? (string) (int) ($service->auto_complete_enabled ?? false) : '0')"
                                            :label="__('Auto Complete')"
                                            placeholder="{{ __('Select') }}"
                                            :options="$allowOptions"
                                        />
                                        <div x-show="parsingEnabled !== '1'" class="mt-2 p-2 bg-amber-50 border border-amber-200 rounded text-xs text-amber-800">
                                            {{ __('Enable Start count parsing to use Auto Complete.') }}
                                        </div>
                                        @error('auto_complete_enabled')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            {{-- 7) Refill --}}
                            <div class="border border-gray-200 rounded-lg p-5 sm:p-6 bg-white shadow-sm">
                                <h3 class="text-base font-semibold text-gray-900 mb-1 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                    {{ __('Refill') }}
                                </h3>
                                <p class="text-xs text-gray-500 mb-4">{{ __('Enable automatic refill for orders') }}</p>

                                <div>
                                    <x-custom-select
                                        name="refill_enabled"
                                        id="refill_enabled"
                                        :value="old('refill_enabled', isset($service) ? (string) (int) ($service->refill_enabled ?? false) : '0')"
                                        :label="__('Enable Refill')"
                                        placeholder="{{ __('Select') }}"
                                        :options="$allowOptions"
                                    />
                                    @error('refill_enabled')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            {{-- 8) Description --}}
                            <div class="border border-gray-200 rounded-lg p-5 sm:p-6 bg-white shadow-sm lg:col-span-2">
                                <h3 class="text-base font-semibold text-gray-900 mb-1 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    {{ __('Additional Information') }}
                                </h3>
                                <p class="text-xs text-gray-500 mb-4">{{ __('Service description') }}</p>

                                <div>
                                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1.5">
                                        {{ __('Description') }}
                                        <span class="text-xs font-normal text-gray-400">(optional)</span>
                                    </label>
                                    <textarea name="description"
                                              id="description"
                                              rows="3"
                                              class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('description') border-red-300 @enderror"
                                              placeholder="{{ __('Write a short description for this service...') }}">{{ old('description', isset($service) ? $service->description : '') }}</textarea>
                                    @error('description')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                        </div>

                        </div>

                        {{-- Buttons --}}
                        <div class="mt-8 pt-6 border-t border-gray-200 flex flex-col sm:flex-row justify-end gap-3">
                            <a href="{{ route('staff.services.index') }}"
                               class="inline-flex justify-center items-center rounded-md border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                                {{ __('Cancel') }}
                            </a>

                            <button type="submit"
                                    class="inline-flex justify-center items-center rounded-md border border-transparent bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                {{ isset($service) ? __('Update Service') : __('Create Service') }}
                            </button>
                        </div>
                    </form>

                </div>
            </div>

        </div>
    </div>

    <script>
        function serviceCreateForm() {
            return {
                denyDuplicates: @js(old('deny_link_duplicates', isset($service) ? (string) (int) ($service->deny_link_duplicates ?? false) : '0')),
                parsingEnabled: @js(old('start_count_parsing_enabled', isset($service) ? (string) (int) ($service->start_count_parsing_enabled ?? false) : '0')),

                init() {
                    // Initialize parsingEnabled from the hidden input value if it exists (for edit mode)
                    this.$nextTick(() => {
                        const parsingInput = document.querySelector('input[name="start_count_parsing_enabled"][type="hidden"]');
                        if (parsingInput) {
                            // Set initial value from hidden input
                            if (parsingInput.value) {
                                this.parsingEnabled = parsingInput.value;
                            }

                            // Watch for parsing enabled changes from custom-select
                            parsingInput.addEventListener('change', () => {
                                this.parsingEnabled = parsingInput.value;
                                this.guardAutoComplete(this.parsingEnabled);
                            });
                        }

                        // Watch for mode changes
                        const modeInput = document.querySelector('input[name="mode"][type="hidden"]');
                        if (modeInput) {
                            modeInput.addEventListener('change', () => {
                                const modeSection = document.querySelector('[x-data*="mode"]');
                                if (modeSection && window.Alpine) {
                                    const modeData = Alpine.$data(modeSection);
                                    if (modeData) {
                                        modeData.mode = modeInput.value;
                                    }
                                }
                            });
                        }
                    });

                    // Initial guard check
                    this.guardAutoComplete(this.parsingEnabled);
                },

                guardAutoComplete(parsingEnabled) {
                    if (parsingEnabled !== '1') {
                        const auto = document.getElementById('auto_complete_enabled');
                        if (auto) {
                            const autoSelect = auto.closest('[x-data*="customSelect"]');
                            if (autoSelect && window.Alpine) {
                                const autoData = Alpine.$data(autoSelect);
                                if (autoData) {
                                    autoData.selectedValue = '0';
                                    const hiddenInput = autoSelect.querySelector('input[type=hidden]');
                                    if (hiddenInput) hiddenInput.value = '0';
                                }
                            }
                        }

                        const count = document.getElementById('count_type');
                        if (count) {
                            const countSelect = count.closest('[x-data*="customSelect"]');
                            if (countSelect && window.Alpine) {
                                const countData = Alpine.$data(countSelect);
                                if (countData) {
                                    countData.selectedValue = '';
                                    const hiddenInput = countSelect.querySelector('input[type=hidden]');
                                    if (hiddenInput) hiddenInput.value = '';
                                }
                            }
                        }
                    }
                }
            }
        }
    </script>
</x-app-layout>
