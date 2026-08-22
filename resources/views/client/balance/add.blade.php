@php
    $providerCode = array_key_first($paymentTypes ?? []);
    $allProviders = array_keys($paymentTypes ?? []);
    $multiProvider = count($allProviders) > 1;
@endphp

<x-client-layout :title="__('Add Balance')">
    <style>
        .add-funds-wrap { max-width: 560px; margin: 0 auto; }
        .add-funds-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 26px 24px 28px;
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.25);
        }
        [data-theme="light"] .add-funds-card { box-shadow: 0 12px 40px rgba(0, 0, 0, 0.08); }
        .add-funds-balance-box {
            padding: 18px 20px;
            border-radius: 14px;
            border: 1px solid rgba(6, 182, 212, 0.35);
            background: rgba(6, 182, 212, 0.06);
            margin-bottom: 26px;
        }
        .add-funds-balance-label { font-size: 13px; font-weight: 500; color: var(--text3); margin-bottom: 6px; }
        .add-funds-balance-val { font-size: 2rem; font-weight: 800; color: var(--teal); letter-spacing: -0.02em; }
        .add-funds-field-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 600;
            color: var(--text2);
            margin-bottom: 10px;
        }
        .add-funds-field-label i { color: var(--text3); font-size: 14px; }
        .add-funds-input-wrap { position: relative; margin-bottom: 14px; }
        .add-funds-input {
            width: 100%;
            padding: 14px 16px 14px 36px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: rgba(0, 0, 0, 0.28);
            color: var(--text);
            font-size: 16px;
            font-family: inherit;
            outline: none;
            transition: border-color 0.2s;
        }
        [data-theme="light"] .add-funds-input { background: var(--card2, #f0f2f7); }
        .add-funds-input:focus { border-color: var(--purple-light); }
        .add-funds-input::placeholder { color: var(--text3); }
        .add-funds-input-prefix {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text3);
            font-size: 15px;
            font-weight: 600;
            pointer-events: none;
        }
        .add-funds-presets { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 28px; }
        .add-funds-preset {
            padding: 10px 16px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: rgba(255, 255, 255, 0.04);
            color: var(--text);
            font-size: 13px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: background 0.15s, border-color 0.15s;
        }
        .add-funds-preset:hover {
            border-color: rgba(139, 92, 246, 0.45);
            background: rgba(139, 92, 246, 0.12);
        }
        .add-funds-pay-label { margin-bottom: 12px; }

        /* Payment method selector cards */
        .pm-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 4px; }
        .pm-card {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            padding: 16px 12px;
            border-radius: 14px;
            border: 2px solid var(--border);
            background: rgba(255,255,255,0.03);
            cursor: pointer;
            transition: border-color 0.18s, background 0.18s, box-shadow 0.18s;
            text-align: center;
            user-select: none;
        }
        .pm-card:hover { border-color: rgba(139,92,246,0.4); background: rgba(139,92,246,0.06); }
        .pm-card.active {
            border-color: var(--purple);
            background: rgba(139,92,246,0.1);
            box-shadow: 0 0 0 1px rgba(139,92,246,0.35);
        }
        .pm-card input[type="radio"] { position: absolute; opacity: 0; width: 0; height: 0; }
        .pm-card-icon {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }
        .pm-card-icon--heleket   { background: rgba(253,121,43,0.15); color: #fd7b2b; }
        .pm-card-icon--cryptomus { background: rgba(6,182,212,0.15);  color: var(--cyan); }
        .pm-card-name { font-size: 13px; font-weight: 700; color: var(--text); line-height: 1.2; }
        .pm-card-sub  { font-size: 11px; color: var(--text3); line-height: 1.3; }
        .pm-check {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: var(--purple);
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 9px;
            color: #fff;
        }
        .pm-card.active .pm-check { display: flex; }

        /* Single method display (when only one provider) */
        .add-funds-method {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 18px;
            border-radius: 14px;
            border: 1px solid var(--border);
            background: rgba(255, 255, 255, 0.03);
        }
        .add-funds-method-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            background: rgba(253, 121, 43, 0.15);
            color: #fd7b2b;
            flex-shrink: 0;
        }
        .add-funds-method-title { font-size: 15px; font-weight: 700; color: var(--text); margin: 0 0 4px; }
        .add-funds-method-sub { font-size: 12px; color: var(--text3); margin: 0; line-height: 1.4; }

        .add-funds-submit {
            width: 100%;
            margin-top: 26px;
            padding: 16px 20px;
            border: none;
            border-radius: 14px;
            background: linear-gradient(90deg, var(--cyan), var(--blue), var(--purple));
            color: #fff !important;
            font-size: 15px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 6px 24px rgba(139, 92, 246, 0.45);
            transition: filter 0.15s, transform 0.15s;
        }
        .add-funds-submit:hover:not(:disabled) { filter: brightness(1.08); transform: translateY(-1px); }
        .add-funds-submit:disabled { opacity: 0.5; cursor: not-allowed; }
        .add-funds-alert {
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            border: 1px solid var(--border);
        }
        .add-funds-alert.info { background: rgba(139, 92, 246, 0.12); border-color: rgba(139, 92, 246, 0.35); color: var(--purple-light); }
        .add-funds-alert.ok { background: rgba(0, 184, 148, 0.12); border-color: rgba(0, 184, 148, 0.35); color: #00d9a5; }
        .add-funds-alert.warn { background: rgba(253, 203, 110, 0.12); border-color: rgba(253, 203, 110, 0.35); color: #fdcb6e; }
        .add-funds-alert.neutral { background: var(--card2); color: var(--text2); }
        .add-funds-alert a { color: inherit; font-weight: 600; text-decoration: underline; }
        .add-funds-error { margin-top: 8px; font-size: 13px; color: #ff7675; }
        .add-funds-cancel { display: block; text-align: center; margin-top: 16px; font-size: 13px; color: var(--text3); text-decoration: none; }
        .add-funds-cancel:hover { color: var(--text2); }
    </style>

    <div class="add-funds-wrap">
        @if (request()->query('return_to') === 'order' && session('pending_order'))
            <div class="add-funds-alert info">
                <p class="m-0 mb-3">{{ session('info', __('Add funds to complete your order.')) }}</p>
                <a href="{{ route('home.complete-order') }}">{{ __('Complete Your Order') }}</a>
            </div>
        @endif

        @if (session('status'))
            <div class="add-funds-alert ok">
                <p class="m-0">{{ session('status') }}</p>
            </div>
        @endif

        @if (isset($redirectStatus))
            @if ($redirectStatus === 'success' && $payment)
                @if ($payment->status === 'paid')
                    <div class="add-funds-alert ok">
                        <p class="m-0">{{ __('Payment confirmed. Your balance has been updated.') }}</p>
                    </div>
                @else
                    <div class="add-funds-alert warn">
                        <p class="m-0">{{ __('Payment received. Your balance will be updated shortly once confirmed.') }}</p>
                    </div>
                @endif
            @elseif ($redirectStatus === 'return' && $payment)
                <div class="add-funds-alert neutral">
                    <p class="m-0">{{ __('You returned from the payment page. Complete the payment to add balance.') }}</p>
                </div>
            @endif
        @endif

        @if (! $providerCode)
            <div class="add-funds-alert warn">
                <p class="m-0">{{ __('No payment methods are configured. Please contact support.') }}</p>
            </div>
        @else
            <div class="add-funds-card">
                <div class="add-funds-balance-box">
                    <div class="add-funds-balance-label">{{ __('Current Balance') }}</div>
                    <div class="add-funds-balance-val">${{ number_format($client->balance, 2) }}</div>
                </div>

                <form method="POST" action="{{ route('client.balance.store') }}" id="add-funds-form">
                    @csrf
                    <input type="hidden" name="payment_type" id="payment_type_input" value="{{ old('payment_type', $providerCode) }}">

                    <div class="add-funds-field-label">
                        <i class="fa-solid fa-dollar-sign" aria-hidden="true"></i>
                        {{ __('Amount') }}
                    </div>
                    <div class="add-funds-input-wrap">
                        <span class="add-funds-input-prefix" aria-hidden="true">$</span>
                        <input
                            id="amount"
                            name="amount"
                            type="number"
                            step="0.01"
                            min="1"
                            class="add-funds-input"
                            value="{{ old('amount') }}"
                            required
                            autofocus
                            placeholder="{{ __('Enter amount…') }}"
                            autocomplete="off"
                        >
                    </div>
                    @error('amount')
                        <p class="add-funds-error">{{ $message }}</p>
                    @enderror

                    <div class="add-funds-presets" role="group" aria-label="{{ __('Quick amounts') }}">
                        @foreach ([5, 10, 25, 50, 100] as $preset)
                            <button type="button" class="add-funds-preset" data-amount="{{ $preset }}">${{ $preset }}</button>
                        @endforeach
                    </div>

                    <div class="add-funds-field-label add-funds-pay-label">
                        <i class="fa-solid fa-credit-card" aria-hidden="true"></i>
                        {{ __('Payment method') }}
                    </div>

                    @if ($multiProvider)
                        @php
                            $pmMeta = [
                                'heleket'   => ['icon' => 'fa-solid fa-bolt',       'cls' => 'pm-card-icon--heleket',   'name' => 'Heleket',   'sub' => 'Crypto · Fast & secure'],
                                'cryptomus' => ['icon' => 'fa-solid fa-coins',      'cls' => 'pm-card-icon--cryptomus', 'name' => 'Cryptomus', 'sub' => 'Crypto · 100+ currencies'],
                            ];
                            $selectedProvider = old('payment_type', $providerCode);
                        @endphp
                        <div class="pm-grid">
                            @foreach ($allProviders as $pCode)
                                @php $m = $pmMeta[$pCode] ?? ['icon' => 'fa-solid fa-circle', 'cls' => '', 'name' => $pCode, 'sub' => '']; @endphp
                                <label class="pm-card {{ $selectedProvider === $pCode ? 'active' : '' }}" data-provider="{{ $pCode }}">
                                    <input type="radio" name="_pm_radio" value="{{ $pCode }}" {{ $selectedProvider === $pCode ? 'checked' : '' }}>
                                    <span class="pm-check"><i class="fa-solid fa-check"></i></span>
                                    <span class="pm-card-icon {{ $m['cls'] }}"><i class="{{ $m['icon'] }}" aria-hidden="true"></i></span>
                                    <span class="pm-card-name">{{ $m['name'] }}</span>
                                    <span class="pm-card-sub">{{ $m['sub'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    @else
                        <div class="add-funds-method">
                            <div class="add-funds-method-icon" aria-hidden="true">
                                <i class="fa-solid fa-bolt"></i>
                            </div>
                            <div>
                                <p class="add-funds-method-title">{{ __('Heleket payment') }}</p>
                                <p class="add-funds-method-sub">{{ $paymentTypes[$providerCode] ?? __('Pay with cryptocurrency via Heleket.') }}</p>
                            </div>
                        </div>
                    @endif

                    @error('payment_type')
                        <p class="add-funds-error">{{ $message }}</p>
                    @enderror

                    <button type="submit" class="add-funds-submit">
                        <i class="fa-solid fa-lock" aria-hidden="true"></i>
                        {{ __('Pay Now') }}
                    </button>
                </form>

                <a href="{{ route('client.account.edit') }}" class="add-funds-cancel">{{ __('Cancel') }}</a>
            </div>
        @endif

        {{-- Promo Code --}}
        @if(session('promo_success'))
            <div class="add-funds-alert ok" style="margin-top:16px;">
                <p class="m-0">{{ session('promo_success') }}</p>
            </div>
        @endif
        <div class="add-funds-card" style="margin-top:16px;">
            <div class="add-funds-field-label">
                <i class="fa-solid fa-gift" aria-hidden="true"></i>
                {{ __('Have a promo code?') }}
            </div>
            <form method="POST" action="{{ route('client.promo-code.apply') }}">
                @csrf
                <div style="display:flex;gap:8px;align-items:stretch;">
                    <div class="add-funds-input-wrap" style="flex:1;margin-bottom:0;">
                        <input name="promo_code" type="text" class="add-funds-input" value="{{ old('promo_code') }}"
                               placeholder="{{ __('Enter promo code…') }}" autocomplete="off"
                               style="padding-left:14px;text-transform:uppercase;" maxlength="32">
                    </div>
                    <button type="submit" class="add-funds-submit" style="width:auto;margin-top:0;padding:12px 22px;border-radius:12px;white-space:nowrap;">
                        {{ __('Apply') }}
                    </button>
                </div>
                @error('promo_code')
                    <p class="add-funds-error">{{ $message }}</p>
                @enderror
            </form>
        </div>
    </div>

    <script>
        document.querySelectorAll('.add-funds-preset').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var input = document.getElementById('amount');
                if (input) input.value = this.getAttribute('data-amount');
            });
        });

        // Payment method card selector
        document.querySelectorAll('.pm-card').forEach(function (card) {
            card.addEventListener('click', function () {
                document.querySelectorAll('.pm-card').forEach(function (c) { c.classList.remove('active'); });
                card.classList.add('active');
                var radio = card.querySelector('input[type="radio"]');
                if (radio) radio.checked = true;
                var hidden = document.getElementById('payment_type_input');
                if (hidden) hidden.value = card.getAttribute('data-provider');
            });
        });
    </script>
</x-client-layout>
