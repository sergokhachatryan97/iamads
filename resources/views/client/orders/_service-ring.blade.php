<div
    class="order-service-ring relative h-12 w-12 rounded-full shrink-0"
    data-order-id="{{ $order->id }}"
    data-progress="{{ number_format(min(100, max(0, $d->progress)), 1, '.', '') }}"
    style="background: conic-gradient(#0ea5f5 calc({{ number_format(min(100, max(0, $d->progress)), 1, '.', '') }} * 1%), rgba(255,255,255,.25) 0);"
>
    <div class="client-order-avatar-cutout absolute inset-[3px] rounded-full flex items-center justify-center">
        <div class="client-order-avatar-inner h-9 w-9 rounded-full border flex items-center justify-center text-sky-400">
            @if($d->categoryIcon)
                @if(\Illuminate\Support\Str::startsWith($d->categoryIcon, '<svg'))
                    <span class="h-5 w-5 [&_svg]:h-5 [&_svg]:w-5 [&_svg]:text-sky-400">{!! $d->categoryIcon !!}</span>
                @elseif(\Illuminate\Support\Str::startsWith($d->categoryIcon, 'data:'))
                    <img src="{{ $d->categoryIcon }}" alt="icon" class="h-5 w-5 object-contain" />
                @elseif(\Illuminate\Support\Str::startsWith($d->categoryIcon, 'fas ') || \Illuminate\Support\Str::startsWith($d->categoryIcon, 'far ') || \Illuminate\Support\Str::startsWith($d->categoryIcon, 'fab ') || \Illuminate\Support\Str::startsWith($d->categoryIcon, 'fal ') || \Illuminate\Support\Str::startsWith($d->categoryIcon, 'fad '))
                    <i class="{{ $d->categoryIcon }}"></i>
                @else
                    <span class="text-sm font-semibold">{{ $d->categoryIcon }}</span>
                @endif
            @else
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor" aria-hidden="true">
                    <path d="M9.993 15.674l-.425 5.987c.608 0 .872-.261 1.19-.573l2.856-2.744 5.92 4.33c1.085.598 1.85.284 2.137-.999L24 1.255 0 10.246c.707.222 1.94.608 1.94.608l6.845 2.136 15.9-10.037c.75-.452 1.437-.2 .873.252"/>
                </svg>
            @endif
        </div>
    </div>
</div>
