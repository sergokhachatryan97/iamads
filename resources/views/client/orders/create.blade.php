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
                        @php
                            $errorLabels = [
                                'targets' => __('Links & Quantities'),
                                'services' => __('Services'),
                                'link' => __('Link'),
                                'link_2' => __('Source Channel'),
                                'comments' => __('Comments'),
                                'comment_text' => __('Custom Comment'),
                                'star_rating' => __('Star Rating'),
                                'category_id' => __('Category'),
                                'service_id' => __('Service'),
                                'dripfeed_quantity' => __('Dripfeed Quantity'),
                                'dripfeed_interval' => __('Dripfeed Interval'),
                                'dripfeed_interval_unit' => __('Dripfeed Interval Unit'),
                                'speed_tier' => __('Speed Tier'),
                            ];
                            $formatErrorKey = function ($key) use ($errorLabels) {
                                if (preg_match('/^targets\.(\d+)\.link$/', $key, $m)) {
                                    return __('Link') . ' ' . ((int)$m[1] + 1);
                                }
                                if (preg_match('/^targets\.(\d+)\.quantity$/', $key, $m)) {
                                    return __('Link') . ' ' . ((int)$m[1] + 1) . ' – ' . __('Quantity');
                                }
                                if (preg_match('/^services\.(\d+)\.service_id$/', $key, $m)) {
                                    return __('Service') . ' ' . ((int)$m[1] + 1);
                                }
                                if (preg_match('/^services\.(\d+)\.quantity$/', $key, $m)) {
                                    return __('Service') . ' ' . ((int)$m[1] + 1) . ' – ' . __('Quantity');
                                }
                                return $errorLabels[$key] ?? $key;
                            };
                        @endphp
                        <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                            <ul class="list-disc list-inside">
                                @foreach ($errors->messages() as $key => $messages)
                                    @foreach ($messages as $message)
                                        <li><strong>{{ $formatErrorKey($key) }}:</strong> {{ $message }}</li>
                                    @endforeach
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
                            <input type="hidden" name="form_type" value="single">

                            <!-- Category -->
                            <div class="mb-6">
                                <label for="category_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    {{ __('Category') }} <span class="text-red-500">*</span>
                                </label>
                                <select
                                    id="category_id"
                                    name="category_id"
                                    x-model="categoryId"
                                    @change="onCategoryChange(); if (categoryId) loadServices()"
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
                                    :required="categoryId"
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

                            <!-- Custom Comments Field (only for custom_comments service type) -->
                            <div
                                x-show="selectedService?.service_type === 'custom_comments'"
                                x-transition
                                class="mb-6 rounded-lg border border-gray-200 bg-gray-50 p-4"
                            >
                                <label for="comments" class="block text-sm font-semibold text-gray-800 mb-2">
                                    {{ __('Comments') }}
                                    <span class="text-red-500">*</span>
                                </label>

                                <textarea
                                    id="comments"
                                    name="comments"
                                    x-model="comments"
                                    @input="calculateCommentsTotal(); validateCommentsCount()"
                                    @keyup="calculateCommentsTotal(); validateCommentsCount()"
                                    rows="6"
                                    placeholder="{{ __('Enter comments, one per line') }}"
                                    :required="selectedService?.service_type === 'custom_comments'"
                                    :class="commentsCountError ? 'border-red-300 focus:border-red-500 focus:ring-red-500' : 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500'"
                                    class="block w-full resize-y rounded-md bg-white shadow-sm sm:text-sm"
                                ></textarea>

                                <div class="mt-2 flex items-start gap-2 text-xs text-gray-600">
                                    <svg class="h-4 w-4 text-indigo-500 mt-0.5" fill="none" stroke="currentColor" stroke-width="2"
                                         viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              d="M13 16h-1v-4h-1m1-4h.01M12 18a6 6 0 100-12 6 6 0 000 12z" />
                                    </svg>
                                    <p>
                                        {{ __('Each non-empty line will be processed as a separate comment and will create its own order.') }}
                                        <span x-show="selectedService?.min_quantity" class="font-medium">
                                            {{ __('Minimum') }}: <span x-text="selectedService?.min_quantity || 1"></span> {{ __('comments required') }}.
                                        </span>
                                    </p>
                                </div>

                                <p x-show="commentsCountError" class="mt-2 text-sm text-red-600 font-medium" x-text="commentsCountError"></p>

                                <!-- Real-time Total Calculation -->
                                <div class="mt-3 p-3 bg-indigo-50 rounded-md border border-indigo-200" x-show="selectedService?.service_type === 'custom_comments'">
                                    <div class="flex items-center justify-between">
                                        <div class="text-sm text-gray-700">
                                            <span class="font-medium">{{ __('Comments Count') }}:</span>
                                            <span x-text="commentsCount || 0"></span>
                                        </div>
                                        <div class="text-sm text-gray-700">
                                            <span class="font-medium">{{ __('Rate') }}:</span>
                                            <span>$<span x-text="(selectedService?.rate_per_1000 || 0).toFixed(2)"></span> {{ __('per 1000') }}</span>
                                        </div>
                                    </div>
                                </div>

                                @error('comments')
                                <p class="mt-2 text-sm text-red-600 font-medium">{{ $message }}</p>
                                @enderror
                            </div>


                            <!-- App: Star (app_download_custom_review_star, app_download_positive_review_star) -->
                            <div class="mb-6" x-show="['app_download_custom_review_star','app_download_positive_review_star','app_download_positive_review'].includes(selectedService?.template_key)" x-cloak>
                                <div class="p-4 bg-gray-50 rounded-md border border-gray-200">
                                    <div class="mt-4">
                                        <label for="star_rating" class="block text-sm font-medium text-gray-700 mb-1">
                                            {{ __('Star Rating') }} <span class="text-red-500">*</span>
                                        </label>
                                        <select
                                            id="star_rating"
                                            name="star_rating"
                                            x-model.number="starRating"
                                            :required="['app_download_custom_review_star','app_download_positive_review_star','app_download_positive_review'].includes(selectedService?.template_key)"
                                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm max-w-xs">
                                            <option value="5">5 {{ __('stars') }} ({{ __('default') }})</option>
                                            <option value="4">4 {{ __('stars') }}</option>
                                            <option value="3">3 {{ __('stars') }}</option>
                                            <option value="2">2 {{ __('stars') }}</option>
                                            <option value="1">1 {{ __('star') }}</option>
                                        </select>
                                        <p class="mt-1 text-xs text-gray-500">{{ __('Rating from 1 to 5 stars.') }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Link field for custom_comments (uses category link type) -->
                            <div class="mb-6" x-show="selectedService?.service_type === 'custom_comments'">
                                <label for="comments_link" class="block text-sm font-medium text-gray-700 mb-2">
                                    {{ __('Link') }}
                                    <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="comments_link"
                                    :required="selectedService?.service_type === 'custom_comments'"
                                    name="link"
                                    x-model="commentsLink"
                                    @input="commentsLinkValid = validateLink(commentsLink)"
                                    :class="commentsLinkValid ? 'border-gray-300' : 'border-red-300'"
                                    :placeholder="linkPlaceholder()"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <p x-show="!commentsLinkValid && commentsLink" class="mt-1 text-xs text-red-600" x-text="linkErrorMessage"></p>
                                <p class="mt-1 text-xs text-gray-500">
                                    {{ __('Enter the target link for comments. Format depends on the selected category.') }}
                                </p>
                                @error('link')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Invite Subscribers (2 links: source + target) -->
                            <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4"
                                 x-show="selectedService?.template_key === 'invite_subscribers_from_other_channel'"
                                 x-cloak>
                                <p class="text-sm font-medium text-amber-900 mb-3">
                                    {{ __('Invite Subscribers From Other Channel') }}
                                </p>
                                <p class="text-xs text-amber-800 mb-4">
                                    {{ __('Enter the source channel (invite FROM) and target channel/group (invite TO).') }}
                                </p>
                                <div class="space-y-4">
                                    <div>
                                        <label for="invite_source_link" class="block text-sm font-medium text-gray-700 mb-1">
                                            {{ __('Source Channel (invite FROM)') }} <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            id="invite_source_link"
                                            name="link_2"
                                            :disabled="selectedService?.template_key !== 'invite_subscribers_from_other_channel'"
                                            x-model="inviteSourceLink"
                                            @input="inviteSourceLinkValid = validateTelegramLink(inviteSourceLink)"
                                            :class="(inviteSourceLinkValid && !getFieldError('link_2')) ? 'border-gray-300' : 'border-red-300'"
                                            placeholder="https://t.me/source_channel"
                                            :required="selectedService?.template_key === 'invite_subscribers_from_other_channel'"
                                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <p x-show="!inviteSourceLinkValid && inviteSourceLink" class="mt-1 text-xs text-red-600">
                                            {{ __('Please enter a valid Telegram link.') }}
                                        </p>
                                        @error('link_2')
                                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div>
                                        <label for="invite_target_link" class="block text-sm font-medium text-gray-700 mb-1">
                                            {{ __('Target Channel/Group (invite TO)') }} <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            id="invite_target_link"
                                            name="targets[0][link]"
                                            :disabled="selectedService?.template_key !== 'invite_subscribers_from_other_channel'"
                                            x-model="inviteTargetLink"
                                            @input="inviteTargetLinkValid = validateTelegramLink(inviteTargetLink)"
                                            :class="(inviteTargetLinkValid && !getFieldError('targets.0.link')) ? 'border-gray-300' : 'border-red-300'"
                                            placeholder="https://t.me/target_channel or t.me/+inviteHash"
                                            :required="selectedService?.template_key === 'invite_subscribers_from_other_channel'"
                                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <p x-show="!inviteTargetLinkValid && inviteTargetLink" class="mt-1 text-xs text-red-600">
                                            {{ __('Please enter a valid Telegram link.') }}
                                        </p>
                                        @error('targets.0.link')
                                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div class="w-48">
                                        <label for="invite_quantity" class="block text-sm font-medium text-gray-700 mb-1">
                                            {{ __('Quantity') }} <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="number"
                                            id="invite_quantity"
                                            name="targets[0][quantity]"
                                            :disabled="selectedService?.template_key !== 'invite_subscribers_from_other_channel'"
                                            x-model.number="inviteQuantity"
                                            :min="selectedService?.min_quantity || 1"
                                            :max="selectedService?.max_quantity || null"
                                            :required="selectedService?.template_key === 'invite_subscribers_from_other_channel'"
                                            :class="getFieldError('targets.0.quantity') ? 'border-red-300' : 'border-gray-300'"
                                            class="block w-full rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        @error('targets.0.quantity')
                                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <!-- Telegram Premium Folder (system-managed, quantity fixed to 1) -->
                            <div class="mb-6 rounded-lg border border-indigo-200 bg-indigo-50/80 p-4"
                                 x-show="selectedService?.template_key === 'telegram_premium_folder'"
                                 x-cloak>
                                <p class="text-sm font-medium text-indigo-900 mb-1">
                                    {{ __('Premium folder placement') }}
                                </p>
                                <p class="text-xs text-indigo-800 mb-4" x-show="selectedService?.display_note">
                                    <span>{{ __('Informational') }}:</span>
                                    <span class="font-medium" x-text="selectedService?.display_note"></span>
                                </p>
                                <div class="space-y-4">
                                    <div>
                                        <label for="premium_folder_link" class="block text-sm font-medium text-gray-700 mb-1">
                                            {{ __('Channel or group link') }} <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            id="premium_folder_link"
                                            name="targets[0][link]"
                                            x-model="premiumFolderLink"
                                            @input="premiumFolderLinkValid = validateTelegramLink(premiumFolderLink)"
                                            :class="(premiumFolderLinkValid && !getFieldError('targets.0.link')) ? 'border-gray-300' : 'border-red-300'"
                                            placeholder="https://t.me/channel or t.me/+invite"
                                            :required="selectedService?.template_key === 'telegram_premium_folder'"
                                            :disabled="selectedService?.template_key !== 'telegram_premium_folder'"
                                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <p x-show="!premiumFolderLinkValid && premiumFolderLink" class="mt-1 text-xs text-red-600">
                                            {{ __('Please enter a valid Telegram link.') }}
                                        </p>
                                        @error('targets.0.link')
                                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div class="w-full max-w-xs">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            {{ __('Duration') }}
                                        </label>
                                        <p class="text-sm text-gray-800">30 {{ __('days') }} ({{ __('1 month') }})</p>
                                        <input type="hidden" name="duration_days" value="30">
                                        @error('duration_days')
                                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <input type="hidden" name="targets[0][quantity]" value="1">
                                </div>
                            </div>

                            <!-- Targets (Link + Quantity pairs) — x-if removes fields so premium-folder block is the only targets[0] submitter -->
                            <template x-if="selectedService?.service_type !== 'custom_comments' && selectedService?.template_key !== 'invite_subscribers_from_other_channel' && selectedService?.template_key !== 'telegram_premium_folder'">
                            <div class="mb-6" x-cloak>
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
                                        <div class="flex gap-3 items-start"
                                             :class="getTargetError(index, 'link') || getTargetError(index, 'quantity') ? 'p-3 rounded-md border-2 border-red-300 bg-red-50' : ''">
                                            <div class="flex-1">
                                                <label :for="'targets_' + index + '_link'" class="block text-xs font-medium text-gray-700 mb-1">
                                                    {{ __('Link') }} <span x-text="index + 1"></span> <span class="text-red-500">*</span>
                                                </label>
                                                <input
                                                    type="text"
                                                    :id="'targets_' + index + '_link'"
                                                    :name="'targets[' + index + '][link]'"
                                                    x-model="target.link"
                                                    @input="target.linkValid = validateLink(target.link)"
                                                    :class="(target.linkValid && !getTargetError(index, 'link')) ? 'border-gray-300' : 'border-red-300'"
                                                    :placeholder="linkPlaceholder()"
                                                    :required="selectedService?.service_type !== 'custom_comments' && selectedService?.template_key !== 'invite_subscribers_from_other_channel'"
                                                    :disabled="selectedService?.template_key === 'invite_subscribers_from_other_channel'"
                                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                <p x-show="!target.linkValid && target.link" class="mt-1 text-xs text-red-600" x-text="linkErrorMessage"></p>
                                                <p x-show="getTargetError(index, 'link')" class="mt-1 text-xs text-red-600" x-text="getTargetError(index, 'link')"></p>
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
                                                        :required="selectedService?.service_type !== 'custom_comments' && selectedService?.template_key !== 'invite_subscribers_from_other_channel'"
                                                        :disabled="selectedService?.template_key === 'invite_subscribers_from_other_channel'"
                                                        :class="getTargetError(index, 'quantity') ? 'border-red-300' : 'border-gray-300'"
                                                        class="no-spinner block w-full rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm pr-12">
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
                                                <p x-show="getTargetError(index, 'quantity')" class="mt-1 text-xs text-red-600" x-text="getTargetError(index, 'quantity')"></p>
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
                            </template>
                            <!-- Dripfeed Toggle Switch (only when service has dripfeed enabled) -->
                            <div class="mb-6" x-show="selectedService?.dripfeed_enabled === true">
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-md border border-gray-200 mb-4">
                                    <div>
                                        <label class="text-sm font-medium text-gray-900">{{ __('Enable Dripfeed') }}</label>
                                        <p class="text-xs text-gray-500 mt-1">{{ __('Configure dripfeed settings to deliver orders gradually over time') }}</p>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input
                                            type="checkbox"
                                            x-model="dripfeedEnabled"
                                            class="sr-only peer"
                                        >
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                                    </label>
                                </div>
                                <!-- Hidden input to ensure value is always submitted -->
                                <input type="hidden" name="dripfeed_enabled" :value="dripfeedEnabled ? '1' : '0'">
                            </div>

                            <!-- Dripfeed Fields (only when service has dripfeed enabled AND toggle is ON) -->
                            <div class="mb-6" x-show="selectedService?.dripfeed_enabled === true && dripfeedEnabled === true">
                                <div class="p-4 bg-blue-50 rounded-md border border-blue-200 mb-4">
                                    <h3 class="text-sm font-medium text-blue-900 mb-3">{{ __('Dripfeed Settings') }}</h3>

                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <!-- Dripfeed Quantity -->
                                        <div>
                                            <label for="dripfeed_quantity" class="block text-sm font-medium text-gray-700 mb-1">
                                                {{ __('Quantity per Step') }} <span class="text-red-500">*</span>
                                            </label>
                                            <input
                                                type="number"
                                                id="dripfeed_quantity"
                                                name="dripfeed_quantity"
                                                x-model.number="dripfeedQuantity"
                                                :min="1"
                                                :max="getTotalQuantity()"
                                                :required="dripfeedEnabled"
                                                @input="validateDripfeedQuantity"
                                                :class="dripfeedQuantityError ? 'border-red-300 focus:border-red-500 focus:ring-red-500' : 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500'"
                                                class="block w-full rounded-md shadow-sm sm:text-sm">
                                            <p class="mt-1 text-xs text-gray-500">
                                                {{ __('Amount to deliver per drip step') }}
                                                <span x-show="getTotalQuantity() > 0">
                                                    ({{ __('Max') }}: <span x-text="getTotalQuantity()"></span>)
                                                </span>
                                            </p>
                                            <p x-show="dripfeedQuantityError" class="mt-1 text-sm text-red-600" x-text="dripfeedQuantityError"></p>
                                            @error('dripfeed_quantity')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <!-- Dripfeed Interval -->
                                        <div>
                                            <label for="dripfeed_interval" class="block text-sm font-medium text-gray-700 mb-1">
                                                {{ __('Interval') }} <span class="text-red-500">*</span>
                                            </label>
                                            <input
                                                type="number"
                                                id="dripfeed_interval"
                                                name="dripfeed_interval"
                                                x-model.number="dripfeedInterval"
                                                min="1"
                                                :required="dripfeedEnabled"
                                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                            <p class="mt-1 text-xs text-gray-500">
                                                {{ __('Interval value') }}
                                            </p>
                                            @error('dripfeed_interval')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <!-- Dripfeed Interval Unit -->
                                        <div>
                                            <label for="dripfeed_interval_unit" class="block text-sm font-medium text-gray-700 mb-1">
                                                {{ __('Interval Unit') }} <span class="text-red-500">*</span>
                                            </label>
                                            <select
                                                id="dripfeed_interval_unit"
                                                name="dripfeed_interval_unit"
                                                x-model="dripfeedIntervalUnit"
                                                :required="dripfeedEnabled"
                                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                <option value="">{{ __('Select unit') }}</option>
                                                <option value="minutes">{{ __('Minutes') }}</option>
                                                <option value="hours">{{ __('Hours') }}</option>
                                                <option value="days">{{ __('Days') }}</option>
                                            </select>
                                            @error('dripfeed_interval_unit')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-6" x-show="selectedService?.speed_limit_enabled === true" x-cloak>
                                <input type="hidden" name="speed_tier" :value="speedTier">
                                @error('speed_tier')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Service Info -->
                            <div class="mt-4 p-3 bg-gray-50 rounded-md" x-show="selectedService">
                                <!-- Fixed order price (e.g. premium folder: one unit, no per-1000 framing) -->
                                <div class="text-sm text-gray-800" x-show="selectedService?.hide_quantity === true" x-cloak>
                                    <p>
                                        <span class="font-medium">{{ __('Order price') }}:</span>
                                        $<span x-text="Number(calculateCharge() || 0).toFixed(2)"></span>
                                    </p>
                                </div>

                                <div x-show="selectedService?.hide_quantity !== true">
                                <!-- For custom_comments: show only min/max row count -->
                                <div class="text-xs text-gray-600 mb-2" x-show="selectedService?.service_type === 'custom_comments'">
                                    <span class="font-medium">{{ __('Row Count Rules') }}:</span>
                                    <span x-text="'Min: ' + (selectedService?.min_quantity || 1)"></span>
                                    <span x-show="selectedService?.max_quantity" x-text="' - Max: ' + (selectedService?.max_quantity || '')"></span>
                                </div>
                                <!-- For other services: show quantity rules with increment -->
                                <div class="text-xs text-gray-600 mb-2" x-show="selectedService?.service_type !== 'custom_comments'">
                                    <span class="font-medium">{{ __('Quantity Rules') }}:</span>
                                    <span x-text="'Min: ' + (selectedService?.min_quantity || 1)"></span>
                                    <span x-show="selectedService?.max_quantity" x-text="' - Max: ' + (selectedService?.max_quantity || '')"></span>
                                    <span x-show="selectedService?.increment > 0" x-text="' (Increment: ' + (selectedService?.increment || 0) + ')'"></span>
                                </div>

                                <!-- Rate Info -->
                                <div class="text-xs text-gray-600 mb-2">
                                    <span class="font-medium">{{ __('Default Rate') }}:</span>
                                    <span>$<span x-text="(selectedService?.default_rate_per_1000 || 0).toFixed(2)"></span> {{ __('per 1000') }}</span>
                                </div>

                                <!-- Discount Info -->
                                <div class="text-xs text-blue-600 mb-2" x-show="selectedService?.discount_applies && selectedService?.client_discount > 0">
                                    <span class="font-medium">{{ __('Discount Applied') }}:</span>
                                    <span x-text="selectedService?.client_discount || 0"></span>% {{ __('discount') }}
                                    <span class="text-gray-500">
                                        ({{ __('Final Rate') }}: $<span x-text="(selectedService?.rate_per_1000 || 0).toFixed(2)"></span> {{ __('per 1000') }})
                                    </span>
                                </div>

                                <!-- Custom Rate Info -->
                                <div class="text-xs text-green-600 mb-2" x-show="selectedService?.has_custom_rate && selectedService?.custom_rate">
                                    <span class="font-medium">{{ __('Custom Rate Applied') }}:</span>
                                    <span class="text-gray-500 text-xs">({{ __('Discounts do not apply to services with custom rates') }})</span>
                                    <span x-show="selectedService?.custom_rate?.type === 'fixed'">
                                        <br>$<span x-text="(selectedService?.custom_rate?.value || 0).toFixed(2)"></span> {{ __('per 1000') }} ({{ __('Fixed') }})
                                    </span>
                                    <span x-show="selectedService?.custom_rate?.type === 'percent'">
                                        <br><span x-text="selectedService?.custom_rate?.value || 0"></span>% {{ __('of default') }}
                                        ({{ __('Default') }}: $<span x-text="(selectedService?.default_rate_per_1000 || 0).toFixed(2)"></span>)
                                        <br>{{ __('Final Rate') }}: $<span x-text="(selectedService?.rate_per_1000 || 0).toFixed(2)"></span> {{ __('per 1000') }}
                                    </span>
                                </div>

                                <!-- Final Rate (when no custom rate and no discount) -->
                                <div class="text-xs text-gray-600 mb-2" x-show="!selectedService?.has_custom_rate && !selectedService?.discount_applies">
                                    <span class="font-medium">{{ __('Rate') }}:</span>
                                    <span>$<span x-text="(selectedService?.rate_per_1000 || 0).toFixed(2)"></span> {{ __('per 1000') }}</span>
                                </div>
                                </div>
                            </div>

                            <p class="mt-2 text-sm text-gray-700" x-show="selectedService?.service_type === 'custom_comments' && commentsCount > 0">
                                {{ __('Total quantity') }}: <span x-text="commentsCount"></span> |
                                {{ __('Estimated charge') }}: $<span x-text="(commentsTotalCharge || 0).toFixed(2)"></span>
                            </p>


                            <p class="mt-2 text-sm text-gray-700" x-show="selectedService?.service_type !== 'custom_comments' && selectedService && getTotalQuantity() > 0 && selectedService?.hide_quantity !== true">
                                {{ __('Total quantity') }}: <span x-text="getTotalQuantity()"></span> |
                                {{ __('Estimated charge') }}: $<span x-text="Number(calculateCharge() || 0).toFixed(2)"></span>
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
                                    <span x-show="!submitting">Create</span>
                                    <span x-show="submitting">{{ __('Creating...') }}</span>
                                </button>
                            </div>
                        </form>

                        <!-- Multi-Service Order Form -->
                        <form method="POST" action="{{ route('client.orders.multi-store') }}"
                              x-show="orderType === 'multi'"
                              @submit.prevent="submitMultiForm">
                            @csrf
                            <input type="hidden" name="form_type" value="multi">

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
                                    {{ __('Link') }} <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="multi_link"
                                    name="link"
                                    x-model="multiLink"
                                    @input="multiLinkValid = validateLink(multiLink, multiLinkType)"
                                    :class="multiLinkValid ? 'border-gray-300' : 'border-red-300'"
                                    :placeholder="linkPlaceholder(multiLinkType)"
                                    required
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <p x-show="!multiLinkValid && multiLink" class="mt-1 text-sm text-red-600" x-text="multiLinkErrorMessage"></p>
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
                                        <div class="flex flex-col gap-3 p-3 rounded-md border-2"
                                             :class="getServiceError(index, 'service_id') || getServiceError(index, 'quantity') ? 'border-red-300 bg-red-50' : 'border-gray-200 bg-gray-50'">
                                            <div class="flex gap-3 items-start flex-wrap">
                                                <div class="flex-1 min-w-[200px]">
                                                    <label :for="'multi_service_' + index" class="block text-sm font-medium text-gray-700 mb-1">
                                                        {{ __('Service') }} <span class="text-red-500">*</span>
                                                    </label>
                                                    <select
                                                        :id="'multi_service_' + index"
                                                        :name="'services[' + index + '][service_id]'"
                                                        x-model="serviceRow.service_id"
                                                        @change="updateMultiServiceInfo(index)"
                                                        required
                                                        :class="getServiceError(index, 'service_id') ? 'border-red-300' : 'border-gray-300'"
                                                        class="block w-full rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                        <option value="">{{ __('Select a service') }}</option>
                                                        <template x-for="service in getFilteredServicesForRow(index)" :key="'multi-service-' + service.id">
                                                            <option :value="service.id" x-text="service.name"></option>
                                                        </template>
                                                    </select>
                                                </div>
                                                <div class="w-32">
                                                    <label :for="'multi_qty_' + index" class="block text-sm font-medium text-gray-700 mb-1">
                                                        {{ __('Quantity') }} <span class="text-red-500" x-show="!isMultiRowCustomComments(index)">*</span>
                                                    </label>
                                                    <template x-if="!isMultiRowCustomComments(index)">
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
                                                                :class="getServiceError(index, 'quantity') ? 'border-red-300' : 'border-gray-300'"
                                                                class="no-spinner block w-full rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm pr-12">
                                                            <div class="absolute inset-y-0 right-0 flex flex-col justify-center">
                                                                <button type="button" class="h-5 w-6 rounded-t-md border border-gray-300 bg-gray-50 text-gray-700 hover:bg-gray-100 flex items-center justify-center leading-none" @mousedown.prevent @click="stepMultiQuantity(+1, index)" aria-label="Increase"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3"><path fill-rule="evenodd" d="M10 6a1 1 0 0 1 .707.293l4 4a1 1 0 1 1-1.414 1.414L10 8.414 6.707 11.707A1 1 0 0 1 5.293 10.293l4-4A1 1 0 0 1 10 6z" clip-rule="evenodd"/></svg></button>
                                                                <button type="button" class="h-5 w-6 rounded-b-md border border-t-0 border-gray-300 bg-gray-50 text-gray-700 hover:bg-gray-100 flex items-center justify-center leading-none" @mousedown.prevent @click="stepMultiQuantity(-1, index)" aria-label="Decrease"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3"><path fill-rule="evenodd" d="M10 14a1 1 0 0 1-.707-.293l-4-4a1 1 0 1 1 1.414-1.414L10 11.586l3.293-3.293a1 1 0 1 1 1.414 1.414l-4 4A1 1 0 0 1 10 14z" clip-rule="evenodd"/></svg></button>
                                                            </div>
                                                        </div>
                                                    </template>
                                                    <template x-if="isMultiRowCustomComments(index)">
                                                        <div class="py-2 text-sm text-gray-600">
                                                            {{ __('From comments') }}: <span x-text="getRowCommentsLineCount(index)"></span>
                                                            <input type="hidden" :name="'services[' + index + '][quantity]'" :value="getRowCommentsLineCount(index)">
                                                        </div>
                                                    </template>
                                                    <p x-show="getServiceError(index, 'quantity')" class="mt-1 text-xs text-red-600" x-text="getServiceError(index, 'quantity')"></p>
                                                </div>
                                                <div class="w-32 pt-6">
                                                    <button type="button" @click="removeMultiServiceRow(index)" x-show="multiSelectedServices.length > 1" class="px-3 py-2 text-sm text-red-600 hover:text-red-800 hover:bg-red-50 rounded-md transition-colors">{{ __('Remove') }}</button>
                                                </div>
                                                <div class="w-36 pt-6" x-show="serviceRow.service_id">
                                                    <div class="text-xs text-gray-600">
                                                        <div>{{ __('Rate') }}: $<span x-text="(serviceRow.rate_per_1000 || 0).toFixed(2)"></span>/1000</div>
                                                        <div>{{ __('Charge') }}: $<span x-text="calculateMultiServiceCharge(index).toFixed(2)"></span></div>
                                                        <div class="mt-1 text-gray-500" x-show="!isMultiRowCustomComments(index)">
                                                            <span>{{ __('Min') }}: <span x-text="serviceRow.min_quantity || 1"></span></span>
                                                            <span x-show="serviceRow.max_quantity"> | {{ __('Max') }}: <span x-text="serviceRow.max_quantity"></span></span>
                                                            <span x-show="serviceRow.increment > 0"> | {{ __('Inc') }}: <span x-text="serviceRow.increment"></span></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- Per-service: Comments (custom_comments) -->
                                            <div x-show="isMultiRowCustomComments(index)" class="mt-2 pl-0">
                                                <label :for="'multi_comments_' + index" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Comments') }} <span class="text-red-500">*</span></label>
                                                <textarea
                                                    :id="'multi_comments_' + index"
                                                    :name="'services[' + index + '][comments]'"
                                                    x-model="serviceRow.comments"
                                                    rows="4"
                                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                    placeholder="{{ __('One comment per line') }}"></textarea>
                                                <p class="mt-1 text-xs text-gray-500">{{ __('One comment per line. Quantity = number of comments.') }}</p>
                                            </div>
                                            <!-- Per-service: Star Rating (App review) -->
                                            <div x-show="isMultiRowNeedsStarRating(index)" class="mt-2 pl-0">
                                                <label :for="'multi_star_' + index" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Star Rating') }} <span class="text-red-500">*</span></label>
                                                <select
                                                    :id="'multi_star_' + index"
                                                    :name="'services[' + index + '][star_rating]'"
                                                    x-model.number="serviceRow.star_rating"
                                                    :required="isMultiRowNeedsStarRating(index)"
                                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm max-w-xs">
                                                    <option value="5">5 {{ __('stars') }} ({{ __('default') }})</option>
                                                    <option value="4">4 {{ __('stars') }}</option>
                                                    <option value="3">3 {{ __('stars') }}</option>
                                                    <option value="2">2 {{ __('stars') }}</option>
                                                    <option value="1">1 {{ __('star') }}</option>
                                                </select>
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
                                    <span x-show="!submitting">{{ __('Place Orders') }}</span>
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
                orderType: @json(old('form_type') === 'multi' ? 'multi' : 'single'),
                categoryIdsWithTargetType: @js($categoryIdsWithTargetType ?? []),
                categoryLinkTypes: @js($categoryLinkTypes ?? []),
                get categoryHasTargetType() {
                    if (!this.categoryId) return false;
                    const id = Number(this.categoryId);
                    return Array.isArray(this.categoryIdsWithTargetType) && (this.categoryIdsWithTargetType.includes(id) || this.categoryIdsWithTargetType.includes(String(this.categoryId)));
                },
                get linkType() {
                    const id = this.categoryId;
                    return (id && (this.categoryLinkTypes[id] ?? this.categoryLinkTypes[Number(id)])) || 'generic';
                },
                get multiLinkType() {
                    const id = this.multiCategoryId;
                    return (id && (this.categoryLinkTypes[id] ?? this.categoryLinkTypes[Number(id)])) || 'generic';
                },
                get multiNeedsCommentText() {
                    return (this.multiSelectedServices || []).some(row => {
                        if (!row.service_id) return false;
                        const s = (this.multiServices || []).find(x => x.id == row.service_id);
                        return s && (s.needs_comment_text === true || s.needs_comment_text === 1);
                    });
                },
                get multiNeedsStarRating() {
                    return (this.multiSelectedServices || []).some(row => {
                        if (!row.service_id) return false;
                        const s = (this.multiServices || []).find(x => x.id == row.service_id);
                        return s && (s.needs_star_rating === true || s.needs_star_rating === 1);
                    });
                },
                get multiNeedsComments() {
                    return (this.multiSelectedServices || []).some(row => {
                        if (!row.service_id) return false;
                        const s = (this.multiServices || []).find(x => x.id == row.service_id);
                        return s && s.service_type === 'custom_comments';
                    });
                },
                get multiCommentsLineCount() {
                    if (!this.multiComments || typeof this.multiComments !== 'string') return 0;
                    return this.multiComments.split('\n').map(l => l.trim()).filter(l => l !== '').length;
                },
                getRowCommentsLineCount(index) {
                    const row = this.multiSelectedServices?.[index];
                    if (!row || !row.comments || typeof row.comments !== 'string') return 0;
                    return row.comments.split('\n').map(l => l.trim()).filter(l => l !== '').length;
                },
                isMultiRowCustomComments(index) {
                    const row = this.multiSelectedServices?.[index];
                    if (!row?.service_id) return false;
                    const s = (this.multiServices || []).find(x => x.id == row.service_id);
                    return s && s.service_type === 'custom_comments';
                },
                isMultiRowNeedsStarRating(index) {
                    const row = this.multiSelectedServices?.[index];
                    if (!row?.service_id) return false;
                    const s = (this.multiServices || []).find(x => x.id == row.service_id);
                    return s && (s.needs_star_rating === true || s.needs_star_rating === 1);
                },
                get linkErrorMessage() {
                    return this.linkErrorForType(this.linkType);
                },
                get multiLinkErrorMessage() {
                    return this.linkErrorForType(this.multiLinkType);
                },
                linkErrorForType(type) {
                    const m = {
                        telegram: @json(__('common.link_error_telegram')),
                        youtube: @json(__('common.link_error_youtube')),
                        app: @json(__('common.link_error_app')),
                        max: @json(__('common.link_error_max')),
                        whatsapp: @json(__('common.link_error_whatsapp')),
                        tiktok: @json(__('common.link_error_generic')),
                        instagram: @json(__('common.link_error_generic')),
                        facebook: @json(__('common.link_error_generic')),
                        url: @json(__('common.link_error_generic')),
                        generic: @json(__('common.link_error_generic')),
                    };
                    return m[type] || m.generic;
                },
                // Single service order data
                categoryId: '{{ old('category_id', $preselectedCategoryId ?? '') }}',
                targetType: '{{ old('target_type', $preselectedTargetType ?? '') }}',
                serviceId: '{{ old('service_id', $preselectedServiceId ?? '') }}',
                services: [],
                selectedService: null,
                targets: @js(old('targets') ?: [['link' => '', 'quantity' => 1]]),
                loading: false,
                submitting: false,
                // Custom comments
                comments: @json(old('comments', '')),
                commentsLink: @json(old('link', '')),
                commentsLinkValid: true,
                commentsCount: 0,
                commentsTotalCharge: 0,
                chargePerComment: 0,
                commentsCountError: '',
                // Dripfeed
                dripfeedEnabled: {{ old('dripfeed_enabled', 'false') === 'true' ? 'true' : 'false' }},
                dripfeedQuantity: @json(old('dripfeed_quantity')),
                dripfeedInterval: @json(old('dripfeed_interval')),
                dripfeedIntervalUnit: @json(old('dripfeed_interval_unit')),
                dripfeedQuantityError: '',
                // Speed Tier (default 'fast' when service has speed enabled, set in updateServiceInfo)
                speedTier: '{{ old('speed_tier', 'fast') }}',
                // YouTube combo custom comment / App custom review
                commentText: @json(old('comment_text', '')),
                commentTextError: '',
                starRating: {{ old('star_rating', 5) }},
                // Invite Subscribers (2-link service)
                inviteSourceLink: @json(old('link_2', '')),
                inviteTargetLink: @json(old('targets.0.link', '')),
                inviteSourceLinkValid: true,
                inviteTargetLinkValid: true,
                inviteQuantity: {{ old('targets.0.quantity', 1) }},
                premiumFolderLink: @json(old('targets.0.link', '')),
                premiumFolderLinkValid: true,
                premiumFolderDurationDays: {{ (int) old('duration_days', 30) }},
                // Multi-service order data
                multiCategoryId: '{{ old('category_id', $preselectedCategoryId ?? '') }}',
                multiLink: @json(old('link', '')),
                multiLinkValid: true,
                multiCommentText: @json(old('comment_text', '')),
                multiComments: @json(old('comments', '')),
                multiStarRating: {{ old('star_rating', 5) }},
                multiServices: [],
                validationErrors: @json($errors->messages()),
                multiSelectedServices: @js(
                    !empty(old('services'))
                        ? collect(old('services'))->map(fn($s) => [
                            'service_id' => (string)($s['service_id'] ?? ''),
                            'target_type' => '',
                            'quantity' => (int)($s['quantity'] ?? 1),
                            'min_quantity' => 1,
                            'max_quantity' => null,
                            'increment' => 0,
                            'rate_per_1000' => 0,
                            'comments' => (string)($s['comments'] ?? ''),
                            'star_rating' => (int)($s['star_rating'] ?? 5),
                        ])->values()->all()
                        : [['service_id' => '', 'target_type' => '', 'quantity' => 1, 'min_quantity' => 1, 'max_quantity' => null, 'increment' => 0, 'rate_per_1000' => 0, 'comments' => '', 'star_rating' => 5]]
                ),
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
                                    // Ensure all targets have min_quantity set
                                    if (this.selectedService) {
                                        const minQty = this.selectedService.min_quantity || 1;
                                        this.targets.forEach(target => {
                                            if (!target.quantity || target.quantity < minQty) {
                                                target.quantity = minQty;
                                            }
                                        });
                                    }
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
                                target.linkValid = !target.link || this.validateLink(target.link);
                            }
                            if (!target.quantity || target.quantity < 1) {
                                target.quantity = 1;
                            }
                        });
                    }
                    // Validate multi link on init
                    if (this.multiLink) {
                        this.multiLinkValid = this.validateLink(this.multiLink, this.multiLinkType);
                    }
                    // Validate comments link on init
                    if (this.commentsLink) {
                        this.commentsLinkValid = this.validateLink(this.commentsLink);
                    }
                    // Calculate comments total on init if comments exist
                    if (this.comments) {
                        this.$nextTick(() => {
                            this.calculateCommentsTotal();
                        });
                    }
                },

                onCategoryChange() {},

                validateTelegramLink(link) {
                    if (!link || link.trim() === '') return true;
                    const regex = /^(https?:\/\/)?(t\.me|telegram\.me|telegram\.dog)\/([A-Za-z0-9_+\/\-]+(\?[A-Za-z0-9=&_%\-]+)?)$|^@[A-Za-z0-9_]{3,32}$/i;
                    return regex.test(link.trim());
                },
                validateYoutubeLink(link) {
                    if (!link || link.trim() === '') return true;
                    const v = link.trim();
                    return /^(https?:\/\/)?(www\.)?(youtube\.com\/(watch\?v=[A-Za-z0-9_\-]+(\&[^&\s#]+)*|shorts\/[A-Za-z0-9_\-]+|embed\/[A-Za-z0-9_\-]+|live\/[A-Za-z0-9_\-]+)|youtube\.com\/@[A-Za-z0-9_.\-]+|youtube\.com\/channel\/UC[A-Za-z0-9_\-]+|youtube\.com\/c\/[A-Za-z0-9_.\-]+|youtu\.be\/[A-Za-z0-9_\-]+(\?[^\s#]*)?)/i.test(v);
                },
                validateAppLink(link) {
                    if (!link || link.trim() === '') return true;
                    const v = link.trim();
                    const playStore = /^(https?:\/\/)?(www\.)?play\.google\.com\/store\/apps\/details\?id=[a-zA-Z][a-zA-Z0-9_]*(\.[a-zA-Z][a-zA-Z0-9_]*)+/i;
                    const appStore = /^(https?:\/\/)?(www\.)?apps\.apple\.com\/[a-z]{2}\/app\/[^/]+\/id\d+/i;
                    return playStore.test(v) || appStore.test(v);
                },
                validateMaxLink(link) {
                    if (!link || link.trim() === '') return true;
                    const v = link.trim();
                    return /^(https?:\/\/)?(www\.)?(max\.ru\/[^\s]+|maxapp\.ru\/[^\s]+|web\.maxapp\.ru\/[^\s]+)/i.test(v);
                },
                validateWhatsAppLink(link) {
                    if (!link || link.trim() === '') return true;
                    const v = link.trim();
                    return /^(https?:\/\/)?(www\.)?(wa\.me\/\d+[?\d\w\-=]*|chat\.whatsapp\.com\/[A-Za-z0-9_-]+|api\.whatsapp\.com\/send\?[^\s]+)/i.test(v);
                },
                validateTiktokLink(link) {
                    if (!link || link.trim() === '') return true;
                    const v = link.trim();
                    return /^(https?:\/\/)?(www\.)?(tiktok\.com\/|vm\.tiktok\.com\/|vt\.tiktok\.com\/)[^\s]+/i.test(v);
                },
                validateInstagramLink(link) {
                    if (!link || link.trim() === '') return true;
                    const v = link.trim();
                    return /^(https?:\/\/)?(www\.)?(instagram\.com\/|instagr\.am\/)[^\s]+/i.test(v);
                },
                validateFacebookLink(link) {
                    if (!link || link.trim() === '') return true;
                    const v = link.trim();
                    return /^(https?:\/\/)?(www\.|m\.)?(facebook\.com\/|fb\.com\/|fb\.watch\/|fb\.me\/)[^\s]+/i.test(v);
                },
                validateGenericLink(link) {
                    if (!link || link.trim() === '') return true;
                    const v = link.trim();
                    return v.length >= 5 && (/^https?:\/\//i.test(v) || /^[a-zA-Z0-9][a-zA-Z0-9.-]*\.[a-zA-Z]{2,}/.test(v));
                },
                validateLink(link, type) {
                    const t = type || this.linkType;
                    if (t === 'youtube') return this.validateYoutubeLink(link);
                    if (t === 'app') return this.validateAppLink(link);
                    if (t === 'max') return this.validateMaxLink(link);
                    if (t === 'whatsapp') return this.validateWhatsAppLink(link);
                    if (t === 'tiktok') return this.validateTiktokLink(link);
                    if (t === 'instagram') return this.validateInstagramLink(link);
                    if (t === 'facebook') return this.validateFacebookLink(link);
                    if (t === 'url' || t === 'generic') return this.validateGenericLink(link);
                    return this.validateTelegramLink(link);
                },
                getFieldError(key) {
                    const arr = this.validationErrors?.[key];
                    return Array.isArray(arr) && arr[0] ? arr[0] : null;
                },
                getTargetError(index, field) {
                    return this.getFieldError(`targets.${index}.${field}`);
                },
                getServiceError(index, field) {
                    return this.getFieldError(`services.${index}.${field}`);
                },
                linkPlaceholder(type) {
                    const t = type || this.linkType;
                    const placeholders = {
                        telegram: @json(__('home.link_placeholder_tg')),
                        youtube: @json(__('home.link_placeholder_youtube')),
                        app: @json(__('home.link_placeholder_app')),
                        max: @json(__('home.link_placeholder_max')),
                        whatsapp: @json(__('home.link_placeholder_tg')),
                        tiktok: @json(__('home.link_placeholder_tg')),
                        instagram: @json(__('home.link_placeholder_tg')),
                        facebook: @json(__('home.link_placeholder_tg')),
                        url: @json(__('home.link_placeholder_tg')),
                        generic: @json(__('home.link_placeholder_tg')),
                    };
                    return placeholders[t] || placeholders.generic;
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
                        // Clear selected service if not in filtered list
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
                    // Ensure target quantities meet min; preserve higher values (e.g. from old input)
                    if (this.selectedService) {
                        const defaultQty = this.selectedService.min_quantity || 1;
                        this.targets.forEach(target => {
                            const qty = parseInt(target.quantity) || 0;
                            target.quantity = qty >= defaultQty ? qty : defaultQty;
                        });
                        // Recalculate comments total if service changed and comments exist
                        if (this.comments && this.selectedService.service_type === 'custom_comments') {
                            this.$nextTick(() => {
                                this.calculateCommentsTotal();
                                this.validateCommentsCount();
                            });
                        }
                    }
                    // Reset dripfeed fields if service doesn't have dripfeed enabled
                    if (this.selectedService && !this.selectedService.dripfeed_enabled) {
                        this.dripfeedEnabled = false;
                        this.dripfeedQuantity = null;
                        this.dripfeedInterval = null;
                        this.dripfeedIntervalUnit = '';
                        this.dripfeedQuantityError = '';
                    }
                    if (this.selectedService) {
                        if (!this.selectedService.speed_limit_enabled) {
                            this.speedTier = 'normal';
                        } else {
                            const tierMode = this.selectedService.speed_limit_tier_mode === 'super_fast' ? 'super_fast' : 'fast';
                            this.speedTier = tierMode;
                        }
                    }
                    // Invite subscribers: sync invite quantity with min
                    if (this.selectedService?.template_key === 'invite_subscribers_from_other_channel') {
                        const minQty = this.selectedService.min_quantity || 1;
                        if (!this.inviteQuantity || this.inviteQuantity < minQty) {
                            this.inviteQuantity = minQty;
                        }
                    }
                    if (this.selectedService?.template_key === 'telegram_premium_folder') {
                        const opts = this.selectedService.duration_options || [30];
                        if (!opts.map(Number).includes(Number(this.premiumFolderDurationDays))) {
                            this.premiumFolderDurationDays = opts[0];
                        }
                        const existing = (this.targets[0] && this.targets[0].link) ? this.targets[0].link : this.premiumFolderLink;
                        this.premiumFolderLink = existing || '';
                        this.targets = [{ link: this.premiumFolderLink || '', quantity: 1, linkValid: true }];
                    }
                },

                calculateCommentsTotal() {
                    // Reset values first
                    this.commentsCount = 0;
                    this.commentsTotalCharge = 0;
                    this.chargePerComment = 0;

                    if (!this.selectedService || this.selectedService.service_type !== 'custom_comments') {
                        return;
                    }

                    if (!this.comments || typeof this.comments !== 'string' || this.comments.trim() === '') {
                        return;
                    }

                    // Parse comments: split by newline, trim, filter empty
                    const lines = this.comments.split('\n')
                        .map(line => line.trim())
                        .filter(line => line !== '');

                    this.commentsCount = lines.length;

                    if (this.commentsCount === 0) {
                        return;
                    }

                    // Charge per comment: rate_per_1000 / 1000 (speed tier does not change price)
                    const rate = parseFloat(this.selectedService.rate_per_1000) || 0;
                    this.chargePerComment = Math.round((rate / 1000) * 100) / 100;

                    // Total charge = charge per comment * number of comments
                    this.commentsTotalCharge = Math.round(this.chargePerComment * this.commentsCount * 100) / 100;
                },

                validateCommentsCount() {
                    this.commentsCountError = '';

                    if (!this.selectedService || this.selectedService.service_type !== 'custom_comments') {
                        return;
                    }

                    if (!this.comments || typeof this.comments !== 'string' || this.comments.trim() === '') {
                        if (this.selectedService.min_quantity > 0) {
                            this.commentsCountError = `{{ __('Minimum') }} ${this.selectedService.min_quantity} {{ __('comments required') }}.`;
                        }
                        return;
                    }

                    const minQuantity = this.selectedService.min_quantity || 1;

                    if (this.commentsCount < minQuantity) {
                        this.commentsCountError = `{{ __('Minimum') }} ${minQuantity} {{ __('comments required') }}. {{ __('You have entered') }} ${this.commentsCount} {{ __('comment(s)') }}.`;
                    }
                },

                // getTotalRowQuantity() {
                //     return this.commentsCount
                // },
                //
                // getCommentsTotalCharge() {
                //     return this.commentsTotalCharge
                // },

                validateDripfeedQuantity() {
                    this.dripfeedQuantityError = '';
                    if (!this.dripfeedEnabled || !this.dripfeedQuantity) {
                        return;
                    }
                    const totalQty = this.getTotalQuantity();
                    if (totalQty > 0 && this.dripfeedQuantity > totalQty) {
                        this.dripfeedQuantityError = '{{ __('Quantity per step cannot be greater than total order quantity') }}';
                    }
                },

                addTargetRow() {
                    const defaultQty = this.selectedService ? (this.selectedService.min_quantity || 1) : 1;
                    this.targets.push({ link: '', quantity: defaultQty, linkValid: true });
                },

                removeTargetRow(index) {
                    if (this.targets.length > 1) {
                        this.targets.splice(index, 1);
                    }
                },

                getTotalQuantity() {
                    if (this.selectedService?.template_key === 'telegram_premium_folder') {
                        return 1;
                    }
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
                    if (!this.selectedService) return 0;
                    const rate = Number(this.selectedService.rate_per_1000) || 0;
                    if (this.selectedService.hide_quantity === true) {
                        return Number(rate.toFixed(2));
                    }
                    const totalQty = this.getTotalQuantity();
                    return Number(((totalQty * rate) / 1000).toFixed(2));
                },

                submitForm(event) {
                    // For custom_comments, validate comments count and remove empty targets before submit
                    if (this.selectedService?.service_type === 'custom_comments') {
                        this.validateCommentsCount();
                        if (this.commentsCountError) {
                            this.submitting = false;
                            return;
                        }

                        const form = event?.target?.closest('form') || this.$el.querySelector('form[action="{{ route('client.orders.store') }}"]');
                        if (form) {
                            // Remove all targets inputs for custom_comments
                            const targetInputs = form.querySelectorAll('input[name^="targets["]');
                            targetInputs.forEach(input => {
                                input.remove();
                            });
                        }
                    }

                    // Validate dripfeed quantity before submit
                    if (this.dripfeedEnabled) {
                        this.validateDripfeedQuantity();
                        if (this.dripfeedQuantityError) {
                            this.submitting = false;
                            return;
                        }
                    }
                    // Validate invite_subscribers 2-link mode
                    if (this.selectedService?.template_key === 'invite_subscribers_from_other_channel') {
                        if (!this.inviteSourceLinkValid || !this.inviteTargetLinkValid || !this.inviteSourceLink?.trim() || !this.inviteTargetLink?.trim()) {
                            alert('{{ __('Please enter valid source and target Telegram links.') }}');
                            this.submitting = false;
                            return;
                        }
                    }
                    if (this.selectedService?.template_key === 'telegram_premium_folder') {
                        if (!this.premiumFolderLinkValid || !this.premiumFolderLink?.trim()) {
                            alert('{{ __('Please enter a valid Telegram channel or group link.') }}');
                            this.submitting = false;
                            return;
                        }
                    }
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
                        // Load all services for the category (no target_type filter at global level)
                        const url = `{{ route('client.orders.services.by-category') }}?category_id=${this.multiCategoryId}`;
                        const response = await fetch(url);
                        const data = await response.json();
                        this.multiServices = Array.isArray(data) ? data : [];
                        // Clear selected services only if no old input (validation redirect keeps selections)
                        const hasOldSelections = Array.isArray(this.multiSelectedServices) &&
                            this.multiSelectedServices.some(row => row.service_id);
                        if (!hasOldSelections) {
                            this.multiSelectedServices = [{ service_id: '', target_type: '', quantity: 1, min_quantity: 1, max_quantity: null, increment: 0, rate_per_1000: 0 }];
                        } else {
                            // Populate service metadata for old selections
                            this.multiSelectedServices.forEach((row, i) => this.updateMultiServiceInfo(i));
                        }
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
                        target_type: '',
                        quantity: 1,
                        min_quantity: 1,
                        max_quantity: null,
                        increment: 0,
                        rate_per_1000: 0,
                        comments: '',
                        star_rating: 5,
                    });
                },

                removeMultiServiceRow(index) {
                    if (this.multiSelectedServices.length > 1) {
                        this.multiSelectedServices.splice(index, 1);
                    }
                },

                getFilteredServicesForRow(index) {
                    return Array.isArray(this.multiServices) ? this.multiServices : [];
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
                        const defaultQty = service.min_quantity || 1;
                        const currentQty = parseInt(serviceRow.quantity) || 0;
                        serviceRow.quantity = currentQty >= defaultQty ? currentQty : defaultQty;
                        if (service.service_type === 'custom_comments' && serviceRow.comments === undefined) {
                            serviceRow.comments = serviceRow.comments ?? '';
                        }
                        if ((service.needs_star_rating === true || service.needs_star_rating === 1) && serviceRow.star_rating === undefined) {
                            serviceRow.star_rating = serviceRow.star_rating ?? 5;
                        }
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
                    const qty = this.isMultiRowCustomComments(index) ? this.getRowCommentsLineCount(index) : (Number(serviceRow.quantity) || 0);
                    const rate = Number(serviceRow.rate_per_1000) || 0;
                    return Math.round((qty / 1000) * rate * 100) / 100;
                },

                calculateMultiTotalCharge() {
                    return this.multiSelectedServices.reduce((sum, serviceRow, index) => {
                        if (!serviceRow.service_id) return sum;
                        const qty = this.isMultiRowCustomComments(index) ? this.getRowCommentsLineCount(index) : (Number(serviceRow.quantity) || 0);
                        const rate = Number(serviceRow.rate_per_1000) || 0;
                        const charge = Math.round((qty / 1000) * rate * 100) / 100;
                        return sum + charge;
                    }, 0);
                },

                submitMultiForm(event) {
                    if (this.multiSelectedServices.length === 0) {
                        alert('{{ __('Please select at least one service.') }}');
                        return;
                    }

                    if (!this.multiLinkValid) {
                        alert('{{ __('Please enter a valid link.') }}');
                        return;
                    }

                    for (let i = 0; i < this.multiSelectedServices.length; i++) {
                        if (this.isMultiRowCustomComments(i) && this.getRowCommentsLineCount(i) < 1) {
                            alert('{{ __('Please enter at least one comment (one per line) for custom comments service.') }}');
                            return;
                        }
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
