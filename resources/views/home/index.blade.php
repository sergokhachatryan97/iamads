<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>PrimeLike – Order Page Mockup</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        :root {
            --bg: #f4f4f7;
            --card: #ffffff;
            --text: #1c1d24;
            --muted: #6f7383;
            --line: #e8e8ee;
            --dash: #9799a6;
            --blue: #0ea5f5;
            --blue-dark: #0188d8;
            --purple: #7c4dff;
            --shadow: 0 12px 30px rgba(32, 37, 62, 0.08);
            --radius-xl: 28px;
            --radius-lg: 20px;
            --radius-md: 16px;
            --radius-sm: 12px;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background:
                radial-gradient(circle at 0% 100%, rgba(14,165,245,.08), transparent 24%),
                radial-gradient(circle at 100% 100%, rgba(124,77,255,.08), transparent 24%),
                var(--bg);
            color: var(--text);
        }

        .container {
            width: min(1220px, calc(100% - 40px));
            margin: 0 auto;
        }

        .topbar {
            padding: 20px 0;
        }

        .topbar-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
        }

        .brand {
            display: flex;
            align-items: center;
            font-size: 28px;
            font-weight: 700;
        }

        .nav {
            display: flex;
            gap: 44px;
            align-items: center;
            color: #2f3342;
            font-size: 18px;
        }

        .lang-select {
            padding: 8px 32px 8px 14px;
            font-size: 15px;
            border-radius: 12px;
            border: 1px solid var(--line);
            background: rgba(255,255,255,.78);
            color: var(--text);
            cursor: pointer;
        }
        .lang-select:hover { border-color: var(--blue); }

        .login-btn {
            border: 0;
            background: linear-gradient(135deg, var(--blue), var(--blue-dark));
            color: white;
            border-radius: 18px;
            padding: 16px 24px;
            font-size: 18px;
            display: inline-flex;
            align-items: center;
            gap: 14px;
            cursor: pointer;
            box-shadow: var(--shadow);
        }

        .plus {
            font-size: 20px;
            line-height: 1;
            opacity: .9;
        }

        .hero {
            text-align: center;
            padding: 26px 0 28px;
        }

        .hero h1 {
            margin: 0;
            font-size: clamp(42px, 5vw, 68px);
            line-height: 1.06;
            font-weight: 600;
            letter-spacing: -0.03em;
        }

        .hero p {
            margin: 20px auto 0;
            max-width: 820px;
            font-size: 18px;
            color: #424655;
            line-height: 1.5;
        }

        .social-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            margin: 10px 0 22px;
            position: relative;
            z-index: 20;
        }

        .category-wrap {
            position: relative;
            display: inline-flex;
        }

        .social-btn {
            min-width: 66px;
            height: 62px;
            border-radius: 18px;
            border: 1px solid var(--line);
            background: rgba(255,255,255,.78);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 0 18px;
            box-shadow: 0 6px 16px rgba(28,29,36,0.04);
            cursor: pointer;
            transition: .2s ease;
            font-size: 20px;
            font-weight: 600;
            color: #222;
        }

        .social-btn.active {
            min-width: 154px;
            color: white;
            background: linear-gradient(135deg, var(--blue), #1d8adf);
            border-color: transparent;
        }

        .social-btn:hover { transform: translateY(-1px); }
        .social-btn .btn-icon { display: inline-flex; align-items: center; }

        .category-popover {
            position: absolute;
            top: calc(100% + 14px);
            left: 50%;
            transform: translateX(-50%);
            min-width: 240px;
            background: rgba(255,255,255,.96);
            border: 1px solid rgba(233,235,242,.95);
            border-radius: 24px;
            box-shadow: 0 18px 40px rgba(24, 31, 52, 0.12);
            padding: 20px 22px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px 26px;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: .18s ease;
        }

        .category-popover::before {
            content: "";
            position: absolute;
            top: -8px;
            left: 50%;
            width: 16px;
            height: 16px;
            background: rgba(255,255,255,.96);
            border-top: 1px solid rgba(233,235,242,.95);
            border-left: 1px solid rgba(233,235,242,.95);
            transform: translateX(-50%) rotate(45deg);
        }

        .category-wrap.open .category-popover {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
        }

        .popover-link {
            border: 0;
            background: transparent;
            padding: 0;
            text-align: left;
            font-size: 15px;
            color: #272b36;
            cursor: pointer;
            transition: color .16s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .popover-link:hover,
        .popover-link.active {
            color: var(--blue);
        }

        .popover-icon { font-size: 18px; }

        .workspace {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 22px;
            align-items: start;
            margin: 8px 0 40px;
        }

        .services-panel,
        .order-panel {
            background: rgba(255,255,255,.72);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,.8);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow);
        }

        .services-panel {
            padding: 14px;
        }

        .service-group-title {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 18px 18px;
            border-radius: 18px;
            background: white;
            border: 1px solid var(--line);
            margin-bottom: 12px;
            font-size: 18px;
            font-weight: 600;
        }

        .service-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .service-item {
            display: flex;
            align-items: center;
            gap: 16px;
            min-height: 64px;
            padding: 0 18px;
            border-radius: 20px;
            background: rgba(255,255,255,.84);
            border: 1.5px dashed var(--dash);
            color: #20222c;
            font-size: 18px;
            cursor: pointer;
            transition: .18s ease;
            position: relative;
        }

        .service-item:hover {
            border-color: var(--blue);
            background: #fff;
            transform: translateX(2px);
        }

        .service-item.active {
            background: white;
            border-style: solid;
            border-color: #dfe7f5;
            box-shadow: inset 4px 0 0 var(--blue);
        }

        .icon {
            width: 30px;
            text-align: center;
            font-size: 24px;
            flex: 0 0 30px;
        }

        .icon-img, .icon-svg { display: inline-block; width: 1em; height: 1em; vertical-align: middle; }

        .order-panel {
            padding: 26px;
        }

        .selected-service {
            border-radius: 24px;
            border: 1px solid var(--line);
            background: linear-gradient(180deg, rgba(255,255,255,.96), rgba(248,249,255,.96));
            padding: 28px 30px;
            margin-bottom: 20px;
        }

        .selected-service-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 14px;
        }

        .selected-service-header .badge {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--blue), #59b3ff);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            box-shadow: 0 10px 18px rgba(14,165,245,.18);
        }

        .selected-service h2 {
            margin: 0;
            font-size: 30px;
            line-height: 1.15;
        }

        .selected-service p {
            margin: 0;
            color: #3c4150;
            font-size: 18px;
            line-height: 1.55;
            max-width: 900px;
        }

        .order-fields {
            display: grid;
            grid-template-columns: 1fr 170px;
            gap: 16px;
            margin-bottom: 18px;
        }

        .input-wrap,
        .qty-wrap {
            height: 72px;
            border-radius: 20px;
            border: 1px solid #dde2eb;
            background: white;
            display: flex;
            align-items: center;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(35, 42, 62, 0.04);
        }

        .input-wrap .prefix,
        .qty-wrap .prefix {
            width: 72px;
            height: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--blue);
            font-size: 28px;
            border-right: 1px solid #edf0f5;
            background: linear-gradient(180deg, #fbfdff, #f4f8ff);
        }

        input,
        select {
            width: 100%;
            border: 0;
            outline: 0;
            background: transparent;
            font-size: 18px;
            color: #21232d;
            padding: 0 22px;
            font-family: inherit;
        }

        .summary {
            border-top: 1px solid var(--line);
            padding-top: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px 24px;
            align-items: center;
            justify-content: space-between;
        }

        .summary-text {
            font-size: 22px;
            color: #20232d;
        }

        .summary-text strong {
            font-weight: 700;
        }

        .actions {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .ghost-btn {
            border: 0;
            background: transparent;
            color: #2b2f3b;
            font-size: 18px;
            cursor: pointer;
            padding: 12px 6px;
        }

        .primary-btn {
            border: 0;
            color: white;
            border-radius: 18px;
            padding: 18px 36px;
            min-width: 250px;
            font-size: 18px;
            letter-spacing: .08em;
            text-transform: uppercase;
            background: linear-gradient(135deg, #2e6bff, var(--purple));
            box-shadow: 0 12px 22px rgba(88, 87, 255, 0.24);
            cursor: pointer;
        }

        .service-hint {
            color: var(--muted);
            font-size: 15px;
            margin-top: 8px;
        }

        .input-wrap.invalid,
        .qty-wrap.invalid {
            border-color: #ef4444;
            box-shadow: 0 0 0 2px rgba(239,68,68,.15);
        }

        .error-hint { color: #ef4444; font-size: 14px; margin-top: 6px; }

        .primary-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .toast {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: #0f766e;
            color: white;
            padding: 16px 28px;
            border-radius: 16px;
            font-size: 16px;
            font-weight: 500;
            box-shadow: 0 12px 32px rgba(0,0,0,.2);
            z-index: 9999;
            opacity: 0;
            transition: transform .3s ease, opacity .3s ease;
        }
        .toast.show {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }

        @media (max-width: 1100px) {
            .workspace {
                grid-template-columns: 1fr;
            }

            .nav { display: none; }
        }

        .footer {
            margin-top: 60px;
            padding: 48px 0 32px;
            background: linear-gradient(180deg, #1a1c24 0%, #15171e 100%);
            color: rgba(255,255,255,.9);
            font-size: 14px;
        }
        .footer-inner { display: grid; grid-template-columns: 1fr auto auto; gap: 40px 48px; max-width: 1200px; margin: 0 auto; padding: 0 24px; }
        .footer-brand .logo { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; font-size: 22px; font-weight: 700; }
        .footer-brand .tagline { font-size: 16px; line-height: 1.5; margin-bottom: 12px; opacity: .95; }
        .footer-links { display: flex; gap: 48px; }
        .footer-links-col a { display: block; color: rgba(255,255,255,.9); text-decoration: none; margin-bottom: 10px; }
        .footer-links-col a:hover { color: var(--blue); }
        .footer-support h4 { font-size: 15px; margin: 0 0 12px; font-weight: 600; }
        .footer-support .ask-btn { display: inline-flex; align-items: center; gap: 8px; background: var(--blue); color: white; border: 0; padding: 12px 24px; border-radius: 12px; font-size: 15px; font-weight: 500; cursor: pointer; text-decoration: none; margin-bottom: 12px; }
        .footer-support .ask-btn:hover { opacity: .9; }
        .footer-support .email { color: var(--blue); text-decoration: none; display: block; margin-bottom: 8px; }
        .footer-support .email:hover { text-decoration: underline; }
        .footer-support .hours { opacity: .85; font-size: 13px; }
        .footer-bottom { margin-top: 40px; padding-top: 24px; border-top: 1px solid rgba(255,255,255,.1); text-align: center; }
        .footer-payments { margin-bottom: 16px; font-size: 13px; opacity: .8; }
        .footer-copy { font-size: 13px; opacity: .7; }
        @media (max-width: 900px) { .footer-inner { grid-template-columns: 1fr; } }

        @media (max-width: 760px) {
            .container { width: min(100% - 24px, 100%); }
            .hero h1 { font-size: 40px; }
            .order-fields { grid-template-columns: 1fr; }
            .summary { align-items: flex-start; }
            .actions { width: 100%; justify-content: space-between; }
            .primary-btn { min-width: 0; width: 100%; }
            .service-item { font-size: 17px; }
        }
    </style>
</head>
<body>
<header class="topbar">
    <div class="container topbar-inner">
        <div class="brand">
            <div class="brand-mark1"><img src="{{ asset('images/logo.png') }}" alt="Logo" width="100" height="100"></div>
            <div>SmmTool</div>
        </div>

        <nav class="nav">
            <a href="{{ route('home') }}" style="color:inherit;text-decoration:none;">{{ __('home.nav_services') }}</a>
            <a href="{{ route('contacts') }}" style="color:inherit;text-decoration:none;">{{ __('home.nav_contacts') }}</a>
            <x-language-dropdown :simple="true" />
        </nav>

        @auth('client')
        <div style="display:flex;align-items:center;gap:12px;">
            <a href="{{ route('client.orders.index') }}" class="login-btn" style="text-decoration:none;color:inherit;display:inline-flex;">
                <span>{{ __('common.my_orders') }}</span>
            </a>
            <form method="POST" action="{{ route('logout') }}" class="inline" style="margin:0;">
                @csrf
                <button type="submit" style="border:0;background:transparent;color:#2f3342;font-size:18px;cursor:pointer;padding:8px 0;">
                    {{ __('common.logout') }}
                </button>
            </form>
        </div>
        @else
        <a href="{{ route('login') }}" class="login-btn" style="text-decoration:none;color:inherit;display:inline-flex;">
            <span>{{ __('home.login') }}</span>
            <span class="plus">＋</span>
        </a>
        @endauth
    </div>
</header>

<main class="container">
    @if($errors->any())
        <div class="mb-4 p-4 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">
            @foreach($errors->all() as $err)
                <p>{{ $err }}</p>
            @endforeach
        </div>
    @endif
    @if(session('info'))
        <div class="mb-4 p-4 rounded-lg bg-blue-50 border border-blue-200 text-blue-800 text-sm">
            {{ session('info') }}
        </div>
    @endif
    <section class="hero">
        <h1 id="heroTitle">{{ __('home.hero_title', ['category' => $firstCategoryName ?? '']) }}</h1>
        <p id="heroSubtitle">{{ __('home.hero_subtitle') }}</p>
    </section>

    <section class="social-row" id="categoryRow">
        <!-- Categories and services rendered dynamically by JS -->
    </section>

    <section class="workspace">
        <aside class="services-panel">
            <div class="service-group-title">
                <span class="icon" id="serviceGroupIcon">👥</span>
                <span id="serviceGroupTitle">{{ __('home.services_of', ['category' => $firstCategoryName ?? '']) }}</span>
            </div>

            <div class="service-list" id="serviceList">
                <!-- Rendered by JS -->
            </div>
        </aside>

        <section class="order-panel">
            <div class="selected-service">
                <div class="selected-service-header">
                    <div class="badge" id="serviceBadge">✈️</div>
                    <h2 id="serviceTitle">Просмотры</h2>
                </div>
                <p id="serviceDescription">Боты. Весь мир. Канал должен быть открыт. Ссылку указывать на конкретный пост.</p>
            </div>

            <form id="orderForm" class="order-fields" method="POST" action="{{ route('home.create-order') }}" novalidate>
                @csrf
                <input type="hidden" name="service_id" id="formServiceId" value="">
                <input type="hidden" name="category_id" id="formCategoryId" value="">
                <div>
                    <div class="input-wrap" id="linkWrap">
                        <div class="prefix">✈️</div>
                        <input type="text" id="linkInput" name="link" placeholder="https://t.me/username or @username" autocomplete="off" />
                    </div>
                    <div class="service-hint" id="linkHint">{{ __('home.link_hint') }}</div>
                    <div class="error-hint" id="linkError" style="display:none"></div>
                </div>

                <div>
                    <div class="qty-wrap" id="qtyWrap">
                        <div class="prefix">#</div>
                        <input type="number" id="qtyInput" name="quantity" value="100" min="100" max="100000" step="100" />
                    </div>
                    <div class="error-hint" id="qtyError" style="display:none"></div>
                </div>

                <div class="summary">
                    <div class="summary-text" id="summaryText">{{ __('home.total_links') }} | {{ __('home.estimated_charge') }}: <strong>0.00</strong> {{ config('contact.currency', '$') }}</div>
                    <div class="actions">
                        <button type="button" class="ghost-btn" id="cancelBtn">{{ __('home.cancel') }}</button>
                        <button type="submit" class="primary-btn" id="createOrderBtn">{{ __('home.place_order') }}</button>
                    </div>
                </div>
            </form>
        </section>
    </section>
</main>

<footer class="footer">
    <div class="footer-inner">
        <div class="footer-brand">
            <div class="logo">

                <div class="brand">
                    <div class="brand-mark1"><img src="{{ asset('images/logo.png') }}" alt="Logo" width="100" height="100"></div>
                    <div>SmmTool</div>
                </div>
            </div>
        </div>
        <div class="footer-support">
            <h4>{{ __('home.footer_have_questions') }}</h4>
            @if(!empty($contactEmail))
            <a href="mailto:{{ $contactEmail }}" class="ask-btn">{{ __('home.footer_ask') }} ❓</a>
            <a href="mailto:{{ $contactEmail }}" class="email">{{ $contactEmail }}</a>
            @endif
            @if(!empty($contactTelegram))
            <a href="https://t.me/{{ ltrim($contactTelegram, '@') }}" target="_blank" rel="noopener" class="email">Telegram: {{ $contactTelegram }}</a>
            @endif
            @if(config('contact.support_hours'))
            <div class="hours">{{ __('home.footer_support_hours') }}: {{ config('contact.support_hours') }}</div>
            @else
            <div class="hours">{{ __('home.footer_support_hours') }}: {{ __('home.footer_24_7') }}</div>
            @endif
        </div>
    </div>
    <div class="footer-bottom">
        <div class="footer-payments">{{ __('home.footer_payments') }}: 💳</div>
        <div class="footer-copy">© {{ config('contact.company_name') }}, {{ date('Y') }}. {{ __('home.footer_rights') }}</div>
    </div>
</footer>

<script>
(function() {
    'use strict';

    // --- Data from HomeController ---
    const CATEGORIES = @json($categoriesForJs ?? []);
    const T = @json($translations ?? []);
    const CURRENCY = @json(config('contact.currency', '$'));

    // --- State ---
    let state = {
        selectedCategoryId: CATEGORIES.length ? CATEGORIES[0].id : null,
        selectedServiceId: CATEGORIES.length && CATEGORIES[0].services.length ? CATEGORIES[0].services[0].id : null
    };

    // --- DOM ---
    const categoryRow = document.getElementById('categoryRow');
    const serviceList = document.getElementById('serviceList');
    const serviceGroupTitle = document.getElementById('serviceGroupTitle');
    const serviceGroupIcon = document.getElementById('serviceGroupIcon');
    const serviceTitle = document.getElementById('serviceTitle');
    const serviceDescription = document.getElementById('serviceDescription');
    const serviceBadge = document.getElementById('serviceBadge');
    const linkInput = document.getElementById('linkInput');
    const linkWrap = document.getElementById('linkWrap');
    const linkError = document.getElementById('linkError');
    const linkHint = document.getElementById('linkHint');
    const qtyInput = document.getElementById('qtyInput');
    const qtyWrap = document.getElementById('qtyWrap');
    const qtyError = document.getElementById('qtyError');
    const summaryText = document.getElementById('summaryText');
    const createOrderBtn = document.getElementById('createOrderBtn');
    const cancelBtn = document.getElementById('cancelBtn');
    const heroTitle = document.getElementById('heroTitle');
    const heroSubtitle = document.getElementById('heroSubtitle');
    const linkPrefix = document.querySelector('#linkWrap .prefix');
    const qtyPrefix = document.querySelector('#qtyWrap .prefix');

    function getCategory(id) { return CATEGORIES.find(c => c.id == id); }
    function getService(catId, svcId) { const c = getCategory(catId); return c && c.services.find(s => s.id == svcId); }
    function getSelectedCategory() { return getCategory(state.selectedCategoryId); }
    function getSelectedService() { return getService(state.selectedCategoryId, state.selectedServiceId); }
    function getServiceIcon(service, category) { return (service && service.icon) || (category && category.icon) || ''; }

    function renderIconHtml(icon) {
        if (!icon) return '';
        const s = String(icon);
        if (s.trim().toLowerCase().startsWith('<svg')) return '<span class="icon-svg">' + s + '</span>';
        if (s.trim().toLowerCase().startsWith('data:')) return '<img src="' + escapeHtml(s) + '" alt="" class="icon-img">';
        if (/^(fas|far|fab|fal|fad)\s/.test(s)) return '<i class="' + escapeHtml(s) + '"></i>';
        return '<span class="icon-emoji">' + escapeHtml(s) + '</span>';
    }

    function updateHero() {
        const cat = getSelectedCategory();
        heroTitle.textContent = cat ? (T.hero_title || 'Promotion in :category').replace(':category', cat.name) : (T.hero_select_category || 'Select a category');
        heroSubtitle.textContent = cat ? (T.hero_subtitle || 'Choose a service and place your order.') : (T.hero_subtitle_empty || 'Choose a category from the list above.');
    }

    function renderCategories() {
        categoryRow.innerHTML = '';
        const frag = document.createDocumentFragment();
        CATEGORIES.forEach(cat => {
            const wrap = document.createElement('div');
            wrap.className = 'category-wrap';
            wrap.dataset.categoryId = cat.id;
            const iconHtml = renderIconHtml(cat.icon);
            const isActive = cat.id == state.selectedCategoryId;
            const label = isActive
                ? (iconHtml ? '<span class="btn-icon">' + iconHtml + '</span> ' : '') + '<span>' + escapeHtml(cat.name) + '</span>'
                : (iconHtml ? '<span class="btn-icon">' + iconHtml + '</span>' : '<span>' + escapeHtml(cat.name) + '</span>');
            wrap.innerHTML =
                '<button type="button" class="social-btn category-trigger' + (isActive ? ' active' : '') + '">' + label +
                '</button>' +
                '<div class="category-popover">' +
                    cat.services.map(s => {
                        const svcIcon = getServiceIcon(s, cat);
                        return '<button type="button" class="popover-link" data-category-id="' + cat.id + '" data-service-id="' + s.id + '">' +
                            (svcIcon ? '<span class="popover-icon">' + renderIconHtml(svcIcon) + '</span> ' : '') + escapeHtml(s.name) +
                        '</button>';
                    }).join('') +
                '</div>';
            frag.appendChild(wrap);
        });
        categoryRow.appendChild(frag);
    }

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function renderSidebar() {
        const cat = getSelectedCategory();
        if (!cat) return;
        serviceGroupTitle.textContent = (T.services_of || 'Services :category').replace(':category', cat.name);
        serviceGroupIcon.innerHTML = renderIconHtml(cat.icon);
        serviceList.innerHTML = cat.services.map(s => {
            const active = s.id == state.selectedServiceId ? ' active' : '';
            const icon = getServiceIcon(s, cat);
            return '<button type="button" class="service-item' + active + '" data-category-id="' + cat.id + '" data-service-id="' + s.id + '">' +
                '<span class="icon">' + renderIconHtml(icon) + '</span><span>' + escapeHtml(s.name) + '</span></button>';
        }).join('');
    }

    function updateRightPanel() {
        const svc = getSelectedService();
        const cat = getSelectedCategory();
        if (!svc || !cat) return;
        const icon = getServiceIcon(svc, cat);
        serviceTitle.textContent = svc.name;
        serviceDescription.textContent = svc.description || '';
        serviceBadge.innerHTML = icon ? renderIconHtml(icon) : '';
        if (linkPrefix) linkPrefix.innerHTML = renderIconHtml(icon || '🔗');
        linkInput.placeholder = svc.placeholder || 'https://t.me/...';
        linkInput.value = linkInput.value || '';
        qtyInput.min = svc.min;
        qtyInput.max = svc.max;
        qtyInput.step = svc.step;
        let qty = parseInt(qtyInput.value, 10) || svc.min;
        qty = Math.max(svc.min, Math.min(svc.max, Math.round((qty - svc.min) / svc.step) * svc.step + svc.min));
        qtyInput.value = qty;
        validate();
        updatePrice();
    }

    function updatePrice() {
        const svc = getSelectedService();
        if (!svc) return;
        const qty = parseInt(qtyInput.value, 10) || svc.min;
        const pricePer1000 = parseFloat(svc.pricePer1000) || 0;
        const charge = ((qty / 1000) * pricePer1000).toFixed(2);
        const totalLinks = (T && T.total_links) || '1 link';
        const estimatedCharge = (T && T.estimated_charge) || 'Estimated charge';
        summaryText.innerHTML = totalLinks + ' | ' + estimatedCharge + ': <strong>' + charge + '</strong> ' + CURRENCY;
    }

    function validateLinkByType(v, linkType) {
        if (!v || v.length < 3) return false;
        const t = (linkType || 'generic').trim();
        if (t === 'youtube') {
            return /^(https?:\/\/)?(www\.)?(youtube\.com\/(watch\?v=|shorts\/|embed\/)[A-Za-z0-9_\-]+|youtube\.com\/@[A-Za-z0-9_.\-]+|youtube\.com\/channel\/UC[A-Za-z0-9_\-]+|youtube\.com\/c\/[A-Za-z0-9_.\-]+|youtu\.be\/[A-Za-z0-9_\-]+)/i.test(v);
        }
        if (t === 'max') {
            return /^(https?:\/\/)?(www\.)?(max\.ru\/[^\s]+|maxapp\.ru\/[^\s]+|web\.maxapp\.ru\/[^\s]+)/i.test(v);
        }
        if (t === 'whatsapp') {
            return /^(https?:\/\/)?(www\.)?(wa\.me\/\d+[?\d\w\-=]*|chat\.whatsapp\.com\/[A-Za-z0-9_-]+|api\.whatsapp\.com\/send\?[^\s]+)/i.test(v);
        }
        if (t === 'tiktok') {
            return /^(https?:\/\/)?(www\.)?(tiktok\.com\/|vm\.tiktok\.com\/|vt\.tiktok\.com\/)[^\s]+/i.test(v);
        }
        if (t === 'instagram') {
            return /^(https?:\/\/)?(www\.)?(instagram\.com\/|instagr\.am\/)[^\s]+/i.test(v);
        }
        if (t === 'facebook') {
            return /^(https?:\/\/)?(www\.|m\.)?(facebook\.com\/|fb\.com\/|fb\.watch\/|fb\.me\/)[^\s]+/i.test(v);
        }
        if (t === 'url' || t === 'generic') {
            return v.length >= 5 && (/^https?:\/\//i.test(v) || /^[a-zA-Z0-9][a-zA-Z0-9.-]*\.[a-zA-Z]{2,}/.test(v));
        }
        // telegram (default for unknown)
        const hasTme = /t\.me\/|telegram\.me\//i.test(v);
        const hasUser = /^@?[a-zA-Z0-9_]{3,32}$/.test(v);
        return v.length >= 5 && (hasTme || hasUser);
    }
    function validateLink() {
        const v = (linkInput.value || '').trim();
        const cat = getSelectedCategory();
        const linkType = (cat && cat.linkType) ? cat.linkType : 'generic';
        return validateLinkByType(v, linkType);
    }

    function validateQty() {
        const svc = getSelectedService();
        if (!svc) return false;
        const qty = parseInt(qtyInput.value, 10);
        return !isNaN(qty) && qty >= svc.min && qty <= svc.max && (qty - svc.min) % svc.step === 0;
    }

    function validate() {
        const linkOk = validateLink();
        const qtyOk = validateQty();
        linkWrap.classList.toggle('invalid', !linkOk);
        qtyWrap.classList.toggle('invalid', !qtyOk);
        const cat = getSelectedCategory();
        const linkType = (cat && cat.linkType) ? cat.linkType : 'generic';
        const linkMessages = {
            telegram: (T && T.link_error) ? T.link_error : 'Enter a valid link (t.me/... or @username)',
            youtube: (T && T.link_error_youtube) ? T.link_error_youtube : 'Enter a valid YouTube link.',
            max: (T && T.link_error_max) ? T.link_error_max : 'Enter a valid MAX Messenger link.',
            whatsapp: (T && T.link_error_other) ? T.link_error_other : 'Enter a valid WhatsApp link.',
            tiktok: (T && T.link_error_other) ? T.link_error_other : 'Enter a valid link.',
            instagram: (T && T.link_error_other) ? T.link_error_other : 'Enter a valid link.',
            facebook: (T && T.link_error_other) ? T.link_error_other : 'Enter a valid link.',
            url: (T && T.link_error_other) ? T.link_error_other : 'Enter a valid link.',
            generic: (T && T.link_error_other) ? T.link_error_other : 'Enter a valid link.',
        };
        const linkMsg = !linkOk && linkInput.value.trim() ? (linkMessages[linkType] || linkMessages.generic) : '';
        linkError.textContent = linkMsg;
        linkError.style.display = linkMsg ? 'block' : 'none';
        const svc = getSelectedService();
        qtyError.style.display = qtyOk ? 'none' : 'block';
        qtyError.textContent = !qtyOk && svc ? (T.qty_error || 'Quantity :min–:max, step :step').replace(':min', svc.min).replace(':max', svc.max).replace(':step', svc.step) : '';
        createOrderBtn.disabled = !(linkOk && qtyOk);
        return linkOk && qtyOk;
    }

    function selectCategory(catId, preserveService) {
        state.selectedCategoryId = catId;
        const cat = getCategory(catId);
        if (!preserveService || !cat || !cat.services.some(s => s.id == state.selectedServiceId)) {
            state.selectedServiceId = cat && cat.services[0] ? cat.services[0].id : null;
        }
        renderCategories();
        setupHoverDelay();
        updateHero();
        renderSidebar();
        updateRightPanel();
    }

    function selectService(catId, svcId) {
        state.selectedCategoryId = catId;
        state.selectedServiceId = svcId;
        selectCategory(catId, true);
        document.querySelectorAll('.popover-link').forEach(btn => {
            btn.classList.toggle('active', String(btn.dataset.serviceId) === String(svcId) && String(btn.dataset.categoryId) === String(catId));
        });
    }

    function setupHoverDelay() {
        document.querySelectorAll('.category-wrap').forEach(wrap => {
            const trigger = wrap.querySelector('.category-trigger');
            const popover = wrap.querySelector('.category-popover');
            let openTid, closeTid;
            const open = () => {
                if (closeTid) { clearTimeout(closeTid); closeTid = null; }
                if (openTid) return;
                openTid = setTimeout(() => { wrap.classList.add('open'); openTid = null; }, 180);
            };
            const close = () => {
                if (openTid) { clearTimeout(openTid); openTid = null; }
                closeTid = setTimeout(() => { wrap.classList.remove('open'); closeTid = null; }, 220);
            };
            const cancelClose = () => { if (closeTid) { clearTimeout(closeTid); closeTid = null; } };
            trigger.addEventListener('mouseenter', open);
            trigger.addEventListener('mouseleave', close);
            popover.addEventListener('mouseenter', () => { cancelClose(); wrap.classList.add('open'); });
            popover.addEventListener('mouseleave', close);
        });
    }

    function showToast(msg) {
        let t = document.getElementById('orderToast');
        if (!t) { t = document.createElement('div'); t.id = 'orderToast'; t.className = 'toast'; document.body.appendChild(t); }
        t.textContent = msg;
        t.classList.add('show');
        clearTimeout(t._tid);
        t._tid = setTimeout(() => t.classList.remove('show'), 2800);
    }

    function handleCreateOrder(e) {
        e.preventDefault();
        formServiceId.value = state.selectedServiceId || '';
        formCategoryId.value = state.selectedCategoryId || '';
        if (!validate()) return;
        orderForm.submit();
    }

    function init() {
        renderCategories();
        updateHero();
        renderSidebar();
        updateRightPanel();
        setupHoverDelay();

        categoryRow.addEventListener('click', e => {
            const trigger = e.target.closest('.category-trigger');
            if (trigger) { selectCategory(trigger.closest('.category-wrap').dataset.categoryId); return; }
            const popoverLink = e.target.closest('.popover-link');
            if (popoverLink) { selectService(popoverLink.dataset.categoryId, popoverLink.dataset.serviceId); }
        });

        serviceList.addEventListener('click', e => {
            const item = e.target.closest('.service-item');
            if (item) selectService(item.dataset.categoryId, item.dataset.serviceId);
        });

        linkInput.addEventListener('input', validate);
        linkInput.addEventListener('blur', validate);
        qtyInput.addEventListener('input', () => { validate(); updatePrice(); });
        qtyInput.addEventListener('change', () => {
            const svc = getSelectedService();
            if (svc) {
                let qty = parseInt(qtyInput.value, 10) || svc.min;
                qty = Math.max(svc.min, Math.min(svc.max, Math.round((qty - svc.min) / svc.step) * svc.step + svc.min));
                qtyInput.value = qty;
                updatePrice();
            }
        });
        orderForm.addEventListener('submit', handleCreateOrder);
        cancelBtn.addEventListener('click', () => {
            linkInput.value = '';
            const svc = getSelectedService();
            if (svc) { qtyInput.value = svc.min; updatePrice(); }
            validate();
        });
    }

    init();
})();
</script>
</body>
</html>
