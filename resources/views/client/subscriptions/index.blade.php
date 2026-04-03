<x-client-layout :title="__('Subscription Plans')">
    <style>
        .smm-subscriptions-page {
            --smm-plan-radius: var(--radius, 12px);
        }
        .smm-subscriptions-page .smm-sub-header-panel {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--smm-plan-radius);
            padding: 1.25rem 1.5rem;
            color: var(--text2);
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
        }
        [data-theme="dark"] .smm-subscriptions-page .smm-sub-header-panel {
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
        }
        .smm-subscriptions-page .smm-sub-header-panel p {
            margin: 0;
            line-height: 1.6;
            white-space: pre-line;
        }
        .smm-subscriptions-page .smm-plan-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
            align-items: stretch;
        }
        @media (min-width: 768px) {
            .smm-subscriptions-page .smm-plan-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (min-width: 1024px) {
            .smm-subscriptions-page .smm-plan-grid { grid-template-columns: repeat(3, 1fr); }
        }
        .smm-subscriptions-page .smm-plan-card {
            position: relative;
            border-radius: var(--smm-plan-radius);
            overflow: hidden;
            border: 1px solid var(--border);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
            display: flex;
            flex-direction: column;
            min-height: 100%;
        }
        [data-theme="light"] .smm-subscriptions-page .smm-plan-card {
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
        }
        .smm-subscriptions-page .smm-plan-card--v1 {
            background: var(--card);
        }
        .smm-subscriptions-page .smm-plan-card--v2 {
            background: linear-gradient(155deg, var(--purple) 0%, var(--purple-dark) 45%, #16213e 100%);
            border-color: rgba(255, 255, 255, 0.12);
            color: #fff;
        }
        .smm-subscriptions-page .smm-plan-card--v3 {
            background: linear-gradient(180deg, #1e1540 0%, #0d0a18 100%);
            border-color: rgba(108, 92, 231, 0.25);
            color: #fff;
        }
        .smm-subscriptions-page .smm-plan-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            z-index: 10;
            font-size: 11px;
            font-weight: 700;
            padding: 0.35rem 0.75rem;
            border-radius: 9999px;
            background: linear-gradient(135deg, #00b894, #00cec9);
            color: #fff;
            box-shadow: 0 4px 14px rgba(0, 206, 201, 0.35);
        }
        .smm-subscriptions-page .smm-plan-ribbon {
            position: absolute;
            top: 0;
            left: 0;
            z-index: 8;
            background: linear-gradient(135deg, #fdcb6e, #e17055);
            color: #1a1a2e;
            font-size: 11px;
            font-weight: 800;
            padding: 0.35rem 1rem 0.35rem 0.65rem;
            transform: rotate(-12deg) translate(-4px, 8px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            clip-path: polygon(0 0, 100% 0, 85% 100%, 0 100%);
        }
        .smm-subscriptions-page .smm-plan-card__head {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border);
        }
        .smm-subscriptions-page .smm-plan-card--v2 .smm-plan-card__head,
        .smm-subscriptions-page .smm-plan-card--v3 .smm-plan-card__head {
            background: transparent;
            border-bottom-color: rgba(255, 255, 255, 0.12);
        }
        .smm-subscriptions-page .smm-plan-card__head h3 {
            margin: 0;
            font-size: 1.125rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            color: var(--text);
        }
        .smm-subscriptions-page .smm-plan-card--v2 .smm-plan-card__head h3,
        .smm-subscriptions-page .smm-plan-card--v3 .smm-plan-card__head h3 {
            color: #fff;
        }
        .smm-subscriptions-page .smm-plan-card__body {
            padding: 1.5rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .smm-subscriptions-page .smm-plan-price {
            font-size: 2.25rem;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 0.25rem;
            background: linear-gradient(135deg, var(--purple-light), var(--teal));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .smm-subscriptions-page .smm-plan-card--v2 .smm-plan-price,
        .smm-subscriptions-page .smm-plan-card--v3 .smm-plan-price {
            -webkit-text-fill-color: #fff;
            background: none;
            color: #fff;
        }
        .smm-subscriptions-page .smm-plan-cycle {
            font-size: 0.875rem;
            color: var(--text3);
            margin-bottom: 1.5rem;
        }
        .smm-subscriptions-page .smm-plan-card--v2 .smm-plan-cycle {
            color: rgba(255, 255, 255, 0.8);
        }
        .smm-subscriptions-page .smm-plan-card--v3 .smm-plan-cycle {
            color: rgba(255, 255, 255, 0.7);
        }
        .smm-subscriptions-page .smm-plan-features {
            list-style: none;
            margin: 0 0 1.5rem;
            padding: 0;
        }
        .smm-subscriptions-page .smm-plan-features li {
            font-size: 0.875rem;
            padding: 0.25rem 0;
            color: var(--text2);
        }
        .smm-subscriptions-page .smm-plan-features li.is-strong {
            color: var(--text);
            font-weight: 600;
        }
        .smm-subscriptions-page .smm-plan-features li.is-muted {
            color: var(--text3);
        }
        .smm-subscriptions-page .smm-plan-card--v2 .smm-plan-features li {
            color: rgba(255, 255, 255, 0.92);
        }
        .smm-subscriptions-page .smm-plan-card--v2 .smm-plan-features li.dim {
            opacity: 0.8;
        }
        .smm-subscriptions-page .smm-plan-card--v3 .smm-plan-features li {
            color: rgba(255, 255, 255, 0.9);
        }
        .smm-subscriptions-page .smm-plan-included-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text3);
            margin-bottom: 0.5rem;
        }
        .smm-subscriptions-page .smm-plan-card--v2 .smm-plan-included-label {
            color: rgba(255, 255, 255, 0.75);
        }
        .smm-subscriptions-page .smm-plan-card--v3 .smm-plan-included-label {
            color: rgba(255, 255, 255, 0.65);
        }
        .smm-subscriptions-page .smm-plan-included-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        .smm-subscriptions-page .smm-plan-included-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            padding: 0.35rem 0;
            color: var(--text);
            border-bottom: 1px solid var(--border);
        }
        .smm-subscriptions-page .smm-plan-included-list li:last-child {
            border-bottom: 0;
        }
        .smm-subscriptions-page .smm-plan-included-list .qty {
            font-size: 0.75rem;
            color: var(--text3);
            white-space: nowrap;
        }
        .smm-subscriptions-page .smm-plan-card--v2 .smm-plan-included-list li {
            color: #fff;
            border-bottom-color: rgba(255, 255, 255, 0.1);
        }
        .smm-subscriptions-page .smm-plan-card--v2 .smm-plan-included-list .qty {
            color: rgba(255, 255, 255, 0.75);
        }
        .smm-subscriptions-page .smm-plan-card--v3 .smm-plan-included-list li {
            color: #fff;
            border-bottom-color: rgba(255, 255, 255, 0.08);
        }
        .smm-subscriptions-page .smm-plan-card--v3 .smm-plan-included-list .qty {
            color: rgba(255, 255, 255, 0.65);
        }
        .smm-subscriptions-page .smm-plan-btn-wrap {
            margin-top: auto;
            padding-top: 1.5rem;
        }
        .smm-subscriptions-page .smm-plan-btn {
            width: 100%;
            padding: 0.65rem 1rem;
            border-radius: 9999px;
            font-weight: 700;
            font-size: 0.875rem;
            border: none;
            cursor: pointer;
            transition: filter 0.15s, opacity 0.15s, background 0.15s;
            background: linear-gradient(135deg, var(--purple), var(--teal));
            color: #fff;
        }
        .smm-subscriptions-page .smm-plan-btn:hover:not(:disabled) {
            filter: brightness(1.07);
        }
        .smm-subscriptions-page .smm-plan-btn:disabled {
            opacity: 0.45;
            cursor: not-allowed;
            filter: none;
            background: var(--card2);
            color: var(--text3);
        }
        .smm-subscriptions-page .smm-plan-card--v2 .smm-plan-btn:not(:disabled),
        .smm-subscriptions-page .smm-plan-card--v3 .smm-plan-btn:not(:disabled) {
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.35);
        }
        .smm-subscriptions-page .smm-plan-card--v2 .smm-plan-btn:not(:disabled):hover,
        .smm-subscriptions-page .smm-plan-card--v3 .smm-plan-btn:not(:disabled):hover {
            background: rgba(255, 255, 255, 0.28);
            filter: none;
        }
        .smm-subscriptions-page .smm-plan-card--v2 .smm-plan-btn:disabled,
        .smm-subscriptions-page .smm-plan-card--v3 .smm-plan-btn:disabled {
            background: rgba(0, 0, 0, 0.25);
            color: rgba(255, 255, 255, 0.5);
            border-color: rgba(255, 255, 255, 0.12);
        }
        .smm-subscriptions-page .smm-sub-empty {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--smm-plan-radius);
            padding: 3rem 1.5rem;
            text-align: center;
            color: var(--text3);
        }
        .smm-sub-toast {
            background: var(--card) !important;
            border: 1px solid rgba(0, 184, 148, 0.4) !important;
            border-radius: var(--smm-plan-radius) !important;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.25) !important;
        }
        .smm-sub-toast p {
            color: var(--text) !important;
        }
        .smm-sub-toast svg {
            color: #00cec9 !important;
        }

        .smm-client-modals-root .client-smm-modal-backdrop {
            background: rgba(0, 0, 0, 0.72) !important;
        }
        [data-theme="light"] .smm-client-modals-root .client-smm-modal-backdrop {
            background: rgba(15, 23, 42, 0.45) !important;
        }
        .smm-client-modals-root .client-smm-modal-panel {
            background: var(--card) !important;
            color: var(--text);
        }
        .smm-client-modals-root .client-smm-modal-panel .text-gray-900,
        .smm-client-modals-root .client-smm-modal-panel .text-gray-700,
        .smm-client-modals-root .client-smm-modal-panel .text-gray-600,
        .smm-client-modals-root .client-smm-modal-panel .text-gray-500 {
            color: var(--text) !important;
        }
        .smm-client-modals-root .client-smm-modal-panel .border-gray-200,
        .smm-client-modals-root .client-smm-modal-panel .border-gray-300 {
            border-color: var(--border) !important;
        }
        .smm-client-modals-root .client-smm-modal-panel .bg-white {
            background: rgba(0, 0, 0, 0.2) !important;
            color: var(--text2) !important;
        }
        [data-theme="light"] .smm-client-modals-root .client-smm-modal-panel .bg-white {
            background: #fff !important;
        }
        .smm-client-modals-root .client-smm-modal-panel .hover\:bg-gray-50:hover {
            background: rgba(108, 92, 231, 0.12) !important;
        }
        [data-theme="light"] .smm-client-modals-root .client-smm-modal-panel .hover\:bg-gray-50:hover {
            background: var(--card2) !important;
        }
        .smm-client-modals-root .client-smm-modal-panel .text-indigo-600,
        .smm-client-modals-root .client-smm-modal-panel .text-indigo-700 {
            color: var(--purple-light) !important;
        }
        .smm-client-modals-root .client-smm-modal-panel .bg-indigo-100 {
            background: rgba(108, 92, 231, 0.2) !important;
        }
        .smm-client-modals-root .client-smm-modal-panel .hover\:bg-indigo-200:hover {
            background: rgba(108, 92, 231, 0.3) !important;
        }
        .smm-client-modals-root .client-smm-modal-panel .bg-indigo-600 {
            background: var(--purple) !important;
        }
        .smm-client-modals-root .client-smm-modal-panel .hover\:bg-indigo-700:hover {
            filter: brightness(1.08);
        }
        .smm-client-modals-root .client-smm-modal-panel .text-gray-400 {
            color: var(--text3) !important;
        }
        .smm-client-modals-root .client-smm-modal-panel .bg-red-100 {
            background: rgba(239, 68, 68, 0.2) !important;
        }
        .smm-client-modals-root .client-smm-modal-panel .text-red-600 {
            color: #f87171 !important;
        }
        .smm-client-modals-root .client-smm-modal-panel input.border-gray-300,
        .smm-client-modals-root .client-smm-modal-panel input.border-red-300 {
            background: rgba(0, 0, 0, 0.15) !important;
            color: var(--text) !important;
        }
        [data-theme="light"] .smm-client-modals-root .client-smm-modal-panel input.border-gray-300 {
            background: #fff !important;
        }
    </style>

    <div class="smm-subscriptions-page px-4 pb-12 pt-2 sm:px-6 lg:px-8">
        <div class="mx-auto w-full max-w-7xl">
            @if(!empty($headerText))
                <div class="mb-6">
                    <div class="smm-sub-header-panel">
                        <p>{{ $headerText }}</p>
                    </div>
                </div>
            @endif

            @if($plans->count() > 0)
                <div class="smm-plan-grid">
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
                            <div class="h-full">
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
                                                return;
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
                                    @if($plan->preview_variant == 1)
                                        <div class="smm-plan-card smm-plan-card--v1 h-full">
                                            @if($isActive)
                                                <span class="smm-plan-badge">{{ __('Active') }}</span>
                                            @endif
                                            <div class="smm-plan-card__head">
                                                <h3>{{ $plan->name }}</h3>
                                            </div>
                                            <div class="smm-plan-card__body">
                                                <div class="smm-plan-price" x-text="displayPrice"></div>
                                                <div class="smm-plan-cycle">{{ __('common.billed_monthly') }}</div>
                                                @if($plan->preview_features && count($plan->preview_features) > 0)
                                                    <ul class="smm-plan-features">
                                                        @foreach($plan->preview_features as $index => $feature)
                                                            <li class="{{ $index < 2 ? 'is-strong' : 'is-muted' }}">{{ $feature }}</li>
                                                        @endforeach
                                                    </ul>
                                                @endif
                                                @if($plan->planServices->count() > 0)
                                                    <div class="mt-6">
                                                        <div class="smm-plan-included-label">{{ __('common.included_services') }}</div>
                                                        <ul class="smm-plan-included-list">
                                                            @foreach($plan->planServices as $planService)
                                                                <li>
                                                                    <span class="truncate pr-2">{{ $planService->service?->name }}</span>
                                                                    <span class="qty">{{ __('Qty') }} {{ $planService->quantity }}</span>
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                @endif
                                                <div class="smm-plan-btn-wrap">
                                                    <button
                                                        type="button"
                                                        @click="checkBalanceAndOpenModal()"
                                                        :disabled="isActive"
                                                        class="smm-plan-btn"
                                                    >
                                                        <span x-show="!isActive">{{ __('Get Started') }}</span>
                                                        <span x-show="isActive" x-cloak>{{ __('Active') }}</span>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    @elseif($plan->preview_variant == 2)
                                        <div class="smm-plan-card smm-plan-card--v2 h-full">
                                            @if($isActive)
                                                <span class="smm-plan-badge">{{ __('Active') }}</span>
                                            @endif
                                            @if($plan->preview_badge)
                                                <div class="smm-plan-ribbon">{{ $plan->preview_badge }}</div>
                                            @endif
                                            <div class="smm-plan-card__head">
                                                <h3>{{ $plan->name }}</h3>
                                            </div>
                                            <div class="smm-plan-card__body">
                                                <div class="smm-plan-price" x-text="displayPrice"></div>
                                                <div class="smm-plan-cycle">{{ __('common.billed_monthly') }}</div>
                                                @if($plan->preview_features && count($plan->preview_features) > 0)
                                                    <ul class="smm-plan-features">
                                                        @foreach($plan->preview_features as $index => $feature)
                                                            <li class="{{ $index >= 4 ? 'dim' : '' }}">{{ $feature }}</li>
                                                        @endforeach
                                                    </ul>
                                                @endif
                                                @if($plan->planServices->count() > 0)
                                                    <div class="mt-6">
                                                        <div class="smm-plan-included-label">{{ __('common.included_services') }}</div>
                                                        <ul class="smm-plan-included-list">
                                                            @foreach($plan->planServices as $planService)
                                                                <li>
                                                                    <span class="truncate pr-2">{{ $planService->service?->name }}</span>
                                                                    <span class="qty">{{ __('Qty') }} {{ $planService->quantity }}</span>
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                @endif
                                                <div class="smm-plan-btn-wrap">
                                                    <button
                                                        type="button"
                                                        @click="checkBalanceAndOpenModal()"
                                                        :disabled="isActive"
                                                        class="smm-plan-btn"
                                                    >
                                                        <span x-show="!isActive">{{ __('Get Started') }}</span>
                                                        <span x-show="isActive" x-cloak>{{ __('Active') }}</span>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <div class="smm-plan-card smm-plan-card--v3 h-full">
                                            @if($isActive)
                                                <span class="smm-plan-badge">{{ __('Active') }}</span>
                                            @endif
                                            @if($plan->preview_badge)
                                                <div class="smm-plan-ribbon">{{ $plan->preview_badge }}</div>
                                            @endif
                                            <div class="smm-plan-card__head">
                                                <h3>{{ $plan->name }}</h3>
                                            </div>
                                            <div class="smm-plan-card__body">
                                                <div class="smm-plan-price" x-text="displayPrice"></div>
                                                <div class="smm-plan-cycle">{{ __('common.billed_monthly') }}</div>
                                                @if($plan->preview_features && count($plan->preview_features) > 0)
                                                    <ul class="smm-plan-features">
                                                        @foreach($plan->preview_features as $feature)
                                                            <li>{{ $feature }}</li>
                                                        @endforeach
                                                    </ul>
                                                @endif
                                                @if($plan->planServices->count() > 0)
                                                    <div class="mt-6">
                                                        <div class="smm-plan-included-label">{{ __('common.included_services') }}</div>
                                                        <ul class="smm-plan-included-list">
                                                            @foreach($plan->planServices as $planService)
                                                                <li>
                                                                    <span class="truncate pr-2">{{ $planService->service?->name }}</span>
                                                                    <span class="qty">{{ __('Qty') }} {{ $planService->quantity }}</span>
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                @endif
                                                <div class="smm-plan-btn-wrap">
                                                    <button
                                                        type="button"
                                                        @click="checkBalanceAndOpenModal()"
                                                        :disabled="isActive"
                                                        class="smm-plan-btn"
                                                    >
                                                        <span x-show="!isActive">{{ __('Get Started') }}</span>
                                                        <span x-show="isActive" x-cloak>{{ __('Active') }}</span>
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
                <div class="smm-sub-empty">
                    <p class="text-lg">{{ __('No subscription plans available at this time.') }}</p>
                </div>
            @endif
        </div>
    </div>

    @if(session('status') === 'subscription-purchased')
        <div
            x-data="{ show: true }"
            x-show="show"
            x-init="setTimeout(() => show = false, 5000)"
            class="smm-sub-toast fixed bottom-4 right-4 p-4 max-w-sm z-50"
        >
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <p class="text-sm font-medium">
                    {{ session('message', __('Subscription activated successfully!')) }}
                </p>
            </div>
        </div>
    @endif

    <div class="smm-client-modals-root">
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
                <x-confirm-modal
                    name="insufficient-balance-{{ $plan->id }}"
                    title="{{ __('Insufficient Balance') }}"
                    :message="__('Insufficient balance. You need :currency :amount more to purchase this subscription. Would you like to add balance?', ['currency' => $plan->currency, 'amount' => $neededAmountFormatted])"
                    confirmText="{{ __('Add Balance') }}"
                    cancelText="{{ __('Cancel') }}"
                    confirmButtonClass="bg-indigo-600 hover:bg-indigo-700"
                    theme="smm"
                />

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
    </div>
</x-client-layout>
