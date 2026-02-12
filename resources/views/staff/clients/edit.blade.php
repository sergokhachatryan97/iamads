<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Edit Client') }}: {{ $client->name }}
            </h2>
            <a href="{{ route('staff.clients.index') }}" class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-white bg-gray-700 border border-gray-300 rounded-md hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors shadow-sm">
                <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                {{ __('Back to Clients') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-[90rem] mx-auto sm:px-6 lg:px-8">
            <!-- Status Messages -->
            <div id="status-message" class="mb-4 hidden">
                <div class="rounded-md p-3 shadow-lg" id="status-message-content">
                    <p class="text-sm font-medium" id="status-message-text"></p>
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg" style="overflow: visible;">
                <div class="p-6 text-gray-900" style="position: relative;">
                    <form method="POST" action="{{ route('staff.clients.update', $client) }}">
                        @csrf
                        @method('PATCH')

                        <!-- Staff Assignment and Personal Discount Row -->
                        <div class="mb-6 pb-6 border-b border-gray-200">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Staff Assignment (Super Admin Only) -->
                                @if(auth()->guard('staff')->check() && auth()->guard('staff')->user()->hasRole('super_admin'))
                                    <div>
                                        <label for="staff_id" class="block text-sm font-medium text-gray-700 mb-2">
                                            {{ __('Assigned Staff Member') }}
                                        </label>

                                        <x-custom-select
                                            name="staff_id"
                                            :value="old('staff_id', $client->staff_id ?? '')"
                                            placeholder="{{ __('No Staff Assigned') }}"
                                            :options="collect($staffMembers)->map(function ($staff) {
                                                return [
                                                    'value' => $staff->id,
                                                    'label' => $staff->name . ' (' . $staff->email . ')',
                                                ];
                                            })->toArray()"
                                        />

                                        @error('staff_id')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                @endif

                                <!-- Personal Discount -->
                                <div>
                                    <label for="discount" class="block text-sm font-medium text-gray-700 mb-2">
                                        {{ __('Personal Discount (%)') }}
                                    </label>
                                    <input
                                        type="number"
                                        id="discount"
                                        name="discount"
                                        value="{{ old('discount', $client->discount) }}"
                                        step="0.01"
                                        min="0"
                                        max="100"
                                        class="block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    />
                                    <p class="mt-1 text-sm text-gray-500">
                                        {{ __('Enter 0 to disable discount, 100 to make all services free. Discounts do not apply to services with custom rates.') }}
                                    </p>
                                    @error('discount')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Custom Rates -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                {{ __('Custom Rates per Service') }}
                            </label>
                            <p class="text-sm text-gray-500 mb-3">
                                {{ __('Select services and set custom pricing. Services with custom rates are not affected by the personal discount.') }}
                            </p>

                            <!-- Add Custom Rate Section -->
                            <div class="mb-4 p-4 bg-gray-50 border border-gray-200 rounded-lg" style="position: relative; z-index: 99999;">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    {{ __('Select Services to Add Custom Rates') }}
                                </label>
                                <div class="flex gap-2">
                                    <div class="flex-1 relative" id="custom-rates-selector" style="z-index: 10;"
                                         x-data="{
                                             open: false,
                                             selectedServices: [],
                                             categories: @js($categoriesData),
                                             disabledServiceIds: @js($disabledServiceIds),
                                             searchTerm: '',

                                             get filteredCategories() {
                                                 // Filter out services that are already in use
                                                 let filtered = this.categories.map(cat => ({
                                                     ...cat,
                                                     services: cat.services.filter(s =>
                                                         !this.isDisabled(s.id)
                                                     )
                                                 })).filter(cat => cat.services.length > 0);

                                                 // Apply search filter if search term exists
                                                 if (this.searchTerm) {
                                                     const search = this.searchTerm.toLowerCase();
                                                     filtered = filtered.map(cat => ({
                                                         ...cat,
                                                         services: cat.services.filter(s =>
                                                             s.name.toLowerCase().includes(search)
                                                         )
                                                     })).filter(cat => cat.services.length > 0);
                                                 }

                                                 return filtered;
                                             },

                                             toggleService(serviceId) {
                                                 const idStr = String(serviceId);
                                                 if (this.isDisabled(idStr)) return;
                                                 const index = this.selectedServices.indexOf(idStr);
                                                 if (index > -1) {
                                                     this.selectedServices.splice(index, 1);
                                                 } else {
                                                     this.selectedServices.push(idStr);
                                                 }
                                             },

                                             isSelected(serviceId) {
                                                 return this.selectedServices.includes(String(serviceId));
                                             },

                                             isDisabled(serviceId) {
                                                 return this.disabledServiceIds.includes(String(serviceId));
                                             },

                                             toggleSelectAll() {
                                                 const available = this.getAllAvailableServices();
                                                 const allSelected = available.every(id => this.isSelected(id));

                                                 if (allSelected) {
                                                     available.forEach(id => {
                                                         const index = this.selectedServices.indexOf(id);
                                                         if (index > -1) this.selectedServices.splice(index, 1);
                                                     });
                                                 } else {
                                                     available.forEach(id => {
                                                         if (!this.isSelected(id) && !this.isDisabled(id)) {
                                                             this.selectedServices.push(id);
                                                         }
                                                     });
                                                 }
                                             },

                                             getAllAvailableServices() {
                                                 return this.filteredCategories
                                                     .flatMap(cat => cat.services.map(s => String(s.id)))
                                                     .filter(id => !this.isDisabled(id));
                                             },

                                             isAllSelected() {
                                                 const available = this.getAllAvailableServices();
                                                 return available.length > 0 && available.every(id => this.isSelected(id));
                                             },

                                             getDisplayText() {
                                                 if (this.selectedServices.length === 0) {
                                                     return '{{ __('Choose services...') }}';
                                                 }
                                                 return this.selectedServices.length + ' {{ __('selected') }}';
                                             }
                                         }"
                                         @click.away="open = false">
                                        <button
                                            type="button"
                                            @click="open = !open"
                                            class="w-full flex items-center justify-between px-4 py-2 border border-gray-300 rounded-lg shadow-sm bg-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                        >
                                            <span class="text-gray-700" x-text="getDisplayText()"></span>
                                            <svg class="h-5 w-5 text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" :class="{ 'transform rotate-180': open }">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>

                                        <!-- Dropdown -->
                                        <div
                                            x-show="open"
                                            x-cloak
                                            x-transition:enter="transition ease-out duration-100"
                                            x-transition:enter-start="transform opacity-0 scale-95"
                                            x-transition:enter-end="transform opacity-100 scale-100"
                                            x-transition:leave="transition ease-in duration-75"
                                            x-transition:leave-start="transform opacity-100 scale-100"
                                            x-transition:leave-end="transform opacity-0 scale-95"
                                            class="absolute z-30 mt-2 w-full bg-white border border-gray-300 rounded-lg shadow-lg max-h-96 overflow-y-auto"
                                            style="display: none;"
                                            @click.stop
                                        >
                                            <!-- Search Input -->
                                            <div class="p-2 border-b border-gray-200 sticky top-0 bg-white z-20">
                                                <input
                                                    type="text"
                                                    x-model="searchTerm"
                                                    placeholder="{{ __('Search services...') }}"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                    @click.stop
                                                />
                                            </div>

                                            <!-- Select All Option -->
                                            <div class="p-2 border-b border-gray-200 bg-gray-50 sticky top-[49px] z-20">
                                                <label class="flex items-center cursor-pointer">
                                                    <input
                                                        type="checkbox"
                                                        @change="toggleSelectAll()"
                                                        :checked="isAllSelected()"
                                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                                        @click.stop
                                                    >
                                                    <span class="ml-2 text-sm font-medium text-gray-900">{{ __('Select All') }}</span>
                                                </label>
                                            </div>

                                            <!-- Categories and Services -->
                                            <div class="p-2">
                                                <template x-for="category in filteredCategories" :key="'cat-' + category.id">
                                                    <div class="mb-2">
                                                        <div class="px-2 py-1 text-xs font-semibold text-gray-500 uppercase mt-2 first:mt-0" x-text="category.name"></div>
                                                        <template x-for="service in category.services" :key="'service-' + service.id">
                                                            <label class="flex items-center px-2 py-2 hover:bg-gray-100 rounded cursor-pointer" @click.stop>
                                                                <input
                                                                    type="checkbox"
                                                                    :value="service.id"
                                                                    @change="toggleService(service.id)"
                                                                    :checked="isSelected(service.id)"
                                                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                                                    @click.stop
                                                                >
                                                                <span class="ml-2 text-sm text-gray-700 flex-1" x-text="service.name"></span>
                                                                <span class="ml-auto text-xs text-gray-500" x-text="'$' + parseFloat(service.price || 0).toFixed(2)"></span>
                                                            </label>
                                                        </template>
                                                    </div>
                                                </template>
                                                <div x-show="filteredCategories.length === 0" class="px-2 py-4 text-sm text-gray-500 text-center">
                                                    {{ __('No services found') }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        onclick="addSelectedCustomRates()"
                                        class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                    >
                                        {{ __('Add Selected') }}
                                    </button>
                                </div>
                            </div>

                            <!-- Custom Rates List -->
                            <div id="custom-rates-list" class="border border-gray-300 rounded-lg overflow-hidden relative bg-white">
                                <!-- Responsive wrapper for horizontal scrolling -->
                                <div class="overflow-x-auto">
                                    <div class="min-w-[800px]">
                                        <!-- Table Header -->
                                        <div class="bg-gray-50 border-b border-gray-200 sticky top-0 z-20">
                                            <div class="grid grid-cols-12 gap-2 sm:gap-3 md:gap-4 px-3 sm:px-4 md:px-6 py-3">
                                                <div class="col-span-1 flex items-center gap-1 sm:gap-2 min-w-[60px]">
                                                    <input
                                                        type="checkbox"
                                                        id="select-all-rates"
                                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                                        onchange="toggleSelectAllRates(this.checked)"
                                                    >
                                                    <button
                                                        type="button"
                                                        id="remove-selected-rates"
                                                        onclick="removeSelectedRates()"
                                                        class="px-1.5 sm:px-2 py-1 text-xs font-medium text-white bg-red-600 border border-transparent rounded hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 hidden transition-opacity"
                                                        style="display: none;"
                                                    >
                                                        <svg class="w-3 h-3 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                        </svg>
                                                    </button>
                                                </div>
                                                <div class="col-span-2 text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap min-w-[100px]">{{ __('Category') }}</div>
                                                <div class="col-span-2 text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap min-w-[150px]">{{ __('Service') }}</div>
                                                <div class="col-span-1 text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap min-w-[90px]">{{ __('Service rate') }}</div>
                                                <div class="col-span-3 text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap min-w-[200px]">{{ __('Custom rate') }}</div>
                                                <div class="col-span-2 text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap min-w-[100px]">{{ __('Difference') }}</div>
                                                <div class="col-span-1 text-xs font-semibold text-gray-700 uppercase tracking-wider text-center whitespace-nowrap min-w-[60px]">{{ __('Action') }}</div>
                                            </div>
                                        </div>

                                        <!-- Table Body -->
                                        <div id="custom-rates-rows" class="divide-y divide-gray-200">
                                    @php
                                        $clientRates = is_array($client->rates) ? $client->rates : [];
                                    @endphp
                                    @foreach($categories as $category)
                                        @foreach($category->services as $service)
                                            @php
                                                // Only show active services that are not deleted
                                                if (!$service->is_active || $service->trashed()) {
                                                    continue;
                                                }

                                                $hasCustomRate = isset($clientRates[$service->id]);
                                                if (!$hasCustomRate) continue;

                                                $rateData = $clientRates[$service->id];
                                                $rateType = $rateData['type'] ?? 'fixed';
                                                $rateValue = $rateData['value'] ?? 0;
                                            @endphp
                                            <div
                                                class="custom-rate-row hover:bg-gray-50 transition-colors"
                                                data-service-id="{{ $service->id }}"
                                                data-category-id="{{ $category->id }}"
                                                data-category-name="{{ $category->name }}"
                                                x-data
                                                @change="
                                                    if ($event.target?.name === 'rates[{{ $service->id }}][type]') {
                                                        updatePreview($el);
                                                    }
                                                "
                                            >
                                                <input type="hidden" name="rates[{{ $service->id }}][enabled]" value="1" class="rate-enabled-input">
                                                <input type="hidden" name="rates[{{ $service->id }}][remove]" value="0" class="rate-remove-input">
                                                <div class="grid grid-cols-12 gap-2 sm:gap-3 md:gap-4 items-center px-3 sm:px-4 md:px-6 py-3 sm:py-4">
                                                    <div class="col-span-1 flex items-center justify-start min-w-[60px]">
                                                        <input
                                                            type="checkbox"
                                                            class="rate-checkbox rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                                            onchange="updateRemoveSelectedButton()"
                                                        >
                                                    </div>
                                                    <div class="col-span-2 min-w-[100px]">
                                                        <span class="text-xs sm:text-sm font-medium text-gray-900 category-name whitespace-nowrap">{{ $category->name }}</span>
                                                    </div>
                                                    <div class="col-span-2 min-w-[150px]">
                                                        <span class="text-xs sm:text-sm font-medium text-gray-900 service-name break-words">{{ $service->name }}</span>
                                                    </div>
                                                    <div class="col-span-1 default-price-cell min-w-[90px]">
                                                        <span class="text-xs sm:text-sm font-semibold text-gray-700 default-price-value whitespace-nowrap">${{ number_format($service->rate_per_1000 ?? 0, 2) }}</span>
                                                    </div>
                                                    <div class="col-span-3 flex gap-1.5 sm:gap-2 items-center min-w-[200px]">
                                                        <input
                                                            type="number"
                                                            name="rates[{{ $service->id }}][value]"
                                                            value="{{ old("rates.{$service->id}.value", $rateValue) }}"
                                                            step="0.01"
                                                            min="0"
                                                            placeholder="{{ $rateType === 'fixed' ? '0.00' : '0' }}"
                                                            class="rate-value-input w-16 sm:w-20 md:w-24 px-1.5 sm:px-2 py-1 sm:py-1.5 text-xs border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                                            oninput="updatePreview(this.closest('.custom-rate-row'))"
                                                        />
                                                        <x-custom-select
                                                            name="rates[{{ $service->id }}][type]"
                                                            :value="$rateType"
                                                            size="small"
                                                            :options="[
                                                                ['value' => 'fixed', 'label' => '$'],
                                                                ['value' => 'percent', 'label' => '%'],
                                                            ]"
                                                        />
                                                        <span class="text-xs text-gray-500 rate-preview min-w-[60px] sm:min-w-[70px] md:min-w-[80px] font-mono whitespace-nowrap"></span>
                                                    </div>
                                                    <div class="col-span-2 difference-cell min-w-[100px]">
                                                        <span class="text-xs sm:text-sm font-semibold rate-difference whitespace-nowrap">$0.00</span>
                                                    </div>
                                                    <div class="col-span-1 flex items-center justify-center min-w-[60px]">
                                                        <button
                                                            type="button"
                                                            onclick="removeCustomRate({{ $service->id }})"
                                                            class="text-red-600 hover:text-red-800 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 rounded p-1 sm:p-1.5 transition-colors"
                                                            title="{{ __('Remove') }}"
                                                        >
                                                            <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Hidden template for new rows -->
                            <template id="custom-rate-template">
                                <div
                                    class="custom-rate-row hover:bg-gray-50 transition-colors"
                                    data-service-id=""
                                    data-category-id=""
                                    data-category-name=""
                                    x-data
                                    data-template-service-id="SERVICE_ID"
                                >
                                    <input type="hidden" name="rates[SERVICE_ID][enabled]" value="1" class="rate-enabled-input">
                                    <input type="hidden" name="rates[SERVICE_ID][remove]" value="0" class="rate-remove-input">
                                    <div class="grid grid-cols-12 gap-2 sm:gap-3 md:gap-4 items-center px-3 sm:px-4 md:px-6 py-3 sm:py-4">
                                        <div class="col-span-1 flex items-center justify-start min-w-[60px]">
                                            <input
                                                type="checkbox"
                                                class="rate-checkbox rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                                onchange="updateRemoveSelectedButton()"
                                            >
                                        </div>
                                        <div class="col-span-2 min-w-[100px]">
                                            <span class="text-xs sm:text-sm font-medium text-gray-900 category-name whitespace-nowrap"></span>
                                        </div>
                                        <div class="col-span-2 min-w-[150px]">
                                            <span class="text-xs sm:text-sm font-medium text-gray-900 service-name break-words"></span>
                                        </div>
                                        <div class="col-span-1 default-price-cell min-w-[90px]">
                                            <span class="text-xs sm:text-sm font-semibold text-gray-700 default-price-value whitespace-nowrap"></span>
                                        </div>
                                        <div class="col-span-3 flex gap-1.5 sm:gap-2 items-center min-w-[200px]">
                                            <input
                                                type="number"
                                                name="rates[SERVICE_ID][value]"
                                                value="0"
                                                step="0.01"
                                                min="0"
                                                placeholder="0.00"
                                                class="rate-value-input w-16 sm:w-20 md:w-24 px-1.5 sm:px-2 py-1 sm:py-1.5 text-xs border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                                oninput="updatePreview(this.closest('.custom-rate-row'))"
                                            />
                                            <div data-custom-select-container="rates[SERVICE_ID][type]">
                                                <!-- Custom select will be dynamically created here -->
                                            </div>
                                            <span class="text-xs text-gray-500 rate-preview min-w-[60px] sm:min-w-[70px] md:min-w-[80px] font-mono whitespace-nowrap"></span>
                                        </div>
                                        <div class="col-span-2 difference-cell min-w-[100px]">
                                            <span class="text-xs sm:text-sm font-semibold rate-difference whitespace-nowrap">$0.00</span>
                                        </div>
                                        <div class="col-span-1 flex items-center justify-center min-w-[60px]">
                                            <button
                                                type="button"
                                                onclick="removeCustomRate('SERVICE_ID')"
                                                class="text-red-600 hover:text-red-800 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 rounded p-1 sm:p-1.5 transition-colors"
                                                title="{{ __('Remove') }}"
                                            >
                                                <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            @error('rates')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            @error('rates.*')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Social Authentication Section -->
                        @if($client->provider && $client->provider_id)
                            <div class="mb-6 pb-6 border-b border-gray-200">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    {{ __('Social Authentication') }}
                                </label>
                                <p class="text-sm text-gray-500 mb-4">{{ __('This client signed up using social authentication.') }}</p>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            {{ __('Provider') }}
                                        </label>
                                        <input
                                            type="text"
                                            value="{{ ucfirst($client->provider) }}"
                                            readonly
                                            class="block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm bg-gray-50 text-gray-600 cursor-not-allowed sm:text-sm"
                                        />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            {{ __('Provider ID') }}
                                        </label>
                                        <input
                                            type="text"
                                            value="{{ $client->provider_id }}"
                                            readonly
                                            class="block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm bg-gray-50 text-gray-600 cursor-not-allowed sm:text-sm font-mono text-xs"
                                        />
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Social Media Section -->
                        <div class="mb-6 pb-6 border-b border-gray-200">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                {{ __('Social Media') }}
                            </label>
                            <p class="text-sm text-gray-500 mb-4">{{ __('Add social media accounts for this client. Select platform and enter username.') }}</p>

                            <div id="social-media-container" class="space-y-4">
                                @php
                                    $socialMedia = old('social_media', $client->social_media ?? []);
                                    $socialMediaArray = [];

                                    // Handle old input format (array of arrays from form submission)
                                    if (!empty(old('social_media')) && isset(old('social_media')[0]) && is_array(old('social_media')[0])) {
                                        $socialMediaArray = old('social_media');
                                    } elseif (is_array($socialMedia) && !empty($socialMedia)) {
                                        // Handle database format (associative array: platform => username)
                                        foreach ($socialMedia as $platform => $username) {
                                            if (is_string($platform) && !empty($username)) {
                                                $socialMediaArray[] = ['platform' => $platform, 'username' => $username];
                                            }
                                        }
                                    }

                                    // Always ensure at least one empty row is shown
                                    if (empty($socialMediaArray)) {
                                        $socialMediaArray = [['platform' => '', 'username' => '']];
                                    }

                                    // Define platform options for custom-select
                                    $platformOptions = [
                                        'telegram' => __('Telegram'),
                                        'facebook' => __('Facebook'),
                                        'instagram' => __('Instagram')
                                    ];
                                @endphp

                                @foreach($socialMediaArray as $index => $social)
                                    <div class="flex gap-3 items-start social-media-row border border-gray-200 rounded-lg p-4 bg-gray-50">
                                        <div class="flex-1">
                                            <x-custom-select
                                                name="social_media[{{ $index }}][platform]"
                                                id="social_platform_{{ $index }}"
                                                :value="old('social_media.{$index}.platform', $social['platform'] ?? '')"
                                                :label="__('Platform')"
                                                placeholder="{{ __('Select Platform') }}"
                                                :options="$platformOptions"
                                            />
                                            @error('social_media.'.$index.'.platform')
                                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        <div class="flex-1">
                                            <label for="social_username_{{ $index }}" class="block text-sm font-medium text-gray-700 mb-1">
                                                {{ __('Username') }}
                                            </label>
                                            <input
                                                id="social_username_{{ $index }}"
                                                name="social_media[{{ $index }}][username]"
                                                type="text"
                                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                value="{{ old("social_media.{$index}.username", $social['username'] ?? '') }}"
                                                placeholder="{{ __('Enter username or handle') }}"
                                            />
                                            @error('social_media.'.$index.'.username')
                                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        <div class="pt-6">
                                            <button
                                                type="button"
                                                onclick="removeSocialMediaRow(this)"
                                                class="px-3 py-2 text-sm text-red-600 hover:text-red-800 hover:bg-red-50 rounded-md transition-colors"
                                            >
                                                {{ __('Remove') }}
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <button
                                type="button"
                                onclick="addSocialMediaRow()"
                                class="mt-4 inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                {{ __('Add Social Media') }}
                            </button>
                        </div>

                        <!-- Service Limits Section -->
                        <div class="mb-6 pb-6 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900 mb-2">{{ __('Service Limits') }}</h3>
                            <p class="text-sm text-gray-600 mb-4">{{ __('Override service defaults (min/max quantity, increment) for this client. Leave empty to use service defaults.') }}</p>

                            @error('service_limits')
                                <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-md">
                                    <p class="text-sm text-red-800">{{ $message }}</p>
                                </div>
                            @enderror

                            <!-- Add Service Limits Section -->
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    {{ __('Select Services to Add Limits') }}
                                </label>
                                <div class="flex gap-2">
                                    <div class="flex-1 relative" id="service-limits-selector" style="z-index: 10;"
                                         x-data="{
                                             open: false,
                                             selectedServices: [],
                                             categories: @js($serviceLimitsCategoriesData),
                                             disabledServiceIds: @js($disabledServiceLimitIds),
                                             searchTerm: '',

                                             get filteredCategories() {
                                                 let filtered = this.categories.map(cat => ({
                                                     ...cat,
                                                     services: cat.services.filter(s =>
                                                         !this.isDisabled(s.id)
                                                     )
                                                 })).filter(cat => cat.services.length > 0);

                                                 if (this.searchTerm) {
                                                     const search = this.searchTerm.toLowerCase();
                                                     filtered = filtered.map(cat => ({
                                                         ...cat,
                                                         services: cat.services.filter(s =>
                                                             s.name.toLowerCase().includes(search)
                                                         )
                                                     })).filter(cat => cat.services.length > 0);
                                                 }

                                                 return filtered;
                                             },

                                             toggleService(serviceId) {
                                                 const idStr = String(serviceId);
                                                 if (this.isDisabled(idStr)) return;
                                                 const index = this.selectedServices.indexOf(idStr);
                                                 if (index > -1) {
                                                     this.selectedServices.splice(index, 1);
                                                 } else {
                                                     this.selectedServices.push(idStr);
                                                 }
                                             },

                                             isSelected(serviceId) {
                                                 return this.selectedServices.includes(String(serviceId));
                                             },

                                             isDisabled(serviceId) {
                                                 return this.disabledServiceIds.includes(String(serviceId));
                                             },

                                             toggleSelectAll() {
                                                 const available = this.getAllAvailableServices();
                                                 const allSelected = available.every(id => this.isSelected(id));

                                                 if (allSelected) {
                                                     available.forEach(id => {
                                                         const index = this.selectedServices.indexOf(id);
                                                         if (index > -1) this.selectedServices.splice(index, 1);
                                                     });
                                                 } else {
                                                     available.forEach(id => {
                                                         if (!this.isSelected(id) && !this.isDisabled(id)) {
                                                             this.selectedServices.push(id);
                                                         }
                                                     });
                                                 }
                                             },

                                             getAllAvailableServices() {
                                                 return this.filteredCategories
                                                     .flatMap(cat => cat.services.map(s => String(s.id)))
                                                     .filter(id => !this.isDisabled(id));
                                             },

                                             isAllSelected() {
                                                 const available = this.getAllAvailableServices();
                                                 return available.length > 0 && available.every(id => this.isSelected(id));
                                             }
                                         }"
                                         @click.away="open = false"
                                    >
                                        <button
                                            type="button"
                                            @click="open = !open"
                                            class="w-full flex items-center justify-between px-4 py-2 border border-gray-300 rounded-lg shadow-sm bg-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                        >
                                            <span class="text-gray-700" x-text="selectedServices.length > 0 ? selectedServices.length + ' {{ __('selected') }}' : '{{ __('Select services...') }}'"></span>
                                            <svg class="h-5 w-5 text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" :class="{ 'transform rotate-180': open }">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>
                                        <div
                                            x-show="open"
                                            x-cloak
                                            x-transition:enter="transition ease-out duration-100"
                                            x-transition:enter-start="transform opacity-0 scale-95"
                                            x-transition:enter-end="transform opacity-100 scale-100"
                                            x-transition:leave="transition ease-in duration-75"
                                            x-transition:leave-start="transform opacity-100 scale-100"
                                            x-transition:leave-end="transform opacity-0 scale-95"
                                            class="absolute z-30 mt-2 w-full bg-white border border-gray-300 rounded-lg shadow-lg max-h-96 overflow-y-auto"
                                            style="display: none;"
                                            @click.stop
                                        >
                                            <!-- Search Input -->
                                            <div class="p-2 border-b border-gray-200 sticky top-0 bg-white z-20">
                                                <input
                                                    type="text"
                                                    x-model="searchTerm"
                                                    placeholder="{{ __('Search services...') }}"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                                    @click.stop
                                                />
                                            </div>

                                            <!-- Select All Option -->
                                            <div class="p-2 border-b border-gray-200 bg-gray-50 sticky top-[49px] z-20">
                                                <label class="flex items-center cursor-pointer">
                                                    <input
                                                        type="checkbox"
                                                        @change="toggleSelectAll()"
                                                        :checked="isAllSelected()"
                                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                                        @click.stop
                                                    >
                                                    <span class="ml-2 text-sm font-medium text-gray-900">{{ __('Select All') }}</span>
                                                </label>
                                            </div>

                                            <!-- Categories and Services -->
                                            <div class="p-2">
                                                <template x-for="category in filteredCategories" :key="'cat-' + category.id">
                                                    <div class="mb-2">
                                                        <div class="px-2 py-1 text-xs font-semibold text-gray-500 uppercase mt-2 first:mt-0" x-text="category.name"></div>
                                                        <template x-for="service in category.services" :key="'service-' + service.id">
                                                            <label class="flex items-center px-2 py-2 hover:bg-gray-100 rounded cursor-pointer" @click.stop>
                                                                <input
                                                                    type="checkbox"
                                                                    :value="service.id"
                                                                    @change="toggleService(service.id)"
                                                                    :checked="isSelected(service.id)"
                                                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                                                    @click.stop
                                                                >
                                                                <span class="ml-2 text-sm text-gray-700 flex-1" x-text="service.name"></span>
                                                            </label>
                                                        </template>
                                                    </div>
                                                </template>
                                                <div x-show="filteredCategories.length === 0" class="px-2 py-4 text-sm text-gray-500 text-center">
                                                    {{ __('No services found') }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        onclick="addSelectedServiceLimits()"
                                        class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                    >
                                        {{ __('Add Selected') }}
                                    </button>
                                </div>
                            </div>

                            <!-- Service Limits List -->
                            <div id="service-limits-list" class="border border-gray-300 rounded-lg overflow-hidden relative bg-white">
                                <div class="overflow-x-auto">
                                    <div class="min-w-[800px]">
                                        <!-- Table Header -->
                                        <div class="bg-gray-50 border-b border-gray-200 sticky top-0 z-20">
                                            <div class="grid grid-cols-12 gap-2 sm:gap-3 md:gap-4 px-3 sm:px-4 md:px-6 py-3">
                                                <div class="col-span-1 flex items-center gap-1 sm:gap-2 min-w-[60px]">
                                                    <input
                                                        type="checkbox"
                                                        id="select-all-limits"
                                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                                        onchange="toggleSelectAllLimits(this.checked)"
                                                    >
                                                    <button
                                                        type="button"
                                                        id="remove-selected-limits"
                                                        onclick="removeSelectedLimits()"
                                                        class="px-1.5 sm:px-2 py-1 text-xs font-medium text-white bg-red-600 border border-transparent rounded hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 hidden transition-opacity"
                                                        style="display: none;"
                                                    >
                                                        <svg class="w-3 h-3 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                        </svg>
                                                    </button>
                                                </div>
                                                <div class="col-span-2 text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap min-w-[100px]">{{ __('Category') }}</div>
                                                <div class="col-span-2 text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap min-w-[150px]">{{ __('Service') }}</div>
                                                <div class="col-span-2 text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap min-w-[120px]">{{ __('Min Override') }}</div>
                                                <div class="col-span-2 text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap min-w-[120px]">{{ __('Max Override') }}</div>
                                                <div class="col-span-2 text-xs font-semibold text-gray-700 uppercase tracking-wider whitespace-nowrap min-w-[120px]">{{ __('Increment Override') }}</div>
                                                <div class="col-span-1 text-xs font-semibold text-gray-700 uppercase tracking-wider text-center whitespace-nowrap min-w-[60px]">{{ __('Action') }}</div>
                                            </div>
                                        </div>

                                        <!-- Table Body -->
                                        <div id="service-limits-rows" class="divide-y divide-gray-200">
                                            @foreach($categories as $category)
                                                @foreach($category->services as $service)
                                                    @php
                                                        if (!$service->is_active || $service->trashed()) {
                                                            continue;
                                                        }
                                                        $hasLimit = $serviceLimits->where('service_id', $service->id)->first();
                                                        if (!$hasLimit) continue;
                                                    @endphp
                                                    <div
                                                        class="service-limit-row hover:bg-gray-50 transition-colors"
                                                        data-service-id="{{ $service->id }}"
                                                        data-category-id="{{ $category->id }}"
                                                        data-category-name="{{ $category->name }}"
                                                    >
                                                        <input type="hidden" name="service_limits[{{ $service->id }}][remove]" value="0" class="limit-remove-input">
                                                        <div class="grid grid-cols-12 gap-2 sm:gap-3 md:gap-4 items-center px-3 sm:px-4 md:px-6 py-3 sm:py-4">
                                                            <div class="col-span-1 flex items-center justify-start min-w-[60px]">
                                                                <input
                                                                    type="checkbox"
                                                                    class="limit-checkbox rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                                                    onchange="updateRemoveSelectedLimitsButton()"
                                                                >
                                                            </div>
                                                            <div class="col-span-2 min-w-[100px]">
                                                                <span class="text-xs sm:text-sm font-medium text-gray-900 category-name whitespace-nowrap">{{ $category->name }}</span>
                                                            </div>
                                                            <div class="col-span-2 min-w-[150px]">
                                                                <span class="text-xs sm:text-sm font-medium text-gray-900 service-name break-words">{{ $service->name }}</span>
                                                            </div>
                                                            <div class="col-span-2 min-w-[120px]">
                                                                <input
                                                                    type="number"
                                                                    name="service_limits[{{ $service->id }}][min_quantity]"
                                                                    value="{{ old("service_limits.{$service->id}.min_quantity", $hasLimit->min_quantity ?? '') }}"
                                                                    min="0"
                                                                    step="1"
                                                                    class="w-full px-2 py-1.5 text-xs border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                                                    placeholder="{{ __('Default') }}"
                                                                >
                                                            </div>
                                                            <div class="col-span-2 min-w-[120px]">
                                                                <input
                                                                    type="number"
                                                                    name="service_limits[{{ $service->id }}][max_quantity]"
                                                                    value="{{ old("service_limits.{$service->id}.max_quantity", $hasLimit->max_quantity ?? '') }}"
                                                                    min="0"
                                                                    step="1"
                                                                    class="w-full px-2 py-1.5 text-xs border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                                                    placeholder="{{ __('Default') }}"
                                                                >
                                                            </div>
                                                            <div class="col-span-2 min-w-[120px]">
                                                                <input
                                                                    type="number"
                                                                    name="service_limits[{{ $service->id }}][increment]"
                                                                    value="{{ old("service_limits.{$service->id}.increment", $hasLimit->increment ?? '') }}"
                                                                    min="0"
                                                                    step="1"
                                                                    class="w-full px-2 py-1.5 text-xs border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                                                    placeholder="{{ __('Default') }}"
                                                                >
                                                            </div>
                                                            <div class="col-span-1 flex items-center justify-center min-w-[60px]">
                                                                <button
                                                                    type="button"
                                                                    onclick="removeServiceLimit({{ $service->id }})"
                                                                    class="text-red-600 hover:text-red-800 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 rounded p-1 sm:p-1.5 transition-colors"
                                                                    title="{{ __('Remove') }}"
                                                                >
                                                                    <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                                    </svg>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Hidden template for new rows -->
                            <template id="service-limit-template">
                                <div
                                    class="service-limit-row hover:bg-gray-50 transition-colors"
                                    data-service-id=""
                                    data-category-id=""
                                    data-category-name=""
                                    data-template-service-id="SERVICE_ID"
                                >
                                    <input type="hidden" name="service_limits[SERVICE_ID][remove]" value="0" class="limit-remove-input">
                                    <div class="grid grid-cols-12 gap-2 sm:gap-3 md:gap-4 items-center px-3 sm:px-4 md:px-6 py-3 sm:py-4">
                                        <div class="col-span-1 flex items-center justify-start min-w-[60px]">
                                            <input
                                                type="checkbox"
                                                class="limit-checkbox rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                                onchange="updateRemoveSelectedLimitsButton()"
                                            >
                                        </div>
                                        <div class="col-span-2 min-w-[100px]">
                                            <span class="text-xs sm:text-sm font-medium text-gray-900 category-name whitespace-nowrap"></span>
                                        </div>
                                        <div class="col-span-2 min-w-[150px]">
                                            <span class="text-xs sm:text-sm font-medium text-gray-900 service-name break-words"></span>
                                        </div>
                                        <div class="col-span-2 min-w-[120px]">
                                            <input
                                                type="number"
                                                name="service_limits[SERVICE_ID][min_quantity]"
                                                value=""
                                                min="0"
                                                step="1"
                                                class="w-full px-2 py-1.5 text-xs border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                                placeholder="{{ __('Default') }}"
                                            >
                                        </div>
                                        <div class="col-span-2 min-w-[120px]">
                                            <input
                                                type="number"
                                                name="service_limits[SERVICE_ID][max_quantity]"
                                                value=""
                                                min="0"
                                                step="1"
                                                class="w-full px-2 py-1.5 text-xs border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                                placeholder="{{ __('Default') }}"
                                            >
                                        </div>
                                        <div class="col-span-2 min-w-[120px]">
                                            <input
                                                type="number"
                                                name="service_limits[SERVICE_ID][increment]"
                                                value=""
                                                min="0"
                                                step="1"
                                                class="w-full px-2 py-1.5 text-xs border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                                placeholder="{{ __('Default') }}"
                                            >
                                        </div>
                                        <div class="col-span-1 flex items-center justify-center min-w-[60px]">
                                            <button
                                                type="button"
                                                onclick="removeServiceLimit('SERVICE_ID')"
                                                class="text-red-600 hover:text-red-800 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 rounded p-1 sm:p-1.5 transition-colors"
                                                title="{{ __('Remove') }}"
                                            >
                                                <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </template>
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

            <!-- Sign-in History Section -->
            <div class="mt-6 bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900">{{ __('Sign-in History') }}</h3>
                        <a
                            href="{{ route('staff.clients.sign-ins', $client) }}"
                            class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-white bg-gray-700 border border-gray-300 rounded-md hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 uppercase transition-colors shadow-sm"
                        >
                            {{ __('View All') }}
                        </a>
                    </div>

                    @if(isset($recentSignIns) && $recentSignIns->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            {{ __('Date/Time') }}
                                        </th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            {{ __('IP Address') }}
                                        </th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            {{ __('Device') }}
                                        </th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            {{ __('Location') }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($recentSignIns as $log)
                                        <tr>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                                {{ $log->signed_in_at->format('M d, Y H:i:s') }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 font-mono">
                                                {{ $log->ip }}
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-500">
                                                <div>
                                                    <span class="font-medium">{{ ucfirst($log->device_type ?? 'unknown') }}</span>
                                                    @if($log->os || $log->browser)
                                                        <span class="text-gray-400"> • </span>
                                                        <span>{{ $log->os }}</span>
                                                        @if($log->browser)
                                                            <span class="text-gray-400"> / </span>
                                                            <span>{{ $log->browser }}</span>
                                                        @endif
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                @if($log->city || $log->country)
                                                    {{ $log->city }}{{ $log->city && $log->country ? ', ' : '' }}{{ $log->country }}
                                                @else
                                                    <span class="text-gray-400">{{ __('N/A') }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p class="mt-2 text-sm text-gray-500">{{ __('No sign-in history available yet.') }}</p>
                            <p class="mt-1 text-xs text-gray-400">{{ __('Sign-in history will appear here after the client logs in.') }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script>
        // Store services data for lookup (for global access) - only active services
        const servicesDataFlat = {};
        @foreach($categories as $category)
            @foreach($category->services as $service)
                @if($service->is_active && !$service->trashed())
                    servicesDataFlat['{{ $service->id }}'] = {
                        id: '{{ $service->id }}',
                        name: @json($service->name),
                        price: {{ $service->rate_per_1000 ?? 0 }},
                        categoryId: '{{ $category->id }}',
                        categoryName: @json($category->name)
                    };
                @endif
            @endforeach
        @endforeach

        // Helper function to create custom-select HTML for rate type
        function createCustomSelectForRateType(selectName, defaultValue = 'fixed') {
            const selectId = selectName.replace(/[\[\]]/g, '_') + '_select';
            const hiddenInputId = selectId + '_hidden';
            const defaultValueLabel = defaultValue === 'percent' ? '%' : '$';

            return `
                <div class="relative"
                     x-data="{
                        open: false,
                        selectedValue: '${defaultValue}',
                        selectedLabel: '${defaultValueLabel}',
                        options: [
                            { value: 'fixed', label: '$' },
                            { value: 'percent', label: '%' }
                        ],
                        focusedIndex: -1,
                        toggle() { this.open = !this.open; },
                        close() { this.open = false; this.focusedIndex = -1; },
                        selectOption(option) {
                            this.selectedValue = option.value;
                            this.selectedLabel = option.label;
                            this.close();
                            const hiddenInput = this.$refs.hiddenInput;
                            if (hiddenInput) {
                                hiddenInput.value = option.value;
                                hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                        },
                        handleKeydown(event) {
                            if (!this.open) {
                                if (event.key === 'Enter' || event.key === ' ' || event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                                    event.preventDefault();
                                    this.open = true;
                                    this.focusedIndex = 0;
                                }
                                return;
                            }
                            switch(event.key) {
                                case 'Escape':
                                    event.preventDefault();
                                    this.close();
                                    if (this.$refs.trigger) this.$refs.trigger.focus();
                                    break;
                                case 'Enter':
                                case ' ':
                                    event.preventDefault();
                                    if (this.focusedIndex >= 0 && this.options[this.focusedIndex]) {
                                        this.selectOption(this.options[this.focusedIndex]);
                                        if (this.$refs.trigger) this.$refs.trigger.focus();
                                    }
                                    break;
                                case 'ArrowDown':
                                    event.preventDefault();
                                    this.focusedIndex = (this.focusedIndex + 1) % this.options.length;
                                    break;
                                case 'ArrowUp':
                                    event.preventDefault();
                                    this.focusedIndex = this.focusedIndex <= 0 ? this.options.length - 1 : this.focusedIndex - 1;
                                    break;
                            }
                        }
                     }"
                     x-on:keydown="handleKeydown"
                >
                    <input type="hidden" name="${selectName}" id="${hiddenInputId}" x-ref="hiddenInput" value="${defaultValue}">
                    <button type="button"
                            x-ref="trigger"
                            x-on:click="toggle()"
                            :aria-expanded="open"
                            aria-haspopup="listbox"
                            id="${selectId}"
                            class="relative w-16 sm:w-20 rounded-md border border-gray-300 bg-white py-1.5 pl-2 pr-6 text-xs shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 cursor-pointer"
                            :class="{ 'ring-1 ring-indigo-500 border-indigo-500': open }"
                    >
                        <span class="block truncate" x-text="selectedLabel"></span>
                        <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-1">
                            <svg class="h-3 w-3 text-gray-400 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </span>
                    </button>
                    <div x-show="open"
                         x-cloak
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         x-on:click.away="close()"
                         class="absolute z-50 mt-1 w-full overflow-auto rounded-md bg-white py-1 text-xs shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none"
                         role="listbox"
                         style="display: none;"
                    >
                        <template x-for="(option, index) in options" :key="index">
                            <div :data-option-index="index"
                                 x-on:click="selectOption(option)"
                                 class="relative cursor-pointer select-none py-1.5 pl-2 pr-8 hover:bg-indigo-50 focus:bg-indigo-50"
                                 :class="{
                                     'bg-indigo-50': focusedIndex === index,
                                     'bg-indigo-100': selectedValue === option.value
                                 }"
                                 role="option"
                                 :aria-selected="selectedValue === option.value"
                            >
                                <span class="block truncate text-xs" x-text="option.label"></span>
                                <template x-if="selectedValue === option.value">
                                    <span class="absolute inset-y-0 right-0 flex items-center pr-2 text-indigo-600">
                                        <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                        </svg>
                                    </span>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            `;
        }

        function addSelectedCustomRates() {
            // Get selected services from the Alpine.js dropdown
            const dropdownComponent = document.getElementById('custom-rates-selector');
            if (!dropdownComponent) {
                console.error('Dropdown component not found');
                alert('{{ __('Please select at least one service') }}');
                return;
            }

            if (typeof Alpine === 'undefined') {
                console.error('Alpine.js not found');
                alert('{{ __('Please refresh the page and try again.') }}');
                return;
            }

            let alpineData;
            try {
                alpineData = Alpine.$data(dropdownComponent);
            } catch (e) {
                console.error('Error getting Alpine data:', e);
                alert('{{ __('Error accessing dropdown data. Please try again.') }}');
                return;
            }

            if (!alpineData) {
                console.error('Alpine data is null or undefined');
                alert('{{ __('Please select at least one service') }}');
                return;
            }

            // Get selected services - Alpine.js reactive arrays need special handling
            let selectedIdsArray = [];
            try {
                const rawSelected = alpineData.selectedServices;

                if (!rawSelected) {
                    alert('{{ __('Please select at least one service') }}');
                    return;
                }

                // Handle Alpine.js reactive array - convert to plain array safely
                if (Array.isArray(rawSelected)) {
                    // Clone the array to avoid reactivity issues
                    selectedIdsArray = [...rawSelected];
                } else if (rawSelected && typeof rawSelected.length === 'number') {
                    // It's array-like, convert it using slice
                    try {
                        selectedIdsArray = Array.prototype.slice.call(rawSelected);
                    } catch (e) {
                        // Fallback: manually copy
                        selectedIdsArray = [];
                        for (let i = 0; i < rawSelected.length; i++) {
                            selectedIdsArray.push(rawSelected[i]);
                        }
                    }
                } else {
                    console.error('selectedServices is not a valid array:', rawSelected);
                    alert('{{ __('Error reading selected services. Please try again.') }}');
                    return;
                }
            } catch (e) {
                console.error('Error processing selectedIds:', e, alpineData);
                alert('{{ __('Error reading selected services. Please try again.') }}');
                return;
            }

            if (!selectedIdsArray || selectedIdsArray.length === 0) {
                alert('{{ __('Please select at least one service') }}');
                return;
            }

            console.log('Adding services:', selectedIdsArray);

            // Now iterate over the array - use traditional for loop to avoid iteration issues
            for (let i = 0; i < selectedIdsArray.length; i++) {
                const serviceId = selectedIdsArray[i];
                const serviceIdStr = String(serviceId);

                // Check if service is already added (visible)
                const existingRow = document.querySelector(`.custom-rate-row[data-service-id="${serviceIdStr}"]`);
                if (existingRow && existingRow.style.display !== 'none') {
                    continue; // Already visible, skip to next service
                }

                // If there's a hidden row (marked for removal), remove it completely
                if (existingRow) {
                    existingRow.remove();
                }

                // Get service data from servicesDataFlat or from categories
                let service = null;
                try {
                    if (typeof servicesDataFlat !== 'undefined' && servicesDataFlat && servicesDataFlat[serviceIdStr]) {
                        service = servicesDataFlat[serviceIdStr];
                    }
                } catch (e) {
                    console.error('Error accessing servicesDataFlat:', e);
                }

                if (!service && alpineData.categories) {
                    try {
                        // Safely convert categories to array
                        let categoriesArray = [];
                        if (Array.isArray(alpineData.categories)) {
                            categoriesArray = alpineData.categories;
                        } else if (alpineData.categories && typeof alpineData.categories.length === 'number') {
                            try {
                                categoriesArray = Array.prototype.slice.call(alpineData.categories);
                            } catch (e) {
                                // Fallback: manually copy
                                categoriesArray = [];
                                for (let i = 0; i < alpineData.categories.length; i++) {
                                    categoriesArray.push(alpineData.categories[i]);
                                }
                            }
                        } else {
                            console.warn('categories is not a valid array:', alpineData.categories);
                            categoriesArray = [];
                        }

                        // Try to find in categories data using traditional for loop
                        for (let catIdx = 0; catIdx < categoriesArray.length; catIdx++) {
                            const category = categoriesArray[catIdx];
                            if (!category || !category.services) continue;

                            // Safely convert services to array
                            let servicesArray = [];
                            if (Array.isArray(category.services)) {
                                servicesArray = category.services;
                            } else if (category.services && typeof category.services.length === 'number') {
                                try {
                                    servicesArray = Array.prototype.slice.call(category.services);
                                } catch (e) {
                                    // Fallback: manually copy
                                    servicesArray = [];
                                    for (let i = 0; i < category.services.length; i++) {
                                        servicesArray.push(category.services[i]);
                                    }
                                }
                            } else {
                                console.warn('services is not a valid array:', category.services);
                                servicesArray = [];
                            }

                            // Find the service using traditional for loop
                            for (let svcIdx = 0; svcIdx < servicesArray.length; svcIdx++) {
                                const s = servicesArray[svcIdx];
                                if (s && String(s.id) === serviceIdStr) {
                                    service = {
                                        id: s.id,
                                        name: s.name,
                                        price: s.price,
                                        categoryId: category.id,
                                        categoryName: category.name
                                    };
                                    break;
                                }
                            }
                            if (service) break;
                        }
                    } catch (e) {
                        console.error('Error iterating categories:', e);
                    }
                }
                if (!service) {
                    console.warn('Service not found for ID:', serviceIdStr);
                    continue; // Skip to next service if not found
                }

                // Get template and container - defensive checks
                const template = document.getElementById('custom-rate-template');
                const container = document.getElementById('custom-rates-rows');
                if (!template?.content || !container) {
                    console.error('Template or container not found');
                    continue; // Skip to next service
                }

                const newRow = template.content.cloneNode(true);

                // Replace placeholders
                const rowElement = newRow.querySelector('.custom-rate-row');
                if (!rowElement) {
                    console.warn('Row element not found in template');
                    continue; // Skip to next service
                }

                rowElement.setAttribute('data-service-id', serviceIdStr);
                rowElement.setAttribute('data-category-id', service.categoryId || '');
                rowElement.setAttribute('data-category-name', service.categoryName || '');

                // Remove the data-template-service-id attribute if present
                rowElement.removeAttribute('data-template-service-id');

                // Set up the change listener attribute for the custom select
                rowElement.setAttribute('@change', `if ($event.target?.name === 'rates[${serviceIdStr}][type]') { updatePreview($el); }`);

                // Update hidden inputs
                newRow.querySelectorAll('input[type="hidden"]').forEach(input => {
                    input.name = input.name.replace(/SERVICE_ID/g, serviceIdStr);
                });

                // Update category name
                const categoryNameSpan = newRow.querySelector('.category-name');
                if (categoryNameSpan) {
                    categoryNameSpan.textContent = service.categoryName || '';
                }

                // Update service name - defensive check
                const serviceNameSpan = newRow.querySelector('.service-name');
                if (serviceNameSpan) {
                    serviceNameSpan.textContent = service.name || '';
                } else {
                    console.warn('serviceNameSpan not found');
                }

                // Update default price - defensive check
                const priceSpan = newRow.querySelector('.default-price-value');
                if (priceSpan) {
                    priceSpan.textContent = '$' + parseFloat(service.price || 0).toFixed(2);
                } else {
                    console.warn('priceSpan not found');
                }

                // Update input names
                newRow.querySelectorAll('[name*="SERVICE_ID"]').forEach(input => {
                    input.name = input.name.replace(/SERVICE_ID/g, serviceIdStr);
                });

                // Update remove button - defensive check
                const removeButton = newRow.querySelector('button[onclick*="SERVICE_ID"]');
                if (removeButton) {
                    removeButton.setAttribute('onclick', `removeCustomRate('${serviceIdStr}')`);
                }

                // Create custom-select component for rate type using a helper function
                const customSelectContainer = newRow.querySelector('[data-custom-select-container]');
                if (customSelectContainer) {
                    const selectName = `rates[${serviceIdStr}][type]`;
                    // Create a temporary div to parse the HTML
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = createCustomSelectForRateType(selectName, 'fixed');
                    // Replace the container with the first child (the custom select div)
                    const customSelectDiv = tempDiv.firstElementChild;
                    if (customSelectDiv) {
                        customSelectContainer.parentNode.replaceChild(customSelectDiv, customSelectContainer);
                    }
                }

                // Add to DOM first (important: append DocumentFragment before Alpine.initTree)
                container.appendChild(newRow);

                // Initialize Alpine.js for the newly added row (after it's in the DOM)
                const addedRow = container.querySelector(`.custom-rate-row[data-service-id="${serviceIdStr}"]`);
                if (addedRow && typeof Alpine !== 'undefined') {
                    Alpine.initTree(addedRow);

                    // Set up change listener for the custom select
                    const typeInput = addedRow.querySelector(`input[type="hidden"][name="rates[${serviceIdStr}][type]"]`);
                    if (typeInput) {
                        typeInput.addEventListener('change', () => {
                            updatePreview(addedRow);
                        });
                    }
                    updatePreview(addedRow);
                }
            }
            // Clear selection in dropdown
            if (alpineData) {
                alpineData.selectedServices = [];
                alpineData.open = false;
            }

            // Update disabled services after DOM updates
            setTimeout(() => {
                updateDisabledServices();
                showAllRatesRows();
            }, 50);
        }

        function updateDisabledServices() {
            // Get all service IDs that are already in custom rates and visible (not marked for removal)
            const rows = document.querySelectorAll('.custom-rate-row');
            const addedServiceIds = rows ? Array.from(rows ?? [])
                .filter(row => {
                    if (!row || !row.parentElement) return false;
                    const originalDisplay = row.getAttribute('data-original-display');
                    const removeInput = row.querySelector('.rate-remove-input');
                    const isMarkedForRemoval = removeInput && removeInput.value === '1';
                    // Only include rows that are not marked for removal
                    return originalDisplay !== 'none' && !isMarkedForRemoval;
                })
                .map(row => row.getAttribute('data-service-id'))
                .filter(id => id !== null) : [];

            // Update the dropdown component's disabled values
            const dropdownComponent = document.getElementById('custom-rates-selector');
            if (dropdownComponent && typeof Alpine !== 'undefined') {
                try {
                    const alpineData = Alpine.$data(dropdownComponent);
                    if (alpineData) {
                        // Update disabled service IDs (only visible ones)
                        alpineData.disabledServiceIds = addedServiceIds.map(id => String(id));

                        // Remove selected services that are now disabled
                        alpineData.selectedServices = alpineData.selectedServices.filter(id => {
                            return !alpineData.disabledServiceIds.includes(String(id));
                        });
                    }
                } catch (e) {
                    console.error('Error updating disabled services:', e);
                }
            }
        }

        function removeCustomRate(serviceId) {
            const serviceIdStr = String(serviceId);
            const row = document.querySelector(`.custom-rate-row[data-service-id="${serviceIdStr}"]`);
            if (row) {
                // Mark for removal
                const removeInput = row.querySelector('.rate-remove-input');
                if (removeInput) {
                    removeInput.value = '1';
                }
                // Mark as hidden
                row.setAttribute('data-original-display', 'none');
                // Hide the row
                row.style.display = 'none';
                // Uncheck if checked
                const checkbox = row.querySelector('.rate-checkbox');
                if (checkbox) {
                    checkbox.checked = false;
                }
                // Update disabled services after removal
                updateDisabledServices();
                // Update remove button state
                updateRemoveSelectedButton();
                // Show all rows
                showAllRatesRows();
            }
        }

        function toggleSelectAllRates(checked) {
            const checkboxes = document.querySelectorAll('.rate-checkbox');
            checkboxes.forEach(checkbox => {
                const row = checkbox.closest('.custom-rate-row');
                // Only toggle if row is visible (not marked for removal)
                if (row && row.style.display !== 'none') {
                    checkbox.checked = checked;
                }
            });
            updateRemoveSelectedButton();

            // Update select all checkbox state
            const selectAllCheckbox = document.getElementById('select-all-rates');
            if (selectAllCheckbox) {
                const rows = document.querySelectorAll('.custom-rate-row');
                const visibleRows = rows ? Array.from(rows ?? []).filter(row => row && row.style && row.style.display !== 'none') : [];
                const visibleCheckboxes = visibleRows.map(row => row.querySelector('.rate-checkbox')).filter(cb => cb);
                const allChecked = visibleCheckboxes.length > 0 && visibleCheckboxes.every(cb => cb.checked);
                selectAllCheckbox.checked = allChecked;
            }
        }

        function updateRemoveSelectedButton() {
            const checkboxes = document.querySelectorAll('.rate-checkbox');
            const removeButton = document.getElementById('remove-selected-rates');
            if (removeButton) {
                // Count visible checked checkboxes
                const visibleChecked = Array.from(checkboxes ?? []).filter(checkbox => {
                    if (!checkbox.checked) return false;
                    const row = checkbox.closest('.custom-rate-row');
                    return row && row.style.display !== 'none';
                });

                // Show/hide button based on selection
                if (visibleChecked.length > 0) {
                    removeButton.style.display = 'inline-flex';
                    removeButton.classList.remove('hidden');
                } else {
                    removeButton.style.display = 'none';
                    removeButton.classList.add('hidden');
                }
            }
        }

        function removeSelectedRates() {
            const checkboxes = document.querySelectorAll('.rate-checkbox:checked');
            if (checkboxes.length === 0) {
                return;
            }
            checkboxes.forEach(checkbox => {
                const row = checkbox.closest('.custom-rate-row');
                if (row && row.style.display !== 'none') {
                    const serviceId = row.getAttribute('data-service-id');
                    // Mark for removal
                    const removeInput = row.querySelector('.rate-remove-input');
                    if (removeInput) {
                        removeInput.value = '1';
                    }
                    // Mark as hidden
                    row.setAttribute('data-original-display', 'none');
                    // Hide the row
                    row.style.display = 'none';
                    // Uncheck
                    checkbox.checked = false;
                }
            });

            // Update disabled services
            updateDisabledServices();
            // Update button state
            updateRemoveSelectedButton();
            // Uncheck select all
            const selectAllCheckbox = document.getElementById('select-all-rates');
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = false;
            }
            // Show all rows
            showAllRatesRows();
        }

        function updatePreview(row) {
            // Get the hidden input from custom-select (it has name="rates[SERVICE_ID][type]")
            const typeInput = row.querySelector('input[type="hidden"][name*="[type]"]');
            const valueInput = row.querySelector('.rate-value-input');
            const preview = row.querySelector('.rate-preview');
            const differenceSpan = row.querySelector('.rate-difference');

            if (!typeInput || !valueInput || !preview) return;

            // Get default price from the row
            const priceCell = row.querySelector('.default-price-cell');
            const priceSpan = priceCell ? priceCell.querySelector('.default-price-value') : null;
            const defaultPriceText = priceSpan ? priceSpan.textContent.replace('$', '').replace(/,/g, '') : '0';
            const defaultPrice = parseFloat(defaultPriceText) || 0;

            const type = typeInput.value;
            const value = parseFloat(valueInput.value) || 0;

            let finalPrice = 0;
            if (type === 'fixed') {
                finalPrice = value;
            } else if (type === 'percent') {
                finalPrice = defaultPrice * (value / 100);
            }

            preview.textContent = '$' + finalPrice.toFixed(2);

            // Calculate and display difference: default rate - custom rate
            if (differenceSpan) {
                const difference = defaultPrice - finalPrice;
                const absDifference = Math.abs(difference);
                if (absDifference === 0) {
                    differenceSpan.textContent = '$0.00';
                    differenceSpan.className = 'text-sm font-semibold text-gray-500 rate-difference';
                } else if (difference > 0) {
                    // Default rate is higher (custom rate is lower) - savings shown in green
                    differenceSpan.textContent = '$' + absDifference.toFixed(2);
                    differenceSpan.className = 'text-sm font-semibold text-green-600 rate-difference';
                } else {
                    // Default rate is lower (custom rate is higher) - extra cost shown in red
                    differenceSpan.textContent = '$' + absDifference.toFixed(2);
                    differenceSpan.className = 'text-sm font-semibold text-red-600 rate-difference';
                }
            }
        }

        // Initialize previews for existing rows and set up event listeners
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.custom-rate-row').forEach(row => {
                updatePreview(row);
            });

            // Set up checkbox change listeners
            document.querySelectorAll('.rate-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', updateRemoveSelectedButton);
            });

            // Initial button state
            updateRemoveSelectedButton();

            // Show all rows initially
            showAllRatesRows();

            // Show status message if present
            @if(session('success'))
                showStatusMessage('{{ session('success') }}', 'success');
            @endif

            @if(session('error'))
                showStatusMessage('{{ session('error') }}', 'error');
            @endif

            // Initialize service limits checkboxes
            document.querySelectorAll('.limit-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', updateRemoveSelectedLimitsButton);
            });
            updateRemoveSelectedLimitsButton();
        });

        // Service Limits Functions
        function addSelectedServiceLimits() {
            const dropdownComponent = document.getElementById('service-limits-selector');
            if (!dropdownComponent) {
                alert('{{ __('Please select at least one service') }}');
                return;
            }

            if (typeof Alpine === 'undefined') {
                alert('{{ __('Please refresh the page and try again.') }}');
                return;
            }

            let alpineData;
            try {
                alpineData = Alpine.$data(dropdownComponent);
            } catch (e) {
                alert('{{ __('Error accessing dropdown data. Please try again.') }}');
                return;
            }

            if (!alpineData) {
                alert('{{ __('Please select at least one service') }}');
                return;
            }

            let selectedIdsArray = [];
            try {
                const rawSelected = alpineData.selectedServices;
                if (!rawSelected) {
                    alert('{{ __('Please select at least one service') }}');
                    return;
                }

                if (Array.isArray(rawSelected)) {
                    selectedIdsArray = [...rawSelected];
                } else if (rawSelected && typeof rawSelected.length === 'number') {
                    try {
                        selectedIdsArray = Array.prototype.slice.call(rawSelected);
                    } catch (e) {
                        selectedIdsArray = [];
                        for (let i = 0; i < rawSelected.length; i++) {
                            selectedIdsArray.push(rawSelected[i]);
                        }
                    }
                }
            } catch (e) {
                alert('{{ __('Error reading selected services. Please try again.') }}');
                return;
            }

            if (!selectedIdsArray || selectedIdsArray.length === 0) {
                alert('{{ __('Please select at least one service') }}');
                return;
            }

            // Get services data from categories
            const servicesDataFlat = {};
            if (alpineData.categories) {
                alpineData.categories.forEach(category => {
                    category.services.forEach(service => {
                        servicesDataFlat[String(service.id)] = {
                            id: service.id,
                            name: service.name,
                            category_id: category.id,
                            category_name: category.name
                        };
                    });
                });
            }

            for (let i = 0; i < selectedIdsArray.length; i++) {
                const serviceId = selectedIdsArray[i];
                const serviceIdStr = String(serviceId);

                // Check if service is already added
                const existingRow = document.querySelector(`.service-limit-row[data-service-id="${serviceIdStr}"]`);
                if (existingRow && existingRow.style.display !== 'none') {
                    continue;
                }

                if (existingRow) {
                    existingRow.remove();
                }

                const service = servicesDataFlat[serviceIdStr];
                if (!service) continue;

                const template = document.getElementById('service-limit-template');
                const container = document.getElementById('service-limits-rows');
                if (!template || !container) continue;

                const newRow = template.content.cloneNode(true);
                const rowElement = newRow.querySelector('.service-limit-row');
                rowElement.setAttribute('data-service-id', serviceIdStr);
                rowElement.setAttribute('data-category-id', service.category_id);
                rowElement.setAttribute('data-category-name', service.category_name);

                // Update all SERVICE_ID placeholders
                newRow.querySelectorAll('[name*="SERVICE_ID"]').forEach(input => {
                    input.name = input.name.replace(/SERVICE_ID/g, serviceIdStr);
                });

                // Update service and category names
                const categoryNameSpan = newRow.querySelector('.category-name');
                const serviceNameSpan = newRow.querySelector('.service-name');
                if (categoryNameSpan) categoryNameSpan.textContent = service.category_name;
                if (serviceNameSpan) serviceNameSpan.textContent = service.name;

                // Update remove button
                const removeButton = newRow.querySelector('button[onclick*="SERVICE_ID"]');
                if (removeButton) {
                    removeButton.setAttribute('onclick', `removeServiceLimit('${serviceIdStr}')`);
                }

                container.appendChild(newRow);
            }

            // Clear selection
            if (alpineData) {
                alpineData.selectedServices = [];
                alpineData.open = false;
            }

            updateRemoveSelectedLimitsButton();
        }

        function removeServiceLimit(serviceId) {
            const serviceIdStr = String(serviceId);
            const row = document.querySelector(`.service-limit-row[data-service-id="${serviceIdStr}"]`);
            if (row) {
                const removeInput = row.querySelector('.limit-remove-input');
                if (removeInput) {
                    removeInput.value = '1';
                }
                row.style.display = 'none';
                const checkbox = row.querySelector('.limit-checkbox');
                if (checkbox) {
                    checkbox.checked = false;
                }
                updateRemoveSelectedLimitsButton();
            }
        }

        function toggleSelectAllLimits(checked) {
            const checkboxes = document.querySelectorAll('.limit-checkbox');
            checkboxes.forEach(checkbox => {
                const row = checkbox.closest('.service-limit-row');
                if (row && row.style.display !== 'none') {
                    checkbox.checked = checked;
                }
            });
            updateRemoveSelectedLimitsButton();
        }

        function updateRemoveSelectedLimitsButton() {
            const checkboxes = document.querySelectorAll('.limit-checkbox');
            const checkedBoxes = Array.from(checkboxes).filter(cb => {
                const row = cb.closest('.service-limit-row');
                return row && row.style.display !== 'none' && cb.checked;
            });

            const removeButton = document.getElementById('remove-selected-limits');
            if (removeButton) {
                if (checkedBoxes.length > 0) {
                    removeButton.style.display = 'inline-flex';
                    removeButton.classList.remove('hidden');
                } else {
                    removeButton.style.display = 'none';
                    removeButton.classList.add('hidden');
                }
            }
        }

        function removeSelectedLimits() {
            const checkboxes = document.querySelectorAll('.limit-checkbox:checked');
            checkboxes.forEach(checkbox => {
                const row = checkbox.closest('.service-limit-row');
                if (row && row.style.display !== 'none') {
                    const serviceId = row.getAttribute('data-service-id');
                    removeServiceLimit(serviceId);
                }
            });
        }

        // Show all custom rates rows (no pagination)
        function showAllRatesRows() {
            const allRowsInDOM = document.querySelectorAll('.custom-rate-row');

            allRowsInDOM.forEach(row => {
                if (!row || !row.parentElement) return;
                const originalDisplay = row.getAttribute('data-original-display');
                const removeInput = row.querySelector('.rate-remove-input');
                const isMarkedForRemoval = removeInput && removeInput.value === '1';

                // Hide removed rows, show all others
                if (originalDisplay === 'none' || isMarkedForRemoval) {
                    row.style.display = 'none';
                } else {
                    row.style.display = '';
                }
            });
        }

        function showStatusMessage(message, type) {
            const messageContainer = document.getElementById('status-message');
            const messageContent = document.getElementById('status-message-content');
            const messageText = document.getElementById('status-message-text');

            if (!messageContainer || !messageContent || !messageText) return;

            // Set message text
            messageText.textContent = message;

            // Set styling based on type
            if (type === 'success') {
                messageContent.className = 'rounded-md p-3 shadow-lg bg-green-50 border border-green-200';
                messageText.className = 'text-sm font-medium text-green-800';
            } else {
                messageContent.className = 'rounded-md p-3 shadow-lg bg-red-50 border border-red-200';
                messageText.className = 'text-sm font-medium text-red-800';
            }

            // Show message
            messageContainer.classList.remove('hidden');

            // Hide after 3 seconds
            setTimeout(() => {
                messageContainer.classList.add('hidden');
            }, 3000);
        }

        // Service Limits Functions
        function addSelectedServiceLimits() {
            const dropdownComponent = document.getElementById('service-limits-selector');
            if (!dropdownComponent) {
                alert('{{ __('Please select at least one service') }}');
                return;
            }

            if (typeof Alpine === 'undefined') {
                alert('{{ __('Please refresh the page and try again.') }}');
                return;
            }

            let alpineData;
            try {
                alpineData = Alpine.$data(dropdownComponent);
            } catch (e) {
                alert('{{ __('Error accessing dropdown data. Please try again.') }}');
                return;
            }

            if (!alpineData) {
                alert('{{ __('Please select at least one service') }}');
                return;
            }

            let selectedIdsArray = [];
            try {
                const rawSelected = alpineData.selectedServices;
                if (!rawSelected) {
                    alert('{{ __('Please select at least one service') }}');
                    return;
                }

                if (Array.isArray(rawSelected)) {
                    selectedIdsArray = [...rawSelected];
                } else if (rawSelected && typeof rawSelected.length === 'number') {
                    try {
                        selectedIdsArray = Array.prototype.slice.call(rawSelected);
                    } catch (e) {
                        selectedIdsArray = [];
                        for (let i = 0; i < rawSelected.length; i++) {
                            selectedIdsArray.push(rawSelected[i]);
                        }
                    }
                }
            } catch (e) {
                alert('{{ __('Error reading selected services. Please try again.') }}');
                return;
            }

            if (!selectedIdsArray || selectedIdsArray.length === 0) {
                alert('{{ __('Please select at least one service') }}');
                return;
            }

            // Get services data from categories
            const servicesDataFlat = {};
            if (alpineData.categories) {
                alpineData.categories.forEach(category => {
                    category.services.forEach(service => {
                        servicesDataFlat[String(service.id)] = {
                            id: service.id,
                            name: service.name,
                            category_id: category.id,
                            category_name: category.name
                        };
                    });
                });
            }

            for (let i = 0; i < selectedIdsArray.length; i++) {
                const serviceId = selectedIdsArray[i];
                const serviceIdStr = String(serviceId);

                // Check if service is already added
                const existingRow = document.querySelector(`.service-limit-row[data-service-id="${serviceIdStr}"]`);
                if (existingRow && existingRow.style.display !== 'none') {
                    continue;
                }

                if (existingRow) {
                    existingRow.remove();
                }

                const service = servicesDataFlat[serviceIdStr];
                if (!service) continue;

                const template = document.getElementById('service-limit-template');
                const container = document.getElementById('service-limits-rows');
                if (!template || !container) continue;

                const newRow = template.content.cloneNode(true);
                const rowElement = newRow.querySelector('.service-limit-row');
                rowElement.setAttribute('data-service-id', serviceIdStr);
                rowElement.setAttribute('data-category-id', service.category_id);
                rowElement.setAttribute('data-category-name', service.category_name);

                // Update all SERVICE_ID placeholders
                newRow.querySelectorAll('[name*="SERVICE_ID"]').forEach(input => {
                    input.name = input.name.replace(/SERVICE_ID/g, serviceIdStr);
                });

                // Update service and category names
                const categoryNameSpan = newRow.querySelector('.category-name');
                const serviceNameSpan = newRow.querySelector('.service-name');
                if (categoryNameSpan) categoryNameSpan.textContent = service.category_name;
                if (serviceNameSpan) serviceNameSpan.textContent = service.name;

                // Update remove button
                const removeButton = newRow.querySelector('button[onclick*="SERVICE_ID"]');
                if (removeButton) {
                    removeButton.setAttribute('onclick', `removeServiceLimit('${serviceIdStr}')`);
                }

                container.appendChild(newRow);
            }

            // Clear selection
            if (alpineData) {
                alpineData.selectedServices = [];
                alpineData.open = false;
            }

            updateRemoveSelectedLimitsButton();
        }

        function removeServiceLimit(serviceId) {
            const serviceIdStr = String(serviceId);
            const row = document.querySelector(`.service-limit-row[data-service-id="${serviceIdStr}"]`);
            if (row) {
                const removeInput = row.querySelector('.limit-remove-input');
                if (removeInput) {
                    removeInput.value = '1';
                }
                row.style.display = 'none';
                const checkbox = row.querySelector('.limit-checkbox');
                if (checkbox) {
                    checkbox.checked = false;
                }
                updateRemoveSelectedLimitsButton();
            }
        }

        function toggleSelectAllLimits(checked) {
            const checkboxes = document.querySelectorAll('.limit-checkbox');
            checkboxes.forEach(checkbox => {
                const row = checkbox.closest('.service-limit-row');
                if (row && row.style.display !== 'none') {
                    checkbox.checked = checked;
                }
            });
            updateRemoveSelectedLimitsButton();
        }

        function updateRemoveSelectedLimitsButton() {
            const checkboxes = document.querySelectorAll('.limit-checkbox');
            const checkedBoxes = Array.from(checkboxes).filter(cb => {
                const row = cb.closest('.service-limit-row');
                return row && row.style.display !== 'none' && cb.checked;
            });

            const removeButton = document.getElementById('remove-selected-limits');
            if (removeButton) {
                if (checkedBoxes.length > 0) {
                    removeButton.style.display = 'inline-flex';
                    removeButton.classList.remove('hidden');
                } else {
                    removeButton.style.display = 'none';
                    removeButton.classList.add('hidden');
                }
            }
        }

        function removeSelectedLimits() {
            const checkboxes = document.querySelectorAll('.limit-checkbox:checked');
            checkboxes.forEach(checkbox => {
                const row = checkbox.closest('.service-limit-row');
                if (row && row.style.display !== 'none') {
                    const serviceId = row.getAttribute('data-service-id');
                    removeServiceLimit(serviceId);
                }
            });
        }

        // Social Media Functions
        function addSocialMediaRow() {
            const container = document.getElementById('social-media-container');
            if (!container) return;

            // Get current row count
            const existingRows = container.querySelectorAll('.social-media-row');
            const newIndex = existingRows.length;

            // Platform options (from PHP)
            const platformOptions = {
                'telegram': @json(__('Telegram')),
                'facebook': @json(__('Facebook')),
                'instagram': @json(__('Instagram'))
            };

            const platformLabel = @json(__('Platform'));
            const selectPlatformLabel = @json(__('Select Platform'));
            const usernameLabel = @json(__('Username'));
            const usernamePlaceholder = @json(__('Enter username or handle'));
            const removeLabel = @json(__('Remove'));

            // Create new row HTML
            const newRow = document.createElement('div');
            newRow.className = 'flex gap-3 items-start social-media-row border border-gray-200 rounded-lg p-4 bg-gray-50';
            
            let optionsHtml = '<option value="">' + selectPlatformLabel + '</option>';
            for (const [value, label] of Object.entries(platformOptions)) {
                optionsHtml += '<option value="' + value + '">' + label + '</option>';
            }
            
            newRow.innerHTML = 
                '<div class="flex-1">' +
                    '<label class="block text-sm font-medium text-gray-700 mb-1">' + platformLabel + '</label>' +
                    '<select name="social_media[' + newIndex + '][platform]" id="social_platform_' + newIndex + '" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">' +
                        optionsHtml +
                    '</select>' +
                '</div>' +
                '<div class="flex-1">' +
                    '<label for="social_username_' + newIndex + '" class="block text-sm font-medium text-gray-700 mb-1">' + usernameLabel + '</label>' +
                    '<input id="social_username_' + newIndex + '" name="social_media[' + newIndex + '][username]" type="text" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="' + usernamePlaceholder + '" />' +
                '</div>' +
                '<div class="pt-6">' +
                    '<button type="button" onclick="removeSocialMediaRow(this)" class="px-3 py-2 text-sm text-red-600 hover:text-red-800 hover:bg-red-50 rounded-md transition-colors">' + removeLabel + '</button>' +
                '</div>';

            container.appendChild(newRow);
        }

        function removeSocialMediaRow(button) {
            const row = button.closest('.social-media-row');
            if (row) {
                row.remove();
                
                // Re-index remaining rows to ensure sequential array indices
                const container = document.getElementById('social-media-container');
                if (container) {
                    const rows = container.querySelectorAll('.social-media-row');
                    rows.forEach(function(r, index) {
                        // Update platform select name and id
                        const platformSelect = r.querySelector('select[name*="[platform]"]');
                        if (platformSelect) {
                            platformSelect.name = 'social_media[' + index + '][platform]';
                            platformSelect.id = 'social_platform_' + index;
                        }
                        
                        // Update username input name and id
                        const usernameInput = r.querySelector('input[name*="[username]"]');
                        if (usernameInput) {
                            usernameInput.name = 'social_media[' + index + '][username]';
                            usernameInput.id = 'social_username_' + index;
                        }
                    });
                }
            }
        }
    </script>
</x-app-layout>

