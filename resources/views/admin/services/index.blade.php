<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Services') }}
            </h2>
            <button type="button"
                    @click="openCreate()"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition ease-in-out duration-150">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                {{ __('Add service') }}
            </button>
        </div>
    </x-slot>

    <div class="py-12" x-data="serviceManagement(@js($services ?? []), @js($categories ?? []))">
        <div class="max-w-[95%] mx-auto sm:px-6 lg:px-8">
            <!-- Success Message -->
            <div x-show="successMessage"
                 x-cloak
                 x-transition
                 class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                <p class="text-sm text-green-800" x-text="successMessage"></p>
            </div>

            <!-- Error Messages -->
            @if ($errors->any())
                <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                    <ul class="text-sm text-red-800">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Services Table -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Name
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Category
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Rate per 1000
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Min / Max
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Active
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <template x-for="service in services" :key="service.id">
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900" x-text="service.name"></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="service.category?.name || '-'"></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="parseFloat(service.rate_per_1000).toFixed(4)"></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <span x-text="service.min_quantity"></span> / <span x-text="service.max_quantity"></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span x-show="service.is_active" class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Yes</span>
                                        <span x-show="!service.is_active" class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">No</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button type="button"
                                                @click="openEdit(service)"
                                                class="text-indigo-600 hover:text-indigo-900">
                                            Edit
                                        </button>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="services.length === 0">
                                <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                                    {{ __('No services found.') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <div x-show="isOpen"
             x-cloak
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-[9999] overflow-y-auto"
             style="display: none;"
             @click.away="closeModal()"
             @keydown.escape.window="closeModal()">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <!-- Background overlay -->
                <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75"></div>

                <!-- Modal panel -->
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full"
                     @click.stop>
                    <form :action="actionUrl"
                          method="POST"
                          @submit.prevent="submitForm()"
                          @change="
                            if ($event.target.name === 'mode' && $event.target.type === 'hidden') {
                                formData.mode = $event.target.value;
                            }
                          "
                          x-ref="serviceForm">
                        @csrf
                        <input type="hidden" name="_method" :value="isEdit ? 'PUT' : 'POST'">

                        <!-- Modal Header -->
                        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900" x-text="isEdit ? 'Edit Service' : 'Create Service'"></h3>
                            <button type="button"
                                    @click="closeModal()"
                                    class="text-gray-400 hover:text-gray-500 focus:outline-none">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>

                        <!-- Modal Body -->
                        <div class="px-6 py-4 max-h-[calc(100vh-200px)] overflow-y-auto space-y-6">
                            <!-- 1. Basic info -->
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 mb-4">Basic info</h4>
                                <div class="space-y-4">
                                    <div>
                                        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                                            Service name <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text"
                                               id="name"
                                               name="name"
                                               x-model="formData.name"
                                               required
                                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>

                                    <div>
                                        <label for="category_id" class="block text-sm font-medium text-gray-700 mb-2">
                                            Category <span class="text-red-500">*</span>
                                        </label>
                                        <x-custom-select
                                            name="category_id"
                                            id="category_id"
                                            :value="formData.category_id"
                                            placeholder="Choose category"
                                            :options="collect($categories)->mapWithKeys(fn($category) => [$category->id => $category->name])->toArray()"
                                        />
                                    </div>

                                    <div>
                                        <label for="mode" class="block text-sm font-medium text-gray-700 mb-2">
                                            Mode <span class="text-red-500">*</span>
                                        </label>
                                        <x-custom-select
                                            name="mode"
                                            id="mode"
                                            :value="formData.mode"
                                            placeholder="{{ __('Select mode') }}"
                                            :options="['manual' => 'Manual', 'provider' => 'Provider']"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- 2. Mode & options (only for manual mode) -->
                            <div x-show="formData.mode === 'manual'" x-cloak>
                                <h4 class="text-sm font-medium text-gray-900 mb-4">Mode & options</h4>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label for="service_type" class="block text-sm font-medium text-gray-700 mb-2">
                                            Service type
                                        </label>
                                        <x-custom-select
                                            name="service_type"
                                            id="service_type"
                                            :value="formData.service_type"
                                            placeholder="Default"
                                            :options="['default' => 'Default', 'custom' => 'Custom']"
                                        />
                                    </div>

                                    <div>
                                        <label for="dripfeed_enabled" class="block text-sm font-medium text-gray-700 mb-2">
                                            Drip-feed
                                        </label>
                                        <x-custom-select
                                            name="dripfeed_enabled"
                                            id="dripfeed_enabled"
                                            :value="formData.dripfeed_enabled ? '1' : '0'"
                                            placeholder="{{ __('Select') }}"
                                            :options="['0' => 'Disallow', '1' => 'Allow']"
                                        />
                                    </div>

                                    <div>
                                        <label for="user_can_cancel" class="block text-sm font-medium text-gray-700 mb-2">
                                            Cancel
                                        </label>
                                        <x-custom-select
                                            name="user_can_cancel"
                                            id="user_can_cancel"
                                            :value="formData.user_can_cancel ? '1' : '0'"
                                            placeholder="{{ __('Select') }}"
                                            :options="['0' => 'Disallow', '1' => 'Allow']"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- 3. Rate per 1000 block -->
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 mb-4">Rate per 1000 block</h4>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label for="rate_per_1000" class="block text-xs text-gray-600 mb-1">
                                            Service rate <span class="text-red-500">*</span>
                                        </label>
                                        <input type="number"
                                               id="rate_per_1000"
                                               name="rate_per_1000"
                                               x-model="formData.rate_per_1000"
                                               step="0.0001"
                                               min="0"
                                               required
                                               placeholder="Service rate"
                                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>

                                    <div>
                                        <label for="service_cost_per_1000" class="block text-xs text-gray-600 mb-1 flex items-center gap-1">
                                            Service cost (optional)
                                            <svg class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"></path>
                                            </svg>
                                        </label>
                                        <input type="number"
                                               id="service_cost_per_1000"
                                               name="service_cost_per_1000"
                                               x-model="formData.service_cost_per_1000"
                                               step="0.0001"
                                               min="0"
                                               placeholder="Service cost (optional)"
                                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                </div>
                            </div>

                            <!-- 4. Quantity limits -->
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 mb-4">Quantity limits</h4>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label for="min_quantity" class="block text-sm font-medium text-gray-700 mb-2">
                                            Min quantity <span class="text-red-500">*</span>
                                        </label>
                                        <input type="number"
                                               id="min_quantity"
                                               name="min_quantity"
                                               x-model="formData.min_quantity"
                                               min="1"
                                               required
                                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>

                                    <div>
                                        <label for="max_quantity" class="block text-sm font-medium text-gray-700 mb-2">
                                            Max quantity <span class="text-red-500">*</span>
                                        </label>
                                        <input type="number"
                                               id="max_quantity"
                                               name="max_quantity"
                                               x-model="formData.max_quantity"
                                               min="1"
                                               required
                                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                </div>
                            </div>

                            <!-- 5. Deny link duplicates -->
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 mb-4">Deny link duplicates</h4>
                                <div class="space-y-4">
                                    <div>
                                        <label for="deny_link_duplicates" class="block text-sm font-medium text-gray-700 mb-2">
                                            Deny link duplicates
                                        </label>
                                        <x-custom-select
                                            name="deny_link_duplicates"
                                            id="deny_link_duplicates"
                                            :value="formData.deny_link_duplicates ? '1' : '0'"
                                            placeholder="{{ __('Select') }}"
                                            :options="['0' => 'No', '1' => 'Yes']"
                                        />
                                    </div>

                                    <div x-show="formData.deny_link_duplicates" x-cloak>
                                        <label for="deny_duplicates_days" class="block text-sm font-medium text-gray-700 mb-2">
                                            Deny duplicates days <span class="text-red-500">*</span>
                                        </label>
                                        <input type="number"
                                               id="deny_duplicates_days"
                                               name="deny_duplicates_days"
                                               x-model="formData.deny_duplicates_days"
                                               min="0"
                                               max="65535"
                                               :required="formData.deny_link_duplicates"
                                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                </div>
                            </div>

                            <!-- 6. Increment -->
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 mb-4">Increment</h4>
                                <div>
                                    <label for="increment" class="block text-sm font-medium text-gray-700 mb-2">
                                        Increment <span class="text-red-500">*</span>
                                    </label>
                                    <input type="number"
                                           id="increment"
                                           name="increment"
                                           x-model="formData.increment"
                                           min="0"
                                           required
                                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                </div>
                            </div>

                            <!-- 7. Start count parsing -->
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 mb-4">Start count parsing</h4>
                                <div class="space-y-4">
                                    <label class="flex items-center gap-3">
                                        <button type="button"
                                                @click="formData.start_count_parsing_enabled = !formData.start_count_parsing_enabled; if (!formData.start_count_parsing_enabled) formData.auto_complete_enabled = false;"
                                                class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                                :class="formData.start_count_parsing_enabled ? 'bg-indigo-600' : 'bg-gray-200'"
                                                role="switch"
                                                :aria-checked="formData.start_count_parsing_enabled">
                                            <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                                  :class="formData.start_count_parsing_enabled ? 'translate-x-5' : 'translate-x-0'"></span>
                                        </button>
                                        <input type="hidden"
                                               name="start_count_parsing_enabled"
                                               :value="formData.start_count_parsing_enabled ? '1' : '0'">
                                        <span class="text-sm font-medium text-gray-700">Start count parsing</span>
                                    </label>

                                    <div x-show="formData.start_count_parsing_enabled" x-cloak class="mt-3">
                                        <label for="count_type" class="block text-sm font-medium text-gray-700 mb-2">
                                            Count type <span class="text-red-500">*</span>
                                        </label>
                                        <x-custom-select
                                            name="count_type"
                                            id="count_type"
                                            :value="formData.count_type"
                                            placeholder="{{ __('Select count type') }}"
                                            :options="[
                                                'telegram_members' => 'Telegram Members',
                                                'instagram_likes' => 'Instagram Likes',
                                                'instagram_followers' => 'Instagram Followers',
                                                'youtube_views' => 'YouTube Views'
                                            ]"
                                            :required="formData.start_count_parsing_enabled"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- 8. Auto complete -->
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 mb-4">Auto complete</h4>
                                <label class="flex items-center gap-3">
                                    <button type="button"
                                            @click="if (formData.start_count_parsing_enabled) { formData.auto_complete_enabled = !formData.auto_complete_enabled; }"
                                            :disabled="!formData.start_count_parsing_enabled"
                                            class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                                            :class="formData.auto_complete_enabled ? 'bg-indigo-600' : 'bg-gray-200'"
                                            role="switch"
                                            :aria-checked="formData.auto_complete_enabled">
                                        <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                              :class="formData.auto_complete_enabled ? 'translate-x-5' : 'translate-x-0'"></span>
                                    </button>
                                    <input type="hidden"
                                           name="auto_complete_enabled"
                                           :value="formData.auto_complete_enabled ? '1' : '0'">
                                    <span class="text-sm font-medium text-gray-700" :class="{ 'text-gray-400': !formData.start_count_parsing_enabled }">
                                        Auto Complete
                                    </span>
                                </label>
                            </div>

                            <!-- 9. Refill -->
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 mb-4">Refill</h4>
                                <label class="flex items-center gap-3">
                                    <button type="button"
                                            @click="formData.refill_enabled = !formData.refill_enabled"
                                            class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                            :class="formData.refill_enabled ? 'bg-indigo-600' : 'bg-gray-200'"
                                            role="switch"
                                            :aria-checked="formData.refill_enabled">
                                        <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                              :class="formData.refill_enabled ? 'translate-x-5' : 'translate-x-0'"></span>
                                    </button>
                                    <input type="hidden"
                                           name="refill_enabled"
                                           :value="formData.refill_enabled ? '1' : '0'">
                                    <span class="text-sm font-medium text-gray-700">Refill</span>
                                </label>
                            </div>

                            <!-- 10. Status -->
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 mb-4">Status</h4>
                                <label class="flex items-center gap-3">
                                    <button type="button"
                                            @click="formData.is_active = !formData.is_active"
                                            class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                            :class="formData.is_active ? 'bg-indigo-600' : 'bg-gray-200'"
                                            role="switch"
                                            :aria-checked="formData.is_active">
                                        <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                              :class="formData.is_active ? 'translate-x-5' : 'translate-x-0'"></span>
                                    </button>
                                    <input type="hidden"
                                           name="is_active"
                                           :value="formData.is_active ? '1' : '0'">
                                    <span class="text-sm font-medium text-gray-700">Active</span>
                                </label>
                            </div>

                            <!-- 11. Description -->
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 mb-4">Description</h4>
                                <div>
                                    <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                                        Description
                                    </label>
                                    <textarea id="description"
                                              name="description"
                                              x-model="formData.description"
                                              rows="4"
                                              class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Modal Footer -->
                        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50">
                            <button type="button"
                                    @click="closeModal()"
                                    class="inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                Cancel
                            </button>
                            <button type="submit"
                                    class="inline-flex justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                Save
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function serviceManagement(servicesData, categoriesData) {
            return {
                services: servicesData || [],
                categories: categoriesData || [],
                isOpen: false,
                isEdit: false,
                actionUrl: '',
                successMessage: @json(session('success')),
                formData: {
                    id: null,
                    name: '',
                    description: '',
                    category_id: '',
                    mode: @json(\App\Models\Service::MODE_DEFAULT),
                    service_type: 'default',
                    dripfeed_enabled: false,
                    user_can_cancel: false,
                    rate_per_1000: 0,
                    service_cost_per_1000: null,
                    min_quantity: 1,
                    max_quantity: 1,
                    deny_link_duplicates: false,
                    deny_duplicates_days: 90,
                    increment: 0,
                    start_count_parsing_enabled: false,
                    count_type: '',
                    auto_complete_enabled: false,
                    refill_enabled: false,
                    is_active: true,
                },
                init() {
                    // Watch for mode changes from custom-select component
                    this.$watch('formData.mode', (newMode) => {
                        // Mode is already updated in formData
                    });
                },
                openCreate() {
                    this.isEdit = false;
                    this.actionUrl = '{{ route('admin.services.store') }}';
                    this.resetForm();
                    this.isOpen = true;
                    this.$nextTick(() => {
                        this.setupModeWatcher();
                    });
                },
                setupModeWatcher() {
                    // This is now handled by x-effect in the template
                },
                openEdit(service) {
                    this.isEdit = true;
                    this.actionUrl = `{{ route('admin.services.update', '') }}/${service.id}`;
                    this.formData = {
                        id: service.id,
                        name: service.name || '',
                        description: service.description || '',
                        category_id: String(service.category_id || ''),
                        mode: service.mode || @json(\App\Models\Service::MODE_DEFAULT),
                        service_type: service.service_type || 'default',
                        dripfeed_enabled: Boolean(service.dripfeed_enabled),
                        user_can_cancel: Boolean(service.user_can_cancel),
                        rate_per_1000: parseFloat(service.rate_per_1000 || 0),
                        service_cost_per_1000: service.service_cost_per_1000 ? parseFloat(service.service_cost_per_1000) : null,
                        min_quantity: parseInt(service.min_quantity || 1),
                        max_quantity: parseInt(service.max_quantity || 1),
                        deny_link_duplicates: Boolean(service.deny_link_duplicates),
                        deny_duplicates_days: parseInt(service.deny_duplicates_days || 90),
                        increment: parseInt(service.increment || 0),
                        start_count_parsing_enabled: Boolean(service.start_count_parsing_enabled),
                        count_type: service.count_type || '',
                        auto_complete_enabled: Boolean(service.auto_complete_enabled),
                        refill_enabled: Boolean(service.refill_enabled),
                        is_active: Boolean(service.is_active),
                    };
                    this.isOpen = true;
                    this.$nextTick(() => {
                        this.syncCustomSelects();
                        this.setupModeWatcher();
                    });
                },
                closeModal() {
                    this.isOpen = false;
                    this.resetForm();
                },
                resetForm() {
                    this.formData = {
                        id: null,
                        name: '',
                        description: '',
                        category_id: '',
                        mode: @json(\App\Models\Service::MODE_DEFAULT),
                        service_type: 'default',
                        dripfeed_enabled: false,
                        user_can_cancel: false,
                        rate_per_1000: 0,
                        service_cost_per_1000: null,
                        min_quantity: 1,
                        max_quantity: 1,
                        deny_link_duplicates: false,
                        deny_duplicates_days: 90,
                        increment: 0,
                        start_count_parsing_enabled: false,
                        count_type: '',
                        auto_complete_enabled: false,
                        refill_enabled: false,
                        is_active: true,
                    };
                },
                syncCustomSelects() {
                    const form = this.$refs.serviceForm;
                    if (!form) return;

                    const updateHiddenInput = (name, value) => {
                        const hidden = form.querySelector(`input[name="${name}"][type="hidden"]`);
                        if (hidden) {
                            hidden.value = value;
                        }
                    };

                    // Update custom-select hidden inputs
                    const categoryHidden = form.querySelector('input[name="category_id"][type="hidden"]');
                    if (categoryHidden) {
                        categoryHidden.value = String(this.formData.category_id || '');
                    }

                    const modeHidden = form.querySelector('input[name="mode"][type="hidden"]');
                    if (modeHidden) {
                        modeHidden.value = this.formData.mode || @json(\App\Models\Service::MODE_DEFAULT);
                        // Update formData.mode from hidden input if it changed
                        this.formData.mode = modeHidden.value;
                    }

                    const serviceTypeHidden = form.querySelector('input[name="service_type"][type="hidden"]');
                    if (serviceTypeHidden) {
                        serviceTypeHidden.value = this.formData.service_type || 'default';
                    }

                    updateHiddenInput('dripfeed_enabled', this.formData.dripfeed_enabled ? '1' : '0');
                    updateHiddenInput('user_can_cancel', this.formData.user_can_cancel ? '1' : '0');
                    updateHiddenInput('deny_link_duplicates', this.formData.deny_link_duplicates ? '1' : '0');

                    const countTypeHidden = form.querySelector('input[name="count_type"][type="hidden"]');
                    if (countTypeHidden) {
                        countTypeHidden.value = this.formData.count_type || '';
                    }
                },
                init() {
                    // Watch for mode changes from custom-select component
                    this.$watch('formData.mode', (newMode) => {
                        const modeHidden = this.$refs.serviceForm?.querySelector('input[name="mode"][type="hidden"]');
                        if (modeHidden) {
                            modeHidden.value = newMode;
                        }
                    });

                    // Listen for custom-select changes
                    this.$nextTick(() => {
                        const form = this.$refs.serviceForm;
                        if (form) {
                            const modeHidden = form.querySelector('input[name="mode"][type="hidden"]');
                            if (modeHidden) {
                                modeHidden.addEventListener('change', () => {
                                    this.formData.mode = modeHidden.value;
                                });
                            }
                        }
                    });
                },
                submitForm() {
                    const form = this.$refs.serviceForm;
                    if (!form) return;

                    // Sync all form values before submit
                    this.syncCustomSelects();

                    // Submit the form normally (Laravel will handle redirect)
                    form.submit();
                }
            };
        }
    </script>
</x-app-layout>
