@props(['variant' => 'default'])

@php
    $supportList = collect(config('contact.telegram_support_list', []))->filter(fn($s) => !empty($s['username']));
    $isFloat = $variant === 'float';
    $isSidebar = $variant === 'sidebar';
    $panelClass = match($variant) {
        'sidebar' => 'absolute bottom-full left-0 mb-2 w-56 rounded-lg shadow-lg overflow-hidden',
        'float' => '',
        default => 'absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-56 bg-white border border-gray-200 rounded-lg shadow-lg overflow-hidden',
    };
    $panelStyle = match($variant) {
        'sidebar' => 'z-index:60;background:var(--card,#1a1a2e);border:1px solid var(--border,rgba(255,255,255,0.08));',
        'float' => 'z-index:9999;position:absolute;bottom:calc(100% + 10px);right:0;width:240px;border-radius:12px;overflow:hidden;background:var(--card,#1a1a2e);border:1px solid var(--border2,rgba(255,255,255,0.08));box-shadow:0 8px 32px rgba(0,0,0,0.45);',
        default => 'z-index:60;',
    };
    $headerClass = $isSidebar
        ? 'px-3 py-2 border-b text-xs font-semibold uppercase tracking-wider'
        : ($isFloat ? '' : 'px-3 py-2 border-b border-gray-100 text-gray-500 text-xs font-semibold uppercase tracking-wider');
    $headerStyle = $isSidebar
        ? 'border-color:var(--border);color:var(--text3);'
        : ($isFloat ? 'padding:10px 14px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;color:var(--text3,#5a6178);border-bottom:1px solid var(--border2,rgba(255,255,255,0.08));' : '');
    $linkClass = $isSidebar
        ? 'flex items-center gap-3 px-3 py-2.5 text-sm transition-colors hover:bg-[rgba(42,171,238,0.12)]'
        : ($isFloat ? '' : 'flex items-center gap-3 px-3 py-2.5 text-sm text-gray-700 transition-colors hover:bg-indigo-50 hover:text-indigo-700');
    $linkStyle = $isSidebar
        ? 'color:var(--text2);'
        : ($isFloat ? 'display:flex;align-items:center;gap:10px;padding:10px 14px;font-size:13px;color:var(--text,#e2e8f0);text-decoration:none;transition:background 0.15s;' : '');
    $subClass = $isSidebar ? 'text-xs ml-auto' : ($isFloat ? '' : 'text-gray-400 text-xs ml-auto');
    $subStyle = $isSidebar
        ? 'color:var(--text3);'
        : ($isFloat ? 'margin-left:auto;font-size:11px;color:var(--text3,#5a6178);' : '');
@endphp

@if($supportList->count() > 0)
<div x-data="{ open: false, timeout: null }"
     x-on:mouseenter="clearTimeout(timeout); open = true"
     x-on:mouseleave="timeout = setTimeout(() => open = false, 300)"
     x-on:click.outside="open = false"
     class="relative inline-block" style="position:relative;display:inline-block;">
    {{ $slot }}

    <div x-show="open" x-transition.opacity x-cloak class="{{ $panelClass }}" style="{{ $panelStyle }}">
        <div class="{{ $headerClass }}" style="{{ $headerStyle }}">
            {{ __('Select Support Language') }}
        </div>
        @foreach($supportList as $support)
            @php $handle = ltrim($support['username'], '@'); @endphp
            <a href="https://t.me/{{ $handle }}" target="_blank" rel="noopener noreferrer"
                class="{{ $linkClass }}" style="{{ $linkStyle }}"
                @if($isFloat) onmouseenter="this.style.background='rgba(42,171,238,0.12)'" onmouseleave="this.style.background='transparent'" @endif>
                <span style="font-size:18px;flex-shrink:0;">{{ $support['flag'] }}</span>
                <span style="font-weight:500;">{{ $support['label'] }}</span>
                <span class="{{ $subClass }}" style="{{ $subStyle }}">{{ '@' . $handle }}</span>
            </a>
        @endforeach
    </div>
</div>
@endif
