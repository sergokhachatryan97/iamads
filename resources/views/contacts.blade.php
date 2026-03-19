<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ __('contacts.title') }} – {{ config('contact.company_name') }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f5f5f7;
            color: #1c1d24;
        }
        .container { width: min(1000px, calc(100% - 40px)); margin: 0 auto; }
        .topbar { padding: 20px 0; }
        .topbar-inner { display: flex; align-items: center; justify-content: space-between; gap: 24px; }
        .brand { display: flex; align-items: center; gap: 14px; font-size: 24px; font-weight: 700; }
        .nav { display: flex; gap: 44px; align-items: center; color: #2f3342; font-size: 18px; }
        .nav a { color: inherit; text-decoration: none; }
        .nav a:hover { color: #0ea5f5; }
        .login-btn {
            border: 0; background: linear-gradient(135deg, #0ea5f5, #0188d8); color: white;
            border-radius: 18px; padding: 16px 24px; font-size: 18px;
            display: inline-flex; align-items: center; gap: 14px; cursor: pointer; text-decoration: none;
        }
        .plus { font-size: 28px; line-height: 1; opacity: .9; }
        main { padding: 60px 0 80px; text-align: center; }
        .contacts-title { font-size: clamp(36px, 5vw, 48px); font-weight: 700; margin: 0 0 48px; color: #1c1d24; }
        .contacts-grid {
            display: grid; grid-template-columns: repeat(3, 1fr);
            gap: 32px; justify-items: center; align-items: stretch;
        }
        @media (max-width: 900px) { .contacts-grid { grid-template-columns: 1fr; } }
        .contact-card {
            background: #fff; border-radius: 20px; padding: 32px 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,.08); min-width: 260px; max-width: 320px;
            display: flex; flex-direction: column; align-items: center; text-align: center;
        }
        .contact-card.telegram {
            background: #0088cc; color: #fff; box-shadow: 0 4px 24px rgba(0,136,204,.35);
        }
        .contact-card .icon { font-size: 48px; margin-bottom: 20px; }
        .contact-card.telegram .icon { color: #fff; }
        .contact-card:not(.telegram) .icon { color: #2563eb; }
        .contact-card .label { font-size: 14px; color: #6b7280; margin-bottom: 8px; font-weight: 500; }
        .contact-card.telegram .label { color: rgba(255,255,255,.9); }
        .contact-card .value {
            font-size: 18px; font-weight: 700; word-break: break-all;
        }
        .contact-card a.value { color: #1c1d24; text-decoration: none; }
        .contact-card a.value:hover { text-decoration: underline; }
        .contact-card.telegram a.value { color: #fff; }
        .footer {
            padding: 48px 0 24px; background: linear-gradient(180deg, #1a1c24 0%, #15171e 100%);
            color: rgba(255,255,255,.9); font-size: 14px;
        }
        .footer-inner { max-width: 1000px; margin: 0 auto; padding: 0 24px; text-align: center; }
        .footer-copy { opacity: .7; margin-top: 16px; }
    </style>
</head>
<body>
<header class="topbar">
    <div class="container topbar-inner">
        <a href="{{ route('home') }}" class="brand" style="text-decoration:none;color:inherit;">
            <div class="brand-mark" style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#0ea5f5,#7c4dff);"></div>
            {{ config('contact.company_name') }}
        </a>
        <nav class="nav">
            <a href="{{ route('home') }}">{{ __('home.nav_services') }}</a>
            <a href="{{ route('contacts') }}">{{ __('home.nav_contacts') }}</a>
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
        <a href="{{ route('login') }}" class="login-btn">
            <span>{{ __('home.login') }}</span>
            <span class="plus">＋</span>
        </a>
        @endauth
    </div>
</header>

<main class="container">
    <h1 class="contacts-title">{{ __('contacts.title') }}</h1>
    <div class="contacts-grid">
        @if($contactEmail)
        <div class="contact-card">
            <div class="icon"><i class="far fa-envelope"></i></div>
            <div class="label">{{ __('contacts.help_desk') }}</div>
            <a href="mailto:{{ $contactEmail }}" class="value">{{ $contactEmail }}</a>
        </div>
        @endif

        @if(!empty($contactTelegram))
        <div class="contact-card telegram">
            <div class="icon"><i class="fab fa-telegram-plane"></i></div>
            <div class="label">{{ __('contacts.telegram_bot') }}</div>
            <a href="https://t.me/{{ ltrim($contactTelegram, '@') }}" target="_blank" rel="noopener" class="value">{{ $contactTelegram }}</a>
        </div>
        @endif

        @if(!empty($contactCooperationEmail))
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
        <div class="footer-copy">© {{ config('contact.company_name') }}, {{ date('Y') }}. {{ __('home.footer_rights') }}</div>
    </div>
</footer>
</body>
</html>
