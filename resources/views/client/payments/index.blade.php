@php
    $filters = [
        'all'          => __('All'),
        'paid'         => __('Paid'),
        'pending'      => __('Awaiting payment'),
        'unsuccessful' => __('Unsuccessful'),
    ];
@endphp

<x-client-layout :title="__('Payment History')">
    @include('client.payments._styles')
    <style>
        .ph-page { max-width: 820px; margin: 0 auto; }
        .ph-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 18px; }
        .ph-head p { margin: 0; font-size: 14px; color: var(--text2); }

        .ph-stats { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 14px; margin-bottom: 20px; }
        .ph-stat {
            background: var(--card); border: 1px solid var(--border); border-radius: var(--radius, 12px);
            padding: 18px; position: relative; overflow: hidden;
        }
        .ph-stat::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
            background: linear-gradient(90deg, var(--cyan), var(--blue), var(--purple));
        }
        .ph-stat-val {
            font-size: 22px; font-weight: 800;
            background: linear-gradient(135deg, var(--cyan), var(--blue), var(--purple));
            -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
        }
        .ph-stat-label { font-size: 12px; color: var(--text3); margin-top: 4px; }

        .ph-tabs { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; }
        .ph-tab {
            padding: 7px 14px; border-radius: 999px; font-size: 13px; font-weight: 600;
            color: var(--text2); background: var(--card); border: 1px solid var(--border); text-decoration: none;
        }
        .ph-tab:hover { border-color: rgba(139,92,246,0.4); color: var(--text); }
        .ph-tab.active { background: rgba(139,92,246,0.12); border-color: var(--purple); color: var(--text); }

        .ph-list { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius, 12px); overflow: hidden; }
        .ph-row { display: flex; align-items: center; gap: 14px; padding: 14px 18px; border-bottom: 1px solid var(--border); }
        .ph-row:last-child { border-bottom: 0; }
        .ph-main { flex: 1; min-width: 0; }
        .ph-meta { font-size: 12px; color: var(--text3); margin-top: 3px; }
        .ph-amount { font-size: 15px; font-weight: 700; color: var(--text2); white-space: nowrap; text-align: right; }
        .ph-amount.paid { color: var(--teal, #00b894); }
        .ph-side { display: flex; flex-direction: column; align-items: flex-end; gap: 2px; }
        .ph-action { min-width: 150px; display: flex; justify-content: flex-end; }

        .ph-empty { text-align: center; padding: 44px 16px; color: var(--text3); font-size: 14px; }
        .ph-empty i { font-size: 30px; color: var(--purple-light); margin-bottom: 12px; display: block; }
        .ph-empty p { margin: 0 0 16px; }

        .ph-pager { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-top: 14px; font-size: 13px; color: var(--text3); }
        .ph-pager a, .ph-pager span.off {
            padding: 7px 14px; border-radius: 10px; border: 1px solid var(--border);
            background: var(--card); color: var(--text2); text-decoration: none;
        }
        .ph-pager span.off { opacity: 0.45; }
        .ph-note { font-size: 12px; color: var(--text3); margin-top: 14px; line-height: 1.5; }

        @media (max-width: 560px) {
            .ph-row { flex-wrap: wrap; padding: 14px; }
            .ph-main { flex-basis: 100%; }
            .ph-side { flex: 1; align-items: flex-start; padding-left: 52px; }
            .ph-action { min-width: 0; }
        }
    </style>

    <div class="ph-page">
        <div class="ph-head">
            <p>{{ __('All your balance top-ups in one place.') }}</p>
            <a href="{{ route('client.balance.add') }}" class="ph-btn">
                <i class="fa-solid fa-plus" aria-hidden="true"></i> {{ __('Add Balance') }}
            </a>
        </div>

        <div class="ph-stats">
            <div class="ph-stat">
                <div class="ph-stat-val">${{ number_format($totalDeposited, 2) }}</div>
                <div class="ph-stat-label">{{ __('Total deposited') }}</div>
            </div>
            <div class="ph-stat">
                <div class="ph-stat-val">{{ $paidCount }}</div>
                <div class="ph-stat-label">{{ __('Successful payments') }}</div>
            </div>
            <div class="ph-stat">
                <div class="ph-stat-val">{{ $lastPaidAt ? \Illuminate\Support\Carbon::parse($lastPaidAt)->format('M j, Y') : '—' }}</div>
                <div class="ph-stat-label">{{ __('Last top-up') }}</div>
            </div>
        </div>

        <nav class="ph-tabs" aria-label="{{ __('Filter') }}">
            @foreach ($filters as $key => $label)
                <a href="{{ route('client.payments.index', $key === 'all' ? [] : ['status' => $key]) }}"
                   class="ph-tab {{ $filter === $key ? 'active' : '' }}">{{ $label }}</a>
            @endforeach
        </nav>

        <div class="ph-list">
            @forelse ($payments as $payment)
                <div class="ph-row">
                    <div class="ph-main ph-method-cell">
                        @include('client.payments._method', ['payment' => $payment])
                    </div>
                    <div class="ph-side">
                        <div class="ph-amount {{ $payment->status === 'paid' ? 'paid' : '' }}">
                            {{ $payment->status === 'paid' ? '+' : '' }}${{ number_format((float) $payment->amount, 2) }}
                        </div>
                        <div class="ph-meta">{{ $payment->created_at->format('M j, Y · H:i') }} · #{{ $payment->id }}</div>
                    </div>
                    <div class="ph-action">
                        @include('client.payments._status', ['payment' => $payment, 'continueSince' => $continueSince])
                    </div>
                </div>
            @empty
                <div class="ph-empty">
                    <i class="fa-solid fa-receipt" aria-hidden="true"></i>
                    @if ($filter === 'all')
                        <p>{{ __('You have no payments yet.') }}</p>
                        <a href="{{ route('client.balance.add') }}" class="ph-btn">
                            <i class="fa-solid fa-plus" style="margin: auto" aria-hidden="true"></i> {{ __('Make your first top-up') }}
                        </a>
                    @else
                        <p>{{ __('No payments match this filter.') }}</p>
                    @endif
                </div>
            @endforelse
        </div>

        @if ($payments->hasPages())
            <div class="ph-pager">
                @if ($payments->onFirstPage())
                    <span class="off">&larr; {{ __('Previous') }}</span>
                @else
                    <a href="{{ $payments->previousPageUrl() }}">&larr; {{ __('Previous') }}</a>
                @endif
                <span>{{ $payments->currentPage() }} / {{ $payments->lastPage() }}</span>
                @if ($payments->hasMorePages())
                    <a href="{{ $payments->nextPageUrl() }}">{{ __('Next') }} &rarr;</a>
                @else
                    <span class="off">{{ __('Next') }} &rarr;</span>
                @endif
            </div>
        @endif

        <p class="ph-note">
            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
            {{ __('Crypto payments are credited automatically after network confirmation, usually within a few minutes. If a payment is not credited, contact support with its number.') }}
        </p>
    </div>
</x-client-layout>
