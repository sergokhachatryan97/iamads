<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" data-theme="dark">
<head>
    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','GTM-TRRBPCBV');</script>
    <!-- End Google Tag Manager -->
    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','GTM-W7F85JNQ');</script>
    <!-- End Google Tag Manager -->
    <!-- Yandex.Metrika counter -->
    <script type="text/javascript">
        (function(m,e,t,r,i,k,a){
            m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
            m[i].l=1*new Date();
            for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
            k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)
        })(window, document,'script','https://mc.yandex.ru/metrika/tag.js?id=108785800', 'ym');

        ym(108785800, 'init', {ssr:true, webvisor:true, clickmap:true, ecommerce:"dataLayer", referrer: document.referrer, url: location.href, accurateTrackBounce:true, trackLinks:true});
    </script>
    <noscript><div><img src="https://mc.yandex.ru/watch/108785800" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
    <!-- /Yandex.Metrika counter -->
    <!-- Yandex.Metrika counter -->
    <script type="text/javascript">
        (function(m,e,t,r,i,k,a){
            m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
            m[i].l=1*new Date();
            for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
            k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)
        })(window, document,'script','https://mc.yandex.ru/metrika/tag.js?id=108804961', 'ym');

        ym(108804961, 'init', {ssr:true, webvisor:true, clickmap:true, ecommerce:"dataLayer", referrer: document.referrer, url: location.href, accurateTrackBounce:true, trackLinks:true});
    </script>
    <noscript><div><img src="https://mc.yandex.ru/watch/108804961" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
    <!-- /Yandex.Metrika counter -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <x-heleket-site-verification />

    <title>{{ config('app.name', 'Laravel') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" type="image/png" href="{{ asset('images/new-logo.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/new-logo.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        /* ─── Brand tokens ─── */
        :root { --radius: 12px; --radius-lg: 20px; }

        [data-theme="dark"] {
            --bg: #06060f;
            --bg2: #0a0a1a;
            --card: #0e0e24;
            --card2: #13133a;
            --cyan: #06B6D4;
            --blue: #3B82F6;
            --purple: #8B5CF6;
            --purple-light: #A78BFA;
            --purple-dark: #7C3AED;
            --text: #e2e8f0;
            --text2: #a8b8cc;
            --text3: #4a5568;
            --border2: rgba(139,92,246,0.18);
            --danger: #f87171;
            --navbar-bg: rgba(6,6,15,0.94);
        }
        [data-theme="light"] {
            --bg: #f1f0ff;
            --bg2: #e9e7ff;
            --card: #ffffff;
            --card2: #f6f5ff;
            --cyan: #0891B2;
            --blue: #2563EB;
            --purple: #7C3AED;
            --purple-light: #8B5CF6;
            --purple-dark: #6D28D9;
            --text: #0f0f23;
            --text2: #4a5568;
            --text3: #9ca3af;
            --border2: rgba(124,58,237,0.14);
            --danger: #dc2626;
            --navbar-bg: rgba(241,240,255,0.96);
        }

        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }

        body.landing-guest {
            margin: 0;
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--bg);
            background-image: radial-gradient(ellipse 80% 45% at 50% 0%, rgba(139,92,246,0.07) 0%, transparent 70%);
            color: var(--text);
            line-height: 1.6;
            min-height: 100vh;
        }
        a { color: inherit; }

        /* ─── Navbar ─── */
        .landing-navbar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 1000;
            background: var(--navbar-bg); backdrop-filter: blur(24px);
            border-bottom: 1px solid var(--border2); padding: 0 24px;
        }
        .landing-navbar-inner {
            max-width: 1280px; margin: 0 auto; height: 58px;
            display: flex; align-items: center; gap: 16px;
        }
        .landing-navbar-spacer { flex: 1; min-width: 8px; }

        .landing-logo { display: flex; align-items: center; gap: 10px; line-height: 1.1; text-decoration: none; }
        .landing-logo-mark {
            height: 54px; width: auto; flex-shrink: 0;
            filter: drop-shadow(0 2px 12px rgba(139,92,246,0.35));
        }
        .landing-logo-text { display: flex; flex-direction: column; }
        .landing-logo-name {
            font-size: 17px; font-weight: 800;
            background: linear-gradient(90deg, var(--cyan), var(--blue), var(--purple));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }
        .landing-logo-slogan { font-size: 9px; color: var(--text2); letter-spacing: 0.5px; }

        .landing-nav-home {
            color: var(--text2); font-size: 13px; font-weight: 500; text-decoration: none;
            padding: 6px 12px; border-radius: 6px; transition: color 0.2s, background 0.2s;
        }
        .landing-nav-home:hover { color: var(--text); background: rgba(139,92,246,0.08); }

        .landing-nav-right { display: flex; align-items: center; gap: 10px; }

        .landing-theme-toggle {
            background: rgba(255,255,255,0.04); border: 1px solid var(--border2);
            width: 36px; height: 36px; border-radius: 8px; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px; color: var(--text2); transition: all 0.2s;
        }
        .landing-theme-toggle:hover { border-color: var(--cyan); color: var(--text); }

        .landing-lang select.lang-select {
            background: rgba(255,255,255,0.04) !important;
            border: 1px solid var(--border2) !important;
            color: var(--text) !important;
            border-radius: 8px !important;
            padding: 6px 28px 6px 10px !important;
            font-size: 13px !important;
            font-family: inherit !important;
        }
        .landing-lang select.lang-select:focus { outline: none; border-color: var(--cyan) !important; }

        /* ─── Navbar auth buttons ─── */
        .landing-auth-btn {
            padding: 7px 16px; border-radius: 8px; font-size: 13px; font-weight: 500;
            text-decoration: none; transition: all 0.2s; white-space: nowrap;
        }
        .landing-auth-btn-signin {
            color: var(--text); border: 1px solid var(--border2); background: transparent;
        }
        .landing-auth-btn-signin:hover { border-color: var(--purple); color: var(--purple-light); }
        .landing-auth-btn-signup {
            color: #fff !important;
            background: linear-gradient(90deg, var(--cyan), var(--blue), var(--purple));
            font-weight: 600; border: none;
        }
        .landing-auth-btn-signup:hover { transform: translateY(-1px); box-shadow: 0 4px 16px rgba(139,92,246,0.5); }

        /* ─── Auth page layout ─── */
        .landing-auth-main {
            min-height: 100vh; display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            padding: 92px 20px 40px;
        }

        /* ─── Auth card ─── */
        .landing-auth-card {
            width: 100%; max-width: 420px;
            background: var(--card);
            border: 1px solid var(--border2);
            border-radius: var(--radius-lg);
            padding: 0 26px 30px;
            position: relative;
            overflow: hidden;
            box-shadow:
                0 0 0 1px rgba(59,130,246,0.06),
                0 8px 40px rgba(0,0,0,0.4),
                0 2px 0 0 rgba(6,182,212,0.05) inset;
        }
        [data-theme="light"] .landing-auth-card {
            box-shadow: 0 8px 40px rgba(124,58,237,0.1), 0 1px 0 0 rgba(37,99,235,0.08) inset;
        }

        /* Top accent stripe — brand gradient */
        .landing-auth-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--cyan), var(--blue), var(--purple));
            z-index: 1;
        }

        .landing-auth-slot { padding-top: 28px; }

        /* ─── Title ─── */
        .landing-auth-card h2.text-2xl,
        .landing-auth-slot h2.text-2xl {
            color: var(--text) !important;
            font-size: 1.35rem !important;
            font-weight: 800 !important;
            letter-spacing: -0.02em;
            margin-bottom: 1.25rem !important;
        }

        /* ─── Labels ─── */
        .landing-auth-card label.block.font-medium,
        .landing-auth-slot label.block.font-medium {
            color: var(--text2) !important;
            font-size: 12px !important;
            font-weight: 500 !important;
            letter-spacing: 0.01em;
        }

        /* ─── Input fields ─── */
        .landing-auth-card input[type="text"],
        .landing-auth-card input[type="email"],
        .landing-auth-card input[type="password"],
        .landing-auth-slot input[type="text"],
        .landing-auth-slot input[type="email"],
        .landing-auth-slot input[type="password"] {
            margin-top: 4px !important;
            width: 100% !important;
            background: rgba(6,6,30,0.6) !important;
            border: 1px solid rgba(139,92,246,0.25) !important;
            border-radius: 10px !important;
            padding: 10px 14px !important;
            color: var(--text) !important;
            font-size: 14px !important;
            font-family: inherit !important;
            box-shadow: none !important;
            transition: border-color 0.2s, box-shadow 0.2s !important;
        }
        [data-theme="light"] .landing-auth-card input[type="text"],
        [data-theme="light"] .landing-auth-card input[type="email"],
        [data-theme="light"] .landing-auth-card input[type="password"],
        [data-theme="light"] .landing-auth-slot input[type="text"],
        [data-theme="light"] .landing-auth-slot input[type="email"],
        [data-theme="light"] .landing-auth-slot input[type="password"] {
            background: #f8f7ff !important;
            border-color: rgba(124,58,237,0.18) !important;
        }
        .landing-auth-card input:focus,
        .landing-auth-slot input:focus {
            outline: none !important;
            border-color: var(--cyan) !important;
            box-shadow: 0 0 0 3px rgba(6,182,212,0.14) !important;
            --tw-ring-shadow: none !important;
        }
        [data-theme="light"] .landing-auth-card input:focus,
        [data-theme="light"] .landing-auth-slot input:focus {
            border-color: var(--blue) !important;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.12) !important;
        }

        /* ─── Validation errors ─── */
        .landing-auth-card .text-red-600,
        .landing-auth-slot .text-red-600,
        .landing-auth-card ul.text-sm.text-red-600,
        .landing-auth-slot ul.text-sm.text-red-600 {
            color: var(--danger) !important;
        }

        /* ─── Submit / primary button ─── */
        .landing-auth-card button[type="submit"],
        .landing-auth-slot button[type="submit"] {
            background: linear-gradient(90deg, var(--cyan), var(--blue), var(--purple)) !important;
            border: none !important;
            color: #fff !important;
            padding: 11px 26px !important;
            border-radius: 10px !important;
            font-size: 13px !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.07em !important;
            box-shadow: 0 4px 20px rgba(139,92,246,0.5), 0 1px 0 rgba(255,255,255,0.1) inset !important;
            transition: transform 0.2s, box-shadow 0.2s !important;
        }
        .landing-auth-card button[type="submit"]:hover,
        .landing-auth-slot button[type="submit"]:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 24px rgba(139,92,246,0.55) !important;
        }

        /* ─── Links ─── */
        .landing-auth-card a.underline,
        .landing-auth-slot a.underline,
        .landing-auth-card a.text-sm,
        .landing-auth-slot a.text-sm {
            color: var(--text2) !important;
            text-decoration: none;
            transition: color 0.2s;
        }
        .landing-auth-card a.text-indigo-600,
        .landing-auth-slot a.text-indigo-600 {
            color: var(--cyan) !important;
            font-weight: 600;
        }
        .landing-auth-card a:hover,
        .landing-auth-slot a:hover { color: var(--cyan) !important; }

        .landing-auth-card .text-gray-600,
        .landing-auth-slot .text-gray-600,
        .landing-auth-card .text-gray-900,
        .landing-auth-slot .text-gray-900 { color: var(--text2) !important; }

        /* ─── Checkbox ─── */
        .landing-auth-slot input[type="checkbox"] {
            border-color: rgba(139,92,246,0.3) !important;
            background: rgba(255,255,255,0.04) !important;
            border-radius: 4px !important;
        }
        [data-theme="light"] .landing-auth-slot input[type="checkbox"] {
            background: #f8f7ff !important;
        }
        .landing-auth-slot input[type="checkbox"]:checked {
            background: var(--purple) !important;
            border-color: var(--purple) !important;
        }

        /* ─── Social login / divider ─── */
        .landing-auth-slot .border-gray-300 { border-color: var(--border2) !important; }
        .landing-auth-slot span.bg-white,
        .landing-auth-slot span.px-2.bg-white {
            background: var(--card) !important;
            color: var(--text3) !important;
            font-size: 11px !important;
            font-weight: 500;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }
        .landing-auth-slot .grid.grid-cols-2 > a,
        .landing-auth-slot #telegram-button-visual {
            background: rgba(255,255,255,0.03) !important;
            border: 1px solid rgba(139,92,246,0.15) !important;
            color: var(--text) !important;
            border-radius: 10px !important;
            box-shadow: none !important;
            font-size: 13px !important;
            font-weight: 500 !important;
            transition: border-color 0.2s, background 0.2s, box-shadow 0.2s !important;
        }
        [data-theme="light"] .landing-auth-slot .grid.grid-cols-2 > a,
        [data-theme="light"] .landing-auth-slot #telegram-button-visual {
            background: #f8f7ff !important;
            border-color: rgba(124,58,237,0.15) !important;
        }
        .landing-auth-slot .grid.grid-cols-2 > a:hover,
        .landing-auth-slot #telegram-button-visual:hover {
            border-color: rgba(6,182,212,0.45) !important;
            background: rgba(6,182,212,0.06) !important;
            box-shadow: 0 0 0 1px rgba(6,182,212,0.1) !important;
        }

        /* ─── Session status message ─── */
        .landing-auth-slot .text-green-600 {
            display: block;
            padding: 10px 12px; border-radius: 8px;
            font-size: 13px; margin-bottom: 1rem !important;
            background: rgba(6,182,212,0.08);
            border: 1px solid rgba(6,182,212,0.25);
            color: var(--cyan) !important;
        }

        /* ─── Tablet / mobile ─── */
        @media (max-width: 768px) {
            .landing-logo-sub { display: none; }
            .landing-logo-mark { height: 32px; }
            .landing-navbar { padding: 0 12px; }
            .landing-navbar-inner { height: 50px; gap: 8px; }
            .landing-nav-home { display: none; }
            .landing-lang { display: none; }
            .landing-nav-right { gap: 6px !important; }
            .landing-theme-toggle { width: 34px; height: 34px; font-size: 13px; }
            .landing-auth-btn { padding: 6px 12px; font-size: 12px; display: inline-flex !important; }
            .landing-auth-btn-signin { border-color: rgba(139,92,246,0.25) !important; color: var(--text) !important; }
            .landing-auth-main { padding: 62px 14px 20px; }
            .landing-auth-card { max-width: 100%; padding: 0 16px 20px; border-radius: 16px; }
            .landing-auth-slot { padding-top: 22px; }
            .landing-auth-slot .mt-4 { margin-top: 10px !important; }
            .landing-auth-slot .mb-6 { margin-bottom: 14px !important; }
            .landing-auth-slot .mt-6 { margin-top: 12px !important; }
            .landing-auth-slot .mb-4 { margin-bottom: 10px !important; }
            .landing-auth-card input[type="text"],
            .landing-auth-card input[type="email"],
            .landing-auth-card input[type="password"],
            .landing-auth-slot input[type="text"],
            .landing-auth-slot input[type="email"],
            .landing-auth-slot input[type="password"] {
                font-size: 16px !important;
                padding: 10px 12px !important;
                border-radius: 10px !important;
                margin-top: 2px !important;
            }
            .landing-auth-card label.block,
            .landing-auth-slot label.block { font-size: 12px !important; margin-bottom: 0 !important; }
            .landing-auth-card button[type="submit"],
            .landing-auth-slot button[type="submit"] {
                width: 100% !important;
                padding: 12px 20px !important;
                font-size: 12px !important;
                border-radius: 10px !important;
                margin: auto !important;
            }
            .landing-auth-slot .flex.items-center.justify-end,
            .landing-auth-slot .flex.items-center.justify-between {
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 10px !important;
                margin-top: 14px !important;
            }
            .landing-auth-slot .flex.items-center.justify-end a,
            .landing-auth-slot .flex.items-center.justify-between a { text-align: center; font-size: 13px !important; }
            .landing-auth-slot .flex.items-center.justify-between .flex.items-center.gap-4 {
                flex-direction: row !important; justify-content: center !important; gap: 16px !important;
            }
            .landing-auth-slot .grid.grid-cols-2 { gap: 8px !important; }
            .landing-auth-slot .grid.grid-cols-2 > a,
            .landing-auth-slot #telegram-button-visual { padding: 8px 10px !important; font-size: 13px !important; }
            #telegram-button-container { height: 36px !important; }
            .landing-auth-slot .relative .text-sm span { font-size: 11px !important; }
            .landing-auth-card h2.text-2xl,
            .landing-auth-slot h2.text-2xl { font-size: 1.1rem !important; margin-bottom: 10px !important; }
            .landing-auth-slot .block.mt-4 { margin-top: 8px !important; }
            .landing-auth-slot .block.mt-4 span { font-size: 13px !important; }
        }

        @media (max-width: 380px) {
            .landing-auth-main { padding: 54px 10px 14px; }
            .landing-auth-card { padding: 0 12px 16px; border-radius: 14px; }
            .landing-navbar-inner { height: 46px; }
            .landing-logo-mark { height: 28px; }
            .landing-theme-toggle { width: 30px; height: 30px; font-size: 12px; }
            .landing-auth-btn { padding: 4px 8px; font-size: 11px; }
            .landing-auth-slot input[type="text"],
            .landing-auth-slot input[type="email"],
            .landing-auth-slot input[type="password"] { padding: 9px 10px !important; font-size: 15px !important; }
        }
    </style>
</head>
<body class="landing-guest">
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-TRRBPCBV"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-W7F85JNQ"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->
    <nav class="landing-navbar">
        <div class="landing-navbar-inner">
            <a href="{{ route('home') }}" class="landing-logo" style="display:flex;flex-direction:column;align-items:center;gap:4px;text-decoration:none;">
                <img src="{{ asset('images/new-logo.png') }}" alt="{{ config('app.name', 'SMM Tool') }}" class="landing-logo-mark">
{{--                <span class="landing-logo-sub" style="font-size:10px;color:#8892a4;white-space:nowrap;">Social Media Growth Tool</span>--}}
            </a>
            <div class="landing-navbar-spacer" aria-hidden="true"></div>
            <a href="{{ route('home') }}" class="landing-nav-home">{{ __('Back to home') }}</a>
            <div class="landing-nav-right">
                <a href="{{ route('login') }}" class="landing-auth-btn landing-auth-btn-signin">{{ __('Sign In') }}</a>
                <a href="{{ route('register') }}" class="landing-auth-btn landing-auth-btn-signup">{{ __('Sign Up') }}</a>
                <button type="button" class="landing-theme-toggle" id="guestThemeToggle" onclick="guestToggleTheme()" title="{{ __('Toggle theme') }}">
                    <i class="fa-solid fa-moon" id="guestThemeIcon"></i>
                </button>
                <div class="landing-lang">
                    <x-language-dropdown variant="landing" />
                </div>
            </div>
        </div>
    </nav>

    <main class="landing-auth-main">
        <div class="landing-auth-card">
            <div class="landing-auth-slot">
                {{ $slot }}
            </div>
        </div>
    </main>

    <script>
        function guestToggleTheme() {
            const html = document.documentElement;
            const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            localStorage.setItem('smm-theme', next);
            const icon = document.getElementById('guestThemeIcon');
            if (icon) icon.className = next === 'dark' ? 'fa-solid fa-moon' : 'fa-solid fa-sun';
        }
        (function () {
            const saved = localStorage.getItem('smm-theme');
            if (saved === 'light' || saved === 'dark') {
                document.documentElement.setAttribute('data-theme', saved);
                const icon = document.getElementById('guestThemeIcon');
                if (icon && saved === 'light') icon.className = 'fa-solid fa-sun';
            }
        })();
    </script>
</body>
</html>
