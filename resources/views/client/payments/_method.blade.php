{{-- Payment method icon + name. Params: $payment, $small (optional) --}}
@php
    $m = match ($payment->provider) {
        'heleket'             => ['icon' => 'fa-solid fa-bolt',        'cls' => 'ph-ico--heleket',   'name' => 'Heleket',   'sub' => 'Crypto'],
        'cryptomus'           => ['icon' => 'fa-solid fa-coins',       'cls' => 'ph-ico--cryptomus', 'name' => 'Cryptomus', 'sub' => 'Crypto'],
        'baosbabs_usdt_trc20' => ['icon' => 'fa-solid fa-dollar-sign', 'cls' => 'ph-ico--usdt',      'name' => 'USDT',      'sub' => 'TRC-20'],
        'baosbabs_usdt_bep20' => ['icon' => 'fa-solid fa-dollar-sign', 'cls' => 'ph-ico--bep20',     'name' => 'USDT',      'sub' => 'BEP-20'],
        'baosbabs_trx'        => ['icon' => 'fa-solid fa-coins',       'cls' => 'ph-ico--trx',       'name' => 'TRX',       'sub' => 'Tron'],
        default               => ['icon' => 'fa-solid fa-credit-card', 'cls' => '', 'name' => config("payments.methods.{$payment->provider}.title", ucfirst($payment->provider)), 'sub' => ''],
    };
@endphp
<span class="ph-ico {{ ! empty($small) ? 'ph-ico--sm' : '' }} {{ $m['cls'] }}"><i class="{{ $m['icon'] }}" aria-hidden="true"></i></span>
<span class="ph-method">{{ $m['name'] }} @if ($m['sub'])<span>· {{ $m['sub'] }}</span>@endif</span>
