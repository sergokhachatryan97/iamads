@props([
    'title' => null,
    'client',
    'contactTelegram' => '',
    'hideSidebar' => false,
])

@php
    $smmPageTitle = ($title !== null && $title !== '') ? $title : match (true) {
        request()->routeIs('dashboard') => __('Dashboard'),
        request()->routeIs('client.orders.create') => __('New Order'),
        request()->routeIs('client.orders.index') => __('Orders'),
        request()->routeIs('client.services.*') => __('Services'),
        request()->routeIs('client.subscriptions.my-subscriptions') => __('My Subscriptions'),
        request()->routeIs('client.subscriptions.index') => __('Subscription Plans'),
        request()->routeIs('client.balance.*') => __('Add Funds'),
        request()->routeIs('client.api.*') => __('API Documentation'),
        request()->routeIs('client.referral.*') => __('Referral Program'),
        request()->routeIs('client.account.*') => __('Settings'),
        request()->routeIs('client.orders.*') => __('Orders'),
        request()->routeIs('contacts') => __('contacts.title'),
        default => __('Dashboard'),
    };
    $smmLocaleCount = count(config('locales', []));
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <x-heleket-site-verification />
    <title>{{ $smmPageTitle }} — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" type="image/png" href="{{ asset('images/adtag_fav.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/adtag_fav.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root { --radius: 12px; --sidebar-w: 260px; }
        [data-theme="dark"] {
            --bg: #0a0a0f; --card: #1a1a2e; --card2: #16213e;
            --purple: #6c5ce7; --purple-light: #a29bfe; --purple-dark: #5a4fcf;
            --teal: #00d2d3;
            /* Light copy on dark surfaces (avoid muddy grays that fail on --card) */
            --text: #ffffff; --text2: #ffffff; --text3: #ffffff;
            --border: rgba(255,255,255,0.08);
            --sidebar-bg: #12121c;
        }
        [data-theme="light"] {
            --bg: #f5f7fa; --card: #ffffff; --card2: #f0f2f7;
            --purple: #6c5ce7; --purple-light: #7c6ff0; --purple-dark: #5a4fcf;
            --teal: #00b4b5;
            --text: #1a1a2e; --text2: #5a6178; --text3: #8892a4;
            --border: rgba(0,0,0,0.08);
            --sidebar-bg: #ffffff;
        }
        .smm-dash-body {
            margin: 0;
            font-family: Inter, system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            min-height: 100dvh;
            overflow-x: hidden;
        }
        .smm-dash-shell { display: flex; min-height: 100vh; }
        .smm-dash-sidebar {
            width: var(--sidebar-w); flex-shrink: 0; background: var(--sidebar-bg);
            border-right: 1px solid var(--border); display: flex; flex-direction: column;
            position: fixed; inset: 0 auto 0 0; z-index: 40; transform: translateX(-100%);
            transition: transform 0.25s ease;
            padding-bottom: env(safe-area-inset-bottom, 0);
        }
        @media (max-width: 1023px) {
            .smm-dash-sidebar {
                padding-top: max(0px, env(safe-area-inset-top, 0px));
                z-index: 60;
            }
        }
        @media (max-width: 768px) {
            .smm-dash-sidebar {
                padding-bottom: max(66px, calc(56px + env(safe-area-inset-bottom, 0px)));
            }
            .smm-dash-overlay { z-index: 55; }
        }
        @media (min-width: 1024px) {
            .smm-dash-sidebar { position: sticky; top: 0; height: 100vh; transform: none; }
        }
        .smm-dash-sidebar.open { transform: translateX(0); }
        .smm-dash-overlay {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 35;
        }
        .smm-dash-overlay.show { display: block; }
        @media (min-width: 1024px) { .smm-dash-overlay { display: none !important; } }
        .smm-dash-brand {
            padding: 10px 18px; border-bottom: 1px solid var(--border);
            display: flex; flex-direction: column; align-items: center; gap: 4px; text-decoration: none; color: inherit;
        }
        .smm-dash-brand-mark {
            height: 40px; width: auto; max-width: 100%;
            display: block; flex-shrink: 0;
            filter: drop-shadow(0 4px 14px rgba(124, 58, 237, 0.35));
        }
        .smm-dash-brand-text { font-weight: 800; font-size: 15px; background: linear-gradient(135deg, var(--purple), var(--teal)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .smm-dash-brand-sub { font-size: 10px; color: var(--text3); margin-top: 2px; }
        .smm-dash-nav { flex: 1; padding: 12px 10px; overflow-y: auto; }
        .smm-dash-nav a {
            display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 8px;
            color: var(--text2); text-decoration: none; font-size: 13px; font-weight: 500; margin-bottom: 2px;
            transition: background 0.15s, color 0.15s;
        }
        .smm-dash-nav a:hover { background: rgba(108,92,231,0.1); color: var(--text); }
        .smm-dash-nav a.active { background: rgba(108,92,231,0.18); color: var(--purple-light); }
        .smm-dash-nav a i { width: 20px; text-align: center; opacity: 0.9; }
        .smm-dash-sidebar-foot {
            margin-top: auto;
            padding: 12px 10px 16px;
            border-top: 1px solid var(--border);
            flex-shrink: 0;
        }
        .smm-dash-sidebar-telegram {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 8px;
            color: #2aabee;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: background 0.15s, color 0.15s;
        }
        .smm-dash-sidebar-telegram:hover {
            background: rgba(42, 171, 238, 0.12);
            color: #5dcef5;
        }
        .smm-dash-sidebar-telegram i {
            font-size: 18px;
            width: 20px;
            text-align: center;
        }
        .smm-client-top-bar-util {
            display: flex;
            align-items: center;
            flex-wrap: nowrap;
            gap: 8px;
            flex-shrink: 0;
        }
        .smm-client-top-bar-util .lang-landing-details {
            position: relative;
        }
        .smm-client-top-bar .lang-landing-details > summary {
            list-style: none;
            min-height: 40px;
            padding: 8px 12px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: rgba(255, 255, 255, 0.05);
            color: var(--text2);
            font-size: 13px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-sizing: border-box;
            transition: background 0.15s, color 0.15s, border-color 0.15s;
        }
        .smm-client-top-bar .lang-landing-details > summary::-webkit-details-marker { display: none; }
        .smm-client-top-bar .lang-landing-details > summary:hover {
            background: rgba(108, 92, 231, 0.12);
            color: var(--text);
            border-color: rgba(108, 92, 231, 0.25);
        }
        [data-theme="light"] .smm-client-top-bar .lang-landing-details > summary {
            background: rgba(0, 0, 0, 0.04);
        }
        [data-theme="light"] .smm-client-top-bar .lang-landing-details > summary:hover {
            background: rgba(108, 92, 231, 0.1);
        }
        .smm-client-top-bar .lang-landing-dropdown {
            left: auto;
            right: 0;
        }
        .smm-dash-theme {
            width: 40px;
            height: 40px;
            min-width: 40px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: rgba(255, 255, 255, 0.05);
            color: var(--text2);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 16px;
            transition: background 0.15s, color 0.15s, border-color 0.15s;
        }
        .smm-dash-theme:hover {
            background: rgba(108, 92, 231, 0.12);
            color: var(--purple-light);
            border-color: rgba(108, 92, 231, 0.25);
        }
        [data-theme="light"] .smm-dash-theme {
            background: rgba(0, 0, 0, 0.04);
        }
        .smm-dash-logout-form { margin: 0; display: inline-flex; flex-shrink: 0; }
        .smm-dash-logout-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            min-width: 40px;
            padding: 0;
            border-radius: 10px;
            border: 1px solid rgba(255, 118, 117, 0.4);
            background: rgba(255, 118, 117, 0.08);
            color: #ff7675;
            font-size: 16px;
            font-family: inherit;
            cursor: pointer;
            transition: background 0.15s, border-color 0.15s, color 0.15s;
            flex-shrink: 0;
        }
        .smm-dash-logout-btn:hover {
            background: rgba(255, 118, 117, 0.18);
            border-color: rgba(255, 154, 153, 0.55);
            color: #fab1a0;
        }
        [data-theme="light"] .smm-dash-logout-btn {
            color: #dc2626;
            border-color: rgba(220, 38, 38, 0.35);
            background: rgba(220, 38, 38, 0.06);
        }
        [data-theme="light"] .smm-dash-logout-btn:hover {
            background: rgba(220, 38, 38, 0.12);
            border-color: rgba(220, 38, 38, 0.45);
            color: #b91c1c;
        }
        /* Sidebar logout: hidden on desktop (shown in top bar), visible on mobile only */
        .smm-sidebar-logout-form { display: none; margin: 0; }
        .smm-sidebar-logout-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            padding: 10px 12px;
            border-radius: 8px;
            border: none;
            background: none;
            color: #ff7675;
            font-size: 13px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: background 0.15s;
            margin-top: 4px;
        }
        .smm-sidebar-logout-btn:hover {
            background: rgba(255, 118, 117, 0.12);
        }
        .smm-sidebar-logout-btn i {
            font-size: 16px;
            width: 20px;
            text-align: center;
        }
        .smm-dash-balance-pill {
            display: flex; align-items: center; justify-content: space-between; gap: 8px;
            background: rgba(108,92,231,0.12); border: 1px solid rgba(108,92,231,0.25);
            border-radius: 10px; padding: 10px 12px; margin-bottom: 10px;
        }
        .smm-dash-balance-pill strong { color: var(--teal); font-size: 15px; }
        .smm-dash-main { flex: 1; min-width: 0; display: flex; flex-direction: column; }
        .smm-dash-top {
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
            padding: 14px 18px; border-bottom: 1px solid var(--border); background: var(--card);
            position: sticky; top: 0; z-index: 30;
        }
        .smm-dash-menu-toggle {
            display: flex; align-items: center; justify-content: center;
            width: 40px; height: 40px; border-radius: 8px; border: 1px solid var(--border);
            background: transparent; color: var(--text); cursor: pointer;
        }
        @media (min-width: 1024px) { .smm-dash-menu-toggle { display: none; } }
        .smm-dash-user { font-size: 13px; color: var(--text2); text-align: right; }
        .smm-dash-user span { color: var(--text); font-weight: 600; display: block; }
        .smm-dash-content {
            padding: 22px 18px max(40px, env(safe-area-inset-bottom, 0px));
            flex: 1;
            min-width: 0;
            box-sizing: border-box;
        }
        @media (min-width: 768px) {
            .smm-dash-content { padding: 28px 28px max(48px, env(safe-area-inset-bottom, 0px)); }
        }
        @media (max-width: 479px) {
            .smm-dash-content { padding: 16px 10px max(32px, env(safe-area-inset-bottom, 0px)); }
        }
        .smm-client-top-bar {
            display: flex;
            flex-direction: row;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px 14px;
            padding: 18px 20px;
            margin: -22px -18px 26px;
            border-bottom: 1px solid var(--border);
            box-sizing: border-box;
        }
        @media (min-width: 768px) {
            .smm-client-top-bar { margin: -28px -28px 28px; padding: 17px 24px; flex-wrap: nowrap; }
        }
        @media (max-width: 767px) {
            .smm-client-top-bar {
                margin-left: -18px;
                margin-right: -18px;
                padding-left: 16px;
                padding-right: 16px;
            }
        }
        @media (max-width: 479px) {
            .smm-client-top-bar {
                margin-left: -12px;
                margin-right: -12px;
                padding: 14px 12px;
                gap: 10px;
            }
        }
        html[data-theme="dark"] .smm-client-top-bar {
            background: #0e0e12;
            border-bottom-color: rgba(255, 255, 255, 0.06);
        }
        html[data-theme="light"] .smm-client-top-bar {
            background: #e8eaef;
            border-bottom-color: rgba(0, 0, 0, 0.08);
        }
        .smm-client-top-bar-lead {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
            flex: 1 1 0;
        }
        .smm-client-top-bar-title {
            margin: 0;
            font-size: clamp(1.05rem, 4vw, 1.55rem);
            font-weight: 800;
            letter-spacing: -0.02em;
            color: var(--text);
            line-height: 1.2;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            min-width: 0;
        }
        .smm-client-top-bar-date {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--text3);
            flex: 0 0 auto;
            white-space: nowrap;
        }
        @media (max-width: 639px) {
            .smm-client-top-bar-date { display: none; }
        }

        /* ===== MOBILE CLIENT TOP BAR ===== */
        @media (max-width: 767px) {
            .smm-client-top-bar {
                flex-wrap: nowrap;
                gap: 8px;
                padding: 10px 12px;
                margin-left: -18px;
                margin-right: -18px;
                align-items: center;
            }
            .smm-client-top-bar-lead {
                flex: 1 1 auto;
                min-width: 0;
                max-width: none;
                gap: 8px;
            }
            .smm-client-top-bar-title { font-size: 1rem; }
            .smm-client-top-bar-right {
                flex: 0 0 auto;
                flex-wrap: nowrap;
                gap: 6px;
            }
            .smm-client-top-balance-pill {
                padding: 4px 6px 4px 10px;
                font-size: 13px;
                gap: 6px;
            }
            .smm-client-top-balance-add { width: 26px; height: 26px; font-size: 11px; }
            /* New Order: icon only */
            .smm-btn-order-text { display: none; }
            .smm-client-top-btn-order {
                width: 36px; height: 36px; min-width: 36px;
                padding: 0; border-radius: 10px;
                justify-content: center;
                box-shadow: 0 2px 10px rgba(108, 92, 231, 0.35);
            }
            .smm-client-top-btn-order .smm-btn-order-icon { font-size: 16px; }
            /* Utility buttons */
            .smm-client-top-bar-util { gap: 4px; }
            .smm-dash-theme,
            .smm-dash-logout-btn { width: 34px; height: 34px; min-width: 34px; font-size: 14px; }
            .smm-client-top-bar .lang-landing-details > summary {
                min-height: 34px; padding: 6px 8px; font-size: 12px;
            }
            .smm-dash-menu-toggle { width: 34px; height: 34px; font-size: 14px; }
            /* Mobile: hide top bar logout, show sidebar logout */
            .smm-dash-logout-form { display: none; }
            .smm-sidebar-logout-form { display: block !important; }
        }

        @media (max-width: 479px) {
            .smm-client-top-bar {
                margin-left: -12px; margin-right: -12px;
                padding: 8px 10px; gap: 5px;
            }
            .smm-client-top-bar-title { font-size: 0.9rem; }
            .smm-dash-menu-toggle { width: 32px; height: 32px; font-size: 13px; border-radius: 8px; }
            .smm-client-top-balance-pill { padding: 3px 4px 3px 8px; font-size: 12px; gap: 4px; }
            .smm-client-top-balance-add { width: 22px; height: 22px; font-size: 10px; }
            .smm-client-top-btn-order {
                width: 32px; height: 32px; min-width: 32px; border-radius: 8px;
            }
            .smm-client-top-btn-order .smm-btn-order-icon { font-size: 14px; }
            .smm-dash-theme { width: 32px; height: 32px; min-width: 32px; font-size: 13px; border-radius: 8px; }
            .smm-client-top-bar .lang-landing-details > summary {
                min-height: 32px; padding: 5px 6px; font-size: 11px; border-radius: 8px;
            }
            /* Hide lang on very small — it's in sidebar */
            .smm-client-top-bar-util .lang-landing-details { display: none; }
        }
        .smm-client-top-bar-date i { opacity: 0.85; font-size: 14px; }
        .smm-client-top-bar-right {
            display: flex;
            align-items: center;
            flex-wrap: nowrap;
            gap: 10px;
            flex: 0 0 auto;
        }
        .smm-client-top-balance-pill {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 6px 8px 6px 16px;
            border-radius: 999px;
            border: 1px solid rgba(0, 210, 211, 0.5);
            background: rgba(0, 0, 0, 0.22);
            font-size: 14px;
            color: var(--text2);
            flex-shrink: 0;
            white-space: nowrap;
        }
        html[data-theme="light"] .smm-client-top-balance-pill {
            background: rgba(255, 255, 255, 0.9);
            border-color: rgba(0, 180, 181, 0.55);
        }
        .smm-client-top-balance-pill strong {
            color: var(--teal);
            font-weight: 800;
        }
        .smm-client-top-balance-add {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--teal);
            color: #fff !important;
            text-decoration: none;
            flex-shrink: 0;
            font-size: 13px;
            transition: transform 0.15s, filter 0.15s;
        }
        .smm-client-top-balance-add:hover { filter: brightness(1.08); transform: scale(1.04); }
        .smm-client-top-btn-order {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 10px;
            border: none;
            background: var(--purple);
            color: #fff !important;
            font-weight: 700;
            font-size: 14px;
            text-decoration: none;
            font-family: inherit;
            cursor: pointer;
            box-shadow: 0 4px 18px rgba(108, 92, 231, 0.45);
            transition: filter 0.15s, transform 0.15s;
            flex-shrink: 0;
            white-space: nowrap;
        }
        .smm-client-top-btn-order:hover { filter: brightness(1.06); transform: translateY(-1px); }
        .smm-client-page-head { margin-bottom: 1.25rem; }
        .smm-client-page-head h1,
        .smm-client-page-head h2 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text);
            line-height: 1.35;
        }
        .smm-client-page-head .font-semibold.text-xl.text-gray-800,
        .smm-client-page-head .text-gray-800 { color: var(--text) !important; }
        .smm-account-nav-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
        }
        .smm-account-nav-card nav a {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            font-size: 14px;
            font-weight: 500;
            color: var(--text2);
            text-decoration: none;
            transition: background 0.15s, color 0.15s;
        }
        .smm-account-nav-card nav a:hover {
            background: rgba(108, 92, 231, 0.1);
            color: var(--text);
        }
        .smm-account-nav-card nav a.is-active {
            background: rgba(108, 92, 231, 0.18);
            color: var(--purple-light);
            border-left: 3px solid var(--purple);
            padding-left: 13px;
        }
        .smm-account-nav-card nav a svg { flex-shrink: 0; margin-right: 12px; opacity: 0.85; color: inherit; }
        /* ===== BOTTOM NAV BAR ===== */
        .smm-bottom-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 50;
            background: var(--sidebar-bg);
            border-top: 1px solid var(--border);
            padding: 6px 0 max(6px, env(safe-area-inset-bottom, 0px));
        }
        .smm-bottom-nav-inner {
            display: flex;
            justify-content: space-around;
            align-items: center;
            max-width: 500px;
            margin: 0 auto;
        }
        .smm-bottom-nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
            padding: 4px 8px;
            text-decoration: none;
            color: var(--text3);
            font-size: 10px;
            font-weight: 500;
            transition: color 0.15s;
            border: none;
            background: none;
            cursor: pointer;
            font-family: inherit;
        }
        .smm-bottom-nav-item i { font-size: 18px; }
        .smm-bottom-nav-item:hover,
        .smm-bottom-nav-item.active { color: var(--purple-light); }
        [data-theme="light"] .smm-bottom-nav-item.active { color: var(--purple); }
        @media (max-width: 768px) {
            .smm-bottom-nav { display: block; }
            .smm-dash-content {
                padding-bottom: max(70px, calc(56px + env(safe-area-inset-bottom, 0px))) !important;
            }
        }

        /* ===== SHARED FILTER LAYOUT ===== */
        .co-filter-row {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .co-filter-search-row {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
        }
        .co-filter-search-box {
            flex: 1;
            min-width: 0;
        }
        .co-filter-tabs-row {
            display: flex;
            align-items: center;
            gap: 4px;
            width: 100%;
            overflow: hidden;
        }
        .co-filter-toggle-btn {
            display: flex;
            width: 40px;
            height: 40px;
            flex-shrink: 0;
            align-items: center;
            justify-content: center;
            border-radius: 9999px;
            transition: all 0.15s;
            border: none;
            cursor: pointer;
        }
        .co-filter-tabs {
            display: flex;
            align-items: center;
            gap: 4px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            flex: 1 1 auto;
            min-width: 0;
        }
        .co-filter-tabs::-webkit-scrollbar { display: none; }
        .co-filter-tabs a,
        .co-filter-tabs button {
            flex-shrink: 0;
            white-space: nowrap;
        }

        .smm-dash-shell.smm-dash-no-sidebar { display: block; }
        .smm-dash-shell.smm-dash-no-sidebar .smm-dash-main { width: 100%; max-width: 100%; }
    </style>
</head>
<body class="smm-dash-body" x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false">
    @unless($hideSidebar)
    <div class="smm-dash-overlay" :class="{ 'show': sidebarOpen }" @click="sidebarOpen = false"></div>
    @endunless
    <div class="smm-dash-shell {{ $hideSidebar ? 'smm-dash-no-sidebar' : '' }}">
        @unless($hideSidebar)
        <aside class="smm-dash-sidebar" :class="{ 'open': sidebarOpen }">
            <a href="{{ route('dashboard') }}" class="smm-dash-brand" @click="sidebarOpen = false">
                <img src="{{ asset('images/adtag_fav.png') }}" alt="{{ config('app.name', 'SMM Tool') }}" class="smm-dash-brand-mark">
                <div class="smm-dash-brand-sub">Social Media Growth Tool</div>
            </a>
            <nav class="smm-dash-nav">
                <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" @click="sidebarOpen = false">
                    <i class="fa-solid fa-chart-pie"></i> {{ __('Dashboard') }}
                </a>
                <a href="{{ route('client.orders.create') }}" class="{{ request()->routeIs('client.orders.create') ? 'active' : '' }}" @click="sidebarOpen = false">
                    <i class="fa-solid fa-plus"></i> {{ __('New Order') }}
                </a>
                <a href="{{ route('client.orders.index') }}" class="{{ request()->routeIs('client.orders.index') ? 'active' : '' }}" @click="sidebarOpen = false">
                    <i class="fa-solid fa-list"></i> {{ __('Orders') }}
                </a>
                <a href="{{ route('client.services.index') }}" class="{{ request()->routeIs('client.services.*') ? 'active' : '' }}" @click="sidebarOpen = false">
                    <i class="fa-solid fa-layer-group"></i> {{ __('Services') }}
                </a>
{{--                <a href="{{ route('client.subscriptions.index') }}" class="{{ request()->routeIs('client.subscriptions.index') || request()->routeIs('client.subscriptions.create') ? 'active' : '' }}" @click="sidebarOpen = false">--}}
{{--                    <i class="fa-solid fa-tags"></i> {{ __('Subscription Plans') }}--}}
{{--                </a>--}}
{{--                <a href="{{ route('client.subscriptions.my-subscriptions') }}" class="{{ request()->routeIs('client.subscriptions.my-subscriptions') ? 'active' : '' }}" @click="sidebarOpen = false">--}}
{{--                    <i class="fa-solid fa-receipt"></i> {{ __('My Subscriptions') }}--}}
{{--                </a>--}}
                <a href="{{ route('client.balance.add') }}" class="{{ request()->routeIs('client.balance.*') ? 'active' : '' }}" @click="sidebarOpen = false">
                    <i class="fa-solid fa-wallet"></i> {{ __('Add Balance') }}
                </a>
                <a href="{{ route('client.api.index') }}" class="{{ request()->routeIs('client.api.*') ? 'active' : '' }}" @click="sidebarOpen = false">
                    <i class="fa-solid fa-code"></i> {{ __('API') }}
                </a>

                <a href="{{ route('client.referral.index') }}" class="{{ request()->routeIs('client.referral.*') ? 'active' : '' }}" @click="sidebarOpen = false">
                    <i class="fa-solid fa-user-plus"></i> {{ __('Referral Program') }}
                </a>

                <a href="{{ route('contacts') }}" class="{{ request()->routeIs('contacts') ? 'active' : '' }}" @click="sidebarOpen = false">
                    <i class="fa-solid fa-headset"></i> {{ __('Support') }}
                </a>
                <a href="{{ route('client.account.edit') }}" class="{{ request()->routeIs('client.account.*') ? 'active' : '' }}" @click="sidebarOpen = false">
                    <i class="fa-solid fa-gear"></i> {{ __('Settings') }}
                </a>
                <a href="{{ route('home') }}" @click="sidebarOpen = false">
                    <i class="fa-solid fa-house"></i> {{ __('Homepage') }}
                </a>
            </nav>
            <div class="smm-dash-sidebar-foot">
                <x-telegram-support-picker variant="sidebar">
                    <button type="button" x-on:click="open = !open" class="smm-dash-sidebar-telegram w-full">
                        <i class="fa-brands fa-telegram" aria-hidden="true"></i>
                        <span>{{ __('Support') }}</span>
                    </button>
                </x-telegram-support-picker>
                <form method="POST" action="{{ route('logout') }}" class="smm-sidebar-logout-form" style="margin:0;">
                    @csrf
                    <button type="submit" class="smm-sidebar-logout-btn">
                        <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                        <span>{{ __('Log Out') }}</span>
                    </button>
                </form>
            </div>
        </aside>
        @endunless
        <div class="smm-dash-main" style="flex:1;min-width:0">
            <div class="smm-dash-content">
                @if(!$hideSidebar && isset($client) && $client)
                    <header class="smm-client-top-bar">
                        <div class="smm-client-top-bar-lead">
                            <button type="button" class="smm-dash-menu-toggle" @click="sidebarOpen = true" aria-label="{{ __('Menu') }}">
                                <i class="fa-solid fa-bars"></i>
                            </button>
                            <h1 class="smm-client-top-bar-title">{{ $smmPageTitle }}</h1>
                        </div>
                        <div class="smm-client-top-bar-date">
                            <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                            <span>{{ now()->translatedFormat('F j, Y') }}</span>
                        </div>
                        <div class="smm-client-top-bar-right">
                            <div class="smm-client-top-balance-pill">
                                <span><strong>${{ number_format((float) $client->balance, 2) }}</strong></span>
                                <a href="{{ route('client.balance.add') }}" class="smm-client-top-balance-add" title="{{ __('Add Balance') }}" aria-label="{{ __('Add Balance') }}">
                                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                                </a>
                            </div>
                            <a href="{{ route('client.orders.create') }}" class="smm-client-top-btn-order">
                                <i class="fa-solid fa-plus smm-btn-order-icon" aria-hidden="true"></i>
                                <span class="smm-btn-order-text">{{ __('New Order') }}</span>
                            </a>
                            <div class="smm-client-top-bar-util">
                                @if($smmLocaleCount > 1)
                                    <x-language-dropdown variant="landing" />
                                @endif
                                <button type="button" class="smm-dash-theme" onclick="smmDashToggleTheme()" title="{{ __('Toggle theme') }}" aria-label="{{ __('Toggle theme') }}">
                                    <i class="fa-solid fa-moon" id="smmDashThemeIcon"></i>
                                </button>
                                <form method="POST" action="{{ route('logout') }}" class="smm-dash-logout-form">
                                    @csrf
                                    <button type="submit" class="smm-dash-logout-btn" title="{{ __('Log Out') }}" aria-label="{{ __('Log Out') }}">
                                        <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </header>
                @endif
                {{ $slot }}
            </div>
        </div>
    </div>

    @unless($hideSidebar)
    {{-- Bottom Navigation Bar (mobile only) --}}
    <nav class="smm-bottom-nav">
        <div class="smm-bottom-nav-inner">
            <a href="{{ route('client.orders.index') }}" class="smm-bottom-nav-item {{ request()->routeIs('client.orders.*') ? 'active' : '' }}">
                <i class="fa-solid fa-receipt"></i>
                <span>{{ __('Orders') }}</span>
            </a>
            <a href="{{ route('client.services.index') }}" class="smm-bottom-nav-item {{ request()->routeIs('client.services.*') ? 'active' : '' }}">
                <i class="fa-solid fa-layer-group"></i>
                <span>{{ __('Services') }}</span>
            </a>
            <a href="{{ route('client.orders.create') }}" class="smm-bottom-nav-item {{ request()->routeIs('client.orders.create') ? 'active' : '' }}" style="color:var(--purple-light);">
                <i class="fa-solid fa-plus-circle" style="font-size:22px;"></i>
                <span>{{ __('New') }}</span>
            </a>
            <a href="{{ route('contacts') }}" class="smm-bottom-nav-item {{ request()->routeIs('contacts') ? 'active' : '' }}">
                <i class="fa-solid fa-headset"></i>
                <span>{{ __('Support') }}</span>
            </a>
            <button type="button" class="smm-bottom-nav-item" @click="sidebarOpen = true">
                <i class="fa-solid fa-bars"></i>
                <span>{{ __('Menu') }}</span>
            </button>
        </div>
    </nav>
    @endunless

    <script>
        function smmDashToggleTheme() {
            const html = document.documentElement;
            const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            localStorage.setItem('smm-theme', next);
            const icon = document.getElementById('smmDashThemeIcon');
            if (icon) icon.className = next === 'dark' ? 'fa-solid fa-moon' : 'fa-solid fa-sun';
        }
        (function () {
            const saved = localStorage.getItem('smm-theme');
            if (saved === 'light' || saved === 'dark') {
                document.documentElement.setAttribute('data-theme', saved);
                const icon = document.getElementById('smmDashThemeIcon');
                if (icon && saved === 'light') icon.className = 'fa-solid fa-sun';
            }
        })();
    </script>
</body>
</html>
