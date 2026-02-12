@php
    $isEdit = ($mode ?? 'create') === 'edit';
    $plan = $plan ?? null;

    $initialCategoryId = (string) ($initialCategoryId ?? '');
    $monthlyPrice = (string) ($monthlyPrice ?? '');
    $monthlyEnabled = (bool) ($monthlyEnabled ?? true);
    $initialSelectedServices = $initialSelectedServices ?? [];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $isEdit ? __('Edit Subscription Plan') : __('Create Subscription Plan') }}
            </h2>

            <a href="{{ route('staff.subscriptions.index') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition">
                {{ __('Back to Plans') }}
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

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

            <div
                class="grid grid-cols-1 lg:grid-cols-3 gap-6"
                x-data="subscriptionPlanForm({
                    mode: @js($isEdit ? 'edit' : 'create'),
                    originalCategoryId: @js($isEdit ? (string) ($plan?->category_id ?? '') : ''),
                    initialCategoryId: @js($initialCategoryId),

                    servicesByCategory: @js($servicesByCategory ?? []),
                    initialSelectedServices: @js($initialSelectedServices),

                    planName: @js(old('name', $plan?->name ?? '')),
                    description: @js(old('description', $plan?->description ?? '')),
                    currency: @js(old('currency', $plan?->currency ?? 'USD')),

                    monthlyPrice: @js($monthlyPrice),
                    monthlyEnabled: @js($monthlyEnabled),

                    previewVariant: @js((string) old('preview_variant', $plan?->preview_variant ?? 1)),
                    previewBadge: @js(old('preview_badge', $plan?->preview_badge ?? '')),
                    previewFeatures: @js(old('preview_features', $plan?->preview_features ?? [])),
                })"
                x-init="init()"
            >
                {{-- LEFT: FORM --}}
                <div class="lg:col-span-2">
                    <div class="bg-white shadow-sm sm:rounded-lg">
                        <form method="POST"
                              @submit.prevent="if (validateAllQuantities() && validateMonthlyPrice()) { $el.submit() }"
                              action="{{ $isEdit ? route('staff.subscriptions.update', $plan) : route('staff.subscriptions.store') }}">
                            @csrf
                            @if($isEdit)
                                @method('PUT')
                            @endif

                            <div class="p-6 space-y-6">

                                {{-- Basic Information --}}
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('Basic Information') }}</h3>

                                    <div class="space-y-4">
                                        <div>
                                            <x-input-label for="name" :value="__('Plan Name') . ' *'" />
                                            <x-text-input id="name" name="name" type="text"
                                                          x-model="planName"
                                                          class="mt-1 block w-full"
                                                          required />
                                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                                        </div>

                                        <div>
                                            <x-input-label for="description" :value="__('Description')" />
                                            <textarea id="description" name="description" rows="3"
                                                      class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                                      x-model="description"></textarea>
                                            <x-input-error :messages="$errors->get('description')" class="mt-2" />
                                        </div>

                                        <div>
                                            <x-custom-select
                                                name="category_id"
                                                id="category_id"
                                                :value="old('category_id', $plan?->category_id ?? '')"
                                                :label="__('Category') . ' *'"
                                                placeholder="{{ __('Select a category') }}"
                                                :options="collect($categories)->mapWithKeys(fn($c) => [$c->id => $c->name])->toArray()"
                                            />
                                            <x-input-error :messages="$errors->get('category_id')" class="mt-2" />
                                        </div>

                                        <div>
                                            <x-input-label for="currency" :value="__('Currency') . ' *'" />
                                            <x-text-input id="currency" name="currency" type="text"
                                                          x-model="currency"
                                                          class="mt-1 block w-full" maxlength="3" required />
                                            <x-input-error :messages="$errors->get('currency')" class="mt-2" />
                                        </div>

                                        <div>
                                            <label class="flex items-center">
                                                <input type="checkbox" name="is_active" value="1"
                                                       {{ old('is_active', $plan?->is_active ?? true) ? 'checked' : '' }}
                                                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                                <span class="ml-2 text-sm text-gray-600">{{ __('Active') }}</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                {{-- Pricing (MONTHLY ONLY) --}}
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('Pricing') }}</h3>

                                    <div>
                                        <div class="flex items-center mb-2">
                                            <input type="checkbox"
                                                   id="enable_monthly"
                                                   name="prices[monthly][enabled]"
                                                   value="1"
                                                   x-model="pricesEnabled.monthly"
                                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                            <x-input-label for="enable_monthly" :value="__('Monthly Price')" class="ml-2 mb-0" />
                                        </div>

                                        <input id="price_monthly"
                                               name="prices[monthly][price]"
                                               type="number"
                                               step="0.01"
                                               min="0"
                                               x-model="monthlyPrice"
                                               x-bind:disabled="!pricesEnabled.monthly"
                                               class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                                        <x-input-error :messages="$errors->get('prices.monthly.price')" class="mt-2" />

                                        {{-- client-side monthly price error --}}
                                        <template x-if="priceError">
                                            <div class="mt-2 text-sm text-red-600" x-text="priceError"></div>
                                        </template>

                                        {{-- backend afterValidator error (key=prices) --}}
                                        <x-input-error :messages="$errors->get('prices')" class="mt-2" />
                                    </div>
                                </div>

                                {{-- Preview Settings --}}
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('Preview Settings') }}</h3>

                                    <div class="space-y-4">
                                        <div>
                                            <x-input-label for="preview_variant" :value="__('Preview Variant') . ' *'" />
                                            <select id="preview_variant" name="preview_variant"
                                                    x-model="previewVariant"
                                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                                    required>
                                                <option value="1">Variant 1 - Basic (Light)</option>
                                                <option value="2">Variant 2 - Standard (Blue with Badge)</option>
                                                <option value="3">Variant 3 - Premium (Dark)</option>
                                            </select>
                                            <x-input-error :messages="$errors->get('preview_variant')" class="mt-2" />
                                        </div>

                                        <div>
                                            <x-input-label for="preview_badge" :value="__('Preview Badge')" />
                                            <x-text-input id="preview_badge" name="preview_badge" type="text"
                                                          x-model="previewBadge"
                                                          class="mt-1 block w-full" placeholder="e.g., BEST VALUE" />
                                            <x-input-error :messages="$errors->get('preview_badge')" class="mt-2" />
                                        </div>
                                    </div>
                                </div>

                                {{-- Services --}}
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('Services') }}</h3>

                                    <div x-show="selectedCategoryId">
                                        <div x-show="availableServices.length === 0" class="text-gray-500 text-sm mb-4">
                                            {{ __('No active services available for the selected category.') }}
                                        </div>

                                        <div x-show="availableServices.length > 0">
                                            {{-- Multi-select dropdown --}}
                                            <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                                                <button type="button" @click="open = !open"
                                                        class="w-full bg-white border border-gray-300 rounded-md shadow-sm pl-3 pr-10 py-2 text-left focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
                                                    <span class="block truncate text-sm text-gray-700"
                                                          x-text="selectedServices.length > 0 ? selectedServices.length + ' service(s) selected' : 'Select services'"></span>
                                                    <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                        </svg>
                                                    </span>
                                                </button>

                                                <div x-show="open" x-transition @click.stop
                                                     class="absolute z-50 mt-1 w-full bg-white shadow-lg max-h-72 rounded-md py-2 text-base ring-1 ring-black ring-opacity-5 overflow-auto">
                                                    {{-- Select all --}}
                                                    <div class="px-4 py-2 border-b border-gray-100 sticky top-0 bg-white z-10">
                                                        <label class="flex items-center gap-2 cursor-pointer select-none">
                                                            <input type="checkbox"
                                                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                                                   :checked="availableServices.length > 0 && selectedServices.length === availableServices.length"
                                                                   :indeterminate="selectedServices.length > 0 && selectedServices.length < availableServices.length"
                                                                   @click.stop
                                                                   @change="toggleSelectAll($event.target.checked)">
                                                            <span class="text-sm font-medium text-gray-700">Select all</span>
                                                            <span class="text-xs text-gray-400" x-text="'(' + selectedServices.length + '/' + availableServices.length + ')'"></span>
                                                        </label>
                                                    </div>

                                                    {{-- Services list --}}
                                                    <template x-for="service in availableServices" :key="service.id">
                                                        <label class="flex items-center px-4 py-2 hover:bg-indigo-50 cursor-pointer">
                                                            <input type="checkbox"
                                                                   @click.stop
                                                                   @change="toggleService(service)"
                                                                   :checked="selectedServices.some(s => String(s.id) === String(service.id))"
                                                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                                            <span class="ml-2 text-sm text-gray-700" x-text="service.name"></span>
                                                        </label>
                                                    </template>
                                                </div>
                                            </div>

                                            {{-- Selected services + quantities --}}
                                            <div x-show="selectedServices.length > 0" class="mt-4 space-y-3">
                                                <template x-for="(service, index) in selectedServices" :key="service.id">
                                                    <div class="flex items-center gap-4 p-2 border border-gray-200 rounded-lg bg-gray-50">
                                                        <div class="flex-1 min-w-0">
                                                            <div class="font-medium text-gray-900 truncate" x-text="service.name"></div>
                                                            <input type="hidden" :name="`services[${index}][service_id]`" :value="service.id" />
                                                        </div>

                                                        <div class="w-28">
                                                            <label :for="`quantity_${service.id}`" class="block font-medium text-sm text-gray-700 mb-1">
                                                                {{ __('Qty') }} *
                                                            </label>

                                                            <input
                                                                :id="`quantity_${service.id}`"
                                                                :name="`services[${index}][quantity]`"
                                                                type="number"
                                                                inputmode="numeric"
                                                                :min="service.min_quantity || 1"
                                                                :max="service.max_quantity || null"
                                                                class="block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm h-9 text-sm"
                                                                x-model.number="service.quantity"
                                                                @input="onQuantityInput(service)"
                                                                @blur="onQuantityBlur(service)"
                                                                required
                                                            />

                                                            <div class="text-xs text-gray-600 mb-2">
                                                                <span class="font-medium">Qty:</span>
                                                                <span x-text="'Min: ' + (service?.min_quantity|| 1)"></span>
                                                                <span x-show="service?.max_quantity" x-text="' - Max: ' + (service?.max_quantity || '')"></span>
                                                            </div>
                                                            <template x-if="quantityErrors[String(service.id)]">
                                                                <div class="mt-1 text-xs text-red-600" x-text="quantityErrors[String(service.id)]"></div>
                                                            </template>
                                                        </div>

                                                        <button type="button"
                                                                @click="removeService(service.id)"
                                                                style="margin-top: -20px"
                                                                class="inline-flex items-center justify-center h-8 w-8 rounded-md bg-red-600 text-white hover:bg-red-700 transition"
                                                                title="Remove">
                                                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </div>

                                    <div x-show="!selectedCategoryId" class="text-gray-500 text-sm">
                                        {{ __('Please select a category first to choose services.') }}
                                    </div>

                                    <x-input-error :messages="$errors->get('services')" class="mt-2" />
                                </div>

                                <div class="flex justify-end gap-4">
                                    <a href="{{ route('staff.subscriptions.index') }}" class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700">
                                        Cancel
                                    </a>
                                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                                        {{ $isEdit ? 'Update Plan' : 'Create Plan' }}
                                    </button>
                                </div>

                            </div>
                        </form>
                    </div>
                </div>

                {{-- RIGHT: LIVE PREVIEW --}}
                <div class="lg:col-span-1">
                    <div class="sticky top-6">
                        <div class="bg-white shadow-sm rounded-lg p-4">
                            <h4 class="text-md font-semibold text-gray-900 mb-4">{{ __('Live Preview') }}</h4>

                            <div class="bg-gray-50 p-4 rounded-lg">
                                <div class="max-w-sm mx-auto">
                                    <div>
                                        {{-- Variant 1 --}}
                                        <template x-if="String(previewVariant) === '1'">
                                            <div class="bg-white rounded-lg shadow-md overflow-hidden relative">
                                                <div class="bg-gray-100 px-6 py-4 border-b border-gray-200">
                                                    <h3 class="text-xl font-bold text-gray-900 uppercase" x-text="planName || 'PLAN NAME'"></h3>
                                                </div>

                                                <div class="p-6">
                                                    <div class="mb-6">
                                                        <div class="text-4xl font-bold text-gray-900 mb-1"
                                                             x-text="formattedMonthly()"></div>
                                                        <div class="text-sm text-gray-600">Billed monthly</div>
                                                    </div>

                                                    <template x-if="previewFeatures.filter(f => f).length > 0">
                                                        <ul class="space-y-2 mb-6">
                                                            <template x-for="(feature, index) in previewFeatures.filter(f => f)" :key="index">
                                                                <li class="text-sm"
                                                                    :class="index < 2 ? 'text-gray-900 font-medium' : 'text-gray-400'"
                                                                    x-text="feature"></li>
                                                            </template>
                                                        </ul>
                                                    </template>

                                                    <template x-if="selectedServices.length > 0">
                                                        <div class="mb-6">
                                                            <div class="text-xs font-semibold text-gray-600 uppercase mb-2">Included Services</div>
                                                            <ul class="space-y-2">
                                                                <template x-for="s in selectedServices" :key="s.id">
                                                                    <li class="flex items-center justify-between text-sm">
                                                                        <span class="text-gray-900 truncate pr-2" x-text="s.name"></span>
                                                                        <span class="text-gray-500 text-xs whitespace-nowrap">Qty<span x-text="s.quantity"></span></span>
                                                                    </li>
                                                                </template>
                                                            </ul>
                                                        </div>
                                                    </template>

                                                    <template x-if="selectedServices.length === 0">
                                                        <div class="mb-6 text-sm text-gray-400">No services selected yet.</div>
                                                    </template>

                                                    <button type="button" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded transition">
                                                        Get Started
                                                    </button>
                                                </div>
                                            </div>
                                        </template>

                                        {{-- Variant 2 --}}
                                        <template x-if="String(previewVariant) === '2'">
                                            <div class="bg-blue-600 rounded-lg shadow-lg overflow-hidden relative">
                                                <template x-if="previewBadge">
                                                    <div class="absolute top-0 left-0 bg-yellow-400 text-yellow-900 text-xs font-bold px-4 py-1 transform -rotate-12 -translate-x-1 translate-y-1 shadow-md"
                                                         style="clip-path: polygon(0 0, 100% 0, 85% 100%, 0 100%);"
                                                         x-text="previewBadge"></div>
                                                </template>

                                                <div class="px-6 py-4">
                                                    <h3 class="text-xl font-bold text-white uppercase" x-text="planName || 'PLAN NAME'"></h3>
                                                </div>

                                                <div class="p-6 bg-blue-600">
                                                    <div class="mb-6">
                                                        <div class="text-4xl font-bold text-white mb-1"
                                                             x-text="formattedMonthly()"></div>
                                                        <div class="text-sm text-blue-100">Billed monthly</div>
                                                    </div>

                                                    <template x-if="previewFeatures.filter(f => f).length > 0">
                                                        <ul class="space-y-2 mb-6">
                                                            <template x-for="(feature, index) in previewFeatures.filter(f => f)" :key="index">
                                                                <li class="text-sm text-white"
                                                                    :class="index < 4 ? 'opacity-100' : 'opacity-75'"
                                                                    x-text="feature"></li>
                                                            </template>
                                                        </ul>
                                                    </template>

                                                    <template x-if="selectedServices.length > 0">
                                                        <div class="mb-6">
                                                            <div class="text-xs font-semibold text-blue-100 uppercase mb-2">Included Services</div>
                                                            <ul class="space-y-2">
                                                                <template x-for="s in selectedServices" :key="s.id">
                                                                    <li class="flex items-center justify-between text-sm">
                                                                        <span class="text-white truncate pr-2" x-text="s.name"></span>
                                                                        <span class="text-blue-100 text-xs whitespace-nowrap">Qty<span x-text="s.quantity"></span></span>
                                                                    </li>
                                                                </template>
                                                            </ul>
                                                        </div>
                                                    </template>

                                                    <template x-if="selectedServices.length === 0">
                                                        <div class="mb-6 text-sm text-blue-200">No services selected yet.</div>
                                                    </template>

                                                    <button type="button" class="w-full bg-blue-700 hover:bg-blue-800 text-white font-semibold py-2 px-4 rounded transition">
                                                        Get Started
                                                    </button>
                                                </div>
                                            </div>
                                        </template>

                                        {{-- Variant 3 --}}
                                        <template x-if="String(previewVariant) === '3'">
                                            <div class="bg-blue-900 rounded-lg shadow-lg overflow-hidden relative">
                                                <template x-if="previewBadge">
                                                    <div class="absolute top-0 left-0 bg-yellow-400 text-yellow-900 text-xs font-bold px-4 py-1 transform -rotate-12 -translate-x-1 translate-y-1 shadow-md z-10"
                                                         style="clip-path: polygon(0 0, 100% 0, 85% 100%, 0 100%);"
                                                         x-text="previewBadge"></div>
                                                </template>

                                                <div class="px-6 py-4">
                                                    <h3 class="text-xl font-bold text-white uppercase" x-text="planName || 'PLAN NAME'"></h3>
                                                </div>

                                                <div class="p-6 bg-blue-900">
                                                    <div class="mb-6">
                                                        <div class="text-4xl font-bold text-white mb-1"
                                                             x-text="formattedMonthly()"></div>
                                                        <div class="text-sm text-blue-200">Billed monthly</div>
                                                    </div>

                                                    <template x-if="previewFeatures.filter(f => f).length > 0">
                                                        <ul class="space-y-2 mb-6">
                                                            <template x-for="(feature, index) in previewFeatures.filter(f => f)" :key="index">
                                                                <li class="text-sm text-white" x-text="feature"></li>
                                                            </template>
                                                        </ul>
                                                    </template>

                                                    <template x-if="selectedServices.length > 0">
                                                        <div class="mb-6">
                                                            <div class="text-xs font-semibold text-blue-200 uppercase mb-2">Included Services</div>
                                                            <ul class="space-y-2">
                                                                <template x-for="s in selectedServices" :key="s.id">
                                                                    <li class="flex items-center justify-between text-sm">
                                                                        <span class="text-white truncate pr-2" x-text="s.name"></span>
                                                                        <span class="text-blue-200 text-xs whitespace-nowrap">Qty<span x-text="s.quantity"></span></span>
                                                                    </li>
                                                                </template>
                                                            </ul>
                                                        </div>
                                                    </template>

                                                    <template x-if="selectedServices.length === 0">
                                                        <div class="mb-6 text-sm text-blue-200">No services selected yet.</div>
                                                    </template>

                                                    <button type="button" class="w-full bg-blue-700 hover:bg-blue-800 text-white font-semibold py-2 px-4 rounded transition">
                                                        Get Started
                                                    </button>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                <div class="mt-4 text-xs text-gray-600">
                                    <div>
                                        <span class="font-medium">Services selected:</span>
                                        <span x-text="selectedServices.length"></span>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
                {{-- /RIGHT --}}
            </div>
        </div>
    </div>

    <script>
        function subscriptionPlanForm(payload) {
            return {
                mode: payload.mode,
                originalCategoryId: payload.originalCategoryId || '',
                servicesByCategory: Object.freeze(payload.servicesByCategory || {}),

                selectedCategoryId: payload.initialCategoryId ? String(payload.initialCategoryId) : null,
                availableServices: [],
                selectedServices: Array.isArray(payload.initialSelectedServices) ? payload.initialSelectedServices : [],

                pricesEnabled: { monthly: !!payload.monthlyEnabled },
                priceError: '',

                quantityErrors: {},

                planName: payload.planName || '',
                description: payload.description || '',
                currency: payload.currency || 'USD',
                monthlyPrice: payload.monthlyPrice || '',

                previewVariant: payload.previewVariant ?? '1',
                previewBadge: payload.previewBadge || '',
                previewFeatures: Array.isArray(payload.previewFeatures) ? payload.previewFeatures : [],

                init() {
                    this.$nextTick(() => {
                        const el = document.getElementById('category_id') || document.querySelector('[name="category_id"]');

                        if (el) {
                            const val = el.value;
                            if (val) {
                                this.selectedCategoryId = String(val);
                                this.categoryChanged(val);
                            } else if (this.selectedCategoryId) {
                                this.categoryChanged(this.selectedCategoryId);
                            }

                            el.addEventListener('change', (e) => {
                                const v = e.target.value;
                                this.selectedCategoryId = v ? String(v) : null;
                                this.categoryChanged(v);
                            });
                        } else if (this.selectedCategoryId) {
                            this.categoryChanged(this.selectedCategoryId);
                        }

                        document.addEventListener('filter-value-changed', (e) => {
                            if (e.detail && e.detail.name === 'category_id') {
                                const v = e.detail.value;
                                this.selectedCategoryId = v ? String(v) : null;
                                this.categoryChanged(v);
                            }
                        });

                        // ensure previewVariant default is string
                        this.previewVariant = String(this.previewVariant ?? '1');
                    });
                },

                categoryChanged(categoryId) {
                    if (!categoryId) {
                        this.selectedCategoryId = null;
                        this.availableServices = [];
                        this.selectedServices = [];
                        return;
                    }

                    const key = String(categoryId);
                    this.selectedCategoryId = key;
                    this.availableServices = this.servicesByCategory[key] || [];

                    // edit: changing away from original clears selected
                    if (this.mode === 'edit' && this.originalCategoryId && key !== String(this.originalCategoryId)) {
                        this.selectedServices = [];
                        this.quantityErrors = {};
                        return;
                    }

                    // keep only active/available
                    this.selectedServices = (this.selectedServices || []).filter(s =>
                        this.availableServices.some(a => String(a.id) === String(s.id))
                    );

                    // ensure name/min/max + set default qty if missing
                    this.selectedServices = this.selectedServices.map(s => {
                        const found = this.availableServices.find(a => String(a.id) === String(s.id));
                        const min = found?.min_quantity ?? s.min_quantity ?? 1;
                        return {
                            ...s,
                            name: found ? found.name : (s.name || ''),
                            min_quantity: found?.min_quantity ?? s.min_quantity ?? 1,
                            max_quantity: found?.max_quantity !== undefined ? found.max_quantity : (s.max_quantity ?? null),
                            quantity: (s.quantity ?? min),
                        };
                    });
                },

                toggleService(service) {
                    const id = String(service.id);
                    const idx = this.selectedServices.findIndex(s => String(s.id) === id);

                    if (idx >= 0) {
                        this.selectedServices.splice(idx, 1);
                        delete this.quantityErrors[id];
                        return;
                    }

                    this.selectedServices.push({
                        id: service.id,
                        name: service.name,
                        quantity: service.min_quantity || 1,
                        min_quantity: service.min_quantity || 1,
                        max_quantity: service.max_quantity || null
                    });
                },

                toggleSelectAll(checked) {
                    if (checked) {
                        this.selectedServices = this.availableServices.map(s => ({
                            id: s.id,
                            name: s.name,
                            quantity: s.min_quantity || 1,
                            min_quantity: s.min_quantity || 1,
                            max_quantity: s.max_quantity || null
                        }));
                        this.quantityErrors = {};
                    } else {
                        this.selectedServices = [];
                        this.quantityErrors = {};
                    }
                },

                removeService(serviceId) {
                    const id = String(serviceId);
                    const idx = this.selectedServices.findIndex(s => String(s.id) === id);
                    if (idx >= 0) this.selectedServices.splice(idx, 1);
                    delete this.quantityErrors[id];
                },

                onQuantityInput(service) {
                    const id = String(service.id);
                    if (service.quantity === '' || service.quantity === null || service.quantity === undefined) {
                        this.quantityErrors[id] = 'Quantity is required';
                        return;
                    }

                    const q = Number(service.quantity);
                    if (!Number.isFinite(q) || q <= 0) {
                        this.quantityErrors[id] = 'Quantity must be a positive number';
                        return;
                    }

                    this.quantityErrors[id] = '';
                },

                onQuantityBlur(service) {
                    const id = String(service.id);

                    const min = Number(service.min_quantity || 1);
                    const max = (service.max_quantity !== null && service.max_quantity !== undefined)
                        ? Number(service.max_quantity)
                        : null;

                    let q = Number(service.quantity);
                    if (!Number.isFinite(q) || q <= 0) q = min;

                    if (q < min) q = min;
                    if (max !== null && Number.isFinite(max) && q > max) q = max;

                    service.quantity = q;

                    if (q < min) {
                        this.quantityErrors[id] = `Min ${min}`;
                        return;
                    }
                    if (max !== null && Number.isFinite(max) && q > max) {
                        this.quantityErrors[id] = `Max ${max}`;
                        return;
                    }

                    this.quantityErrors[id] = '';
                },

                validateAllQuantities() {
                    this.quantityErrors = {};

                    for (const s of (this.selectedServices || [])) {
                        const id = String(s.id);
                        const min = Number(s.min_quantity || 1);
                        const max = (s.max_quantity !== null && s.max_quantity !== undefined) ? Number(s.max_quantity) : null;

                        const q = Number(s.quantity);

                        if (!Number.isFinite(q) || q <= 0) {
                            this.quantityErrors[id] = 'Quantity is required';
                            continue;
                        }
                        if (q < min) {
                            this.quantityErrors[id] = `Min ${min}`;
                            continue;
                        }
                        if (max !== null && Number.isFinite(max) && q > max) {
                            this.quantityErrors[id] = `Max ${max}`;
                            continue;
                        }
                    }

                    return Object.values(this.quantityErrors).filter(Boolean).length === 0;
                },

                validateMonthlyPrice() {
                    // monthly must be enabled + >0
                    this.priceError = '';

                    if (!this.pricesEnabled.monthly) {
                        this.priceError = 'Monthly price must be enabled.';
                        return false;
                    }

                    const v = Number(this.monthlyPrice);
                    if (!Number.isFinite(v) || v <= 0) {
                        this.priceError = 'Monthly price must be greater than 0.';
                        return false;
                    }

                    return true;
                },

                formattedMonthly() {
                    const v = parseFloat(this.monthlyPrice);
                    return Number.isFinite(v) ? (this.currency + ' ' + v.toFixed(2)) : 'N/A';
                }
            }
        }
    </script>
</x-app-layout>
