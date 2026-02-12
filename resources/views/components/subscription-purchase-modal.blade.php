@props([
    'name',
    'planId' => null,
    'planName' => '',
    'price' => null,
    'currency' => 'USD',
    'clientBalance' => 0,
    'show' => false,
])

<x-modal :name="$name" :show="$show" maxWidth="md">
    <div class="p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">
                {{ __('Activate Subscription') }}
            </h3>
            <button
                type="button"
                @click="$dispatch('close-modal', '{{ $name }}')"
                class="text-gray-400 hover:text-gray-500"
            >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <div class="mb-4">
            <p class="text-sm text-gray-600 mb-2">
                <span class="font-medium">{{ __('Plan') }}:</span> {{ $planName }}
            </p>
            @if($price !== null)
                <p class="text-sm text-gray-600 mb-2">
                    <span class="font-medium">{{ __('Price') }}:</span> {{ $currency }} {{ number_format($price, 2) }} / {{ __('month') }}
                </p>
            @endif
            <p class="text-xs text-gray-500">
                <span class="font-medium">{{ __('Your Balance') }}:</span> 
                <span>{{ $currency }} {{ number_format($clientBalance, 2) }}</span>
            </p>
        </div>

        <form 
            method="POST" 
            action="{{ route('client.subscriptions.purchase', $planId) }}"
            x-data="{
                links: [{ value: '', valid: true }],
                errors: {},
                submitting: false,
                validateLink(link) {
                    const trimmed = link.trim();
                    
                    if (!trimmed) {
                        return false;
                    }
                    
                    // Must not contain spaces
                    if (trimmed.indexOf(' ') !== -1) {
                        return false;
                    }
                    
                    // Check if it's a @username format
                    if (trimmed.match(/^@[A-Za-z0-9_]{3,}$/i)) {
                        return true; // Valid @username format
                    }
                    
                    // Must start with https://t.me/ or t.me/
                    if (!trimmed.match(/^https?:\/\/t\.me\//i) && !trimmed.match(/^t\.me\//i)) {
                        return false;
                    }
                    
                    // Must match regex for Telegram links
                    if (!trimmed.match(/^https?:\/\/t\.me\/[A-Za-z0-9_]{3,}$/i) && 
                        !trimmed.match(/^https?:\/\/t\.me\/\+[A-Za-z0-9_-]{10,}$/i) &&
                        !trimmed.match(/^t\.me\/[A-Za-z0-9_]{3,}$/i) &&
                        !trimmed.match(/^t\.me\/\+[A-Za-z0-9_-]{10,}$/i)) {
                        return false;
                    }
                    
                    return true;
                },
                addLink() {
                    this.links.push({ value: '', valid: true });
                },
                removeLink(index) {
                    if (this.links.length > 1) {
                        this.links.splice(index, 1);
                    }
                },
                handleSubmit(e) {
                    e.preventDefault();
                    
                    // Validate all links
                    let hasError = false;
                    this.errors = {};
                    
                    this.links.forEach((link, index) => {
                        if (!link.value.trim()) {
                            this.errors[index] = '{{ __('Please enter a valid Telegram link.') }}';
                            hasError = true;
                        } else if (!this.validateLink(link.value)) {
                            this.errors[index] = '{{ __('Please enter a valid Telegram link.') }}';
                            hasError = true;
                        } else {
                            this.errors[index] = '';
                        }
                    });
                    
                    if (hasError) {
                        return;
                    }
                    
                    this.submitting = true;
                    e.target.submit();
                }
            }"
            @submit="handleSubmit"
        >
            @csrf
            <input type="hidden" name="billing_cycle" value="monthly">

            <div class="mb-4">
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-sm font-medium text-gray-700">
                        {{ __('Telegram Links') }} <span class="text-red-500">*</span>
                    </label>
                    <button
                        type="button"
                        @click="addLink()"
                        class="inline-flex items-center px-2 py-1 text-xs font-medium text-indigo-700 bg-indigo-100 hover:bg-indigo-200 rounded-md">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        {{ __('Add Link') }}
                    </button>
                </div>
                
                <template x-for="(link, index) in links" :key="index">
                    <div class="mb-3">
                        <div class="flex gap-2">
                            <div class="flex-1">
                                <input
                                    type="text"
                                    :name="'links[' + index + ']'"
                                    x-model="link.value"
                                    @input="link.valid = validateLink(link.value); errors[index] = ''"
                                    @blur="link.valid = validateLink(link.value)"
                                    :class="link.valid ? 'border-gray-300' : 'border-red-300'"
                                    placeholder="@username, https://t.me/yourchannel or https://t.me/+invitecode"
                                    class="block w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    required
                                >
                                <p x-show="errors[index]" x-text="errors[index]" class="mt-1 text-sm text-red-600"></p>
                            </div>
                            <button
                                type="button"
                                @click="removeLink(index)"
                                x-show="links.length > 1"
                                class="px-3 py-2 text-sm text-red-600 hover:text-red-800 hover:bg-red-50 rounded-md transition-colors">
                                {{ __('Remove') }}
                            </button>
                        </div>
                    </div>
                </template>
                
                <p class="mt-1 text-xs text-gray-500">
                    {{ __('Paste your Telegram channel/group links. If multiple links, service quantity will be divided equally. Example: @username, https://t.me/xxx or https://t.me/+invite') }}
                </p>
                
                {{-- Server-side validation errors --}}
                @error('links')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                @error('links.*')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                @error('balance')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                @error('plan')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <div class="flex items-start">
                    <div class="flex items-center h-5">
                        <input
                            id="auto_renew"
                            name="auto_renew"
                            type="checkbox"
                            value="1"
                            class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded"
                        >
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="auto_renew" class="font-medium text-gray-700">
                            {{ __('Auto-renew subscription') }}
                        </label>
                        <p class="text-gray-500 mt-1">
                            {{ __('If checked and your balance is sufficient, this subscription will be automatically renewed when it expires.') }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <button
                    type="button"
                    @click="$dispatch('close-modal', '{{ $name }}')"
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    :disabled="submitting"
                >
                    {{ __('Cancel') }}
                </button>
                <button
                    type="submit"
                    class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    :disabled="submitting"
                >
                    <span x-show="!submitting">{{ __('Activate') }}</span>
                    <span x-show="submitting">{{ __('Processing...') }}</span>
                </button>
            </div>
        </form>
    </div>
</x-modal>

