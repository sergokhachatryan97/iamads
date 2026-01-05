<x-client-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('New Order') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if ($errors->any())
                        <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                            <ul class="list-disc list-inside">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div x-data="orderForm()" x-init="init()">
                        <!-- Tabs -->
                        <div class="mb-6 border-b border-gray-200">
                            <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                                <button
                                    type="button"
                                    @click="orderType = 'single'"
                                    :class="orderType === 'single' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                    {{ __('Single Service Order') }}
                                </button>
                                <button
                                    type="button"
                                    @click="orderType = 'multi'"
                                    :class="orderType === 'multi' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                    {{ __('Multi-Service Order') }}
                                </button>
                            </nav>
                        </div>
                        <!-- Single Service Order Form -->
                        <form method="POST" action="{{ route('client.orders.store') }}"
                              x-show="orderType === 'single'"
                              @submit.prevent="submitForm">
                            @csrf

                            <!-- Category -->
                            <div class="mb-6">
                                <label for="category_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    {{ __('Category') }} <span class="text-red-500">*</span>
                                </label>
                                <select
                                    id="category_id"
                                    name="category_id"
                                    x-model="categoryId"
                                    @change="loadServices"
                                    required
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">{{ __('Select a category') }}</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}" {{ old('category_id', $preselectedCategoryId ?? '') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                                @error('category_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Service -->
                            <div class="mb-6">
                                <label for="service_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    {{ __('Service') }} <span class="text-red-500">*</span>
                                </label>
                                <select
                                    id="service_id"
                                    name="service_id"
                                    x-model="serviceId"
                                    @change="updateServiceInfo"
                                    :disabled="!categoryId || loading"
                                    required
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm disabled:bg-gray-100 disabled:cursor-not-allowed">
                                    <option value="" x-text="loading ? '{{ __('Loading...') }}' : '{{ __('Select a service') }}'"></option>
                                    <template x-for="service in (Array.isArray(services) ? services : [])" :key="'service-' + service.id">
                                        <option :value="service.id" :selected="serviceId == service.id" x-text="service.name"></option>
                                    </template>
                                </select>
                                @error('service_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Targets (Link + Quantity pairs) -->
                            <div class="mb-6">
                                <div class="flex items-center justify-between mb-2">
                                    <label class="block text-sm font-medium text-gray-700">
                                        {{ __('Links & Quantities') }} <span class="text-red-500">*</span>
                                    </label>
                                    <button
                                        type="button"
                                        @click="addTargetRow"
                                        class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-indigo-700 bg-indigo-100 hover:bg-indigo-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                        </svg>
                                        {{ __('Add Link') }}
                                    </button>
                                </div>

                                <div class="space-y-3">
                                    <template x-for="(target, index) in targets" :key="index">
                                        <div class="flex gap-3 items-start">
                                            <div class="flex-1">
                                                <label :for="'targets_' + index + '_link'" class="block text-xs font-medium text-gray-700 mb-1">
                                                    {{ __('Link') }} <span class="text-red-500">*</span>
                                                </label>
                                                <input
                                                    type="text"
                                                    :id="'targets_' + index + '_link'"
                                                    :name="'targets[' + index + '][link]'"
                                                    x-model="target.link"
                                                    @input="target.linkValid = validateTelegramLink(target.link)"
                                                    :class="target.linkValid ? 'border-gray-300' : 'border-red-300'"
                                                    placeholder="https://t.me/username or @username"
                                                    required
                                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                <p x-show="!target.linkValid && target.link" class="mt-1 text-xs text-red-600">
                                                    {{ __('Please enter a valid Telegram link.') }}
                                                </p>
                                            </div>
                                            <div class="w-32">
                                                <label :for="'targets_' + index + '_quantity'" class="block text-xs font-medium text-gray-700 mb-1">
                                                    {{ __('Quantity') }} <span class="text-red-500">*</span>
                                                </label>
                                                <div class="relative">
                                                    <input
                                                        type="number"
                                                        :id="'targets_' + index + '_quantity'"
                                                        :name="'targets[' + index + '][quantity]'"
                                                        x-model.number="target.quantity"
                                                        :min="selectedService?.min_quantity || 1"
                                                        :max="selectedService?.max_quantity || null"
                                                        @keydown.arrow-up.prevent="stepQuantity(+1, index)"
                                                        @keydown.arrow-down.prevent="stepQuantity(-1, index)"
                                                        @wheel.prevent="stepQuantity($event.deltaY > 0 ? -1 : +1, index)"
                                                        @change="target.quantity = adjustQuantityToIncrement(target.quantity, index)"
                                                        @blur="$el.dispatchEvent(new Event('change'))"
                                                        required
                                                        class="no-spinner block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm pr-12">
                                                    <div class="absolute inset-y-0 right-0 flex flex-col justify-center">
                                                        <button
                                                            type="button"
                                                            class="h-5 w-6 rounded-t-md border border-gray-300 bg-gray-50 text-gray-700 hover:bg-gray-100 flex items-center justify-center leading-none"
                                                            @mousedown.prevent
                                                            @click="stepQuantity(+1, index)"
                                                            aria-label="Increase quantity">
                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3">
                                                                <path fill-rule="evenodd" d="M10 6a1 1 0 0 1 .707.293l4 4a1 1 0 1 1-1.414 1.414L10 8.414 6.707 11.707A1 1 0 0 1 5.293 10.293l4-4A1 1 0 0 1 10 6z" clip-rule="evenodd"/>
                                                            </svg>
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="h-5 w-6 rounded-b-md border border-t-0 border-gray-300 bg-gray-50 text-gray-700 hover:bg-gray-100 flex items-center justify-center leading-none"
                                                            @mousedown.prevent
                                                            @click="stepQuantity(-1, index)"
                                                            aria-label="Decrease quantity">
                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3">
                                                                <path fill-rule="evenodd" d="M10 14a1 1 0 0 1-.707-.293l-4-4a1 1 0 1 1 1.414-1.414L10 11.586l3.293-3.293a1 1 0 1 1 1.414 1.414l-4 4A1 1 0 0 1 10 14z" clip-rule="evenodd"/>
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="pt-6">
                                                <button
                                                    type="button"
                                                    @click="removeTargetRow(index)"
                                                    x-show="targets.length > 1"
                                                    class="px-3 py-2 text-sm text-red-600 hover:text-red-800 hover:bg-red-50 rounded-md transition-colors">
                                                    {{ __('Remove') }}
                                                </button>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                                @error('targets')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Service Info -->
                            <div class="mt-4 p-3 bg-gray-50 rounded-md" x-show="selectedService">
                                <div class="text-xs text-gray-600 mb-2">
                                    <span class="font-medium">{{ __('Quantity Rules') }}:</span>
                                    <span x-text="'Min: ' + (selectedService?.min_quantity || 1)"></span>
                                    <span x-show="selectedService?.max_quantity" x-text="' - Max: ' + (selectedService?.max_quantity || '')"></span>
                                    <span x-show="selectedService?.increment > 0" x-text="' (Increment: ' + (selectedService?.increment || 0) + ')'"></span>
                                </div>

                                <!-- Rate Info -->
                                <div class="text-xs text-gray-600 mb-2">
                                    <span class="font-medium">{{ __('Default Rate') }}:</span>
                                    <span>$<span x-text="(selectedService?.default_rate_per_1000 || 0).toFixed(2)"></span> {{ __('per 100') }}</span>
                                </div>

                                <!-- Discount Info -->
                                <div class="text-xs text-blue-600 mb-2" x-show="selectedService?.discount_applies && selectedService?.client_discount > 0">
                                    <span class="font-medium">{{ __('Discount Applied') }}:</span>
                                    <span x-text="selectedService?.client_discount || 0"></span>% {{ __('discount') }}
                                    <span class="text-gray-500">
                                        ({{ __('Final Rate') }}: $<span x-text="(selectedService?.rate_per_1000 || 0).toFixed(2)"></span> {{ __('per 100') }})
                                    </span>
                                </div>

                                <!-- Custom Rate Info -->
                                <div class="text-xs text-green-600 mb-2" x-show="selectedService?.has_custom_rate && selectedService?.custom_rate">
                                    <span class="font-medium">{{ __('Custom Rate Applied') }}:</span>
                                    <span class="text-gray-500 text-xs">({{ __('Discounts do not apply to services with custom rates') }})</span>
                                    <span x-show="selectedService?.custom_rate?.type === 'fixed'">
                                        <br>$<span x-text="(selectedService?.custom_rate?.value || 0).toFixed(2)"></span> {{ __('per 100') }} ({{ __('Fixed') }})
                                    </span>
                                    <span x-show="selectedService?.custom_rate?.type === 'percent'">
                                        <br><span x-text="selectedService?.custom_rate?.value || 0"></span>% {{ __('of default') }}
                                        ({{ __('Default') }}: $<span x-text="(selectedService?.default_rate_per_1000 || 0).toFixed(2)"></span>)
                                        <br>{{ __('Final Rate') }}: $<span x-text="(selectedService?.rate_per_1000 || 0).toFixed(2)"></span> {{ __('per 100') }}
                                    </span>
                                </div>

                                <!-- Final Rate (when no custom rate and no discount) -->
                                <div class="text-xs text-gray-600 mb-2" x-show="!selectedService?.has_custom_rate && !selectedService?.discount_applies">
                                    <span class="font-medium">{{ __('Rate') }}:</span>
                                    <span>$<span x-text="(selectedService?.rate_per_1000 || 0).toFixed(2)"></span> {{ __('per 100') }}</span>
                                </div>
                            </div>

                            <p class="mt-2 text-sm text-gray-700" x-show="selectedService && getTotalQuantity() > 0">
                                {{ __('Total quantity') }}: <span x-text="getTotalQuantity()"></span> |
                                {{ __('Estimated charge') }}: $<span x-text="calculateCharge()"></span>
                            </p>

                            <!-- Submit Button -->
                            <div class="flex items-center justify-end gap-4 mt-6">
                                <a href="{{ route('client.orders.index') }}"
                                   class="text-sm text-gray-600 hover:text-gray-900">
                                    {{ __('Cancel') }}
                                </a>
                                <button
                                    type="submit"
                                    :disabled="submitting"
                                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition ease-in-out duration-150">
                                    <span x-show="!submitting">{{ __('Create Order') }}</span>
                                    <span x-show="submitting">{{ __('Creating...') }}</span>
                                </button>
                            </div>
                        </form>

                        <!-- Multi-Service Order Form -->
                        <form method="POST" action="{{ route('client.orders.multi-store') }}"
                              x-show="orderType === 'multi'"
                              @submit.prevent="submitMultiForm">
                            @csrf

                            <!-- Category -->
                            <div class="mb-6">
                                <label for="multi_category_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    {{ __('Category') }} <span class="text-red-500">*</span>
                                </label>
                                <select
                                    id="multi_category_id"
                                    name="category_id"
                                    x-model="multiCategoryId"
                                    @change="loadMultiServices"
                                    required
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">{{ __('Select a category') }}</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}" {{ old('category_id', $preselectedCategoryId ?? '') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                                @error('category_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Link (Single) -->
                            <div class="mb-6">
                                <label for="multi_link" class="block text-sm font-medium text-gray-700 mb-2">
                                    {{ __('Telegram Link') }} <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="multi_link"
                                    name="link"
                                    x-model="multiLink"
                                    @input="multiLinkValid = validateTelegramLink(multiLink)"
                                    :class="multiLinkValid ? 'border-gray-300' : 'border-red-300'"
                                    placeholder="https://t.me/username or @username"
                                    required
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <p x-show="!multiLinkValid && multiLink" class="mt-1 text-sm text-red-600">
                                    {{ __('Please enter a valid Telegram link.') }}
                                </p>
                                <p class="mt-1 text-xs text-gray-500">
                                    {{ __('Enter a Telegram channel, group, or user link.') }}
                                </p>
                                @error('link')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Services Multi-Select -->
                            <div class="mb-6">
                                <div class="flex items-center justify-between mb-2">
                                    <label class="block text-sm font-medium text-gray-700">
                                        {{ __('Select Services') }} <span class="text-red-500">*</span>
                                    </label>
                                    <button
                                        type="button"
                                        @click="addMultiServiceRow"
                                        class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-indigo-700 bg-indigo-100 hover:bg-indigo-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                        </svg>
                                        {{ __('Add Service') }}
                                    </button>
                                </div>
                                <div x-show="multiLoading" class="text-sm text-gray-500 mb-2">
                                    {{ __('Loading services...') }}
                                </div>
                                <div x-show="!multiLoading && multiServices.length === 0 && multiCategoryId" class="text-sm text-gray-500 mb-2">
                                    {{ __('No active services found for this category.') }}
                                </div>
                                <div class="space-y-3" x-show="!multiLoading && multiServices.length > 0">
                                    <template x-for="(serviceRow, index) in multiSelectedServices" :key="index">
                                        <div class="flex gap-3 items-start p-3 bg-gray-50 rounded-md border border-gray-200">
                                            <div class="flex-1">
                                                <label :for="'multi_service_' + index" class="block text-sm font-medium text-gray-700 mb-1">
                                                    {{ __('Service') }} <span class="text-red-500">*</span>
                                                </label>
                                                <select
                                                    :id="'multi_service_' + index"
                                                    :name="'services[' + index + '][service_id]'"
                                                    x-model="serviceRow.service_id"
                                                    @change="updateMultiServiceInfo(index)"
                                                    required
                                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                    <option value="">{{ __('Select a service') }}</option>
                                                    <template x-for="service in (Array.isArray(multiServices) ? multiServices : [])" :key="'multi-service-' + service.id">
                                                        <option :value="service.id" x-text="service.name"></option>
                                                    </template>
                                                </select>
                                            </div>
                                            <div class="w-32">
                                                <label :for="'multi_qty_' + index" class="block text-sm font-medium text-gray-700 mb-1">
                                                    {{ __('Quantity') }} <span class="text-red-500">*</span>
                                                </label>
                                                <div class="relative">
                                                    <input
                                                        type="number"
                                                        :id="'multi_qty_' + index"
                                                        :name="'services[' + index + '][quantity]'"
                                                        x-model.number="serviceRow.quantity"
                                                        :min="serviceRow.min_quantity || 1"
                                                        :max="serviceRow.max_quantity || null"
                                                        @keydown.arrow-up.prevent="stepMultiQuantity(+1, index)"
                                                        @keydown.arrow-down.prevent="stepMultiQuantity(-1, index)"
                                                        @wheel.prevent="stepMultiQuantity($event.deltaY > 0 ? -1 : +1, index)"
                                                        @change="serviceRow.quantity = adjustMultiQuantityToIncrement(serviceRow.quantity, index)"
                                                        @blur="$el.dispatchEvent(new Event('change'))"
                                                        required
                                                        class="no-spinner block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm pr-12">
                                                    <div class="absolute inset-y-0 right-0 flex flex-col justify-center">
                                                        <button
                                                            type="button"
                                                            class="h-5 w-6 rounded-t-md border border-gray-300 bg-gray-50 text-gray-700 hover:bg-gray-100 flex items-center justify-center leading-none"
                                                            @mousedown.prevent
                                                            @click="stepMultiQuantity(+1, index)"
                                                            aria-label="Increase quantity">
                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3">
                                                                <path fill-rule="evenodd" d="M10 6a1 1 0 0 1 .707.293l4 4a1 1 0 1 1-1.414 1.414L10 8.414 6.707 11.707A1 1 0 0 1 5.293 10.293l4-4A1 1 0 0 1 10 6z" clip-rule="evenodd"/>
                                                            </svg>
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="h-5 w-6 rounded-b-md border border-t-0 border-gray-300 bg-gray-50 text-gray-700 hover:bg-gray-100 flex items-center justify-center leading-none"
                                                            @mousedown.prevent
                                                            @click="stepMultiQuantity(-1, index)"
                                                            aria-label="Decrease quantity">
                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3">
                                                                <path fill-rule="evenodd" d="M10 14a1 1 0 0 1-.707-.293l-4-4a1 1 0 1 1 1.414-1.414L10 11.586l3.293-3.293a1 1 0 1 1 1.414 1.414l-4 4A1 1 0 0 1 10 14z" clip-rule="evenodd"/>
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="w-32 pt-6">
                                                <button
                                                    type="button"
                                                    @click="removeMultiServiceRow(index)"
                                                    x-show="multiSelectedServices.length > 1"
                                                    class="px-3 py-2 text-sm text-red-600 hover:text-red-800 hover:bg-red-50 rounded-md transition-colors">
                                                    {{ __('Remove') }}
                                                </button>
                                            </div>
                                            <div class="w-32 pt-6" x-show="serviceRow.service_id">
                                                <div class="text-xs text-gray-600">
                                                    <div>{{ __('Charge') }}: $<span x-text="calculateMultiServiceCharge(index).toFixed(2)"></span></div>
                                                    <div class="mt-1 text-gray-500">
                                                        <span>{{ __('Min') }}: <span x-text="serviceRow.min_quantity || 1"></span></span>
                                                        <span x-show="serviceRow.max_quantity"> | {{ __('Max') }}: <span x-text="serviceRow.max_quantity"></span></span>
                                                        <span x-show="serviceRow.increment > 0"> | {{ __('Inc') }}: <span x-text="serviceRow.increment"></span></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                                @error('services')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Total Preview -->
                            <div class="mb-6 p-4 bg-indigo-50 rounded-lg border border-indigo-200" x-show="multiSelectedServices.length > 0">
                                <div class="flex justify-between items-center">
                                    <span class="font-medium text-gray-900">{{ __('Total Charge') }}:</span>
                                    <span class="text-xl font-bold text-indigo-600">$<span x-text="calculateMultiTotalCharge().toFixed(2)"></span></span>
                                </div>
                                <div class="mt-2 text-sm text-gray-600">
                                    <span x-text="multiSelectedServices.length"></span> {{ __('service(s) selected') }}
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="flex items-center justify-end gap-4 mt-6">
                                <a href="{{ route('client.orders.index') }}"
                                   class="text-sm text-gray-600 hover:text-gray-900">
                                    {{ __('Cancel') }}
                                </a>
                                <button
                                    type="submit"
                                    :disabled="submitting || multiSelectedServices.length === 0"
                                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition ease-in-out duration-150">
                                    <span x-show="!submitting">{{ __('Create Orders') }}</span>
                                    <span x-show="submitting">{{ __('Creating...') }}</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function orderForm() {
            return {
                orderType: 'single', // 'single' or 'multi'
                // Single service order data
                categoryId: '{{ old('category_id', $preselectedCategoryId ?? '') }}',
                serviceId: '{{ old('service_id', $preselectedServiceId ?? '') }}',
                services: [],
                selectedService: null,
                targets: @js(old('targets') ?: [['link' => '', 'quantity' => 1]]),
                loading: false,
                submitting: false,
                // Multi-service order data
                multiCategoryId: '{{ old('category_id', $preselectedCategoryId ?? '') }}',
                multiLink: '{{ old('link', '') }}',
                multiLinkValid: true,
                multiServices: [],
                multiSelectedServices: [{ service_id: '', quantity: 1, min_quantity: 1, max_quantity: null, increment: 0, rate_per_1000: 0 }],
                multiLoading: false,

                init() {
                    // Ensure services is always an array
                    if (!Array.isArray(this.services)) {
                        this.services = [];
                    }
                    if (!Array.isArray(this.multiServices)) {
                        this.multiServices = [];
                    }
                    if (!Array.isArray(this.multiSelectedServices)) {
                        this.multiSelectedServices = [];
                    }
                    if (this.categoryId) {
                        this.loadServices().then(() => {
                            if (this.serviceId && this.services.length > 0) {
                                this.$nextTick(() => {
                                    this.updateServiceInfo();
                                });
                            }
                        });
                    }
                    if (this.multiCategoryId) {
                        this.loadMultiServices();
                    }
                    // Ensure at least one target row exists
                    if (!this.targets || this.targets.length === 0) {
                        this.targets = [{ link: '', quantity: 1, linkValid: true }];
                    } else {
                        this.targets.forEach((target, index) => {
                            if (target.linkValid === undefined) {
                                target.linkValid = !target.link || this.validateTelegramLink(target.link);
                            }
                            if (!target.quantity || target.quantity < 1) {
                                target.quantity = 1;
                            }
                        });
                    }
                    // Validate multi link on init
                    if (this.multiLink) {
                        this.multiLinkValid = this.validateTelegramLink(this.multiLink);
                    }
                },

                validateTelegramLink(link) {
                    if (!link || link.trim() === '') {
                        return true;
                    }
                    const regex = /^(https?:\/\/)?(t\.me|telegram\.me|telegram\.dog)\/([A-Za-z0-9_+\/\-]+(\?[A-Za-z0-9=&_%\-]+)?)$|^@[A-Za-z0-9_]{5,32}$/i;
                    return regex.test(link.trim());
                },

                // Single service order methods
                async loadServices() {
                    if (!this.categoryId) {
                        this.services = [];
                        return Promise.resolve();
                    }

                    this.loading = true;
                    try {
                        const response = await fetch(`{{ route('client.orders.services.by-category') }}?category_id=${this.categoryId}`);
                        const data = await response.json();
                        this.services = Array.isArray(data) ? data : [];
                        // Clear selected service if category changed
                        if (this.serviceId && !this.services.find(s => s.id == this.serviceId)) {
                            this.serviceId = '';
                            this.selectedService = null;
                        }
                    } catch (error) {
                        console.error('Error loading services:', error);
                        this.services = [];
                    } finally {
                        this.loading = false;
                    }
                },

                updateServiceInfo() {
                    if (!this.serviceId) {
                        this.selectedService = null;
                        return;
                    }
                    this.selectedService = this.services.find(s => s.id == this.serviceId) || null;
                    // Update default quantity for all targets
                    if (this.selectedService) {
                        const defaultQty = this.selectedService.increment > 0
                            ? this.selectedService.increment
                            : (this.selectedService.min_quantity || 1);
                        this.targets.forEach(target => {
                            if (!target.quantity || target.quantity < 1) {
                                target.quantity = defaultQty;
                            }
                        });
                    }
                },

                addTargetRow() {
                    const defaultQty = this.getMinQuantity();
                    this.targets.push({ link: '', quantity: defaultQty, linkValid: true });
                },

                removeTargetRow(index) {
                    if (this.targets.length > 1) {
                        this.targets.splice(index, 1);
                    }
                },

                getTotalQuantity() {
                    return this.targets.reduce((sum, target) => {
                        return sum + (parseInt(target.quantity) || 0);
                    }, 0);
                },

                getMinQuantity() {
                    if (!this.selectedService) {
                        return 1;
                    }
                    return this.selectedService.min_quantity || 1;
                },

                getStepValue() {
                    if (!this.selectedService) {
                        return 1;
                    }
                    const increment = this.selectedService.increment || 0;
                    return increment > 0 ? increment : 1;
                },

                adjustQuantityToIncrement(value, index) {
                    if (!this.selectedService) return value;

                    const min = Number(this.selectedService.min_quantity || 1);
                    const step = Number(this.selectedService.increment || 0);
                    const max = this.selectedService.max_quantity != null
                        ? Number(this.selectedService.max_quantity)
                        : null;

                    let v = Number(value);
                    if (!Number.isFinite(v) || v <= 0) v = min;
                    v = Math.round(v);

                    if (step <= 0) {
                        v = Math.max(min, v);
                        if (max !== null) v = Math.min(max, v);
                        this.targets[index].quantity = v;
                        return v;
                    }

                    // Allowed values: min OR multiples of step (step, 2*step, 3*step...)
                    if (v <= min) {
                        v = min;
                    } else if (v < step) {
                        v = step; // between min and first step => go to step
                    } else {
                        // Round to nearest multiple of step
                        const remainder = v % step;
                        if (remainder === 0) {
                            // Already a multiple, keep it
                        } else {
                            // Round up to next multiple
                            v = Math.ceil(v / step) * step;
                        }
                    }

                    if (max !== null) v = Math.min(max, v);
                    this.targets[index].quantity = v;
                    return v;
                },

                stepQuantity(direction, index) {
                    if (!this.selectedService) return;

                    const min = Number(this.selectedService.min_quantity || 1);
                    const step = Number(this.selectedService.increment || 0);
                    const max = this.selectedService.max_quantity != null
                        ? Number(this.selectedService.max_quantity)
                        : null;

                    let v = Number(this.targets[index].quantity ?? min);
                    if (!Number.isFinite(v) || v <= 0) v = min;
                    v = Math.round(v);

                    if (step <= 0) {
                        // No increment: just add/subtract 1
                        v = direction > 0 ? v + 1 : v - 1;
                        v = Math.max(min, v);
                        if (max !== null) v = Math.min(max, v);
                        this.targets[index].quantity = v;
                        return;
                    }

                    // Sequence: min -> step -> 2*step -> 3*step -> ...
                    if (direction > 0) {
                        // Increment: 10 -> 50 -> 100 -> 150
                        if (v < min) {
                            v = min;
                        } else if (v === min) {
                            v = step;
                        } else {
                            v = v + step;
                        }
                    } else {
                        // Decrement: 150 -> 100 -> 50 -> 10
                        if (v <= min) {
                            v = min;
                        } else if (v <= step) {
                            v = min; // step -> min
                        } else {
                            v = v - step;
                        }
                    }

                    if (max !== null) v = Math.min(max, v);
                    this.targets[index].quantity = v;
                    // Trigger change event to adjust to increment
                    this.$nextTick(() => {
                        this.targets[index].quantity = this.adjustQuantityToIncrement(v, index);
                    });
                },

                calculateCharge() {
                    if (!this.selectedService) return '0.00';
                    const totalQty = this.getTotalQuantity();
                    const rate = Number(this.selectedService.rate_per_1000) || 0;
                    return (Math.round((totalQty / 100) * rate * 100) / 100).toFixed(2);
                },

                submitForm(event) {
                    this.submitting = true;
                    const form = event?.target?.closest('form') || this.$el.querySelector('form[action="{{ route('client.orders.store') }}"]');
                    if (form) {
                        form.submit();
                    }
                },

                // Multi-service order methods
                async loadMultiServices() {
                    if (!this.multiCategoryId) {
                        this.multiServices = [];
                        this.multiSelectedServices = [];
                        return;
                    }

                    this.multiLoading = true;
                    try {
                        const response = await fetch(`{{ route('client.orders.services.by-category') }}?category_id=${this.multiCategoryId}`);
                        const data = await response.json();
                        this.multiServices = Array.isArray(data) ? data : [];
                        // Clear selected services if category changed
                        this.multiSelectedServices = [];
                    } catch (error) {
                        console.error('Error loading services:', error);
                        this.multiServices = [];
                    } finally {
                        this.multiLoading = false;
                    }
                },

                addMultiServiceRow() {
                    this.multiSelectedServices.push({
                        service_id: '',
                        quantity: 1,
                        min_quantity: 1,
                        max_quantity: null,
                        increment: 0,
                        rate_per_1000: 0,
                    });
                },

                removeMultiServiceRow(index) {
                    if (this.multiSelectedServices.length > 1) {
                        this.multiSelectedServices.splice(index, 1);
                    }
                },

                updateMultiServiceInfo(index) {
                    const serviceRow = this.multiSelectedServices[index];
                    if (!serviceRow.service_id) {
                        return;
                    }
                    const service = this.multiServices.find(s => s.id == serviceRow.service_id);
                    if (service) {
                        serviceRow.min_quantity = service.min_quantity || 1;
                        serviceRow.max_quantity = service.max_quantity || null;
                        serviceRow.increment = service.increment || 0;
                        serviceRow.rate_per_1000 = service.rate_per_1000 || 0;
                        // Set default quantity
                        const defaultQty = service.increment > 0
                            ? service.increment
                            : (service.min_quantity || 1);
                        serviceRow.quantity = defaultQty;
                    }
                },

                adjustMultiQuantityToIncrement(value, index) {
                    const serviceRow = this.multiSelectedServices[index];
                    if (!serviceRow || !serviceRow.service_id) return value;

                    const min = Number(serviceRow.min_quantity || 1);
                    const step = Number(serviceRow.increment || 0);
                    const max = serviceRow.max_quantity != null ? Number(serviceRow.max_quantity) : null;

                    let v = Number(value);
                    if (!Number.isFinite(v) || v <= 0) v = min;
                    v = Math.round(v);

                    if (step <= 0) {
                        v = Math.max(min, v);
                        if (max !== null) v = Math.min(max, v);
                        serviceRow.quantity = v;
                        return v;
                    }

                    // Allowed values: min OR multiples of step (step, 2*step, 3*step...)
                    if (v <= min) {
                        v = min;
                    } else if (v < step) {
                        v = step; // between min and first step => go to step
                    } else {
                        // Round to nearest multiple of step
                        const remainder = v % step;
                        if (remainder === 0) {
                            // Already a multiple, keep it
                        } else {
                            // Round up to next multiple
                            v = Math.ceil(v / step) * step;
                        }
                    }

                    if (max !== null) v = Math.min(max, v);
                    serviceRow.quantity = v;
                    return v;
                },

                stepMultiQuantity(direction, index) {
                    const serviceRow = this.multiSelectedServices[index];
                    if (!serviceRow || !serviceRow.service_id) return;

                    const min = Number(serviceRow.min_quantity || 1);
                    const step = Number(serviceRow.increment || 0);
                    const max = serviceRow.max_quantity != null ? Number(serviceRow.max_quantity) : null;

                    let v = Number(serviceRow.quantity ?? min);
                    if (!Number.isFinite(v) || v <= 0) v = min;
                    v = Math.round(v);

                    if (step <= 0) {
                        // No increment: just add/subtract 1
                        v = direction > 0 ? v + 1 : v - 1;
                        v = Math.max(min, v);
                        if (max !== null) v = Math.min(max, v);
                        serviceRow.quantity = v;
                        return;
                    }

                    // Sequence: min -> step -> 2*step -> 3*step -> ...
                    if (direction > 0) {
                        // Increment: 10 -> 50 -> 100 -> 150
                        if (v < min) {
                            v = min;
                        } else if (v === min) {
                            v = step;
                        } else {
                            v = v + step;
                        }
                    } else {
                        // Decrement: 150 -> 100 -> 50 -> 10
                        if (v <= min) {
                            v = min;
                        } else if (v <= step) {
                            v = min; // step -> min
                        } else {
                            v = v - step;
                        }
                    }

                    if (max !== null) v = Math.min(max, v);
                    serviceRow.quantity = v;
                    // Trigger change event to adjust to increment
                    this.$nextTick(() => {
                        serviceRow.quantity = this.adjustMultiQuantityToIncrement(v, index);
                    });
                },

                calculateMultiServiceCharge(index) {
                    const serviceRow = this.multiSelectedServices[index];
                    if (!serviceRow || !serviceRow.service_id) return 0;
                    const qty = Number(serviceRow.quantity) || 0;
                    const rate = Number(serviceRow.rate_per_1000) || 0;
                    return Math.round((qty / 100) * rate * 100) / 100;
                },

                calculateMultiTotalCharge() {
                    return this.multiSelectedServices.reduce((sum, serviceRow) => {
                        if (!serviceRow.service_id) return sum;
                        const qty = Number(serviceRow.quantity) || 0;
                        const rate = Number(serviceRow.rate_per_1000) || 0;
                        const charge = Math.round((qty / 100) * rate * 100) / 100;
                        return sum + charge;
                    }, 0);
                },

                submitMultiForm(event) {
                    if (this.multiSelectedServices.length === 0) {
                        alert('{{ __('Please select at least one service.') }}');
                        return;
                    }

                    if (!this.multiLinkValid) {
                        alert('{{ __('Please enter a valid Telegram link.') }}');
                        return;
                    }

                    this.submitting = true;
                    const form = event?.target?.closest('form') || this.$el.querySelector('form[action="{{ route('client.orders.multi-store') }}"]');
                    if (form) {
                        form.submit();
                    } else {
                        // Fallback: try to find form by x-show condition
                        const forms = this.$el.querySelectorAll('form');
                        for (let f of forms) {
                            if (f.action && f.action.includes('multi-store')) {
                                f.submit();
                                return;
                            }
                        }
                        console.error('Could not find multi-store form');
                    }
                }
            }
        }
    </script>
    <style>
        /* Chrome, Safari, Edge */
        .no-spinner::-webkit-outer-spin-button,
        .no-spinner::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        /* Firefox */
        .no-spinner {
            -moz-appearance: textfield;
        }
    </style>

</x-client-layout>
