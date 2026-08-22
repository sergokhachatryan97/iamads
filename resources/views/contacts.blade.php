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
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <x-heleket-site-verification />
    <x-cryptomus-site-verification />
    <title>{{ __('contacts.title') }} – {{ config('contact.company_name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="icon" type="image/png" href="{{ asset('images/new-logo.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/new-logo.png') }}">
    <style>
        /* ─── Brand tokens (same system as home/login) ─── */
        :root { --radius: 14px; }
        [data-theme="dark"] {
            --bg: #06060f; --bg2: #0a0a1a; --card: #0e0e24;
            --cyan: #06B6D4; --blue: #3B82F6; --purple: #8B5CF6; --purple-light: #A78BFA; --purple-dark: #7C3AED;
            --text: #e2e8f0; --text2: #a8b8cc; --text3: #4a5568;
            --border2: rgba(139,92,246,0.18);
            --navbar-bg: rgba(6,6,15,0.94);
        }
        [data-theme="light"] {
            --bg: #f1f0ff; --bg2: #e9e7ff; --card: #ffffff;
            --cyan: #0891B2; --blue: #2563EB; --purple: #7C3AED; --purple-light: #8B5CF6; --purple-dark: #6D28D9;
            --text: #0f0f23; --text2: #4a5568; --text3: #9ca3af;
            --border2: rgba(124,58,237,0.14);
            --navbar-bg: rgba(241,240,255,0.96);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Inter, system-ui, sans-serif;
            background: var(--bg);
            background-image: radial-gradient(ellipse 70% 35% at 50% 0%, rgba(139,92,246,0.07) 0%, transparent 65%);
            color: var(--text); line-height: 1.6; min-height: 100vh; display: flex; flex-direction: column;
        }
        a { color: inherit; text-decoration: none; }

        /* ─── Navbar ─── */
        .navbar { position: fixed; top: 0; left: 0; right: 0; z-index: 1000; background: var(--navbar-bg); backdrop-filter: blur(24px); border-bottom: 1px solid var(--border2); padding: 0 24px; }
        .navbar-inner { max-width: 1280px; margin: 0 auto; height: 62px; display: flex; align-items: center; gap: 28px; }
        .logo { display: flex; flex-direction: column; align-items: center; gap: 4px; line-height: 1.1; flex-shrink: 0; }
        .logo-mark { height: 58px; width: auto; flex-shrink: 0; filter: drop-shadow(0 2px 10px rgba(139,92,246,0.3)); }
        .nav-links { display: flex; align-items: center; gap: 2px; flex: 1; list-style: none; }
        .nav-links a { color: var(--text2); font-size: 13px; font-weight: 500; padding: 6px 12px; border-radius: 6px; transition: all 0.2s; }
        .nav-links a:hover { color: var(--text); background: rgba(59,130,246,0.08); }
        .nav-right { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
        .theme-toggle { background: rgba(255,255,255,0.04); border: 1px solid var(--border2); width: 36px; height: 36px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 14px; color: var(--text2); transition: all 0.2s; }
        .theme-toggle:hover { border-color: var(--cyan); color: var(--text); }
        .btn-signin { background: transparent; border: 1px solid var(--border2); color: var(--text); padding: 6px 14px; border-radius: 8px; font-size: 13px; font-weight: 500; cursor: pointer; transition: all 0.2s; font-family: inherit; }
        .btn-signin:hover { border-color: var(--purple); color: var(--purple-light); }
        .btn-signup { background: linear-gradient(90deg, var(--cyan), var(--blue), var(--purple)); border: none; color: #fff; padding: 7px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s; font-family: inherit; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .btn-signup:hover { transform: translateY(-1px); box-shadow: 0 4px 18px rgba(139,92,246,0.45); }

        /* dark nav overrides */
        [data-theme="dark"] .navbar .nav-links a { color: rgba(255,255,255,0.85); }
        [data-theme="dark"] .navbar .nav-links a:hover { color: #fff; background: rgba(255,255,255,0.08); }
        [data-theme="dark"] .navbar .theme-toggle { color: rgba(255,255,255,0.8); }
        [data-theme="dark"] .navbar .theme-toggle:hover { color: #fff; border-color: var(--cyan); }
        [data-theme="dark"] .navbar .btn-signin { color: rgba(255,255,255,0.9); border-color: rgba(255,255,255,0.22); }
        [data-theme="dark"] .navbar .btn-signin:hover { color: #fff; border-color: rgba(139,92,246,0.5); background: rgba(139,92,246,0.08); }
        [data-theme="dark"] .navbar .lang-landing-details > summary { color: #fff; }

        /* ─── Page ─── */
        .contacts-main { padding: 100px 24px 60px; max-width: 760px; margin: 0 auto; flex: 1; width: 100%; }
        .contacts-header { text-align: center; margin-bottom: 40px; }
        .contacts-title {
            font-size: clamp(26px, 4vw, 36px); font-weight: 800; letter-spacing: -0.02em; margin-bottom: 10px;
            background: linear-gradient(90deg, var(--cyan), var(--blue) 48%, var(--purple-light));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }
        .contacts-intro { color: var(--text2); font-size: 15px; max-width: 28rem; margin: 0 auto; line-height: 1.6; }

        /* ─── Cards ─── */
        .contacts-grid { display: flex; flex-direction: column; gap: 12px; }
        .contact-card {
            background: var(--card);
            border: 1px solid var(--border2);
            border-radius: var(--radius);
            padding: 22px 26px;
            transition: border-color 0.2s, box-shadow 0.2s;
            position: relative; overflow: hidden;
        }
        .contact-card::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0; height: 2px;
            background: linear-gradient(90deg, var(--cyan), var(--blue), var(--purple));
            opacity: 0; transition: opacity 0.2s;
        }
        .contact-card:hover { border-color: rgba(139,92,246,0.3); box-shadow: 0 4px 28px rgba(0,0,0,0.2); }
        .contact-card:hover::before { opacity: 1; }

        .card-header { display: flex; align-items: center; gap: 14px; margin-bottom: 4px; }
        .card-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
        .card-icon.email-icon  { background: rgba(59,130,246,0.12); color: var(--blue); }
        .card-icon.telegram-icon { background: rgba(42,171,238,0.14); color: #2aabee; }
        .card-icon.coop-icon   { background: rgba(6,182,212,0.12);  color: var(--cyan); }
        .card-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text3); }
        .card-title { font-size: 15px; font-weight: 700; color: var(--text); }
        .card-value { margin-top: 2px; }
        .card-value a { color: var(--cyan); font-size: 15px; font-weight: 600; transition: color 0.2s; }
        .card-value a:hover { color: var(--blue); text-decoration: underline; }

        /* ─── Telegram rows ─── */
        .tg-links { display: flex; flex-direction: column; gap: 2px; margin-top: 10px; }
        .tg-link {
            display: flex; align-items: center; gap: 14px;
            padding: 12px 14px; border-radius: 10px;
            border: 1px solid transparent;
            transition: background 0.15s, border-color 0.15s;
            text-decoration: none; color: var(--text);
        }
        .tg-link:hover { background: rgba(42,171,238,0.07); border-color: rgba(42,171,238,0.2); }
        .tg-flag  { font-size: 22px; flex-shrink: 0; }
        .tg-info  { flex: 1; min-width: 0; }
        .tg-lang  { font-size: 14px; font-weight: 600; }
        .tg-handle { font-size: 12px; color: var(--text3); }
        .tg-arrow { color: var(--text3); font-size: 12px; transition: transform 0.2s, color 0.2s; }
        .tg-link:hover .tg-arrow { transform: translateX(4px); color: #2aabee; }

        /* ─── Footer ─── */
        .footer { padding: 28px 24px 18px; border-top: 1px solid var(--border2); background: var(--bg2); margin-top: auto; }
        .footer-inner { max-width: 1100px; margin: 0 auto; text-align: center; font-size: 12px; color: var(--text3); }

        /* ─── Responsive ─── */
        @media (max-width: 768px) {
            .logo-mark { height: 46px; }
            .navbar-inner { height: 56px; }
        }
        @media (max-width: 600px) {
            .nav-links { display: none; }
            .contacts-main { padding: 80px 16px 40px; }
            .contact-card { padding: 18px; }
            .tg-link { padding: 10px 12px; }
        }
    </style>
</head>
<body>
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-TRRBPCBV"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-W7F85JNQ"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->

<nav class="navbar">
    <div class="navbar-inner">
        <a href="{{ route('home') }}" class="logo" style="text-decoration:none;">
            <img src="{{ asset('images/new-logo.png') }}" alt="{{ config('contact.company_name') }}" class="logo-mark">
{{--            <span class="logo-slogan" style="font-size:10px;color:#8892a4;white-space:nowrap;">Social Media Growth Tool</span>--}}
        </a>
        <ul class="nav-links">
            <li><a href="{{ route('home') }}">{{ __('home.nav_services') }}</a></li>
            <li><a href="{{ route('contacts') }}">{{ __('home.nav_contacts') }}</a></li>
        </ul>
        <div class="nav-right">
            <button type="button" class="theme-toggle" id="contactsThemeBtn" onclick="contactsToggleTheme()" title="{{ __('Toggle theme') }}"><i class="fa-solid fa-moon" id="contactsThemeIcon"></i></button>
            <x-language-dropdown variant="landing" />
            @auth('client')
                <a href="{{ route('client.orders.index') }}" class="btn-signup">{{ __('Services') }}</a>
            @else
                <a href="{{ route('login') }}" class="btn-signin">{{ __('home.login') }}</a>
                <a href="{{ route('register') }}" class="btn-signup">{{ __('Sign Up') }}</a>
            @endauth
        </div>
    </div>
</nav>

<main class="contacts-main">
    <div class="contacts-header">
        <h1 class="contacts-title">{{ __('contacts.title') }}</h1>
        <p class="contacts-intro">{{ __('contacts.intro') }}</p>
    </div>

    <div class="contacts-grid">
        {{-- Email --}}
        <div class="contact-card">
            <div class="card-header">
                <div class="card-icon email-icon"><i class="far fa-envelope"></i></div>
                <div>
                    <div class="card-label">{{ __('contacts.help_desk') }}</div>
                    <div class="card-value">
                        @if(filled($contactEmail))
                            <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>
                        @else
                            <span style="color:var(--text3);font-size:14px;">{{ __('contacts.not_available') }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Telegram --}}
        <div class="contact-card">
            <div class="card-header">
                <div class="card-icon telegram-icon"><i class="fab fa-telegram-plane"></i></div>
                <div>
                    <div class="card-label">{{ __('contacts.telegram_bot') }}</div>
                    <div class="card-value">
                        <a href="https://t.me/smmtw" target="_blank" rel="noopener noreferrer">@smmtw</a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Cooperation --}}
        @if(filled($contactCooperationEmail))
        <div class="contact-card">
            <div class="card-header">
                <div class="card-icon coop-icon"><i class="far fa-handshake"></i></div>
                <div>
                    <div class="card-label">{{ __('contacts.cooperation') }}</div>
                    <div class="card-value">
                        <a href="mailto:{{ $contactCooperationEmail }}">{{ $contactCooperationEmail }}</a>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>
</main>

<footer class="footer">
    <div class="footer-inner">
        &copy; {{ config('contact.company_name') }}, {{ date('Y') }}. {{ __('home.footer_rights') }}
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
