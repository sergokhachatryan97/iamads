{{-- Status badge, or "Continue payment" for a recent open invoice. Params: $payment, $continueSince --}}
@php
    $isOpen = in_array($payment->status, ['new', 'pending'], true);
    $canContinue = $isOpen && $payment->pay_url && $payment->created_at->gte($continueSince);
    [$badgeCls, $badgeIcon, $badgeText] = match (true) {
        $payment->status === 'paid'     => ['paid', 'fa-circle-check', __('Paid')],
        $isOpen                         => ['muted', 'fa-circle-minus', __('Not completed')],
        $payment->status === 'expired'  => ['muted', 'fa-clock', __('Expired')],
        $payment->status === 'refunded' => ['refunded', 'fa-rotate-left', __('Refunded')],
        default                         => ['failed', 'fa-circle-xmark', __('Failed')],
    };
@endphp
@if ($canContinue)
    <a href="{{ $payment->pay_url }}" class="ph-btn ph-btn--sm">
        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i> {{ __('Continue payment') }}
    </a>
@else
    <span class="ph-badge ph-badge--{{ $badgeCls }}"><i class="fa-solid {{ $badgeIcon }}" aria-hidden="true"></i> {{ $badgeText }}</span>
@endif
