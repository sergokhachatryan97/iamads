@props(['simple' => false, 'variant' => 'default'])
@php
    $locales = config('locales', []);
    $currentLocale = app()->getLocale();
    $currentLabel = $locales[$currentLocale]['native'] ?? strtoupper($currentLocale);
@endphp
@if(count($locales) > 1)
@if($variant === 'landing')
@once
    <style>
        .lang-landing-details { position: relative; }
        .lang-landing-details > summary {
            list-style: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 4px;
            margin: 0;
            border: none;
            background: transparent;
            color: var(--text2, #8892a4);
            font-size: 13px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            letter-spacing: 0.04em;
            user-select: none;
            -webkit-user-select: none;
        }
        .lang-landing-details > summary::-webkit-details-marker { display: none; }
        .lang-landing-details > summary::marker { content: ''; }
        .lang-landing-details > summary:hover { color: var(--text, #e2e8f0); }
        .lang-landing-details > summary i { font-size: 14px; opacity: 0.92; }
        .lang-landing-dropdown {
            position: absolute;
            right: 0;
            top: calc(100% + 6px);
            min-width: 148px;
            padding: 6px 0;
            background: var(--card, #1a1a2e);
            border: 1px solid var(--border, var(--border2, rgba(255,255,255,0.08)));
            border-radius: 10px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.35);
            z-index: 60;
        }
        [data-theme="light"] .lang-landing-dropdown {
            box-shadow: 0 12px 36px rgba(0,0,0,0.12);
        }
        .lang-landing-dropdown a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 14px;
            font-size: 13px;
            font-weight: 500;
            color: var(--text2, #8892a4);
            text-decoration: none;
        }
        .lang-landing-dropdown a:hover {
            background: rgba(108,92,231,0.12);
            color: var(--text, #e2e8f0);
        }
        .lang-landing-dropdown a.is-active {
            color: var(--purple-light, #a29bfe);
            font-weight: 700;
            background: rgba(108,92,231,0.08);
        }
        .lang-landing-flag { font-size: 16px; line-height: 1; }
    </style>
@endonce
<details class="lang-landing-details">
    <summary aria-label="{{ __('common.language') }}">
        <i class="fa-solid fa-globe" aria-hidden="true"></i>
        <span>{{ $currentLabel }}</span>
    </summary>
    <div class="lang-landing-dropdown">
        @foreach($locales as $code => $info)
            <a href="{{ route('locale.switch', $code) }}" class="{{ $code === $currentLocale ? 'is-active' : '' }}">
                <span class="lang-landing-flag">{{ $info['flag'] ?? '🌐' }}</span>
                {{ $info['native'] ?? $info['name'] ?? strtoupper($code) }}
            </a>
        @endforeach
    </div>
</details>
@elseif($simple)
<select onchange="window.location.href=this.value" aria-label="{{ __('common.language') }}"
        class="lang-select appearance-none bg-white border border-gray-200 rounded-md px-3 py-2 pr-8 text-sm text-gray-700 cursor-pointer hover:border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
    @foreach($locales as $code => $info)
        <option value="{{ route('locale.switch', $code) }}" {{ $code === $currentLocale ? 'selected' : '' }}>
            {{ ($info['flag'] ?? '') . ' ' . ($info['native'] ?? $info['name'] ?? $code) }}
        </option>
    @endforeach
</select>
@else
<div class="relative" x-data="{ open: false }" @click.outside="open = false">
    <button type="button"
            @click="open = ! open"
            class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-200 rounded-md hover:bg-gray-50 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1 transition-colors">
        <span class="text-lg">{{ $locales[$currentLocale]['flag'] ?? '🌐' }}</span>
        <span>{{ $locales[$currentLocale]['native'] ?? strtoupper($currentLocale) }}</span>
        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
        </svg>
    </button>
    <div x-show="open"
         x-transition:enter="transition ease-out duration-100"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="absolute right-0 z-50 mt-1 w-40 py-1 bg-white rounded-md shadow-lg ring-1 ring-black ring-opacity-5 origin-top-right"
         style="display: none;">
        @foreach($locales as $code => $info)
            <a href="{{ route('locale.switch', $code) }}"
               class="flex items-center gap-2 px-4 py-2 text-sm {{ $code === $currentLocale ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-gray-700 hover:bg-gray-50' }}">
                <span class="text-lg">{{ $info['flag'] ?? '🌐' }}</span>
                {{ $info['native'] ?? $info['name'] ?? $code }}
            </a>
        @endforeach
    </div>
</div>
@endif
@endif
