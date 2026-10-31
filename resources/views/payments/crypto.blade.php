@php
    $network = str_contains($payment->provider, 'bep20') ? 'BNB Smart Chain (BEP-20)' : 'Tron (TRC-20)';
    $assetLabel = str_contains($payment->provider, 'trx') ? 'TRX' : 'USDT';
    $address = (string) ($invoice['wallet_address'] ?? '');
    $amountCrypto = (string) ($invoice['amount_crypto'] ?? '');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('Payment') }} {{ $methodTitle }} — {{ config('app.name') }}</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #0a0a0f; color: #e2e8f0; min-height: 100vh; margin: 0; display: flex; align-items: center; justify-content: center; padding: 24px; box-sizing: border-box; }
        .card { width: 100%; max-width: 440px; background: #12121a; border: 1px solid rgba(255,255,255,0.08); border-radius: 18px; padding: 28px 24px; }
        h1 { font-size: 18px; margin: 0 0 4px; text-align: center; }
        .sub { font-size: 13px; color: #8892a4; text-align: center; margin: 0 0 20px; }
        .qr { display: flex; justify-content: center; margin: 0 0 20px; }
        .qr > div { background: #fff; padding: 10px; border-radius: 12px; }
        .label { font-size: 12px; color: #8892a4; margin: 0 0 6px; text-transform: uppercase; letter-spacing: .04em; }
        .field { display: flex; gap: 8px; align-items: center; background: #0a0a0f; border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 12px 14px; margin: 0 0 16px; }
        .field code { flex: 1; font-size: 14px; word-break: break-all; color: #e2e8f0; }
        .field .big { font-size: 20px; font-weight: 700; }
        .copy { background: #6c5ce7; color: #fff; border: 0; border-radius: 8px; padding: 7px 12px; font-size: 12px; cursor: pointer; white-space: nowrap; }
        .warn { font-size: 13px; line-height: 1.5; color: #f3ba2f; background: rgba(243,186,47,0.08); border: 1px solid rgba(243,186,47,0.25); border-radius: 12px; padding: 12px 14px; margin: 0 0 16px; }
        .status { display: flex; align-items: center; justify-content: center; gap: 10px; font-size: 14px; color: #8892a4; margin: 4px 0 16px; }
        .spinner { width: 16px; height: 16px; border: 2px solid rgba(108,92,231,0.2); border-top-color: #6c5ce7; border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .done { text-align: center; font-size: 15px; line-height: 1.5; margin: 0 0 16px; }
        .done.bad { color: #ff6b6b; }
        .done.ok { color: #26a17b; }
        .back { display: block; text-align: center; color: #8892a4; font-size: 13px; }
    </style>
</head>
<body>
<div class="card">
    <h1>{{ __('Pay with') }} {{ $methodTitle }}</h1>
    <p class="sub">{{ __('Amount') }}: ${{ number_format((float) $payment->amount, 2) }} · {{ $network }}</p>

    <div id="pay-active" @if ($status !== 'pending') hidden @endif>
        @if ($address !== '')
            <div class="qr"><div id="qr"></div></div>
        @endif

        <p class="label">{{ __('Send exactly') }}</p>
        <div class="field">
            <code class="big" id="amount">{{ $amountCrypto }} {{ $assetLabel }}</code>
            <button type="button" class="copy" data-copy="{{ $amountCrypto }}">{{ __('Copy') }}</button>
        </div>

        <p class="label">{{ __('To address') }}</p>
        <div class="field">
            <code>{{ $address }}</code>
            <button type="button" class="copy" data-copy="{{ $address }}">{{ __('Copy') }}</button>
        </div>

        <div class="warn">
            {{ __('Send the exact amount in one transaction, only via :network. A different amount or network may not be credited.', ['network' => $network]) }}
        </div>

        <div class="status">
            <span class="spinner" aria-hidden="true"></span>
            <span>{{ __('Waiting for payment') }}@if ($expiresAt) · <span id="timer"></span>@endif</span>
        </div>
    </div>

    <p class="done ok" id="pay-paid" @if ($status !== 'paid') hidden @endif>{{ __('Payment received. Redirecting…') }}</p>
    <p class="done bad" id="pay-expired" @if (! in_array($status, ['expired', 'failed'], true)) hidden @endif>
        {{ __('This invoice has expired. If you already sent the payment, contact support with your transaction ID.') }}
    </p>

    <a class="back" href="{{ $returnUrl }}">{{ __('Back') }}</a>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
    (function () {
        var qrEl = document.getElementById('qr');
        if (qrEl && window.QRCode) {
            new QRCode(qrEl, { text: @json($address), width: 180, height: 180 });
        }

        document.querySelectorAll('.copy').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var text = btn.getAttribute('data-copy');
                var label = btn.textContent;
                navigator.clipboard.writeText(text).then(function () {
                    btn.textContent = @json(__('Copied'));
                    setTimeout(function () { btn.textContent = label; }, 1500);
                });
            });
        });

        function show(status) {
            document.getElementById('pay-active').hidden = status !== 'pending';
            document.getElementById('pay-paid').hidden = status !== 'paid';
            document.getElementById('pay-expired').hidden = status !== 'expired' && status !== 'failed';
        }

        var expiresAt = @json($expiresAt?->getTimestampMs());
        var timerEl = document.getElementById('timer');
        if (expiresAt && timerEl) {
            var tick = function () {
                var left = Math.max(0, Math.floor((expiresAt - Date.now()) / 1000));
                timerEl.textContent = Math.floor(left / 60) + ':' + String(left % 60).padStart(2, '0');
            };
            tick();
            setInterval(tick, 1000);
        }

        var statusUrl = @json($statusUrl);
        var poll = function () {
            fetch(statusUrl, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) {
                    if (!data) return;
                    show(data.status);
                    if (data.status === 'paid' && data.redirect_url) {
                        setTimeout(function () { window.location.href = data.redirect_url; }, 1500);
                    } else if (data.status === 'pending') {
                        setTimeout(poll, 10000);
                    }
                })
                .catch(function () { setTimeout(poll, 15000); });
        };
        if (@json($status) === 'pending') {
            setTimeout(poll, 10000);
        }
    })();
</script>
</body>
</html>
