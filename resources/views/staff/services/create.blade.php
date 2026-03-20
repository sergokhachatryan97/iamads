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

    <div class="py-6">
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

                                    {{-- Template Preview (read-only) --}}
{{--                                    <div x-show="selectedTemplate" x-cloak class="mt-4 p-4 bg-indigo-50 border border-indigo-200 rounded-md">--}}
{{--                                        <h4 class="text-sm font-semibold text-indigo-900 mb-3">{{ __('Template Configuration Preview') }}</h4>--}}
{{--                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">--}}
{{--                                            <div>--}}
{{--                                                <span class="font-medium text-gray-700">{{ __('Action') }}:</span>--}}
{{--                                                <span class="text-gray-900" x-text="templatePreview?.action || 'N/A'"></span>--}}
{{--                                            </div>--}}
{{--                                            <div>--}}
{{--                                                <span class="font-medium text-gray-700">{{ __('Policy Key') }}:</span>--}}
{{--                                                <span class="text-gray-900" x-text="templatePreview?.policy_key || 'N/A'"></span>--}}
{{--                                            </div>--}}
{{--                                            <div>--}}
{{--                                                <span class="font-medium text-gray-700">{{ __('Allowed Link Kinds') }}:</span>--}}
{{--                                                <span class="text-gray-900" x-text="templatePreview?.allowed_link_kinds?.join(', ') || 'N/A'"></span>--}}
{{--                                            </div>--}}
{{--                                            <div>--}}
{{--                                                <span class="font-medium text-gray-700">{{ __('Allowed Peer Types') }}:</span>--}}
{{--                                                <span class="text-gray-900" x-text="templatePreview?.allowed_peer_types?.join(', ') || 'N/A'"></span>--}}
{{--                                            </div>--}}
{{--                                            <div>--}}
{{--                                                <span class="font-medium text-gray-700">{{ __('Requires Start Param') }}:</span>--}}
{{--                                                <span class="text-gray-900" x-text="templatePreview?.requires_start_param ? 'Yes' : 'No'"></span>--}}
{{--                                            </div>--}}
{{--                                            <div x-show="templatePreview?.requires_duration_days">--}}
{{--                                                <span class="font-medium text-gray-700">{{ __('Requires Duration Days') }}:</span>--}}
{{--                                                <span class="text-gray-900">Yes</span>--}}
{{--                                            </div>--}}
{{--                                            <div x-show="templatePreview?.requires_watch_time">--}}
{{--                                                <span class="font-medium text-gray-700">{{ __('Requires Watch Time') }}:</span>--}}
{{--                                                <span class="text-gray-900">Yes (seconds)</span>--}}
{{--                                            </div>--}}
{{--                                        </div>--}}
{{--                                    </div>--}}

                                    {{-- Manual mode only fields --}}
                                    <div x-show="mode === 'manual'" x-cloak class="mt-4 pt-4 border-t border-gray-200">
                                        <p class="text-xs font-medium text-gray-600 mb-3">{{ __('Manual Mode Settings') }}</p>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                            {{-- Service type --}}
{{--                                            <div>--}}
{{--                                                <x-custom-select--}}
{{--                                                    name="service_type"--}}
{{--                                                    id="service_type"--}}
{{--                                                    :value="old('service_type', isset($service) ? ($service->service_type ?? 'default') : 'default')"--}}
{{--                                                    :label="__('Service type')"--}}
{{--                                                    placeholder="{{ __('Select service type') }}"--}}
{{--                                                    :options="$serviceTypeOptions"--}}
{{--                                                />--}}
{{--                                                @error('service_type')--}}
{{--                                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>--}}
{{--                                                @enderror--}}
{{--                                            </div>--}}

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
                                                {{ __('Allow clients to choose speed tier (normal/fast/super_fast) with price multipliers') }}
                                            </p>
                                            <p class="mt-1 text-xs text-gray-500" x-show="dripfeedEnabled" x-cloak>
                                                {{ __('Disabled (dripfeed enabled)') }}
                                            </p>
                                            @error('speed_limit_enabled')
                                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div x-show="speedLimitEnabled" x-cloak class="grid grid-cols-1 md:grid-cols-2 gap-4 pl-6 border-l-2 border-indigo-200">
                                            <div>
                                                <label for="speed_multiplier_fast" class="block text-sm font-medium text-gray-700 mb-1.5">
                                                    {{ __('Fast Multiplier') }} <span class="text-red-500">*</span>
                                                </label>
                                                <input
                                                    type="number"
                                                    name="speed_multiplier_fast"
                                                    id="speed_multiplier_fast"
                                                    x-model.number="speedMultiplierFast"
                                                    value="{{ old('speed_multiplier_fast', isset($service) ? ($service->speed_multiplier_fast ?? 1.50) : 1.50) }}"
                                                    :required="speedLimitEnabled"
                                                    min="1"
                                                    max="10"
                                                    step="0.01"
                                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('speed_multiplier_fast') border-red-300 @enderror"
                                                    placeholder="1.50">
                                                @error('speed_multiplier_fast')
                                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                                @enderror
                                                <p class="mt-1 text-xs text-gray-500">{{ __('Price multiplier for fast tier (e.g., 1.50 = 50% increase)') }}</p>
                                            </div>

                                            <div>
                                                <label for="speed_multiplier_super_fast" class="block text-sm font-medium text-gray-700 mb-1.5">
                                                    {{ __('Super Fast Speed Multiplier') }} <span class="text-red-500">*</span>
                                                </label>
                                                <input
                                                    type="number"
                                                    name="speed_multiplier_super_fast"
                                                    id="speed_multiplier_super_fast"
                                                    x-model.number="speedMultiplierSuperFast"
                                                    value="{{ old('speed_multiplier_super_fast', isset($service) ? ($service->speed_multiplier_super_fast ?? 2.00) : 2.00) }}"
                                                    :required="speedLimitEnabled"
                                                    min="1"
                                                    max="10"
                                                    step="0.01"
                                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('speed_multiplier_super_fast') border-red-300 @enderror"
                                                    placeholder="2.00">
                                                @error('speed_multiplier_super_fast')
                                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                                @enderror
                                                <p class="mt-1 text-xs text-gray-500">{{ __('Execution speed multiplier for super fast tier (e.g., 2.00 = 2x faster)') }}</p>
                                            </div>

                                            {{-- Rate Multipliers (for pricing) --}}
                                            <div class="col-span-2 mt-4 pt-4 border-t border-gray-200">
                                                <p class="text-xs font-medium text-gray-600 mb-3">{{ __('Rate Multipliers (Pricing)') }}</p>
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                    <div>
                                                        <label for="rate_multiplier_fast" class="block text-sm font-medium text-gray-700 mb-1.5">
                                                            {{ __('Fast Rate Multiplier') }} <span class="text-red-500">*</span>
                                                        </label>
                                                        <input
                                                            type="number"
                                                            name="rate_multiplier_fast"
                                                            id="rate_multiplier_fast"
                                                            x-model.number="rateMultiplierFast"
                                                            value="{{ old('rate_multiplier_fast', isset($service) ? ($service->rate_multiplier_fast ?? 1.000) : 1.000) }}"
                                                            :required="speedLimitEnabled"
                                                            :disabled="!speedLimitEnabled"
                                                            min="1"
                                                            max="10"
                                                            step="0.001"
                                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('rate_multiplier_fast') border-red-300 @enderror"
                                                            :class="!speedLimitEnabled ? 'opacity-50 cursor-not-allowed' : ''"
                                                            placeholder="1.000">
                                                        @error('rate_multiplier_fast')
                                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                                        @enderror
                                                        <p class="mt-1 text-xs text-gray-500">{{ __('Price multiplier for fast tier (e.g., 1.500 = 50% increase)') }}</p>
                                                    </div>

                                                    <div>
                                                        <label for="rate_multiplier_super_fast" class="block text-sm font-medium text-gray-700 mb-1.5">
                                                            {{ __('Super Fast Rate Multiplier') }} <span class="text-red-500">*</span>
                                                        </label>
                                                        <input
                                                            type="number"
                                                            name="rate_multiplier_super_fast"
                                                            id="rate_multiplier_super_fast"
                                                            x-model.number="rateMultiplierSuperFast"
                                                            value="{{ old('rate_multiplier_super_fast', isset($service) ? ($service->rate_multiplier_super_fast ?? 1.000) : 1.000) }}"
                                                            :required="speedLimitEnabled"
                                                            :disabled="!speedLimitEnabled"
                                                            min="1"
                                                            max="10"
                                                            step="0.001"
                                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('rate_multiplier_super_fast') border-red-300 @enderror"
                                                            :class="!speedLimitEnabled ? 'opacity-50 cursor-not-allowed' : ''"
                                                            placeholder="1.000">
                                                        @error('rate_multiplier_super_fast')
                                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                                        @enderror
                                                        <p class="mt-1 text-xs text-gray-500">{{ __('Price multiplier for super fast tier (e.g., 2.000 = 100% increase)') }}</p>
                                                    </div>
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
                                    {{ __('Pricing') }} & {{ __('Quantity Limits') }}
                                </h3>
                                <p class="text-xs text-gray-500 mb-4">
                                    {{ __('Set the service rate per 100 units') }}
                                </p>
                                <div class="row">
                                    <div class="col-3">
                                        <label for="rate_per_1000" class="block text-sm font-medium text-gray-700 mb-1.5">
                                            {{ __('Service Rate') }} <span class="text-red-500">*</span>
                                            <span class="text-xs font-normal text-gray-500">(per 100)</span>
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
                speedMultiplierFast: @js(old('speed_multiplier_fast', isset($service) ? ($service->speed_multiplier_fast ?? 1.50) : 1.50)),
                speedMultiplierSuperFast: @js(old('speed_multiplier_super_fast', isset($service) ? ($service->speed_multiplier_super_fast ?? 2.00) : 2.00)),
                rateMultiplierFast: @js(old('rate_multiplier_fast', isset($service) ? ($service->rate_multiplier_fast ?? 1.000) : 1.000)),
                rateMultiplierSuperFast: @js(old('rate_multiplier_super_fast', isset($service) ? ($service->rate_multiplier_super_fast ?? 1.000) : 1.000)),
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
                },

                loadTemplatesByCategory() {
                    if (this.categoryIsYoutube) {
                        this.filteredTemplates = this.allTemplates['youtube'] || {};
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
