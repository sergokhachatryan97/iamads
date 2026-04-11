<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <x-heleket-site-verification />
    <title>{{ __('contacts.title') }} – {{ config('contact.company_name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/apple-touch-icon.png') }}">
    <style>
        :root { --radius: 12px; }
        [data-theme="dark"] {
            --bg: #0a0a0f; --card: #1a1a2e; --card2: #16213e;
            --purple: #6c5ce7; --purple-light: #a29bfe; --purple-dark: #5a4fcf;
            --teal: #00d2d3;
            --text: #e2e8f0; --text2: #8892a4; --text3: #5a6178;
            --border2: rgba(255,255,255,0.08);
            --navbar-bg: rgba(10,10,15,0.92);
        }
        [data-theme="light"] {
            --bg: #f5f7fa; --card: #ffffff; --card2: #f0f2f7;
            --purple: #6c5ce7; --purple-light: #7c6ff0; --purple-dark: #5a4fcf;
            --teal: #00b4b5;
            --text: #1a1a2e; --text2: #5a6178; --text3: #8892a4;
            --border2: rgba(0,0,0,0.08);
            --navbar-bg: rgba(245,247,250,0.92);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Inter, system-ui, sans-serif; background: var(--bg); color: var(--text); line-height: 1.6; min-height: 100vh; }
        a { color: inherit; text-decoration: none; }

        .navbar { position: fixed; top: 0; left: 0; right: 0; z-index: 1000; background: var(--navbar-bg); backdrop-filter: blur(20px); border-bottom: 1px solid var(--border2); padding: 0 24px; }
        .navbar-inner { max-width: 1280px; margin: 0 auto; height: 60px; display: flex; align-items: center; gap: 28px; }
        .logo { display: flex; align-items: center; gap: 10px; line-height: 1.1; flex-shrink: 0; }
        .logo-mark { width: 34px; height: 34px; border-radius: 9px; flex-shrink: 0; box-shadow: 0 4px 14px rgba(124, 58, 237, 0.35); }
        .logo-text { display: flex; flex-direction: column; }
        .logo-name { font-size: 18px; font-weight: 800; background: linear-gradient(135deg, var(--purple), var(--teal)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .logo-slogan { font-size: 9px; color: var(--text2); letter-spacing: 0.5px; }
        .nav-links { display: flex; align-items: center; gap: 2px; flex: 1; list-style: none; }
        .nav-links a { color: var(--text2); font-size: 13px; font-weight: 500; padding: 6px 12px; border-radius: 6px; transition: all 0.2s; }
        .nav-links a:hover { color: var(--text); background: rgba(108,92,231,0.08); }
        .nav-right { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
        .theme-toggle { background: rgba(255,255,255,0.06); border: 1px solid var(--border2); width: 34px; height: 34px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 14px; color: var(--text2); transition: all 0.2s; }
        .theme-toggle:hover { border-color: var(--purple); color: var(--text); }
        .btn-signin { background: transparent; border: 1px solid var(--border2); color: var(--text); padding: 6px 14px; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: pointer; transition: all 0.2s; font-family: inherit; }
        .btn-signin:hover { border-color: var(--purple); color: var(--purple); }
        .btn-signup { background: linear-gradient(135deg, var(--purple), var(--purple-dark)); border: none; color: #fff; padding: 7px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s; font-family: inherit; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .btn-signup:hover { transform: translateY(-1px); box-shadow: 0 4px 16px rgba(108,92,231,0.4); }

        .contacts-main { padding: 88px 24px 80px; max-width: 1100px; margin: 0 auto; }
        .contacts-title { font-size: clamp(28px, 4vw, 40px); font-weight: 800; text-align: center; margin-bottom: 16px; letter-spacing: -0.02em; color: var(--text); }
        .contacts-intro { text-align: center; color: var(--text2); font-size: 15px; max-width: 36rem; margin: 0 auto 32px; line-height: 1.5; }
        .contacts-value-muted { color: var(--text3) !important; font-weight: 500 !important; }
        .contact-card.telegram .contacts-value-muted { color: rgba(255,255,255,0.8) !important; }
        .contacts-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        @media (max-width: 900px) { .contacts-grid { grid-template-columns: 1fr; } .nav-links { display: none; } }
        .contact-card {
            background: var(--card); border: 1px solid var(--border2); border-radius: var(--radius);
            padding: 28px 22px; text-align: center; transition: border-color 0.2s, box-shadow 0.2s;
        }
        .contact-card:hover { border-color: rgba(108,92,231,0.35); box-shadow: 0 8px 32px rgba(0,0,0,0.2); }
        .contact-card.telegram {
            background: linear-gradient(145deg, #0088cc, #006699); border-color: rgba(255,255,255,0.15); color: #fff;
        }
        .contact-card .icon { font-size: 40px; margin-bottom: 16px; color: var(--purple-light); }
        .contact-card.telegram .icon { color: #fff; }
        .contact-card .label { font-size: 12px; color: var(--text3); margin-bottom: 8px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; }
        .contact-card.telegram .label { color: rgba(255,255,255,0.85); }
        .contact-card .value { font-size: 16px; font-weight: 700; word-break: break-all; color: var(--text); }
        .contact-card a.value { color: var(--teal); }
        .contact-card a.value:hover { text-decoration: underline; }
        .contact-card.telegram a.value { color: #fff; }

        .footer { padding: 40px 24px 24px; border-top: 1px solid var(--border2); margin-top: auto; }
        .footer-inner { max-width: 1100px; margin: 0 auto; text-align: center; font-size: 13px; color: var(--text3); }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="navbar-inner">
        <a href="{{ route('home') }}" class="logo" style="text-decoration:none;">
            <img src="{{ asset('images/logo-icon.svg') }}" alt="" class="logo-mark">
            <span class="logo-text">
                <span class="logo-name">{{ config('contact.company_name') }}</span>
                <span class="logo-slogan">{{ __('Social Media Growth') }}</span>
            </span>
        </a>
        <ul class="nav-links">
            <li><a href="{{ route('home') }}">{{ __('home.nav_services') }}</a></li>
            <li><a href="{{ route('contacts') }}">{{ __('home.nav_contacts') }}</a></li>
        </ul>
        <div class="nav-right">
            <button type="button" class="theme-toggle" id="contactsThemeBtn" onclick="contactsToggleTheme()" title="{{ __('Toggle theme') }}"><i class="fa-solid fa-moon" id="contactsThemeIcon"></i></button>
            <x-language-dropdown variant="landing" />
            @auth('client')
                <a href="{{ route('dashboard') }}" class="btn-signup">{{ __('Dashboard') }}</a>
                <form method="POST" action="{{ route('logout') }}" class="inline" style="margin:0;">
                    @csrf
                    <button type="submit" class="btn-signin">{{ __('common.logout') }}</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="btn-signin">{{ __('home.login') }}</a>
                <a href="{{ route('register') }}" class="btn-signup">{{ __('Sign Up') }}</a>
            @endauth
        </div>
    </div>
</nav>

<main class="contacts-main">
    <h1 class="contacts-title">{{ __('contacts.title') }}</h1>
    <p class="contacts-intro">{{ __('contacts.intro') }}</p>
    <div class="contacts-grid">
        <div class="contact-card">
            <div class="icon"><i class="far fa-envelope"></i></div>
            <div class="label">{{ __('contacts.help_desk') }}</div>
            @if(filled($contactEmail))
                <a href="mailto:{{ $contactEmail }}" class="value">{{ $contactEmail }}</a>
            @else
                <span class="value contacts-value-muted">{{ __('contacts.not_available') }}</span>
            @endif
        </div>
        <div class="contact-card {{ filled($contactTelegram) ? 'telegram' : '' }}">
            <div class="icon"><i class="fab fa-telegram-plane"></i></div>
            <div class="label">{{ __('contacts.telegram_bot') }}</div>
            @if(filled($contactTelegram))
                <a href="https://t.me/{{ ltrim($contactTelegram, '@') }}" target="_blank" rel="noopener" class="value">{{ $contactTelegram }}</a>
            @else
                <span class="value contacts-value-muted">{{ __('contacts.not_available') }}</span>
            @endif
        </div>
        @if(filled($contactCooperationEmail))
            <div class="contact-card">
                <div class="icon"><i class="far fa-handshake"></i></div>
                <div class="label">{{ __('contacts.cooperation') }}</div>
                <a href="mailto:{{ $contactCooperationEmail }}" class="value">{{ $contactCooperationEmail }}</a>
            </div>
        @endif
    </div>
</main>

<footer class="footer">
    <div class="footer-inner">
        © {{ config('contact.company_name') }}, {{ date('Y') }}. {{ __('home.footer_rights') }}
    </div>
</footer>

<script>
function contactsToggleTheme() {
    const html = document.documentElement;
    const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    localStorage.setItem('smm-theme', next);
    const icon = document.getElementById('contactsThemeIcon');
    if (icon) icon.className = next === 'dark' ? 'fa-solid fa-moon' : 'fa-solid fa-sun';
}
(function () {
    const saved = localStorage.getItem('smm-theme');
    if (saved === 'light' || saved === 'dark') {
        document.documentElement.setAttribute('data-theme', saved);
        const icon = document.getElementById('contactsThemeIcon');
        if (icon && saved === 'light') icon.className = 'fa-solid fa-sun';
    }
})();
</script>
</body>
</html>
