<div class="mb-4 flex flex-wrap gap-x-6 gap-y-1 text-base font-medium text-gray-800">
    <span>{{ $label ?? __('Totals') }} —</span>
    <span>{{ __('Orders') }}: <span class="font-semibold">{{ number_format($totals->orders_count ?? 0) }}</span></span>
    <span>{{ __('Quantity') }}: <span class="font-semibold">{{ number_format($totals->total_quantity ?? 0) }}</span></span>
    <span>{{ __('Remains') }}: <span class="font-semibold">{{ number_format($totals->total_remains ?? 0) }}</span></span>
    <span>{{ __('Delivered') }}: <span class="font-semibold">{{ number_format($totals->total_delivered ?? 0) }}</span></span>
    <span>{{ __('Total charge') }}: <span class="font-semibold">{{ number_format((float) ($totals->total_charge ?? 0), 2) }}</span></span>
</div>
