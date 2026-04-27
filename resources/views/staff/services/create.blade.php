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

    <style>
        @media (max-width: 768px) {
            .svc-edit-page { padding: 12px 0 !important; }
            .svc-edit-page > div { max-width: 100% !important; padding: 0 10px !important; }
            /* Stack rate + quantity fields */
            .svc-edit-page .row { flex-direction: column !important; }
            .svc-edit-page .row > [class*="col-"] {
                width: 100% !important;
                max-width: 100% !important;
                flex: 0 0 100% !important;
                padding-left: 0 !important;
                padding-right: 0 !important;
                margin-bottom: 12px !important;
            }
            /* Inputs: larger touch targets */
            .svc-edit-page input[type="number"],
            .svc-edit-page input[type="text"],
            .svc-edit-page input[type="url"],
            .svc-edit-page select,
            .svc-edit-page textarea {
                font-size: 16px !important;
                padding: 10px 12px !important;
                border-radius: 10px !important;
            }
            .svc-edit-page input[type="number"].pl-7 {
                padding-left: 28px !important;
            }
            /* Cards: less padding */
            .svc-edit-page .border.border-gray-200.rounded-lg {
                padding: 14px !important;
            }
            /* Header */
            .svc-edit-page + header .flex { flex-direction: column; gap: 10px; align-items: flex-start !important; }
            /* Submit buttons */
            .svc-edit-page button[type="submit"],
            .svc-edit-page a.inline-flex {
                width: 100%;
                justify-content: center;
            }
            .svc-edit-page .flex.justify-end.gap-3 {
                flex-direction: column !important;
                gap: 8px !important;
            }
        }
        @media (max-width: 480px) {
            .svc-edit-page .border.border-gray-200.rounded-lg { padding: 12px !important; }
            .svc-edit-page h3 { font-size: 14px !important; }
            .svc-edit-page label { font-size: 13px !important; }
        }
    </style>

    <div class="py-6 svc-edit-page">
        <div class="max-w-[95%] mx-auto sm:px-6 lg:px-8">

            {{-- Flash / Errors --}}
            @if (session('status') === 'service-created')
                <div
                    x-data="{ show: true }"
                    x-init="setTimeout(() => show = false, 3000)"
                    x-show="show"
                    x-transition.opacity.duration.300ms
                    class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg"
                >
                    <p class="text-sm text-green-800">{{ __('Service created successfully.') }}</p>
                </div>
            @endif

            @if (session('status') === 'service-updated')
                <div
                    x-data="{ show: true }"
                    x-init="setTimeout(() => show = false, 3000)"
                    x-show="show"
                    x-transition.opacity.duration.300ms
                    class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg"
                >
                    <p class="text-sm text-green-800">{{ __('Service updated successfully.') }}</p>
                </div>
            @endif

            @if ($errors->any())
                <div
                    x-data="{ show: true }"
                    x-init="setTimeout(() => show = false, 3000)"
                    x-show="show"
                    x-transition.opacity.duration.300ms
                    class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg"
                >
                    <p class="text-sm font-medium text-red-800 mb-2">{{ __('Please fix the following errors:') }}</p>
                    <ul class="text-sm text-red-800 list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @php
                $staffServiceSpeedTierMode = old('speed_limit_tier_mode', isset($service) ? ($service->speed_limit_tier_mode ?? 'fast') : 'fast');
                if ($staffServiceSpeedTierMode === 'both') {
                    $staffServiceSpeedTierMode = 'fast';
                }
                $staffSuperFastRaw = old('speed_multiplier_super_fast', isset($service) ? $service->speed_multiplier_super_fast : null);
                $staffSuperFastMultiplier = ($staffSuperFastRaw !== null && (float) $staffSuperFastRaw > 1) ? (float) $staffSuperFastRaw : 2;
            @endphp

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
                                    <h3 class="text-lg font-semibold text-gray-900">{{ __('Service Information') }}</h3>
                                    <span class="text-xs text-gray-500 font-normal">(Required)</span>
                                </div>

                                {{-- Icon (same pattern as category) --}}
                                <div class="flex items-start gap-3 mb-4">
                                    <div class="flex flex-col gap-1">
                                        <label class="text-sm font-medium text-gray-700 whitespace-nowrap">{{ __('Add icon') }}</label>
                                        <x-icon-picker name="icon" value="{{ old('icon', $service?->icon ?? '') }}" />
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">

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

                                    {{-- Service name (auto-generated but editable) --}}
                                    <div>
                                        <label for="name" class="block text-sm font-medium text-gray-700 mb-1.5">
                                            {{ __('Service Name') }} <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text"
                                               name="name"
                                               id="name"
                                               x-model="serviceName"
                                               value="{{ old('name', $service->name ?? '') }}"
                                               required
                                               maxlength="255"
                                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('name') border-red-300 @enderror"
                                               placeholder="{{ __('Auto-generated from template') }}">
                                        @error('name')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                        <p class="mt-1 text-xs text-gray-500">
                                            {{ __('Auto-generated from template, but can be customized') }}
                                        </p>
                                    </div>

                                    {{-- Service Template (only for categories with service templates, e.g. Telegram, YouTube) --}}
                                    <div x-show="categoryHasTemplates" x-cloak>
                                        <input type="hidden" name="target_type" value="{{ isset($service) ? ($service->target_type ?? '') : '' }}">
                                        <div>
                                            <label for="template_key" class="block text-sm font-medium text-gray-700 mb-1.5">
                                                {{ __('Service Template') }} <span class="text-red-500">*</span>
                                            </label>
                                            <select
                                                name="template_key"
                                                id="template_key"
                                                x-model="selectedTemplate"
                                                @change="updateTemplateInfo()"
                                                :required="categoryHasTemplates"
                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('template_key') border-red-300 @enderror">
                                                <option value="">{{ __('Select a template') }}</option>
                                                <template x-for="[key, label] in Object.entries(filteredTemplates)" :key="key">
                                                    <option :value="key" :selected="selectedTemplate === key" x-text="label"></option>
                                                </template>
                                            </select>
                                            @error('template_key')
                                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                            @enderror
                                            <p class="mt-1 text-xs text-gray-500">
                                                {{ __('Select a template that defines action and policy. Cannot be changed after creation.') }}
                                            </p>
                                        </div>
                                    </div>

                                    {{-- Duration Days (conditional on template) --}}
                                    <div x-show="selectedTemplate && templateRequiresDuration" x-cloak>
                                        <label for="duration_days" class="block text-sm font-medium text-gray-700 mb-1.5">
                                            {{ __('Duration (Days)') }} <span class="text-red-500">*</span>
                                        </label>
                                        <input type="number"
                                               name="duration_days"
                                               id="duration_days"
                                               x-model.number="durationDays"
                                               @input="generateServiceName()"
                                               value="{{ old('duration_days', isset($service) ? ($service->duration_days ?? '') : '') }}"
                                               :required="templateRequiresDuration"
                                               min="1"
                                               max="9999"
                                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('duration_days') border-red-300 @enderror"
                                               placeholder="14, 30, 90, etc.">
                                        @error('duration_days')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                        <p class="mt-1 text-xs text-gray-500">
                                            {{ __('Subscription duration in days (e.g., 14, 30, 90)') }}
                                        </p>
                                    </div>

                                    {{-- Watch Time seconds (YouTube watch-time template) --}}
                                    <div x-show="selectedTemplate && templateRequiresWatchTime" x-cloak>
                                        <label for="watch_time_seconds" class="block text-sm font-medium text-gray-700 mb-1.5">
                                            {{ __('Watch Time (seconds)') }} <span class="text-red-500">*</span>
                                        </label>
                                        <input type="number"
                                               name="watch_time_seconds"
                                               id="watch_time_seconds"
                                               x-model.number="watchTimeSeconds"
                                               value="{{ old('watch_time_seconds', isset($service) ? ($service->watch_time_seconds ?? '') : '30') }}"
                                               :required="templateRequiresWatchTime"
                                               min="1"
                                               max="7200"
                                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('watch_time_seconds') border-red-300 @enderror"
                                               placeholder="30">
                                        @error('watch_time_seconds')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                        <p class="mt-1 text-xs text-gray-500">
                                            {{ __('Minimum watch duration per view in seconds (e.g., 30, 60). Max 7200 (2 hours).') }}
                                        </p>
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

                                            {{-- Drip-feed (toggle) --}}
                                            <div>
                                                <div class="flex items-center justify-between gap-3 min-h-[2.75rem]"
                                                     x-bind:class="{ 'opacity-50 pointer-events-none': speedLimitEnabled }">
                                                    <div class="flex-1 min-w-0 pr-2">
                                                        <span class="text-sm font-medium text-gray-700">{{ __('Drip-feed') }}</span>
                                                        <p class="mt-0.5 text-xs text-gray-500">{{ __('Deliver orders gradually over time') }}</p>
                                                    </div>
                                                    <button type="button"
                                                            role="switch"
                                                            :aria-checked="dripfeedEnabled ? 'true' : 'false'"
                                                            @click="
                                                                if (speedLimitEnabled) return;
                                                                dripfeedEnabled = !dripfeedEnabled;
                                                                if (dripfeedEnabled) {
                                                                    speedLimitEnabled = false;
                                                                    const sl = document.querySelector('input[name=\'speed_limit_enabled\']');
                                                                    if (sl) sl.checked = false;
                                                                }
                                                            "
                                                            :class="dripfeedEnabled ? 'bg-indigo-600' : 'bg-gray-200'"
                                                            class="relative inline-flex h-7 w-12 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                                        <span class="sr-only">{{ __('Toggle drip-feed') }}</span>
                                                        <span aria-hidden="true"
                                                              :class="dripfeedEnabled ? 'translate-x-5' : 'translate-x-1'"
                                                              class="pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
                                                    </button>
                                                    <input type="hidden" name="dripfeed_enabled" :value="dripfeedEnabled ? '1' : '0'">
                                                </div>
                                                @error('dripfeed_enabled')
                                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                                @enderror
                                                <p class="mt-1 text-xs text-gray-500" x-show="speedLimitEnabled" x-cloak>
                                                    {{ __('Disabled (speed limit enabled)') }}
                                                </p>
                                            </div>

                                            {{-- Cancel option (toggle) --}}
                                            <div>
                                                <div class="flex items-center justify-between gap-3 min-h-[2.75rem]">
                                                    <div class="flex-1 min-w-0 pr-2">
                                                        <span class="text-sm font-medium text-gray-700">{{ __('Cancel option') }}</span>
                                                        <p class="mt-0.5 text-xs text-gray-500">{{ __('Allow clients to cancel orders for this service') }}</p>
                                                    </div>
                                                    <button type="button"
                                                            role="switch"
                                                            :aria-checked="userCanCancel ? 'true' : 'false'"
                                                            @click="userCanCancel = !userCanCancel"
                                                            :class="userCanCancel ? 'bg-indigo-600' : 'bg-gray-200'"
                                                            class="relative inline-flex h-7 w-12 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                                        <span class="sr-only">{{ __('Toggle cancel option') }}</span>
                                                        <span aria-hidden="true"
                                                              :class="userCanCancel ? 'translate-x-5' : 'translate-x-1'"
                                                              class="pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
                                                    </button>
                                                    <input type="hidden" name="user_can_cancel" :value="userCanCancel ? '1' : '0'">
                                                </div>
                                                @error('user_can_cancel')
                                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Speed Limit Settings --}}
                                <div x-show="mode === 'manual'" x-cloak class="mt-4 pt-4 border-t border-gray-200">
                                    <p class="text-xs font-medium text-gray-600 mb-3">{{ __('Speed Limit Settings') }}</p>
                                    <div class="space-y-4">
                                        <div>
                                            <label class="flex items-center gap-3">
                                                <input
                                                    type="checkbox"
                                                    name="speed_limit_enabled"
                                                    id="speed_limit_enabled"
                                                    x-model="speedLimitEnabled"
                                                    @change="handleSpeedLimitToggle()"
                                                    :disabled="dripfeedEnabled"
                                                    value="1"
                                                    {{ old('speed_limit_enabled', isset($service) ? ($service->speed_limit_enabled ?? false) : false) ? 'checked' : '' }}
                                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                    :class="dripfeedEnabled ? 'opacity-50 cursor-not-allowed' : ''"
                                                >
                                                <span class="text-sm font-medium text-gray-700" :class="dripfeedEnabled ? 'text-gray-500' : ''">{{ __('Enable Speed Limit') }}</span>
                                            </label>
                                            <p class="mt-1 text-xs text-gray-500">
                                                {{ __('Delivery uses one speed tier per service (fast by default, or super fast only). Price is unchanged.') }}
                                            </p>
                                            <p class="mt-1 text-xs text-gray-500" x-show="dripfeedEnabled" x-cloak>
                                                {{ __('Disabled (dripfeed enabled)') }}
                                            </p>
                                            @error('speed_limit_enabled')
                                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div x-show="speedLimitEnabled" x-cloak class="pl-6 border-l-2 border-indigo-200">
                                            <div class="mb-4">
                                                <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Speed Tier Mode') }}</label>
                                                <div class="flex flex-wrap gap-4">
                                                    <label class="flex items-center gap-2">
                                                        <input type="radio" name="speed_limit_tier_mode" value="fast" x-model="speedLimitTierMode"
                                                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                                        <span class="text-sm text-gray-700">{{ __('Fast (default)') }}</span>
                                                    </label>
                                                    <label class="flex items-center gap-2">
                                                        <input type="radio" name="speed_limit_tier_mode" value="super_fast" x-model="speedLimitTierMode"
                                                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                                        <span class="text-sm text-gray-700">{{ __('Super fast only') }}</span>
                                                    </label>
                                                </div>
                                            </div>

                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div x-show="speedLimitTierMode === 'fast'" x-cloak>
                                                    <label for="speed_multiplier_fast" class="block text-sm font-medium text-gray-700 mb-1.5">
                                                        {{ __('Fast Multiplier') }} <span class="text-red-500">*</span>
                                                    </label>
                                                    <input
                                                        type="number"
                                                        name="speed_multiplier_fast"
                                                        id="speed_multiplier_fast"
                                                        x-model.number="speedMultiplierFast"
                                                        :required="speedLimitEnabled && speedLimitTierMode === 'fast'"
                                                        min="1"
                                                        max="10"
                                                        step="0.01"
                                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('speed_multiplier_fast') border-red-300 @enderror"
                                                        placeholder="1.50">
                                                    @error('speed_multiplier_fast')
                                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                                    @enderror
                                                    <p class="mt-1 text-xs text-gray-500">{{ __('Relative delivery speed for the fast tier (e.g., 1.50 = 50% faster pacing). Does not change the price.') }}</p>
                                                </div>

                                                <div x-show="speedLimitTierMode === 'super_fast'" x-cloak>
                                                    <label for="speed_multiplier_super_fast" class="block text-sm font-medium text-gray-700 mb-1.5">
                                                        {{ __('Super Fast Speed Multiplier') }} <span class="text-red-500">*</span>
                                                    </label>
                                                    <input
                                                        type="number"
                                                        name="speed_multiplier_super_fast"
                                                        id="speed_multiplier_super_fast"
                                                        x-model.number="speedMultiplierSuperFast"
                                                        :required="speedLimitEnabled && speedLimitTierMode === 'super_fast'"
                                                        min="1"
                                                        max="10"
                                                        step="0.01"
                                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('speed_multiplier_super_fast') border-red-300 @enderror"
                                                        placeholder="2">
                                                    @error('speed_multiplier_super_fast')
                                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                                    @enderror
                                                    <p class="mt-1 text-xs text-gray-500">{{ __('Execution speed multiplier for super fast tier (default 2 = 2× faster)') }}</p>
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Dependency Settings --}}
{{--                                <div x-show="mode === 'manual'" x-cloak class="mt-4 pt-4 border-t border-gray-200">--}}
{{--                                    <p class="text-xs font-medium text-gray-600 mb-3">{{ __('Dependency Settings') }}</p>--}}
{{--                                    <div class="space-y-4">--}}
{{--                                        <div>--}}
{{--                                            <label class="flex items-center gap-3">--}}
{{--                                                <input--}}
{{--                                                    type="checkbox"--}}
{{--                                                    name="requires_subscription"--}}
{{--                                                    id="requires_subscription"--}}
{{--                                                    x-model="requiresSubscription"--}}
{{--                                                    @change="handleRequiresSubscriptionToggle()"--}}
{{--                                                    value="1"--}}
{{--                                                    {{ old('requires_subscription', isset($service) ? ($service->requires_subscription ?? false) : false) ? 'checked' : '' }}--}}
{{--                                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"--}}
{{--                                                >--}}
{{--                                                <span class="text-sm font-medium text-gray-700">{{ __('Requires Subscription Before This Service') }}</span>--}}
{{--                                            </label>--}}
{{--                                            <p class="mt-1 text-xs text-gray-500">--}}
{{--                                                {{ __('Orders for this service will require a subscription order to be completed first') }}--}}
{{--                                            </p>--}}
{{--                                            @error('requires_subscription')--}}
{{--                                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>--}}
{{--                                            @enderror--}}
{{--                                        </div>--}}

{{--                                        <div x-show="requiresSubscription" x-cloak class="pl-6 border-l-2 border-indigo-200">--}}
{{--                                            <label for="required_subscription_template_key" class="block text-sm font-medium text-gray-700 mb-1.5">--}}
{{--                                                {{ __('Required Subscription Template') }} <span class="text-red-500">*</span>--}}
{{--                                            </label>--}}
{{--                                            <select--}}
{{--                                                name="required_subscription_template_key"--}}
{{--                                                id="required_subscription_template_key"--}}
{{--                                                x-model="requiredSubscriptionTemplate"--}}
{{--                                                :required="requiresSubscription"--}}
{{--                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('required_subscription_template_key') border-red-300 @enderror">--}}
{{--                                                <option value="">{{ __('Select subscription template') }}</option>--}}
{{--                                                <template x-for="[key, label] in Object.entries(subscriptionTemplates)" :key="key">--}}
{{--                                                    <option :value="key" x-text="label"></option>--}}
{{--                                                </template>--}}
{{--                                            </select>--}}
{{--                                            @error('required_subscription_template_key')--}}
{{--                                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>--}}
{{--                                            @enderror--}}
{{--                                            <p class="mt-1 text-xs text-gray-500">{{ __('Template for the required subscription service (e.g., channel_subscribe)') }}</p>--}}
{{--                                        </div>--}}
{{--                                    </div>--}}
{{--                                </div>--}}

                                <div x-show="mode === 'provider'" x-cloak class="mt-4 pt-4 border-t border-gray-200">
                                    <p class="text-xs font-medium text-gray-600 mb-3">{{ __('External Provider Settings') }}</p>

                                    <div class="p-3 bg-blue-50 border border-blue-200 rounded-md mb-4">
                                        <p class="text-xs text-blue-800 flex items-start gap-2">
                                            <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                            </svg>
                                            <span>{{ __('Orders for this service will be sent to the selected external SMM panel via v2 API.') }}</span>
                                        </p>
                                    </div>

                                    <div class="space-y-4">
                                        {{-- Provider select --}}
                                        <div>
                                            <label for="provider" class="block text-sm font-medium text-gray-700 mb-1.5">
                                                {{ __('External Provider') }} <span class="text-red-500">*</span>
                                            </label>
                                            <select
                                                name="provider"
                                                id="provider"
                                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                            >
                                                <option value="">{{ __('Select provider...') }}</option>
                                                @foreach($providerOptions as $code => $label)
                                                    <option value="{{ $code }}" {{ old('provider', $service->provider ?? '') === $code ? 'selected' : '' }}>
                                                        {{ $label }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('provider')
                                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        {{-- Provider Service ID --}}
                                        <div>
                                            <label for="provider_service_id" class="block text-sm font-medium text-gray-700 mb-1.5">
                                                {{ __('Provider Service ID') }} <span class="text-red-500">*</span>
                                            </label>
                                            <input
                                                type="number"
                                                name="provider_service_id"
                                                id="provider_service_id"
                                                value="{{ old('provider_service_id', $service->provider_service_id ?? '') }}"
                                                min="1"
                                                placeholder="{{ __('e.g. 1234') }}"
                                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                            >
                                            @error('provider_service_id')
                                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                            <p class="mt-1 text-xs text-gray-500">{{ __('The service ID on the external panel (find it in their service list)') }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- 3) Rate per 1000 --}}
                            <div class="border border-gray-200 rounded-lg p-5 sm:p-6 bg-white shadow-sm">
                                <h3 class="text-base font-semibold text-gray-900 mb-1 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    {{ __('Pricing') }} & {{ __('Quantity Limits') }}
                                </h3>
                                <p class="text-xs text-gray-500 mb-4">
                                    {{ __('Set the service rate per 1000 units') }}
                                </p>
                                <div class="row">
                                    <div class="col-3">
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
                                    <div class="row col-9">
                                        <div class="col-4">
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
                                        <div class="col-4">
                                            <label for="max_quantity" class="block text-sm font-medium text-gray-700 mb-1.5">
                                                {{ __('Maximum Order') }} <span class="text-red-500">*</span>
                                            </label>
                                            <input type="number"
                                                   name="max_quantity"
                                                   id="max_quantity"
                                                   value="{{ old('max_quantity', isset($service) ? ($service->max_quantity ?? $service->max_order ?? 100) : '100') }}"
                                                   required
                                                   min="100"
                                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('max_quantity') border-red-300 @enderror"
                                                   placeholder="10000">
                                            @error('max_quantity')
                                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        <div  class="col-3">
                                            <label for="overflow_percent" class="block text-sm font-medium text-gray-700 mb-1.5">
                                                {{ __('Overflow (%)') }}
                                            </label>
                                            <input type="number"
                                                   name="overflow_percent"
                                                   id="overflow_percent"
                                                   value="{{ old('overflow_percent', isset($service) ? ($service->overflow_percent ?? '0') : '0') }}"
                                                   min="0"
                                                   max="100"
                                                   step="0.01"
                                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('overflow_percent') border-red-300 @enderror"
                                                   placeholder="0">
                                            @error('overflow_percent')
                                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                            @enderror
                                            <p class="mt-1 text-xs text-gray-500">
                                                {{ __('Extra target % above base quantity (e.g., 15 = 115% of order quantity)') }}
                                            </p>
                                        </div>

                                    </div>

                                    {{-- Overflow percent --}}
                                </div>
                                <div class="row" style="margin-top: 60px">
                                    <h3 class="text-base font-semibold text-gray-900 mb-1 flex items-center gap-2">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                        {{ __('Additional Information') }}
                                    </h3>
                                    <p class="text-xs text-gray-500 mb-4">{{ __('Service description') }}</p>

                                    <div class="mb-4">
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

                                    <div x-show="!categoryIsTelegram && !categoryIsMax" x-cloak>
                                        <label for="description_for_performer" class="block text-sm font-medium text-gray-700 mb-1.5">
                                            {{ __('Description for Performer') }}
                                            <span class="text-xs font-normal text-gray-400">(optional)</span>
                                        </label>
                                        <textarea name="description_for_performer"
                                                  id="description_for_performer"
                                                  rows="3"
                                                  class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('description_for_performer') border-red-300 @enderror"
                                                  placeholder="{{ __('Instructions or notes for the performer...') }}">{{ old('description_for_performer', isset($service) ? $service->description_for_performer : '') }}</textarea>
                                        @error('description_for_performer')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            {{-- 8) Description --}}
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
                categoryIdsWithTemplates: @js($categoryIdsWithTemplates),
                categoryLinkDrivers: @js($categoryLinkDrivers ?? []),
                categoryId: @js(old('category_id', isset($service) ? (string) ($service->category_id ?? '') : '')),
                get categoryHasTemplates() {
                    if (!this.categoryId) return false;
                    return this.categoryIdsWithTemplates.includes(Number(this.categoryId));
                },
                get categoryIsYoutube() {
                    if (!this.categoryId) return false;
                    return (this.categoryLinkDrivers[this.categoryId] || '') === 'youtube';
                },
                get categoryIsApp() {
                    if (!this.categoryId) return false;
                    return (this.categoryLinkDrivers[this.categoryId] || '') === 'app';
                },
                get categoryIsMax() {
                    if (!this.categoryId) return false;
                    return (this.categoryLinkDrivers[this.categoryId] || '') === 'max';
                },
                get categoryIsTelegram() {
                    if (!this.categoryId) return false;
                    return (this.categoryLinkDrivers[this.categoryId] || '') === 'telegram';
                },
                denyDuplicates: @js(old('deny_link_duplicates', isset($service) ? (string) (int) ($service->deny_link_duplicates ?? false) : '0')),
                parsingEnabled: @js(old('start_count_parsing_enabled', isset($service) ? (string) (int) ($service->start_count_parsing_enabled ?? false) : '0')),
                selectedTemplate: @js(old('template_key', isset($service) ? ($service->template_key ?? '') : '')),
                durationDays: @js(old('duration_days', isset($service) ? ($service->duration_days ?? null) : null)),
                serviceName: @js(old('name', isset($service) ? ($service->name ?? '') : '')),
                templatePreview: null,
                templateRequiresDuration: false,
                templateRequiresWatchTime: false,
                watchTimeSeconds: @js(old('watch_time_seconds', isset($service) ? ($service->watch_time_seconds ?? 30) : 30)),
                filteredTemplates: {},
                allTemplates: @js($templatesByTargetType),
                isEditing: @js(isset($service) && !empty($service->template_key)),
                dripfeedEnabled: @json((bool) (int) old('dripfeed_enabled', isset($service) ? ($service->dripfeed_enabled ? 1 : 0) : 0)),
                userCanCancel: @json((bool) (int) old('user_can_cancel', isset($service) ? ($service->user_can_cancel ? 1 : 0) : 0)),
                speedLimitEnabled: @js(old('speed_limit_enabled', isset($service) ? ($service->speed_limit_enabled ?? false) : false)),
                speedLimitTierMode: @js($staffServiceSpeedTierMode),
                speedMultiplierFast: @js(old('speed_multiplier_fast', isset($service) ? ($service->speed_multiplier_fast ?? 1.50) : 1.50)),
                speedMultiplierSuperFast: @js($staffSuperFastMultiplier),
                requiresSubscription: @js(old('requires_subscription', isset($service) ? ($service->requires_subscription ?? false) : false)),
                requiredSubscriptionTemplate: @js(old('required_subscription_template_key', isset($service) ? ($service->required_subscription_template_key ?? '') : '')),
                subscriptionTemplates: {},

                onCategoryChange() {
                    const input = document.querySelector('input[name="category_id"]');
                    const val = input ? input.value : '';
                    this.categoryId = val || '';
                    if (!this.categoryHasTemplates) {
                        this.selectedTemplate = '';
                        this.filteredTemplates = {};
                        this.templatePreview = null;
                        this.templateRequiresDuration = false;
                        this.templateRequiresWatchTime = false;
                        const templateSelect = document.getElementById('template_key');
                        if (templateSelect) templateSelect.value = '';
                    } else {
                        this.loadTemplatesByCategory();
                        if (!this.filteredTemplates[this.selectedTemplate]) {
                            this.selectedTemplate = '';
                            this.templatePreview = null;
                            this.templateRequiresDuration = false;
                            this.templateRequiresWatchTime = false;
                        }
                        const templateSelect = document.getElementById('template_key');
                        if (templateSelect) templateSelect.value = this.selectedTemplate;
                    }
                },

                init() {
                    // Set initial category state and listen for category changes
                    this.$nextTick(() => {
                        this.onCategoryChange();
                        const catInput = document.querySelector('input[name="category_id"]');
                        if (catInput) {
                            catInput.addEventListener('change', () => this.onCategoryChange());
                        }
                        // When editing, ensure template list is loaded for category
                        if (this.categoryHasTemplates) {
                            this.loadTemplatesByCategory();
                        }
                    });

                    // Load template data if template is selected (after filtering)
                    if (this.selectedTemplate) {
                        this.updateTemplateInfo();
                    }

                    // Initialize subscription templates (only subscribe templates)
                    this.updateSubscriptionTemplates();

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

                    this.$watch('speedLimitTierMode', () => this.ensureSuperFastMultiplierDefault());
                    this.$nextTick(() => this.ensureSuperFastMultiplierDefault());
                },

                loadTemplatesByCategory() {
                    if (this.categoryIsYoutube) {
                        this.filteredTemplates = this.allTemplates['youtube'] || {};
                    } else if (this.categoryIsApp) {
                        this.filteredTemplates = this.allTemplates['app'] || {};
                    } else if (this.categoryIsMax) {
                        this.filteredTemplates = this.allTemplates['max'] || {};
                    } else {
                        // Merge all templates for Telegram category (bot + channel)
                        const bot = this.allTemplates['bot'] || {};
                        const channel = this.allTemplates['channel'] || {};
                        this.filteredTemplates = { ...bot, ...channel };
                    }

                    // In edit mode, ensure selected template is in filtered list
                    if (this.isEditing && this.selectedTemplate && !this.filteredTemplates[this.selectedTemplate]) {
                        const allTemplates = @js($serviceTemplates);
                        if (allTemplates[this.selectedTemplate]) {
                            this.filteredTemplates[this.selectedTemplate] = allTemplates[this.selectedTemplate].label || this.selectedTemplate;
                        }
                    }

                    if (!this.isEditing && this.selectedTemplate && !this.filteredTemplates[this.selectedTemplate]) {
                        this.selectedTemplate = '';
                        this.updateTemplateInfo();
                    }
                },

                updateTemplateInfo() {
                    if (!this.selectedTemplate) {
                        this.templatePreview = null;
                        this.templateRequiresDuration = false;
                        this.templateRequiresWatchTime = false;
                        return;
                    }

                    // Fetch template from backend/config
                    const templates = @js($serviceTemplates);
                    const template = templates[this.selectedTemplate];

                    if (template) {
                        this.templatePreview = template;
                        this.templateRequiresDuration = template.requires_duration_days || false;
                        this.templateRequiresWatchTime = template.requires_watch_time || false;

                        // Auto-generate name if service name is empty
                        if (!this.serviceName || this.serviceName.trim() === '') {
                            this.$nextTick(() => {
                                this.generateServiceName();
                            });
                        }
                    }
                },

                handleSpeedLimitToggle() {
                    if (this.speedLimitEnabled && this.dripfeedEnabled) {
                        this.dripfeedEnabled = false;
                    }
                },

                ensureSuperFastMultiplierDefault() {
                    if (this.speedLimitTierMode !== 'super_fast') {
                        return;
                    }
                    const v = Number(this.speedMultiplierSuperFast);
                    if (! Number.isFinite(v) || v <= 1) {
                        this.speedMultiplierSuperFast = 2;
                    }
                },

                updateSubscriptionTemplates() {
                    // Only show subscribe templates for dependency
                    const allTemplates = @js($serviceTemplates);
                    this.subscriptionTemplates = {};
                    for (const [key, template] of Object.entries(allTemplates)) {
                        if (template.action === 'subscribe') {
                            this.subscriptionTemplates[key] = template.label;
                        }
                    }
                },

                handleRequiresSubscriptionToggle() {
                    if (!this.requiresSubscription) {
                        this.requiredSubscriptionTemplate = '';
                    }
                },

                generateServiceName() {
                    if (!this.templatePreview) {
                        return;
                    }

                    const label = this.templatePreview.label || 'Service';

                    // For templates that require duration input, append duration to name
                    if (this.templateRequiresDuration && this.durationDays) {
                        // Remove "(Daily)" from label and append duration
                        const cleanLabel = label.replace(' (Daily)', '');
                        if (cleanLabel.includes('Channel Subscribe')) {
                            this.serviceName = `Telegram Channel Subscribe | ${this.durationDays}d`;
                        } else if (cleanLabel.includes('Group Subscribe')) {
                            this.serviceName = `Telegram Group Subscribe | ${this.durationDays}d`;
                        } else {
                            this.serviceName = `${cleanLabel} ${this.durationDays}d`;
                        }
                    } else {
                        this.serviceName = label;
                    }
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
