<x-client-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Subscription Plans') }}
        </h2>
    </x-slot>

    <div class="py-12" x-data="{ billingCycle: 'daily' }">
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
                {{-- Billing Cycle Filter Switch --}}
                <div class="mb-6 flex justify-center">
                    <div class="inline-flex w-50 items-center bg-white border border-gray-300 rounded-lg p-1 shadow-sm">
                        <button
                            type="button"
                            @click="billingCycle = 'daily'"
                            class="w-1/2 rounded-md px-4 py-2 text-sm font-semibold transition"
                            :class="billingCycle === 'daily' ? 'bg-gray-900 text-white' : 'text-gray-700 hover:bg-gray-50'"
                        >
                            Pay Daily
                        </button>

                        <button
                            type="button"
                            @click="billingCycle = 'monthly'"
                            class="w-1/2 rounded-md px-4 py-2 text-sm font-semibold transition"
                            :class="billingCycle === 'monthly' ? 'bg-gray-900 text-white' : 'text-gray-700 hover:bg-gray-50'"
                        >
                            Pay Monthly
                        </button>
                    </div>
                </div>


                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 items-stretch">
                    @foreach($plans as $plan)
                        @php
                            $daily = $plan->prices->where('billing_cycle', 'daily')->first();
                            $monthly = $plan->prices->where('billing_cycle', 'monthly')->first();

                            // If you have enabled column use it, else treat "exists" as enabled
                            $dailyEnabled = (bool) ($daily?->enabled ?? (bool) $daily);
                            $monthlyEnabled = (bool) ($monthly?->enabled ?? (bool) $monthly);

                            $dailyPrice = $daily?->price;
                            $monthlyPrice = $monthly?->price;

                            // Check if plan has valid price for the billing cycle
                            $hasDaily = $dailyEnabled && $dailyPrice !== null;
                            $hasMonthly = $monthlyEnabled && $monthlyPrice !== null;
                        @endphp

                        <div
                            class="h-full transition-opacity duration-300"
                            x-data="{
                                currency: @js($plan->currency),
                                dailyEnabled: {{ $dailyEnabled ? 'true' : 'false' }},
                                monthlyEnabled: {{ $monthlyEnabled ? 'true' : 'false' }},
                                dailyPrice: {{ $dailyPrice !== null ? (float) $dailyPrice : 'null' }},
                                monthlyPrice: {{ $monthlyPrice !== null ? (float) $monthlyPrice : 'null' }},
                                hasDaily: {{ $hasDaily ? 'true' : 'false' }},
                                hasMonthly: {{ $hasMonthly ? 'true' : 'false' }},
                                formatPrice(v) {
                                    if (v === null || typeof v === 'undefined') return 'N/A';
                                    return this.currency + ' ' + Number(v).toFixed(2);
                                },
                                get displayPrice() {
                                    if (billingCycle === 'monthly') {
                                        return (this.monthlyEnabled && this.monthlyPrice !== null) ? this.formatPrice(this.monthlyPrice) : 'N/A';
                                    }
                                    return (this.dailyEnabled && this.dailyPrice !== null) ? this.formatPrice(this.dailyPrice) : 'N/A';
                                },
                                get cycleLabel() {
                                    return billingCycle === 'monthly' ? 'Billed monthly' : 'Billed daily';
                                },
                                get isVisible() {
                                    if (billingCycle === 'monthly') {
                                        return this.hasMonthly;
                                    }
                                    return this.hasDaily;
                                }
                            }"
                            x-show="isVisible"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0"
                            x-transition:enter-end="opacity-100"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100"
                            x-transition:leave-end="opacity-0"
                        >
                            {{-- ===== Variant 1 ===== --}}
                            @if($plan->preview_variant == 1)
                                <div class="bg-white rounded-lg shadow-md overflow-hidden relative h-full flex flex-col">
                                    <div class="bg-gray-100 px-6 py-4 border-b border-gray-200">
                                        <h3 class="text-xl font-bold text-gray-900 uppercase">{{ $plan->name }}</h3>
                                    </div>

                                    <div class="p-6 flex-1 flex flex-col">
                                        {{-- Price block --}}
                                        <div class="mb-6">
                                            <div class="text-4xl font-bold text-gray-900 mb-1" x-text="displayPrice"></div>
                                            <div class="text-sm text-gray-600" x-text="cycleLabel"></div>
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

                                        {{-- Included services (live preview style) --}}
                                        @if($plan->planServices->count() > 0)
                                            <div class="mt-6">
                                                <div class="text-xs font-semibold text-gray-600 uppercase mb-2">INCLUDED SERVICES</div>
                                                <ul class="space-y-2">
                                                    @foreach($plan->planServices as $planService)
                                                        <li class="flex items-center justify-between text-sm">
                                                            <span class="text-gray-900 truncate pr-2">{{ $planService->service->name }}</span>
                                                            <span class="text-gray-500 text-xs whitespace-nowrap">x{{ $planService->quantity }}</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif

                                        {{-- Button pinned to bottom --}}
                                        <div class="mt-auto pt-6">
                                            <button class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded transition">
                                                Get Started
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                {{-- ===== Variant 2 ===== --}}
                            @elseif($plan->preview_variant == 2)
                                <div class="bg-blue-600 rounded-lg shadow-lg overflow-hidden relative h-full flex flex-col">
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
                                            <div class="text-sm text-blue-100" x-text="cycleLabel"></div>
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
                                                            <span class="text-blue-100 text-xs whitespace-nowrap">x{{ $planService->quantity }}</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif

                                        {{-- Button pinned to bottom --}}
                                        <div class="mt-auto pt-6">
                                            <button class="w-full bg-blue-700 hover:bg-blue-800 text-white font-semibold py-2 px-4 rounded transition">
                                                Get Started
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                {{-- ===== Variant 3 ===== --}}
                            @else
                                <div class="bg-blue-900 rounded-lg shadow-lg overflow-hidden h-full flex flex-col relative">
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
                                            <div class="text-sm text-blue-200" x-text="cycleLabel"></div>
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
                                                            <span class="text-blue-200 text-xs whitespace-nowrap">x{{ $planService->quantity }}</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif

                                        {{-- Button pinned to bottom --}}
                                        <div class="mt-auto pt-6">
                                            <button class="w-full bg-blue-700 hover:bg-blue-800 text-white font-semibold py-2 px-4 rounded transition">
                                                Get Started
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="bg-white rounded-lg shadow-sm p-12 text-center">
                    <p class="text-gray-500 text-lg">{{ __('No subscription plans available at this time.') }}</p>
                </div>
            @endif
        </div>
    </div>
</x-client-layout>
