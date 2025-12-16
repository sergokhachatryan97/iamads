<x-client-account-layout>
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 text-gray-900">
            <div class="mb-6">
                <h3 class="text-lg font-medium text-gray-900">{{ __('Add Balance') }}</h3>
                <p class="mt-1 text-sm text-gray-600">{{ __('Select a payment method and enter the amount you want to add to your account.') }}</p>
            </div>

            @if (session('status'))
                <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-md">
                    <p class="text-sm text-green-800">{{ session('status') }}</p>
                </div>
            @endif

            <form method="POST" action="{{ route('client.balance.store') }}" class="space-y-6">
                @csrf

                <!-- Current Balance Display -->
                <div class="p-4 bg-gray-50 rounded-md">
                    <div class="text-sm font-medium text-gray-500 mb-1">{{ __('Current Balance') }}</div>
                    <div class="text-2xl font-bold text-gray-900">${{ number_format($client->balance, 2) }}</div>
                </div>

                <!-- Amount -->
                <div>
                    <x-input-label for="amount" :value="__('Amount')" />
                    <div class="mt-1 relative rounded-md shadow-sm">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-gray-500 sm:text-sm">$</span>
                        </div>
                        <x-text-input 
                            id="amount" 
                            name="amount" 
                            type="number" 
                            step="0.01"
                            min="1"
                            class="block w-full pl-7 pr-12" 
                            :value="old('amount')" 
                            required 
                            autofocus 
                            placeholder="0.00"
                        />
                    </div>
                    <x-input-error class="mt-2" :messages="$errors->get('amount')" />
                    <p class="mt-1 text-xs text-gray-500">{{ __('Enter the amount you want to add to your balance.') }}</p>
                </div>

                <!-- Payment Type -->
                <div>
                    <x-input-label for="payment_type" :value="__('Payment Method')" />
                    <div class="mt-2 space-y-2">
                        @foreach($paymentTypes as $key => $label)
                            <label class="flex items-center p-4 border rounded-md cursor-pointer hover:bg-gray-50 transition {{ old('payment_type') === $key ? 'border-indigo-500 bg-indigo-50' : 'border-gray-300' }}">
                                <input 
                                    type="radio" 
                                    name="payment_type" 
                                    value="{{ $key }}" 
                                    class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300"
                                    {{ old('payment_type') === $key ? 'checked' : '' }}
                                    required
                                >
                                <span class="ml-3 text-sm font-medium text-gray-700">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    <x-input-error class="mt-2" :messages="$errors->get('payment_type')" />
                </div>

                <!-- Payment Details (will be shown based on selected payment type) -->
                <div id="payment-details" class="hidden">
                    <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-md">
                        <p class="text-sm text-yellow-800">
                            {{ __('Payment processing details will be displayed here based on your selected payment method.') }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-4">
                    <a href="{{ route('client.account.edit') }}" class="text-sm text-gray-600 hover:text-gray-900">
                        {{ __('Cancel') }}
                    </a>
                    <x-primary-button>
                        {{ __('Continue to Payment') }}
                    </x-primary-button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Show payment details when a payment type is selected
        document.querySelectorAll('input[name="payment_type"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const detailsDiv = document.getElementById('payment-details');
                if (this.checked) {
                    detailsDiv.classList.remove('hidden');
                }
            });
        });

        // Show details if a payment type is already selected (on page reload with errors)
        const selectedPayment = document.querySelector('input[name="payment_type"]:checked');
        if (selectedPayment) {
            document.getElementById('payment-details').classList.remove('hidden');
        }
    </script>
</x-client-account-layout>


