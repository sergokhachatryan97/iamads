<x-client-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('New Multi-Service Order') }}
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

                    <form method="POST" action="{{ route('client.orders.multi-store') }}"
                          x-data="multiOrderForm()"
                          x-init="init()"
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

                        <!-- Link (Single) -->
                        <div class="mb-6">
                            <label for="link" class="block text-sm font-medium text-gray-700 mb-2">
                                {{ __('Telegram Link') }} <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                id="link"
                                name="link"
                                x-model="link"
                                @input="linkValid = validateTelegramLink(link)"
                                :class="linkValid ? 'border-gray-300' : 'border-red-300'"
                                placeholder="https://t.me/username or @username"
                                required
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <p x-show="!linkValid && link" class="mt-1 text-sm text-red-600">
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
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                {{ __('Select Services') }} <span class="text-red-500">*</span>
                            </label>
                            <div x-show="loading" class="text-sm text-gray-500 mb-2">
                                {{ __('Loading services...') }}
                            </div>
                            <div x-show="!loading && services.length === 0 && categoryId" class="text-sm text-gray-500 mb-2">
                                {{ __('No active services found for this category.') }}
                            </div>
                            <div x-show="!loading && services.length > 0" class="space-y-3 border border-gray-200 rounded-lg p-4 bg-gray-50 max-h-96 overflow-y-auto">
                                <template x-for="service in (Array.isArray(services) ? services : [])" :key="'service-' + service.id">
                                    <div class="flex items-start gap-3 p-3 bg-white rounded-md border border-gray-200 hover:border-indigo-300 transition-colors"
                                         :class="isServiceSelected(service.id) ? 'border-indigo-500 bg-indigo-50' : ''">
                                        <div class="flex items-center h-5 mt-0.5">
                                            <input
                                                type="checkbox"
                                                :id="'service_' + service.id"
                                                :value="service.id"
                                                @change="toggleService(service)"
                                                :checked="isServiceSelected(service.id)"
                                                class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded">
                                        </div>
                                        <div class="flex-1">
                                            <label :for="'service_' + service.id" class="font-medium text-gray-900 cursor-pointer">
                                                <span x-text="service.name"></span>
                                            </label>
                                            <div class="mt-1 text-xs text-gray-500">
                                                <span>{{ __('Min') }}: <span x-text="service.min_quantity || 1"></span></span>
                                                <span x-show="service.max_quantity"> | {{ __('Max') }}: <span x-text="service.max_quantity"></span></span>
                                                <span x-show="service.increment > 0"> | {{ __('Increment') }}: <span x-text="service.increment"></span></span>
                                            </div>
                                            <div class="mt-1 text-xs text-gray-600">
                                                <span>{{ __('Rate') }}: $<span x-text="(service.rate_per_1000 || 0).toFixed(2)"></span> {{ __('per 1000') }}</span>
                                            </div>
                                        </div>
                                        <div x-show="isServiceSelected(service.id)" class="w-32">
                                            <label :for="'qty_' + service.id" class="block text-xs font-medium text-gray-700 mb-1">
                                                {{ __('Quantity') }}
                                            </label>
                                            <input
                                                type="number"
                                                :id="'qty_' + service.id"
                                                :name="'services[' + getSelectedServiceIndex(service.id) + '][quantity]'"
                                                :min="service.min_quantity || 1"
                                                :max="service.max_quantity || null"
                                                :step="service.increment > 0 ? service.increment : 1"
                                                x-model.number="getSelectedService(service.id).quantity"
                                                @input="adjustQuantityToIncrement($event.target.value, service.id)"
                                                @change="adjustQuantityToIncrement($event.target.value, service.id)"
                                                required
                                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                            <input type="hidden" :name="'services[' + getSelectedServiceIndex(service.id) + '][service_id]'" :value="service.id">
                                            <div class="mt-1 text-xs text-gray-500">
                                                {{ __('Charge') }}: $<span x-text="calculateServiceCharge(service.id).toFixed(2)"></span>
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
                        <div class="mb-6 p-4 bg-indigo-50 rounded-lg border border-indigo-200" x-show="selectedServices.length > 0">
                            <div class="flex justify-between items-center">
                                <span class="font-medium text-gray-900">{{ __('Total Charge') }}:</span>
                                <span class="text-xl font-bold text-indigo-600">$<span x-text="calculateTotalCharge().toFixed(2)"></span></span>
                            </div>
                            <div class="mt-2 text-sm text-gray-600">
                                <span x-text="selectedServices.length"></span> {{ __('service(s) selected') }}
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="flex items-center justify-end gap-4">
                            <a href="{{ route('client.orders.index') }}"
                               class="text-sm text-gray-600 hover:text-gray-900">
                                {{ __('Cancel') }}
                            </a>
                            <button
                                type="submit"
                                :disabled="submitting || selectedServices.length === 0"
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

    <script>
        function multiOrderForm() {
            return {
                categoryId: '{{ old('category_id', $preselectedCategoryId ?? '') }}',
                link: '{{ old('link', '') }}',
                linkValid: true,
                services: [],
                selectedServices: [],
                loading: false,
                submitting: false,

                init() {
                    if (!Array.isArray(this.services)) {
                        this.services = [];
                    }
                    if (!Array.isArray(this.selectedServices)) {
                        this.selectedServices = [];
                    }
                    if (this.categoryId) {
                        this.loadServices();
                    }
                    // Validate link on init
                    if (this.link) {
                        this.linkValid = this.validateTelegramLink(this.link);
                    }
                },

                validateTelegramLink(link) {
                    if (!link || link.trim() === '') {
                        return true;
                    }
                    const regex = /^(https?:\/\/)?(t\.me|telegram\.me|telegram\.dog)\/([A-Za-z0-9_+\/\-]+(\?[A-Za-z0-9=&_%\-]+)?)$|^@[A-Za-z0-9_]{5,32}$/i;
                    return regex.test(link.trim());
                },

                async loadServices() {
                    if (!this.categoryId) {
                        this.services = [];
                        this.selectedServices = [];
                        return;
                    }

                    this.loading = true;
                    try {
                        const response = await fetch(`{{ route('client.orders.services.by-category') }}?category_id=${this.categoryId}`);
                        const data = await response.json();
                        this.services = Array.isArray(data) ? data : [];
                    } catch (error) {
                        console.error('Error loading services:', error);
                        this.services = [];
                    } finally {
                        this.loading = false;
                    }
                },

                toggleService(service) {
                    const index = this.selectedServices.findIndex(s => s.id === service.id);
                    if (index >= 0) {
                        // Remove service
                        this.selectedServices.splice(index, 1);
                    } else {
                        // Add service with default quantity
                        const defaultQty = service.increment > 0 ? service.increment : (service.min_quantity || 1);
                        this.selectedServices.push({
                            id: service.id,
                            name: service.name,
                            quantity: defaultQty,
                            min_quantity: service.min_quantity || 1,
                            max_quantity: service.max_quantity || null,
                            increment: service.increment || 0,
                            rate_per_1000: service.rate_per_1000 || 0,
                        });
                    }
                },

                isServiceSelected(serviceId) {
                    return this.selectedServices.some(s => s.id === serviceId);
                },

                getSelectedService(serviceId) {
                    return this.selectedServices.find(s => s.id === serviceId) || { quantity: 1 };
                },

                getSelectedServiceIndex(serviceId) {
                    return this.selectedServices.findIndex(s => s.id === serviceId);
                },

                adjustQuantityToIncrement(value, serviceId) {
                    const selected = this.getSelectedService(serviceId);
                    if (!selected || selected.id === undefined) return;

                    const min = Number(selected.min_quantity || 1);
                    const step = Number(selected.increment || 0);
                    const max = selected.max_quantity != null ? Number(selected.max_quantity) : null;

                    let v = Number(value);
                    if (!Number.isFinite(v) || v <= 0) v = min;
                    v = Math.round(v);

                    if (step <= 0) {
                        v = Math.max(min, v);
                        if (max !== null) v = Math.min(max, v);
                        selected.quantity = v;
                        return v;
                    }

                    if (v <= min) {
                        v = min;
                    } else if (v < step) {
                        v = step;
                    } else {
                        v = Math.ceil(v / step) * step;
                        if (v < step) v = step;
                    }

                    if (max !== null) v = Math.min(max, v);
                    selected.quantity = v;
                    return v;
                },

                calculateServiceCharge(serviceId) {
                    const selected = this.getSelectedService(serviceId);
                    if (!selected || selected.id === undefined) return 0;
                    const qty = Number(selected.quantity) || 0;
                    const rate = Number(selected.rate_per_1000) || 0;
                    return Math.round((qty / 1000) * rate * 100) / 100;
                },

                calculateTotalCharge() {
                    return this.selectedServices.reduce((sum, service) => {
                        const qty = Number(service.quantity) || 0;
                        const rate = Number(service.rate_per_1000) || 0;
                        const charge = Math.round((qty / 1000) * rate * 100) / 100;
                        return sum + charge;
                    }, 0);
                },

                submitForm() {
                    if (this.selectedServices.length === 0) {
                        alert('{{ __('Please select at least one service.') }}');
                        return;
                    }

                    if (!this.linkValid) {
                        alert('{{ __('Please enter a valid Telegram link.') }}');
                        return;
                    }

                    this.submitting = true;
                    this.$el.submit();
                }
            }
        }
    </script>
</x-client-layout>

