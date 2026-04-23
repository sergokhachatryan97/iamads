<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" data-theme="dark">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <x-heleket-site-verification />
    <title>{{ __('contacts.title') }} – {{ config('contact.company_name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="icon" type="image/png" href="{{ asset('images/adtag_fav.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/adtag_fav.png') }}">
    <style>
        :root { --radius: 14px; }
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
        body { font-family: Inter, system-ui, sans-serif; background: var(--bg); color: var(--text); line-height: 1.6; min-height: 100vh; display: flex; flex-direction: column; }
        a { color: inherit; text-decoration: none; }

        .navbar { position: fixed; top: 0; left: 0; right: 0; z-index: 1000; background: var(--navbar-bg); backdrop-filter: blur(20px); border-bottom: 1px solid var(--border2); padding: 0 24px; }
        .navbar-inner { max-width: 1280px; margin: 0 auto; height: 60px; display: flex; align-items: center; gap: 28px; }
        .logo { display: flex; flex-direction: column; align-items: center; gap: 4px; line-height: 1.1; flex-shrink: 0; }
        .logo-mark { height: 40px; width: auto; flex-shrink: 0; filter: drop-shadow(0 4px 14px rgba(124, 58, 237, 0.35)); }
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

        .contacts-main { padding: 100px 24px 60px; max-width: 800px; margin: 0 auto; flex: 1; width: 100%; }
        .contacts-header { text-align: center; margin-bottom: 40px; }
        .contacts-title { font-size: clamp(26px, 4vw, 36px); font-weight: 800; letter-spacing: -0.02em; margin-bottom: 10px; background: linear-gradient(135deg, var(--text) 30%, var(--purple-light)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .contacts-intro { color: var(--text2); font-size: 15px; max-width: 28rem; margin: 0 auto; line-height: 1.6; }

        .contacts-grid { display: flex; flex-direction: column; gap: 16px; }

        /* Email card */
        .contact-card {
            background: var(--card); border: 1px solid var(--border2); border-radius: var(--radius);
            padding: 24px 28px; transition: border-color 0.2s, box-shadow 0.2s;
        }
        .contact-card:hover { border-color: rgba(108,92,231,0.3); box-shadow: 0 4px 24px rgba(0,0,0,0.15); }
        .card-header { display: flex; align-items: center; gap: 14px; margin-bottom: 4px; }
        .card-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
        .card-icon.email-icon { background: rgba(108,92,231,0.12); color: var(--purple-light); }
        .card-icon.telegram-icon { background: rgba(42,171,238,0.15); color: #2aabee; }
        .card-icon.coop-icon { background: rgba(0,210,211,0.12); color: var(--teal); }
        .card-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text3); }
        .card-title { font-size: 15px; font-weight: 700; color: var(--text); }
        .card-value { margin-top: 2px; }
        .card-value a { color: var(--teal); font-size: 15px; font-weight: 600; transition: opacity 0.2s; }
        .card-value a:hover { opacity: 0.8; text-decoration: underline; }

        /* Telegram support links */
        .tg-links { display: flex; flex-direction: column; gap: 0; margin-top: 12px; }
        .tg-link {
            display: flex; align-items: center; gap: 14px;
            padding: 14px 18px; border-radius: 10px;
            transition: background 0.15s;
            text-decoration: none; color: var(--text);
        }
        .tg-link:hover { background: rgba(42,171,238,0.08); }
        .tg-flag { font-size: 24px; flex-shrink: 0; }
        .tg-info { flex: 1; min-width: 0; }
        .tg-lang { font-size: 14px; font-weight: 600; }
        .tg-handle { font-size: 12px; color: var(--text3); }
        .tg-arrow { color: var(--text3); font-size: 12px; transition: transform 0.2s, color 0.2s; }
        .tg-link:hover .tg-arrow { transform: translateX(3px); color: #2aabee; }

        .footer { padding: 32px 24px 20px; border-top: 1px solid var(--border2); margin-top: auto; }
        .footer-inner { max-width: 1100px; margin: 0 auto; text-align: center; font-size: 12px; color: var(--text3); }

        @media (max-width: 768px) {
            .logo-slogan { display: none; }
        }
        @media (max-width: 600px) {
            .nav-links { display: none; }
            .contacts-main { padding: 80px 16px 40px; }
            .contact-card { padding: 20px; }
            .tg-link { padding: 12px 14px; }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="navbar-inner">
        <a href="{{ route('home') }}" class="logo" style="text-decoration:none;">
            <img src="{{ asset('images/adtag_fav.png') }}" alt="{{ config('contact.company_name') }}" class="logo-mark">
            <span class="logo-slogan" style="font-size:10px;color:#8892a4;white-space:nowrap;">Social Media Growth Tool</span>
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
                    <div class="card-title">{{ __('Select Support Language') }}</div>
                </div>
            </div>
            <div class="tg-links">
                @foreach(config('contact.telegram_support_list', []) as $support)
                    @if(!empty($support['username']))
                        @php $handle = ltrim($support['username'], '@'); @endphp
                        <a href="https://t.me/{{ $handle }}" target="_blank" rel="noopener noreferrer" class="tg-link">
                            <span class="tg-flag">{{ $support['flag'] }}</span>
                            <div class="tg-info">
                                <div class="tg-lang">{{ $support['label'] }}</div>
                                <div class="tg-handle">{{ '@' . $handle }}</div>
                            </div>
                            <i class="fa-solid fa-arrow-right tg-arrow"></i>
                        </a>
                    @endif
                @endforeach
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
