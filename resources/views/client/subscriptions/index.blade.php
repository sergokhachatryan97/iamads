<x-client-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Subscription Plans') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{-- Header Text (if exists and active) --}}
            @if(!empty($headerText))
                <div class="mb-6">
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="prose max-w-none">
                            <p class="text-gray-700 text-base leading-relaxed whitespace-pre-line">{{ $headerText }}</p>
                        </div>
                    </div>
                </div>
            @endif

            @if($plans->count() > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 items-stretch">
                    @foreach($plans as $plan)
                        @php
                            $monthly = $plan->prices->where('billing_cycle', 'monthly')->first();
                            $monthlyEnabled = (bool) ($monthly?->enabled ?? (bool) $monthly);
                            $monthlyPrice = $monthly?->price;
                            $hasMonthly = $monthlyEnabled && $monthlyPrice !== null;
                        @endphp

                        @php
                            $isActive = in_array($plan->id, $activeSubscriptionPlanIds ?? []);
                        @endphp
                        @if($hasMonthly)
                            {{-- ✅ grid child պետք է լինի h-full --}}
                            <div class="h-full">
                                {{-- ✅ Alpine wrapper-ն էլ պետք է լինի h-full --}}
                                <div
                                    class="h-full"
                                    x-data="{
                                        currency: @js($plan->currency),
                                        monthlyPrice: {{ $monthlyPrice !== null ? (float) $monthlyPrice : 'null' }},
                                        clientBalance: {{ (float) ($clientBalance ?? 0) }},
                                        isActive: {{ $isActive ? 'true' : 'false' }},
                                        formatPrice(v) {
                                            if (v === null || typeof v === 'undefined') return 'N/A';
                                            return this.currency + ' ' + Number(v).toFixed(2);
                                        },
                                        get displayPrice() {
                                            return this.monthlyPrice !== null ? this.formatPrice(this.monthlyPrice) : 'N/A';
                                        },
                                        checkBalanceAndOpenModal() {
                                            if (this.isActive) {
                                                return; // Don't open modal if already active
                                            }
                                            if (this.monthlyPrice === null || this.monthlyPrice <= 0) {
                                                alert('{{ __('This plan is not available.') }}');
                                                return;
                                            }
                                            if (this.clientBalance < this.monthlyPrice) {
                                                window.dispatchEvent(new CustomEvent('open-modal', { detail: 'insufficient-balance-{{ $plan->id }}' }));
                                                return;
                                            }
                                            window.dispatchEvent(new CustomEvent('open-modal', { detail: 'subscription-purchase-{{ $plan->id }}' }));
                                        }
                                    }"
                                    x-on:confirm-action.window="if ($event.detail === 'insufficient-balance-{{ $plan->id }}') { window.location.href = '{{ route('client.balance.add') }}'; }"
                                >
                                    {{-- ===== Variant 1 ===== --}}
                                    @if($plan->preview_variant == 1)
                                        <div class="bg-white rounded-lg shadow-md overflow-hidden relative h-full flex flex-col">
                                            {{-- Active Badge --}}
                                            @if($isActive)
                                                <div class="absolute top-4 right-4 z-10 bg-green-500 text-white text-xs font-semibold px-3 py-1 rounded-full shadow-lg">
                                                    {{ __('Active') }}
                                                </div>
                                            @endif
                                            <div class="bg-gray-100 px-6 py-4 border-b border-gray-200">
                                                <h3 class="text-xl font-bold text-gray-900 uppercase">{{ $plan->name }}</h3>
                                            </div>

                                            <div class="p-6 flex-1 flex flex-col">
                                                {{-- Price block --}}
                                                <div class="mb-6">
                                                    <div class="text-4xl font-bold text-gray-900 mb-1" x-text="displayPrice"></div>
                                                    <div class="text-sm text-gray-600">Billed monthly</div>
                                                </div>

                                                {{-- Features --}}
                                                @if($plan->preview_features && count($plan->preview_features) > 0)
                                                    <ul class="space-y-2 mb-6">
                                                        @foreach($plan->preview_features as $index => $feature)
                                                            <li class="text-sm {{ $index < 2 ? 'text-gray-900 font-medium' : 'text-gray-400' }}">
                                                                {{ $feature }}
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                @endif

                                                {{-- Included services --}}
                                                @if($plan->planServices->count() > 0)
                                                    <div class="mt-6">
                                                        <div class="text-xs font-semibold text-gray-600 uppercase mb-2">INCLUDED SERVICES</div>
                                                        <ul class="space-y-2">
                                                            @foreach($plan->planServices as $planService)
                                                                <li class="flex items-center justify-between text-sm">
                                                                    <span class="text-gray-900 truncate pr-2">{{ $planService->service->name }}</span>
                                                                    <span class="text-gray-500 text-xs whitespace-nowrap">Qty {{ $planService->quantity }}</span>
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                @endif

                                                {{-- Button pinned bottom --}}
                                                <div class="mt-auto pt-6">
                                                    <button 
                                                        @click="checkBalanceAndOpenModal()"
                                                        :disabled="isActive"
                                                        class="w-full text-white font-semibold py-2 px-4 rounded transition"
                                                        :class="isActive ? 'bg-gray-400 cursor-not-allowed' : 'bg-blue-600 hover:bg-blue-700'"
                                                    >
                                                        <span x-show="!isActive">{{ __('Get Started') }}</span>
                                                        <span x-show="isActive">{{ __('Active') }}</span>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- ===== Variant 2 ===== --}}
                                    @elseif($plan->preview_variant == 2)
                                        <div class="bg-blue-600 rounded-lg shadow-lg overflow-hidden relative h-full flex flex-col">
                                            @if($isActive)
                                                <div class="absolute top-4 right-4 z-10 bg-green-500 text-white text-xs font-semibold px-3 py-1 rounded-full shadow-lg">
                                                    {{ __('Active') }}
                                                </div>
                                            @endif
                                            @if($plan->preview_badge)
                                                <div class="absolute top-0 left-0 bg-yellow-400 text-yellow-900 text-xs font-bold px-4 py-1 transform -rotate-12 -translate-x-1 translate-y-1 shadow-md"
                                                     style="clip-path: polygon(0 0, 100% 0, 85% 100%, 0 100%);">
                                                    {{ $plan->preview_badge }}
                                                </div>
                                            @endif

                                            <div class="px-6 py-4">
                                                <h3 class="text-xl font-bold text-white uppercase">{{ $plan->name }}</h3>
                                            </div>

                                            <div class="p-6 bg-blue-600 flex-1 flex flex-col">
                                                {{-- Price block --}}
                                                <div class="mb-6">
                                                    <div class="text-4xl font-bold text-white mb-1" x-text="displayPrice"></div>
                                                    <div class="text-sm text-blue-100">Billed monthly</div>
                                                </div>

                                                {{-- Features --}}
                                                @if($plan->preview_features && count($plan->preview_features) > 0)
                                                    <ul class="space-y-2 mb-6">
                                                        @foreach($plan->preview_features as $index => $feature)
                                                            <li class="text-sm text-white {{ $index < 4 ? 'opacity-100' : 'opacity-75' }}">
                                                                {{ $feature }}
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                @endif

                                                {{-- Included services --}}
                                                @if($plan->planServices->count() > 0)
                                                    <div class="mt-6">
                                                        <div class="text-xs font-semibold text-blue-100 uppercase mb-2">INCLUDED SERVICES</div>
                                                        <ul class="space-y-2">
                                                            @foreach($plan->planServices as $planService)
                                                                <li class="flex items-center justify-between text-sm">
                                                                    <span class="text-white truncate pr-2">{{ $planService->service->name }}</span>
                                                                    <span class="text-blue-100 text-xs whitespace-nowrap">Qty{{ $planService->quantity }}</span>
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                @endif

                                                {{-- Button pinned bottom --}}
                                                <div class="mt-auto pt-6">
                                                    <button 
                                                        @click="checkBalanceAndOpenModal()"
                                                        :disabled="isActive"
                                                        class="w-full text-white font-semibold py-2 px-4 rounded transition"
                                                        :class="isActive ? 'bg-gray-500 cursor-not-allowed' : 'bg-blue-700 hover:bg-blue-800'"
                                                    >
                                                        <span x-show="!isActive">{{ __('Get Started') }}</span>
                                                        <span x-show="isActive">{{ __('Active') }}</span>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- ===== Variant 3 ===== --}}
                                    @else
                                        <div class="bg-blue-900 rounded-lg shadow-lg overflow-hidden h-full flex flex-col relative">
                                            {{-- Active Badge --}}
                                            @if($isActive)
                                                <div class="absolute top-4 right-4 z-20 bg-green-500 text-white text-xs font-semibold px-3 py-1 rounded-full shadow-lg">
                                                    {{ __('Active') }}
                                                </div>
                                            @endif
                                            @if($plan->preview_badge)
                                                <div class="absolute top-0 left-0 bg-yellow-400 text-yellow-900 text-xs font-bold px-4 py-1 transform -rotate-12 -translate-x-1 translate-y-1 shadow-md z-10"
                                                     style="clip-path: polygon(0 0, 100% 0, 85% 100%, 0 100%);">
                                                    {{ $plan->preview_badge }}
                                                </div>
                                            @endif

                                            <div class="px-6 py-4">
                                                <h3 class="text-xl font-bold text-white uppercase">{{ $plan->name }}</h3>
                                            </div>

                                            <div class="p-6 bg-blue-900 flex-1 flex flex-col">
                                                {{-- Price block --}}
                                                <div class="mb-6">
                                                    <div class="text-4xl font-bold text-white mb-1" x-text="displayPrice"></div>
                                                    <div class="text-sm text-blue-200">Billed monthly</div>
                                                </div>

                                                {{-- Features --}}
                                                @if($plan->preview_features && count($plan->preview_features) > 0)
                                                    <ul class="space-y-2 mb-6">
                                                        @foreach($plan->preview_features as $feature)
                                                            <li class="text-sm text-white">{{ $feature }}</li>
                                                        @endforeach
                                                    </ul>
                                                @endif

                                                {{-- Included services --}}
                                                @if($plan->planServices->count() > 0)
                                                    <div class="mt-6">
                                                        <div class="text-xs font-semibold text-blue-200 uppercase mb-2">INCLUDED SERVICES</div>
                                                        <ul class="space-y-2">
                                                            @foreach($plan->planServices as $planService)
                                                                <li class="flex items-center justify-between text-sm">
                                                                    <span class="text-white truncate pr-2">{{ $planService->service->name }}</span>
                                                                    <span class="text-blue-200 text-xs whitespace-nowrap">Qty{{ $planService->quantity }}</span>
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                @endif

                                                {{-- Button pinned bottom --}}
                                                <div class="mt-auto pt-6">
                                                    <button 
                                                        @click="checkBalanceAndOpenModal()"
                                                        :disabled="isActive"
                                                        class="w-full text-white font-semibold py-2 px-4 rounded transition"
                                                        :class="isActive ? 'bg-gray-500 cursor-not-allowed' : 'bg-blue-700 hover:bg-blue-800'"
                                                    >
                                                        <span x-show="!isActive">{{ __('Get Started') }}</span>
                                                        <span x-show="isActive">{{ __('Active') }}</span>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    @endif

                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            @else
                <div class="bg-white rounded-lg shadow-sm p-12 text-center">
                    <p class="text-gray-500 text-lg">{{ __('No subscription plans available at this time.') }}</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Success Message --}}
    @if(session('status') === 'subscription-purchased')
        <div 
            x-data="{ show: true }"
            x-show="show"
            x-init="setTimeout(() => show = false, 5000)"
            class="fixed bottom-4 right-4 bg-green-50 border border-green-200 rounded-lg shadow-lg p-4 max-w-sm z-50"
        >
            <div class="flex items-center">
                <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <p class="text-sm font-medium text-green-800">
                    {{ session('message', __('Subscription activated successfully!')) }}
                </p>
            </div>
        </div>
    @endif

    {{-- Purchase Modals --}}
    @foreach($plans as $plan)
        @php
            $monthly = $plan->prices->where('billing_cycle', 'monthly')->first();
            $monthlyPrice = $monthly?->price;
        @endphp
        @if($monthlyPrice)
            @php
                $neededAmount = max(0, $monthlyPrice - ($clientBalance ?? 0));
                $neededAmountFormatted = number_format($neededAmount, 2);
            @endphp
            {{-- Insufficient Balance Confirmation Modal --}}
            <x-confirm-modal
                name="insufficient-balance-{{ $plan->id }}"
                title="{{ __('Insufficient Balance') }}"
                :message="__('Insufficient balance. You need :currency :amount more to purchase this subscription. Would you like to add balance?', ['currency' => $plan->currency, 'amount' => $neededAmountFormatted])"
                confirmText="{{ __('Add Balance') }}"
                cancelText="{{ __('Cancel') }}"
                confirmButtonClass="bg-indigo-600 hover:bg-indigo-700"
            />

            {{-- Purchase Modal --}}
            <x-subscription-purchase-modal
                name="subscription-purchase-{{ $plan->id }}"
                :planId="$plan->id"
                :planName="$plan->name"
                :price="$monthlyPrice"
                :currency="$plan->currency"
                :clientBalance="$clientBalance"
            />
        @endif
    @endforeach
</x-client-layout>
