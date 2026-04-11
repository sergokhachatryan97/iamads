<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <x-heleket-site-verification />

    <title>{{ config('app.name', 'Laravel') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/apple-touch-icon.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root { --radius: 12px; --radius-lg: 20px; }
        [data-theme="dark"] {
            --bg: #0a0a0f; --bg2: #0d0d18; --card: #1a1a2e; --card2: #16213e;
            --purple: #6c5ce7; --purple-light: #a29bfe; --purple-dark: #5a4fcf;
            --teal: #00d2d3; --teal-dark: #00b4b5;
            --text: #e2e8f0; --text2: #8892a4; --text3: #5a6178;
            --border2: rgba(255,255,255,0.08);
            --danger: #e17055;
            --navbar-bg: rgba(10,10,15,0.92);
        }
        [data-theme="light"] {
            --bg: #f5f7fa; --bg2: #edf0f5; --card: #ffffff; --card2: #f0f2f7;
            --purple: #6c5ce7; --purple-light: #7c6ff0; --purple-dark: #5a4fcf;
            --teal: #00b4b5;
            --text: #1a1a2e; --text2: #5a6178; --text3: #8892a4;
            --border2: rgba(0,0,0,0.08);
            --danger: #e17055;
            --navbar-bg: rgba(245,247,250,0.92);
        }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body.landing-guest {
            margin: 0;
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
            min-height: 100vh;
        }
        a { color: inherit; }

        .landing-navbar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 1000;
            background: var(--navbar-bg); backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border2); padding: 0 24px;
        }
        .landing-navbar-inner {
            max-width: 1280px; margin: 0 auto; height: 56px;
            display: flex; align-items: center; gap: 16px;
        }
        .landing-navbar-spacer { flex: 1; min-width: 8px; }
        .landing-logo { display: flex; align-items: center; gap: 10px; line-height: 1.1; text-decoration: none; }
        .landing-logo-mark { width: 32px; height: 32px; border-radius: 9px; flex-shrink: 0; box-shadow: 0 4px 14px rgba(124, 58, 237, 0.35); }
        .landing-logo-text { display: flex; flex-direction: column; }
        .landing-logo-name {
            font-size: 17px; font-weight: 800;
            background: linear-gradient(135deg, var(--purple), var(--teal));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }
        .landing-logo-slogan { font-size: 9px; color: var(--text2); letter-spacing: 0.5px; }
        .landing-nav-home {
            color: var(--text2); font-size: 13px; font-weight: 500; text-decoration: none;
            padding: 6px 12px; border-radius: 6px; transition: color 0.2s, background 0.2s;
        }
        .landing-nav-home:hover { color: var(--text); background: rgba(108,92,231,0.08); }
        .landing-nav-right { display: flex; align-items: center; gap: 10px; }
        .landing-theme-toggle {
            background: rgba(255,255,255,0.06); border: 1px solid var(--border2);
            width: 34px; height: 34px; border-radius: 8px; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px; color: var(--text2); transition: all 0.2s;
        }
        .landing-theme-toggle:hover { border-color: var(--purple); color: var(--text); }

        .landing-lang select.lang-select {
            background: rgba(255,255,255,0.06) !important;
            border: 1px solid var(--border2) !important;
            color: var(--text) !important;
            border-radius: 8px !important;
            padding: 6px 28px 6px 10px !important;
            font-size: 13px !important;
            font-family: inherit !important;
        }
        .landing-lang select.lang-select:focus {
            outline: none; border-color: var(--purple) !important;
        }

        .landing-auth-main {
            min-height: 100vh; display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            padding: 88px 20px 40px;
        }
        .landing-auth-card {
            width: 100%; max-width: 420px;
            background: var(--card);
            border: 1px solid var(--border2);
            border-radius: var(--radius-lg);
            padding: 28px 26px 30px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.25);
        }
        [data-theme="light"] .landing-auth-card { box-shadow: 0 12px 40px rgba(0,0,0,0.08); }

        /* Form fields (override Tailwind from components) */
        .landing-auth-card label.block.font-medium,
        .landing-auth-slot label.block.font-medium {
            color: var(--text2) !important;
            font-size: 12px !important;
        }
        .landing-auth-card input[type="text"],
        .landing-auth-card input[type="email"],
        .landing-auth-card input[type="password"],
        .landing-auth-slot input[type="text"],
        .landing-auth-slot input[type="email"],
        .landing-auth-slot input[type="password"] {
            margin-top: 4px !important;
            width: 100% !important;
            background: rgba(255,255,255,0.04) !important;
            border: 1px solid var(--border2) !important;
            border-radius: 8px !important;
            padding: 10px 14px !important;
            color: var(--text) !important;
            font-size: 13px !important;
            font-family: inherit !important;
            box-shadow: none !important;
        }
        [data-theme="light"] .landing-auth-card input[type="text"],
        [data-theme="light"] .landing-auth-card input[type="email"],
        [data-theme="light"] .landing-auth-card input[type="password"],
        [data-theme="light"] .landing-auth-slot input[type="text"],
        [data-theme="light"] .landing-auth-slot input[type="email"],
        [data-theme="light"] .landing-auth-slot input[type="password"] {
            background: var(--bg2) !important;
        }
        .landing-auth-card input:focus,
        .landing-auth-slot input:focus {
            outline: none !important;
            border-color: var(--purple) !important;
            ring: none !important;
            --tw-ring-shadow: 0 0 #0000 !important;
        }
        .landing-auth-card .text-red-600,
        .landing-auth-slot .text-red-600,
        .landing-auth-card ul.text-sm.text-red-600,
        .landing-auth-slot ul.text-sm.text-red-600 {
            color: var(--danger) !important;
        }
        .landing-auth-card button[type="submit"],
        .landing-auth-slot button[type="submit"] {
            background: linear-gradient(135deg, var(--purple), var(--teal)) !important;
            border: none !important;
            color: #fff !important;
            padding: 10px 20px !important;
            border-radius: 10px !important;
            font-size: 13px !important;
            font-weight: 700 !important;
            text-transform: none !important;
            letter-spacing: normal !important;
            box-shadow: 0 4px 16px rgba(108,92,231,0.35);
            transition: transform 0.2s, box-shadow 0.2s !important;
        }
        .landing-auth-card button[type="submit"]:hover,
        .landing-auth-slot button[type="submit"]:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(108,92,231,0.45) !important;
        }
        .landing-auth-card a.underline,
        .landing-auth-slot a.underline,
        .landing-auth-card a.text-sm,
        .landing-auth-slot a.text-sm {
            color: var(--text2) !important;
        }
        .landing-auth-card a.text-indigo-600,
        .landing-auth-slot a.text-indigo-600 {
            color: var(--purple-light) !important;
        }
        .landing-auth-card a:hover,
        .landing-auth-slot a:hover {
            color: var(--teal) !important;
        }
        .landing-auth-card .text-gray-600,
        .landing-auth-slot .text-gray-600,
        .landing-auth-card .text-gray-900,
        .landing-auth-slot .text-gray-900 {
            color: var(--text2) !important;
        }
        .landing-auth-card h2.text-2xl,
        .landing-auth-slot h2.text-2xl {
            color: var(--text) !important;
            font-size: 1.35rem !important;
            font-weight: 800 !important;
            letter-spacing: -0.02em;
            margin-bottom: 1.25rem !important;
        }

        /* Social / divider (login & register) */
        .landing-auth-slot .border-gray-300 { border-color: var(--border2) !important; }
        .landing-auth-slot span.bg-white,
        .landing-auth-slot span.px-2.bg-white {
            background: var(--card) !important;
            color: var(--text3) !important;
        }
        .landing-auth-slot .grid.grid-cols-2 > a,
        .landing-auth-slot #telegram-button-visual {
            background: rgba(255,255,255,0.04) !important;
            border: 1px solid var(--border2) !important;
            color: var(--text) !important;
            border-radius: 10px !important;
            box-shadow: none !important;
        }
        [data-theme="light"] .landing-auth-slot .grid.grid-cols-2 > a,
        [data-theme="light"] .landing-auth-slot #telegram-button-visual {
            background: var(--bg2) !important;
        }
        .landing-auth-slot .grid.grid-cols-2 > a:hover,
        .landing-auth-slot #telegram-button-visual:hover {
            border-color: rgba(108,92,231,0.4) !important;
            background: rgba(108,92,231,0.08) !important;
        }
        .landing-auth-slot input[type="checkbox"] {
            border-color: var(--border2) !important;
            background: rgba(255,255,255,0.04) !important;
        }
        .landing-auth-slot input[type="checkbox"]:checked {
            background: var(--purple) !important;
            border-color: var(--purple) !important;
        }

        .landing-auth-slot .text-green-600 {
            display: block;
            padding: 10px 12px; border-radius: 8px; font-size: 13px; margin-bottom: 1rem !important;
            background: rgba(0,184,148,0.12); border: 1px solid rgba(0,184,148,0.35); color: #00b894 !important;
        }
        [data-theme="light"] .landing-auth-slot .text-green-600 { color: #008f73 !important; }

        @media (max-width: 520px) {
            .landing-logo-slogan { display: none; }
            .landing-nav-home { font-size: 12px; padding: 4px 8px; }
            .landing-auth-card { padding: 22px 18px 24px; }
        }
    </style>
</head>
<body class="landing-guest">
    <nav class="landing-navbar">
        <div class="landing-navbar-inner">
            <a href="{{ route('home') }}" class="landing-logo">
                <img src="{{ asset('images/logo-icon.svg') }}" alt="" class="landing-logo-mark">
                <span class="landing-logo-text">
                    <span class="landing-logo-name">{{ config('app.name', 'SMM Tool') }}</span>
                    <span class="landing-logo-slogan">{{ __('Social Media Growth') }}</span>
                </span>
            </a>
            <div class="landing-navbar-spacer" aria-hidden="true"></div>
            <a href="{{ route('home') }}" class="landing-nav-home">{{ __('Back to home') }}</a>
            <div class="landing-nav-right">
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
