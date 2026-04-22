<x-dropdown align="right" width="48" contentClasses="py-1 client-order-dropdown-panel ring-0">
    <x-slot name="trigger">
        <button type="button" class="co-actions-btn-icon focus:outline-none focus:ring-2 focus:ring-purple-500/50 focus:ring-offset-2 focus:ring-offset-[var(--card)]" title="{{ __('Actions') }}" aria-label="{{ __('Actions') }}">
            <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
        </button>
    </x-slot>
    <x-slot name="content">
        <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'client-order-details-{{ $order->id }}' }))" class="co-order-dropdown-action block w-full px-4 py-2 text-start text-sm leading-5 transition duration-150 ease-in-out">
            {{ __('View Details') }}
        </button>
        @if($d->canCancel && $d->isAwaitingOrPending)
            <button type="button"
                onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'cancel-full-{{ $order->id }}' }))"
                class="co-order-dropdown-action co-order-dropdown-action-danger block w-full px-4 py-2 text-start text-sm leading-5 transition duration-150 ease-in-out">
                {{ __('Cancel & Refund') }}
            </button>
        @elseif($d->canCancel && $d->isInProgressOrProcessing)
            <button type="button"
                onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'cancel-partial-{{ $order->id }}' }))"
                class="co-order-dropdown-action co-order-dropdown-action-warn block w-full px-4 py-2 text-start text-sm leading-5 transition duration-150 ease-in-out">
                {{ __('Cancel (Partial)') }}
            </button>
        @endif
    </x-slot>
</x-dropdown>
