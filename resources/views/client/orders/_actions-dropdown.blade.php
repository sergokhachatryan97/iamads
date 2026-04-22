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
            <div
                x-data="{
                    openModal() { $dispatch('open-modal', 'cancel-full-{{ $order->id }}'); },
                    submitForm() { document.getElementById('cancel-full-form-{{ $order->id }}').submit(); }
                }"
                x-on:confirm-action.window="if ($event.detail === 'cancel-full-{{ $order->id }}') { submitForm(); }"
            >
                <button type="button" @click="openModal()" class="co-order-dropdown-action co-order-dropdown-action-danger block w-full px-4 py-2 text-start text-sm leading-5 transition duration-150 ease-in-out">
                    {{ __('Cancel & Refund') }}
                </button>
                <x-confirm-modal
                    name="cancel-full-{{ $order->id }}"
                    theme="smm"
                    title="{{ __('Cancel Order') }}"
                    :message="__('Are you sure you want to cancel this order? A full refund of $:amount will be processed.', ['amount' => $order->charge])"
                    confirm-text="{{ __('Yes, Cancel Order') }}"
                    cancel-text="{{ __('No, Keep Order') }}"
                    confirm-button-class="bg-red-600 hover:bg-red-700"
                >
                    <form id="cancel-full-form-{{ $order->id }}" action="{{ route('client.orders.cancelFull', $order) }}" method="POST" class="hidden">@csrf</form>
                </x-confirm-modal>
            </div>
        @elseif($d->canCancel && $d->isInProgressOrProcessing)
            <div
                x-data="{
                    openModal() { $dispatch('open-modal', 'cancel-partial-{{ $order->id }}'); },
                    submitForm() { document.getElementById('cancel-partial-form-{{ $order->id }}').submit(); }
                }"
                x-on:confirm-action.window="if ($event.detail === 'cancel-partial-{{ $order->id }}') { submitForm(); }"
            >
                <button type="button" @click="openModal()" class="co-order-dropdown-action co-order-dropdown-action-warn block w-full px-4 py-2 text-start text-sm leading-5 transition duration-150 ease-in-out">
                    {{ __('Cancel (Partial)') }}
                </button>
                <x-confirm-modal
                    name="cancel-partial-{{ $order->id }}"
                    theme="smm"
                    title="{{ __('Cancel Remaining Part') }}"
                    :message="__('Are you sure you want to cancel the remaining part of this order? A partial refund will be processed for the undelivered quantity.')"
                    confirm-text="{{ __('Yes, Cancel Remaining') }}"
                    cancel-text="{{ __('No, Keep Order') }}"
                    confirm-button-class="bg-orange-600 hover:bg-orange-700"
                >
                    <form id="cancel-partial-form-{{ $order->id }}" action="{{ route('client.orders.cancelPartial', $order) }}" method="POST" class="hidden">@csrf</form>
                </x-confirm-modal>
            </div>
        @endif
    </x-slot>
</x-dropdown>
