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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('home.page_title', ['name' => config('app.name', 'SMM Tool')]) }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" type="image/png" href="{{ asset('images/adtag_fav.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/adtag_fav.png') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <x-heleket-site-verification />

    <meta property="og:title" content="{{ __('home.meta_og_title', ['name' => config('app.name', 'SMM Tool')]) }}" />
    <meta property="og:description" content="{{ __('home.meta_og_description') }}" />
    <meta property="og:image" content="{{ asset('images/preview.png') }}" />
    <meta property="og:url" content="{{ url()->current() }}" />
    <style>
        :root { --radius: 12px; --radius-lg: 20px; --radius-sm: 8px; }
        [data-theme="dark"] {
            --bg: #0a0a0f; --bg2: #0d0d18; --card: #1a1a2e; --card2: #16213e;
            --purple: #6c5ce7; --purple-light: #a29bfe; --purple-dark: #5a4fcf;
            --teal: #00d2d3; --teal-dark: #00b4b5;
            --text: #e2e8f0; --text2: #8892a4; --text3: #5a6178;
            --border: rgba(108,92,231,0.2); --border2: rgba(255,255,255,0.08);
            --success: #00b894; --warning: #fdcb6e; --danger: #e17055;
            --navbar-bg: rgba(10,10,15,0.92);
        }
        [data-theme="light"] {
            --bg: #f5f7fa; --bg2: #edf0f5; --card: #ffffff; --card2: #f0f2f7;
            --purple: #6c5ce7; --purple-light: #7c6ff0; --purple-dark: #5a4fcf;
            --teal: #00b4b5; --teal-dark: #009a9b;
            --text: #1a1a2e; --text2: #5a6178; --text3: #8892a4;
            --border: rgba(108,92,231,0.15); --border2: rgba(0,0,0,0.08);
            --success: #00b894; --warning: #f39c12; --danger: #e17055;
            --navbar-bg: rgba(245,247,250,0.92);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); line-height: 1.6; overflow-x: hidden; }
        a { text-decoration: none; color: inherit; }

        /* NAVBAR */
        .navbar { position: fixed; top: 0; left: 0; right: 0; z-index: 1000; background: var(--navbar-bg); backdrop-filter: blur(20px); border-bottom: 1px solid var(--border2); padding: 0 24px; }
        .navbar-inner { max-width: 1280px; margin: 0 auto; height: 60px; display: flex; align-items: center; gap: 28px; }
        .logo { display: flex; flex-direction: column; align-items: center; gap: 4px; line-height: 1.1; flex-shrink: 0; }
        .logo-mark { height: 40px; width: auto; flex-shrink: 0; filter: drop-shadow(0 4px 14px rgba(124, 58, 237, 0.35)); }
        .logo-text { display: flex; flex-direction: column; }
        .logo-name { font-size: 18px; font-weight: 800; background: linear-gradient(135deg, var(--purple), var(--teal)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .logo-slogan { display: none; }
        .nav-links { display: flex; align-items: center; gap: 2px; flex: 1; list-style: none; }
        .nav-links a { color: var(--text2); font-size: 13px; font-weight: 500; padding: 6px 12px; border-radius: 6px; transition: all 0.2s; }
        .nav-links a:hover { color: var(--text); background: rgba(108,92,231,0.08); }
        .nav-right { display: flex; align-items: center; gap: 10px; }
        .direct-badge { font-size: 11px; font-weight: 600; color: var(--teal); border: 1px solid rgba(0,210,211,0.3); padding: 4px 10px; border-radius: 20px; white-space: nowrap; display: flex; align-items: center; gap: 4px; }
        .direct-badge i { font-size: 9px; }
        .theme-toggle { background: rgba(255,255,255,0.06); border: 1px solid var(--border2); width: 34px; height: 34px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 14px; transition: all 0.2s; color: var(--text2); }
        .theme-toggle:hover { border-color: var(--purple); color: var(--text); }
        .btn-signin { background: transparent; border: 1px solid var(--border2); color: var(--text); padding: 6px 14px; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: pointer; transition: all 0.2s; font-family: inherit; }
        .btn-signin:hover { border-color: var(--purple); color: var(--purple); }
        .btn-signup { background: linear-gradient(135deg, var(--purple), var(--purple-dark)); border: none; color: #fff; padding: 7px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s; font-family: inherit; }
        .btn-signup:hover { transform: translateY(-1px); box-shadow: 0 4px 16px rgba(108,92,231,0.4); }

        /* Navbar: white copy on dark (default landing theme) */
        [data-theme="dark"] .navbar .logo-slogan { color: rgba(255, 255, 255, 0.82); }
        [data-theme="dark"] .navbar .nav-links a { color: #fff; }
        [data-theme="dark"] .navbar .nav-links a:hover { color: #fff; background: rgba(255, 255, 255, 0.1); }
        [data-theme="dark"] .navbar .theme-toggle { color: #fff; }
        [data-theme="dark"] .navbar .theme-toggle:hover { color: #fff; border-color: rgba(255, 255, 255, 0.45); }
        [data-theme="dark"] .navbar .btn-signin { color: #fff; border-color: rgba(255, 255, 255, 0.28); }
        [data-theme="dark"] .navbar .btn-signin:hover { color: #fff; border-color: rgba(255, 255, 255, 0.55); background: rgba(255, 255, 255, 0.06); }
        [data-theme="dark"] .navbar .lang-landing-details > summary { color: #fff; }
        [data-theme="dark"] .navbar .lang-landing-details > summary:hover { color: #fff; opacity: 0.92; }

        /* Navbar: readable copy on light bar */
        [data-theme="light"] .navbar .logo-slogan { color: #3d4556; }
        [data-theme="light"] .navbar .nav-links a { color: #1a1a2e; font-weight: 600; }
        [data-theme="light"] .navbar .nav-links a:hover { color: var(--purple-dark); background: rgba(108, 92, 231, 0.1); }
        [data-theme="light"] .navbar .theme-toggle {
            background: rgba(0, 0, 0, 0.04);
            border-color: rgba(0, 0, 0, 0.12);
            color: #1a1a2e;
        }
        [data-theme="light"] .navbar .theme-toggle:hover {
            color: var(--purple-dark);
            border-color: rgba(108, 92, 231, 0.45);
            background: rgba(108, 92, 231, 0.08);
        }
        [data-theme="light"] .navbar .btn-signin {
            color: #1a1a2e;
            border-color: rgba(0, 0, 0, 0.14);
        }
        [data-theme="light"] .navbar .btn-signin:hover {
            color: var(--purple-dark);
            border-color: var(--purple);
            background: rgba(108, 92, 231, 0.06);
        }
        [data-theme="light"] .navbar .lang-landing-details > summary { color: #1a1a2e; }
        [data-theme="light"] .navbar .lang-landing-details > summary:hover { color: var(--purple-dark); opacity: 1; }

        /* HERO + ORDER */
        .hero-order-section { padding: 72px 24px 20px; min-height: 75vh; display: flex; flex-direction: column; }
        .hero-compact { text-align: center; padding: 14px 0 16px; }
        .hero-compact h1 { font-size: clamp(24px, 4vw, 40px); font-weight: 900; line-height: 1.15; letter-spacing: -0.5px; background: linear-gradient(135deg, var(--text) 30%, var(--purple-light)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; margin-bottom: 4px; }
        .hero-compact h2 { font-size: clamp(16px, 2.5vw, 22px); font-weight: 700; color: var(--teal); margin-bottom: 6px; }
        .hero-compact p { font-size: 13px; color: var(--text2); max-width: 520px; margin: 0 auto; line-height: 1.5; }

        /* STEPS */
        .fast-order-wrap { flex: 1; max-width: 900px; margin: 0 auto; width: 100%; }
        .steps-indicator { display: flex; align-items: center; justify-content: center; gap: 0; margin-bottom: 14px; }
        .step-item { display: flex; align-items: center; gap: 6px; padding: 6px 10px; cursor: pointer; }
        .step-num { width: 24px; height: 24px; border-radius: 50%; background: rgba(255,255,255,0.06); color: var(--text3); font-size: 11px; font-weight: 700; display: flex; align-items: center; justify-content: center; transition: all 0.3s; }
        .step-item.active .step-num { background: var(--purple); color: #fff; }
        .step-item.done .step-num { background: var(--teal); color: #fff; }
        .step-label { font-size: 11px; font-weight: 500; color: var(--text3); }
        .step-item.active .step-label { color: var(--text); }
        .step-item.done .step-label { color: var(--teal); }
        .step-arrow { color: var(--text3); font-size: 10px; padding: 0 2px; }

        .order-step { background: var(--card); border: 1px solid var(--border2); border-radius: var(--radius); margin-bottom: 8px; overflow: hidden; transition: all 0.3s; }
        .order-step-header { padding: 14px 18px; display: flex; align-items: center; gap: 10px; cursor: pointer; }
        .order-step-num { width: 28px; height: 28px; border-radius: 50%; background: rgba(108,92,231,0.12); border: 2px solid rgba(108,92,231,0.25); color: var(--purple-light); font-weight: 700; font-size: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .order-step.active .order-step-num { background: var(--purple); border-color: var(--purple); color: #fff; }
        .order-step.done .order-step-num { background: var(--teal); border-color: var(--teal); color: #fff; }
        .order-step-title { font-weight: 600; font-size: 13px; }
        .order-step-summary { font-size: 12px; color: var(--teal); margin-left: auto; display: flex; align-items: center; gap: 4px; }
        .order-step-body { max-height: 0; overflow: hidden; transition: max-height 0.4s ease; }
        .order-step.active .order-step-body { max-height: 800px; }
        .order-step-inner { padding: 0 18px 18px; }

        /* Platform grid — center the row when fewer platforms than columns (avoid auto-fill empty tracks) */
        .platform-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(80px, max-content));
            justify-content: center;
            gap: 8px;
        }
        .platform-btn { background: rgba(255,255,255,0.03); border: 2px solid transparent; border-radius: 10px; padding: 10px 4px; text-align: center; cursor: pointer; transition: all 0.2s; display: flex; flex-direction: column; align-items: center; gap: 4px; }
        .platform-btn:hover { background: rgba(108,92,231,0.08); border-color: rgba(108,92,231,0.3); }
        .platform-btn.selected { background: rgba(108,92,231,0.12); border-color: var(--purple); }
        .platform-btn .icon { font-size: 22px; display: flex; align-items: center; justify-content: center; min-height: 22px; }
        .platform-btn .icon img.platform-icon-img { width: 22px; height: 22px; object-fit: contain; border-radius: 6px; display: block; }
        .platform-btn span { font-size: 10px; font-weight: 500; color: var(--text2); }
        .platform-btn.selected span { color: var(--text); }

        /* Services */
        .service-list { display: flex; flex-direction: column; gap: 6px; max-height: 400px; overflow-y: auto; }
        .service-card { background: rgba(255,255,255,0.02); border: 2px solid transparent; border-radius: 10px; padding: 12px 14px; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 10px; }
        .service-card:hover { background: rgba(108,92,231,0.06); border-color: rgba(108,92,231,0.2); }
        .service-card.selected { background: rgba(108,92,231,0.1); border-color: var(--purple); }
        .service-radio { width: 16px; height: 16px; border-radius: 50%; border: 2px solid var(--text3); flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
        .service-card.selected .service-radio { border-color: var(--purple); background: var(--purple); }
        .service-card.selected .service-radio::after { content: ''; width: 5px; height: 5px; border-radius: 50%; background: #fff; }
        .service-info { flex: 1; min-width: 0; }
        .service-name { font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .service-desc { font-size: 11px; color: var(--text2); }
        .service-meta { text-align: right; flex-shrink: 0; }
        .service-price { font-size: 13px; font-weight: 700; color: var(--teal); }
        .service-minmax { font-size: 10px; color: var(--text3); }

        /* Order form */
        .order-form { display: flex; flex-direction: column; gap: 12px; }
        .form-group { display: flex; flex-direction: column; gap: 4px; }
        .form-label { font-size: 12px; font-weight: 500; color: var(--text2); }
        .form-input { background: rgba(255,255,255,0.04); border: 1px solid var(--border2); border-radius: 8px; padding: 10px 14px; color: var(--text); font-size: 13px; font-family: inherit; outline: none; width: 100%; transition: border-color 0.2s; }
        .form-input:focus { border-color: var(--purple); }
        .form-input::placeholder { color: var(--text3); }
        .qty-row { display: flex; gap: 10px; align-items: center; }
        .qty-row .form-input { max-width: 160px; }
        .qty-hint { font-size: 11px; color: var(--text3); }
        .price-calculator { background: linear-gradient(135deg, rgba(108,92,231,0.08), rgba(0,210,211,0.06)); border: 1px solid rgba(108,92,231,0.15); border-radius: 10px; padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; }
        .price-label { font-size: 13px; color: var(--text2); }
        .price-total { font-size: 22px; font-weight: 800; background: linear-gradient(135deg, var(--purple), var(--teal)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .btn-complete { background: linear-gradient(135deg, var(--purple), var(--teal)); border: none; color: #fff; padding: 12px; border-radius: 10px; font-size: 14px; font-weight: 700; cursor: pointer; font-family: inherit; width: 100%; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-complete:hover { transform: translateY(-1px); box-shadow: 0 6px 24px rgba(108,92,231,0.4); }
        .btn-complete:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }

        /* STATS */
        .stats-bar { padding: 32px 24px; }
        .stats-bar-inner { max-width: 1100px; margin: 0 auto; display: flex; justify-content: center; gap: 12px; flex-wrap: wrap; }
        .stat-pill { background: var(--card); border: 1px solid var(--border2); border-radius: 12px; padding: 16px 24px; text-align: center; flex: 1; min-width: 140px; }
        .stat-value { font-size: 22px; font-weight: 800; background: linear-gradient(135deg, var(--purple), var(--teal)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .stat-label { font-size: 11px; color: var(--text2); margin-top: 2px; }

        /* SECTIONS */
        .section { padding: 48px 24px; }
        .section-inner { max-width: 1100px; margin: 0 auto; }
        .section-header { text-align: center; margin-bottom: 32px; }
        .section-tag { display: inline-block; background: rgba(108,92,231,0.12); border: 1px solid rgba(108,92,231,0.25); color: var(--purple-light); font-size: 11px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; padding: 4px 12px; border-radius: 100px; margin-bottom: 10px; }
        .section-title { font-size: clamp(22px, 3.5vw, 34px); font-weight: 800; letter-spacing: -0.3px; margin-bottom: 8px; }
        .section-subtitle { font-size: 14px; color: var(--text2); max-width: 500px; margin: 0 auto; }

        /* Features */
        .features-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 14px; }
        .feature-card { background: var(--card); border: 1px solid var(--border2); border-radius: var(--radius); padding: 22px; transition: all 0.2s; }
        .feature-card:hover { border-color: rgba(108,92,231,0.3); transform: translateY(-2px); }
        .feature-icon { width: 40px; height: 40px; border-radius: 10px; background: rgba(108,92,231,0.12); display: flex; align-items: center; justify-content: center; font-size: 16px; color: var(--purple-light); margin-bottom: 12px; }
        .feature-card h4 { font-size: 14px; font-weight: 700; margin-bottom: 6px; }
        .feature-card p { font-size: 13px; color: var(--text2); line-height: 1.5; }

        /* How It Works */
        .steps-visual { display: flex; justify-content: center; gap: 16px; flex-wrap: wrap; }
        .how-step { text-align: center; padding: 24px 16px; background: var(--card); border-radius: var(--radius); border: 1px solid var(--border2); flex: 1; min-width: 160px; max-width: 240px; }
        .how-step-icon { width: 52px; height: 52px; border-radius: 50%; background: linear-gradient(135deg, var(--purple), var(--teal)); color: #fff; font-size: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; }
        .how-step h4 { font-size: 14px; font-weight: 700; margin-bottom: 4px; }
        .how-step p { font-size: 12px; color: var(--text2); }
        .how-arrow { display: flex; align-items: center; color: var(--text3); font-size: 18px; }

        /* Testimonials */
        .testimonials-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 14px; }
        .testimonial-card { background: var(--card); border: 1px solid var(--border2); border-radius: var(--radius); padding: 20px; }
        .testimonial-stars { color: #f9a825; font-size: 12px; margin-bottom: 8px; }
        .testimonial-text { font-size: 13px; color: var(--text2); line-height: 1.6; margin-bottom: 12px; }
        .testimonial-author { display: flex; align-items: center; gap: 10px; }
        .testimonial-avatar { width: 34px; height: 34px; border-radius: 50%; background: linear-gradient(135deg, var(--purple), var(--teal)); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 12px; color: #fff; }
        .testimonial-name { font-size: 13px; font-weight: 600; }
        .testimonial-role { font-size: 11px; color: var(--text3); }

        /* FLOATING */
        .floating-btns { position: fixed; right: 20px; bottom: 24px; z-index: 900; display: flex; flex-direction: column; gap: 8px; }
        .float-btn { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 18px; border: none; transition: all 0.2s; box-shadow: 0 4px 20px rgba(0,0,0,0.3); }
        .float-btn:hover { transform: scale(1.1); }
        .float-btn-order { background: linear-gradient(135deg, var(--purple), var(--teal)); color: #fff; }
        .float-btn-support { background: #2aabee; color: #fff; }
        .float-btn-whatsapp { background: #25d366; color: #fff; }
        [x-cloak] { display: none !important; }


        /* FOOTER */
        footer { background: var(--card); border-top: 1px solid var(--border2); padding: 40px 24px 24px; }
        .footer-inner { max-width: 1100px; margin: 0 auto; }
        .footer-grid { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 40px; margin-bottom: 28px; }
        .footer-brand p { font-size: 13px; color: var(--text2); line-height: 1.6; margin: 10px 0 16px; }
        .footer-social { display: flex; gap: 8px; }
        .footer-social a { width: 34px; height: 34px; border-radius: 8px; background: rgba(255,255,255,0.04); border: 1px solid var(--border2); display: flex; align-items: center; justify-content: center; color: var(--text2); transition: all 0.2s; font-size: 14px; }
        .footer-social a:hover { color: var(--purple-light); border-color: var(--purple); }
        .footer-col h5 { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; color: var(--text2); margin-bottom: 12px; }
        .footer-col ul { list-style: none; }
        .footer-col ul li { margin-bottom: 8px; }
        .footer-col ul a { color: var(--text2); font-size: 13px; transition: color 0.2s; display: flex; align-items: center; gap: 6px; }
        .footer-col ul a:hover { color: var(--text); }
        .footer-bottom { border-top: 1px solid var(--border2); padding-top: 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .footer-copy { font-size: 12px; color: var(--text3); }

        /* Error */
        .form-error { color: var(--danger); font-size: 11px; margin-top: 2px; }

        /* Services Modal */
        .services-modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 2000; align-items: center; justify-content: center; padding: 24px; }
        .services-modal-overlay.show { display: flex; }
        .services-modal { background: var(--card); border: 1px solid var(--border2); border-radius: var(--radius-lg); max-width: 900px; width: 100%; max-height: 85vh; overflow: hidden; display: flex; flex-direction: column; }
        .services-modal-header { padding: 18px 22px; border-bottom: 1px solid var(--border2); display: flex; align-items: center; gap: 12px; }
        .services-modal-header h3 { font-size: 1rem; font-weight: 700; flex: 1; }
        .services-search { padding: 8px 12px 8px 32px; background: rgba(255,255,255,0.04); border: 1px solid var(--border2); border-radius: 6px; color: var(--text); font-family: inherit; font-size: 13px; outline: none; width: 250px; }
        .services-search:focus { border-color: var(--purple); }
        .services-search-wrap { position: relative; }
        .services-search-wrap i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--text3); font-size: 12px; }
        .svc-modal-close { background: none; border: 1px solid var(--border2); color: var(--text2); width: 30px; height: 30px; border-radius: 6px; cursor: pointer; font-size: 14px; display: flex; align-items: center; justify-content: center; }
        .svc-modal-close:hover { color: var(--text); border-color: var(--purple); }
        .services-modal-body { overflow-y: auto; flex: 1; }
        .svc-table { width: 100%; border-collapse: collapse; }
        .svc-table th { padding: 10px 14px; text-align: left; font-size: 11px; font-weight: 600; color: var(--text3); text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid var(--border2); position: sticky; top: 0; background: var(--card); }
        .svc-table td { padding: 10px 14px; font-size: 13px; border-bottom: 1px solid var(--border2); }
        .svc-table td img.platform-icon-img { width: 20px; height: 20px; object-fit: contain; border-radius: 4px; vertical-align: middle; margin-right: 6px; display: inline-block; }
        .svc-table tr:hover { background: rgba(108,92,231,0.04); }
        .svc-order-btn { padding: 4px 12px; background: var(--purple); border: none; color: #fff; border-radius: 6px; font-size: 11px; font-weight: 600; cursor: pointer; font-family: inherit; transition: all 0.2s; }
        .svc-order-btn:hover { background: var(--purple-light); }
        .svc-rate { font-weight: 700; color: var(--teal); }

        /* MOBILE MENU */
        .mobile-menu-btn { display: none; background: none; border: 1px solid var(--border2); color: var(--text); width: 36px; height: 36px; border-radius: 8px; cursor: pointer; align-items: center; justify-content: center; font-size: 16px; transition: all 0.2s; }
        .mobile-menu-btn:hover { border-color: var(--purple); color: var(--purple); }
        .mobile-menu-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 1100; }
        .mobile-menu-overlay.show { display: block; }
        .mobile-menu {
            position: fixed; top: 0; right: -280px; width: 280px; height: 100vh; z-index: 1200;
            background: var(--card); border-left: 1px solid var(--border2);
            display: flex; flex-direction: column; transition: right 0.3s ease;
            box-shadow: -4px 0 24px rgba(0,0,0,0.3);
        }
        .mobile-menu.show { right: 0; }
        .mobile-menu-header { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; border-bottom: 1px solid var(--border2); }
        .mobile-menu-close { background: none; border: 1px solid var(--border2); color: var(--text2); width: 32px; height: 32px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 14px; }
        .mobile-menu-close:hover { color: var(--text); border-color: var(--purple); }
        .mobile-menu-links { list-style: none; padding: 12px 0; flex: 1; overflow-y: auto; }
        .mobile-menu-links li { margin: 0; }
        .mobile-menu-links a { display: flex; align-items: center; gap: 10px; padding: 14px 20px; color: var(--text); font-size: 14px; font-weight: 500; transition: background 0.15s; }
        .mobile-menu-links a:hover { background: rgba(108,92,231,0.08); }
        .mobile-menu-links a i { width: 20px; text-align: center; color: var(--purple-light); font-size: 14px; }
        .mobile-menu-actions { padding: 16px 20px; border-top: 1px solid var(--border2); display: flex; flex-direction: column; gap: 10px; }
        .mobile-menu-actions .btn-signup { text-align: center; justify-content: center; padding: 10px; font-size: 14px; }
        .mobile-menu-actions .btn-signin { text-align: center; padding: 10px; font-size: 14px; display: block !important; }

        /* ===== RESPONSIVE ===== */

        /* LAPTOP / SMALL DESKTOP (≤1024px) */
        @media (max-width: 1024px) {
            .navbar-inner { gap: 16px; }
            .nav-links a { font-size: 12px; padding: 5px 8px; }
            .direct-badge { display: none; }
            .footer-grid { gap: 24px; }
            .features-grid { grid-template-columns: repeat(2, 1fr); }
            .testimonials-grid { grid-template-columns: repeat(2, 1fr); }
            .services-modal { max-width: 95vw; }
        }

        /* TABLET (≤768px) */
        @media (max-width: 768px) {
            .nav-links, .direct-badge { display: none; }
            .mobile-menu-btn { display: flex; }
            .logo-slogan { display: none; }
            .navbar-inner { gap: 12px; }
            .navbar { padding: 0 16px; }
            .nav-right { gap: 8px; margin-left: auto; }
            .nav-right .btn-signup { padding: 6px 12px; font-size: 12px; }
            .nav-right .btn-signin { padding: 6px 12px; font-size: 12px; }

            .hero-order-section { padding: 68px 16px 16px; min-height: auto; }
            .hero-compact { padding: 10px 0 12px; }
            .hero-compact p { font-size: 12px; }

            .order-step-header { padding: 12px 14px; }
            .order-step-inner { padding: 0 14px 14px; }
            .platform-grid { grid-template-columns: repeat(auto-fit, minmax(72px, max-content)); justify-content: center; }

            .stats-bar { padding: 20px 16px; }
            .stats-bar-inner { gap: 8px; }
            .stat-pill { min-width: 0; flex: 1 1 calc(33% - 8px); padding: 12px 8px; }
            .stat-value { font-size: 18px; }
            .stat-label { font-size: 10px; }

            .section { padding: 32px 16px; }
            .section-header { margin-bottom: 20px; }
            .features-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .feature-card { padding: 16px; }
            .feature-card h4 { font-size: 13px; }
            .feature-card p { font-size: 12px; }

            .how-arrow { display: none; }
            .how-step { min-width: 0; max-width: none; flex: 1 1 calc(33% - 12px); padding: 18px 12px; }
            .how-step-icon { width: 44px; height: 44px; font-size: 18px; }

            .testimonials-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
            .testimonial-card { padding: 16px; }

            .footer-grid { grid-template-columns: 1fr 1fr; gap: 20px; }
            .footer-brand { grid-column: 1 / -1; }

            .services-modal-overlay { padding: 12px; }
            .services-modal { border-radius: var(--radius); max-height: 90vh; }
            .services-modal-header { padding: 14px 16px; flex-wrap: wrap; }
            .services-search { width: 100%; }

            .floating-btns { right: 14px; bottom: 18px; }
            .float-btn { width: 46px; height: 46px; font-size: 16px; }
        }

        /* SMALL MOBILE (≤480px) */
        @media (max-width: 480px) {
            .navbar { padding: 0 12px; }
            .navbar-inner { height: 54px; gap: 8px; }
            .logo-mark { height: 32px; }
            .nav-right .btn-signup { padding: 5px 10px; font-size: 11px; }
            .theme-toggle { width: 30px; height: 30px; font-size: 12px; }
            .mobile-menu-btn { width: 32px; height: 32px; font-size: 14px; }

            .hero-order-section { padding: 60px 12px 12px; }
            .hero-compact h1 { font-size: 20px; }
            .hero-compact h2 { font-size: 14px; }
            .hero-compact p { font-size: 11px; }

            .steps-indicator { gap: 0; }
            .step-item { padding: 4px 6px; gap: 4px; }
            .step-num { width: 22px; height: 22px; font-size: 10px; }
            .step-label { font-size: 10px; }

            .order-step-header { padding: 10px 12px; gap: 8px; }
            .order-step-num { width: 24px; height: 24px; font-size: 11px; }
            .order-step-title { font-size: 12px; }
            .order-step-summary { font-size: 11px; }
            .order-step-inner { padding: 0 12px 12px; }
            .order-step.active .order-step-body { max-height: 1000px; }

            .platform-grid { grid-template-columns: repeat(3, 1fr); gap: 6px; }
            .platform-btn { padding: 8px 4px; border-radius: 8px; }
            .platform-btn .icon { font-size: 20px; }
            .platform-btn span { font-size: 9px; }

            .service-card { padding: 10px 12px; gap: 8px; }
            .service-name { font-size: 12px; white-space: normal; }
            .service-price { font-size: 12px; }
            .service-minmax { font-size: 9px; }

            .form-input { padding: 10px 12px; font-size: 14px; }
            .qty-row { flex-direction: column; align-items: stretch; gap: 6px; }
            .qty-row .form-input { max-width: 100%; }
            .price-calculator { padding: 10px 12px; }
            .price-total { font-size: 20px; }
            .btn-complete { padding: 14px; font-size: 14px; }

            .stats-bar { padding: 16px 12px; }
            .stat-pill { flex: 1 1 calc(50% - 6px); min-width: 0; padding: 10px 6px; border-radius: 10px; }
            .stat-value { font-size: 16px; }
            .stat-label { font-size: 9px; }

            .section { padding: 28px 12px; }
            .section-tag { font-size: 10px; padding: 3px 10px; }
            .section-title { margin-bottom: 6px; }
            .section-subtitle { font-size: 13px; }
            .features-grid { grid-template-columns: 1fr; gap: 8px; }
            .feature-card { padding: 16px; }
            .feature-icon { width: 36px; height: 36px; font-size: 14px; margin-bottom: 10px; }

            .steps-visual { gap: 8px; }
            .how-step { flex: 1 1 100%; max-width: none; padding: 16px 12px; }

            .testimonials-grid { grid-template-columns: 1fr; gap: 8px; }
            .testimonial-card { padding: 14px; }
            .testimonial-text { font-size: 12px; margin-bottom: 10px; }

            footer { padding: 28px 12px 16px; }
            .footer-grid { grid-template-columns: 1fr 1fr; gap: 16px; }
            .footer-brand { text-align: center; }
            .footer-social { justify-content: center; }
            .footer-bottom { flex-direction: column; align-items: center; text-align: center; }

            .floating-btns { right: 12px; bottom: 14px; }
            .float-btn { width: 44px; height: 44px; font-size: 16px; }

            .services-modal-overlay { padding: 0; }
            .services-modal { border-radius: 0; max-height: 100vh; height: 100vh; }
            .services-modal-header { padding: 12px 14px; }
            .services-modal-header h3 { font-size: 14px; }
            .svc-table th, .svc-table td { padding: 8px 10px; font-size: 12px; }
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

<!-- NAVBAR -->
<nav class="navbar">
    <div class="navbar-inner">
      <a href="/" class="logo">
        <img src="{{ asset('images/adtag_fav.png') }}" alt="{{ config('app.name', 'SMM Tool') }}" class="logo-mark">
        <span class="logo-slogan">Social Media Growth Tool</span>
      </a>
      <ul class="nav-links">
        <li><a href="#" onclick="openServicesModal(); return false;">{{ __('Services') }}</a></li>
        <li><a href="#how-it-works">{{ __('How It Works') }}</a></li>
        <li><a href="#why-us">{{ __('Why Choose Us') }}</a></li>
        <li><a href="{{ route('contacts') }}">{{ __('Support') }}</a></li>
      </ul>
      <div class="nav-right">
        <span class="direct-badge"><i class="fa-solid fa-check-circle"></i> {{ __('home.direct_provider_badge') }}</span>
        <button class="theme-toggle" id="themeToggle" onclick="toggleTheme()" title="{{ __('Toggle theme') }}"><i class="fa-solid fa-moon" id="themeIcon"></i></button>
        <x-language-dropdown variant="landing" />
        @auth('client')
          <a href="{{ route('client.orders.index') }}" class="btn-signup">{{ __('Services') }}</a>
        @else
          <a href="{{ route('login') }}" class="btn-signin">{{ __('home.sign_in') }}</a>
          <a href="{{ route('register') }}" class="btn-signup">{{ __('home.sign_up') }}</a>
        @endauth
        <button class="mobile-menu-btn" onclick="openMobileMenu()" aria-label="Menu"><i class="fa-solid fa-bars"></i></button>
      </div>
    </div>
</nav>

<!-- MOBILE MENU -->
<div class="mobile-menu-overlay" id="mobileOverlay" onclick="closeMobileMenu()"></div>
<div class="mobile-menu" id="mobileMenu">
    <div class="mobile-menu-header">
        <a href="/" class="logo" style="text-decoration:none;">
            <img src="{{ asset('images/adtag_fav.png') }}" alt="{{ config('app.name') }}" style="height:32px;width:auto;">
        </a>
        <button class="mobile-menu-close" onclick="closeMobileMenu()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <ul class="mobile-menu-links">
        <li><a href="#" onclick="openServicesModal();closeMobileMenu();return false;"><i class="fa-solid fa-list"></i> {{ __('Services') }}</a></li>
        <li><a href="#how-it-works" onclick="closeMobileMenu()"><i class="fa-solid fa-circle-info"></i> {{ __('How It Works') }}</a></li>
        <li><a href="#why-us" onclick="closeMobileMenu()"><i class="fa-solid fa-star"></i> {{ __('Why Choose Us') }}</a></li>
        <li><a href="{{ route('contacts') }}"><i class="fa-brands fa-telegram"></i> {{ __('Support') }}</a></li>
    </ul>
    <div class="mobile-menu-actions">
        @auth('client')
            <a href="{{ route('client.orders.index') }}" class="btn-signup">{{ __('Services') }}</a>
        @else
            <a href="{{ route('register') }}" class="btn-signup">{{ __('home.sign_up') }}</a>
            <a href="{{ route('login') }}" class="btn-signin">{{ __('home.sign_in') }}</a>
        @endauth
    </div>
</div>

<!-- HERO + ORDER -->
<section class="hero-order-section">
    <div class="hero-compact">
        <h1 id="heroTitle">{{ __('home.smm_services_headline') }}</h1>
        <h2>{{ __('home.hero_headline_sub') }}</h2>
        <p>{{ __('home.hero_intro') }}</p>
    </div>

    <div class="fast-order-wrap">
        <!-- Steps indicator -->
        <div class="steps-indicator">
            <div class="step-item active" data-step="1"><div class="step-num">1</div><div class="step-label">{{ __('home.platform_step') }}</div></div>
            <div class="step-arrow"><i class="fa-solid fa-chevron-right"></i></div>
            <div class="step-item" data-step="2"><div class="step-num">2</div><div class="step-label">{{ __('home.service_step') }}</div></div>
            <div class="step-arrow"><i class="fa-solid fa-chevron-right"></i></div>
            <div class="step-item" data-step="3"><div class="step-num">3</div><div class="step-label">{{ __('home.order_step') }}</div></div>
        </div>

        <!-- Step 1: Platform (= Category) -->
        <div class="order-step active" id="step-1">
            <div class="order-step-header" onclick="goToStep(1)">
                <div class="order-step-num">1</div>
                <div class="order-step-title">{{ __('home.choose_platform') }}</div>
                <div class="order-step-summary" id="step1-summary" style="display:none"><i class="fa-solid fa-check-circle"></i> <span id="step1-text"></span></div>
            </div>
            <div class="order-step-body">
                <div class="order-step-inner">
                    <div class="platform-grid" id="platformGrid"></div>
                </div>
            </div>
        </div>

        <!-- Step 2: Service -->
        <div class="order-step" id="step-2">
            <div class="order-step-header" onclick="goToStep(2)">
                <div class="order-step-num">2</div>
                <div class="order-step-title">{{ __('home.select_service_step') }}</div>
                <div class="order-step-summary" id="step2-summary" style="display:none"><i class="fa-solid fa-check-circle"></i> <span id="step2-text"></span></div>
            </div>
            <div class="order-step-body">
                <div class="order-step-inner">
                    <div class="service-list" id="serviceList"></div>
                </div>
            </div>
        </div>

        <!-- Step 3: Order -->
        <div class="order-step" id="step-3">
            <div class="order-step-header" onclick="goToStep(3)">
                <div class="order-step-num">3</div>
                <div class="order-step-title">{{ __('home.complete_order_step') }}</div>
            </div>
            <div class="order-step-body">
                <div class="order-step-inner">
                    <div class="order-form" id="orderForm">
                        <input type="hidden" id="formServiceId">
                        <input type="hidden" id="formCategoryId">
                        <div class="form-group">
                            <label class="form-label">{{ __('home.your_link') }}</label>
                            <input class="form-input" type="text" id="orderLink" placeholder="{{ __('home.paste_link_placeholder') }}">
                            <div class="form-error" id="linkError"></div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">{{ __('Quantity') }}</label>
                            <div class="qty-row">
                                <input class="form-input" type="number" id="orderQty" placeholder="1000" min="100">
                                <span class="qty-hint" id="qtyHint">Min: 100 &middot; Max: 100,000</span>
                            </div>
                            <div class="form-error" id="qtyError"></div>
                        </div>
                        <div class="price-calculator">
                            <div>
                                <div class="price-label">{{ __('home.total_price_label') }}</div>
                                <div style="font-size:11px;color:var(--text3);margin-top:1px" id="priceBreakdown">&mdash;</div>
                            </div>
                            <div class="price-total" id="priceTotal">$0.00</div>
                        </div>
                        <button type="button" class="btn-complete" id="btnComplete" disabled onclick="submitFastOrder()">
                            <i class="fa-solid fa-lock"></i> {{ __('home.complete_order_button') }}
                        </button>
                        <div class="form-error" id="orderError" style="text-align:center;margin-top:8px"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- STATS -->
<div class="stats-bar">
    <div class="stats-bar-inner">
        <div class="stat-pill"><div class="stat-value">2B+</div><div class="stat-label">{{ __('Completions') }}</div></div>
        <div class="stat-pill"><div class="stat-value">2M+</div><div class="stat-label">{{ __('Orders Completed') }}</div></div>
        <div class="stat-pill"><div class="stat-value">{{ __('home.stat_years_value') }}</div><div class="stat-label">{{ __('In the Market') }}</div></div>
        <div class="stat-pill"><div class="stat-value">24/7</div><div class="stat-label">{{ __('Support') }}</div></div>
        <div class="stat-pill"><div class="stat-value">200K+</div><div class="stat-label">{{ __('Happy Clients') }}</div></div>
    </div>
</div>

<!-- WHY CHOOSE US -->
<section class="section" id="why-us">
    <div class="section-inner">
        <div class="section-header">
            <div class="section-tag">{{ __('home.why_us_tag') }}</div>
            <h2 class="section-title">{{ __('home.why_us_title') }}</h2>
            <p class="section-subtitle">{{ __('home.why_us_subtitle') }}</p>
        </div>
        <div class="features-grid">
            <div class="feature-card"><div class="feature-icon"><i class="fa-solid fa-bolt"></i></div><h4>{{ __('home.why_us_feat_instant_title') }}</h4><p>{{ __('home.why_us_feat_instant_desc') }}</p></div>
            <div class="feature-card"><div class="feature-icon"><i class="fa-solid fa-shield-halved"></i></div><h4>{{ __('home.why_us_feat_safe_title') }}</h4><p>{{ __('home.why_us_feat_safe_desc') }}</p></div>
            <div class="feature-card"><div class="feature-icon" style="background:rgba(0,210,211,0.1)"><i class="fa-solid fa-dollar-sign" style="color:var(--teal)"></i></div><h4>{{ __('home.why_us_feat_prices_title') }}</h4><p>{{ __('home.why_us_feat_prices_desc') }}</p></div>
            <div class="feature-card"><div class="feature-icon"><i class="fa-solid fa-headset"></i></div><h4>{{ __('home.why_us_feat_support_title') }}</h4><p>{{ __('home.why_us_feat_support_desc') }}</p></div>
            <div class="feature-card"><div class="feature-icon" style="background:rgba(0,210,211,0.1)"><i class="fa-solid fa-globe" style="color:var(--teal)"></i></div><h4>{{ __('home.why_us_feat_platforms_title') }}</h4><p>{{ __('home.why_us_feat_platforms_desc') }}</p></div>
            <div class="feature-card"><div class="feature-icon"><i class="fa-solid fa-code"></i></div><h4>{{ __('home.why_us_feat_api_title') }}</h4><p>{{ __('home.why_us_feat_api_desc') }}</p></div>
        </div>
    </div>
</section>

<!-- HOW IT WORKS -->
<section class="section" id="how-it-works">
    <div class="section-inner">
        <div class="section-header">
            <div class="section-tag">{{ __('How It Works') }}</div>
            <h2 class="section-title">{{ __('3 Simple Steps') }}</h2>
        </div>
        <div class="steps-visual">
            <div class="how-step"><div class="how-step-icon"><i class="fa-solid fa-mobile-screen"></i></div><h4>{{ __('home.choose_platform') }}</h4><p>{{ __('home.pick_platform_desc') }}</p></div>
            <div class="how-arrow"><i class="fa-solid fa-arrow-right"></i></div>
            <div class="how-step"><div class="how-step-icon"><i class="fa-solid fa-list"></i></div><h4>{{ __('home.pick_service') }}</h4><p>{{ __('home.select_service_desc') }}</p></div>
            <div class="how-arrow"><i class="fa-solid fa-arrow-right"></i></div>
            <div class="how-step"><div class="how-step-icon"><i class="fa-solid fa-rocket"></i></div><h4>{{ __('home.get_results') }}</h4><p>{{ __('home.get_results_desc') }}</p></div>
        </div>
    </div>
</section>

<!-- TESTIMONIALS -->
<section class="section" id="testimonials">
    <div class="section-inner">
      <div class="section-header">
        <div class="section-tag">{{ __('home.testimonials_tag') }}</div>
        <h2 class="section-title">{{ __('home.testimonials_title') }}</h2>
      </div>
      <div class="testimonials-grid">
        @foreach(range(1, 6) as $ti)
        <div class="testimonial-card">
          <div class="testimonial-stars">{!! $ti === 4 ? '&#9733;&#9733;&#9733;&#9733;&#9734;' : '&#9733;&#9733;&#9733;&#9733;&#9733;' !!}</div>
          <p class="testimonial-text">{{ __('home.testimonial_'.$ti.'_text') }}</p>
          <div class="testimonial-author">
            <div class="testimonial-avatar">{{ __('home.testimonial_'.$ti.'_avatar') }}</div>
            <div><div class="testimonial-name">{{ __('home.testimonial_'.$ti.'_name') }}</div><div class="testimonial-role">{{ __('home.testimonial_'.$ti.'_role') }}</div></div>
          </div>
        </div>
        @endforeach
      </div>
    </div>
</section>

<!-- FOOTER -->
<footer>
    <div class="footer-inner">
      <div class="footer-grid">
        <div class="footer-brand">
          <a href="/" class="logo">
            <img src="{{ asset('images/adtag_fav.png') }}" alt="{{ config('app.name', 'SMM Tool') }}" class="logo-mark" style="height:44px;width:auto">
            <span class="logo-slogan">Social Media Growth Tool</span>
          </a>
          <p>{{ __('home.footer_brand_blurb') }}</p>
          <div class="footer-social">
            @if($contactTelegram)
              <a href="https://t.me/{{ $contactTelegram }}" target="_blank" rel="noopener noreferrer" title="{{ __('home.footer_telegram') }}"><i class="fa-brands fa-telegram"></i></a>
            @endif
            <a href="https://wa.me/37495189233" target="_blank" rel="noopener noreferrer" title="{{ __('home.footer_whatsapp') }}"><i class="fa-brands fa-whatsapp"></i></a>
          </div>
        </div>
        <div class="footer-col">
          <h5>{{ __('home.footer_col_company') }}</h5>
          <ul>
            <li><a href="#" onclick="openServicesModal(); return false;"><i class="fa-solid fa-list"></i> {{ __('home.footer_services') }}</a></li>
            <li><a href="#why-us"><i class="fa-solid fa-building"></i> {{ __('home.footer_about') }}</a></li>
            <li><a href="#"><i class="fa-solid fa-pen"></i> {{ __('home.footer_blog') }}</a></li>
            <li><a href="#"><i class="fa-solid fa-code"></i> {{ __('home.footer_api') }}</a></li>
          </ul>
        </div>
        <div class="footer-col">
          <h5>{{ __('home.footer_col_support') }}</h5>
          <ul>
            @if($contactTelegram)
              <li><a href="https://t.me/{{ $contactTelegram }}" target="_blank" rel="noopener noreferrer"><i class="fa-brands fa-telegram"></i> {{ __('home.footer_telegram') }}</a></li>
            @endif
            <li><a href="https://wa.me/37495189233" target="_blank" rel="noopener noreferrer"><i class="fa-brands fa-whatsapp"></i> {{ __('home.footer_whatsapp') }}</a></li>
            <li><a href="#"><i class="fa-solid fa-file-contract"></i> {{ __('home.footer_terms') }}</a></li>
            <li><a href="#"><i class="fa-solid fa-shield-halved"></i> {{ __('home.footer_privacy_footer') }}</a></li>
          </ul>
        </div>
      </div>
      <div class="footer-bottom">
        <div class="footer-copy">{{ __('home.footer_copyright_line', ['year' => date('Y'), 'name' => config('app.name', 'SMM Tool'), 'powered' => config('app.name', 'SMM Tool')]) }}</div>
        <div class="footer-legal">
          <a href="#">{{ __('home.footer_privacy_footer') }}</a>
          <a href="#">{{ __('home.footer_cookies_link') }}</a>
        </div>
      </div>
    </div>
</footer>

<!-- SERVICES MODAL -->
<div class="services-modal-overlay" id="servicesModal" onclick="if(event.target===this)closeServicesModal()">
    <div class="services-modal">
      <div class="services-modal-header">
        <h3><i class="fa-solid fa-list" style="color:var(--purple-light);margin-right:6px"></i> {{ __('All Services') }}</h3>
        <div class="services-search-wrap"><i class="fa-solid fa-search"></i><input type="text" class="services-search" id="svcModalSearch" placeholder="{{ __('Search services...') }}" oninput="filterServicesModal(this.value)"></div>
        <button class="svc-modal-close" onclick="closeServicesModal()">&times;</button>
      </div>
      <div class="services-modal-body">
        <table class="svc-table">
          <thead><tr><th>{{ __('Platform') }}</th><th>{{ __('Service') }}</th><th>{{ __('Rate/1K') }}</th><th>{{ __('Min') }}</th><th>{{ __('Max') }}</th><th></th></tr></thead>
          <tbody id="svcModalBody"></tbody>
        </table>
      </div>
    </div>
</div>

<!-- FLOATING BUTTONS -->
<div class="floating-btns">
    <a href="https://wa.me/37495189233" target="_blank" rel="noopener noreferrer" class="float-btn float-btn-whatsapp" title="WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
    <x-telegram-support-picker variant="float">
        <button type="button" x-on:click="open = !open" class="float-btn float-btn-support" title="{{ __('home.float_support_title') }}"><i class="fa-brands fa-telegram"></i></button>
    </x-telegram-support-picker>
</div>

@if($errors->any())
    <div style="position:fixed;top:70px;right:20px;z-index:9999;background:var(--danger);color:#fff;padding:12px 20px;border-radius:8px;font-size:13px;max-width:400px;">
        @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

@php
$homeI18n = [
    'noServices' => __('home.js_no_services'),
    'qtyBetween' => __('home.js_qty_between'),
    'qtyHint' => __('home.qty_hint_template'),
    'pasteLinkPlaceholder' => __('home.paste_link_placeholder'),
    'priceBreakdown' => __('home.price_breakdown_template'),
    'noServicesModal' => __('home.js_no_services_modal'),
    'order' => __('home.js_order'),
    'processing' => __('home.js_processing'),
    'completeOrder' => __('home.complete_order_button'),
    'orderFailed' => __('home.js_order_failed'),
    'noUuid' => __('home.js_no_uuid'),
    'checkoutFailed' => __('home.js_checkout_failed'),
    'simulateFailed' => __('home.js_simulate_failed'),
    'noLoginUrl' => __('home.js_no_login_url'),
    'paymentFailed' => __('home.js_payment_failed'),
];
@endphp
<script>
const HOME_I18N = @json($homeI18n);
const CATEGORIES = @json($categoriesForJs);
let state = { categoryId: null, serviceId: null, currentStep: 1 };

function escapeHtml(text) {
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}

// Platform icon map by linkType
const PLATFORM_ICONS = {
    'telegram': '<i class="fa-brands fa-telegram" style="color:#2aabee"></i>',
    'youtube': '<i class="fa-brands fa-youtube" style="color:#ff0000"></i>',
    'instagram': '<i class="fa-brands fa-instagram" style="color:#e1306c"></i>',
    'tiktok': '<i class="fa-brands fa-tiktok" style="color:#69c9d0"></i>',
    'facebook': '<i class="fa-brands fa-facebook" style="color:#1877f2"></i>',
    'twitter': '<i class="fa-brands fa-x-twitter" style="color:#e7e9ea"></i>',
    'discord': '<i class="fa-brands fa-discord" style="color:#5865f2"></i>',
    'spotify': '<i class="fa-brands fa-spotify" style="color:#1db954"></i>',
    'twitch': '<i class="fa-brands fa-twitch" style="color:#9146ff"></i>',
    'vk': '<i class="fa-brands fa-vk" style="color:#4680c2"></i>',
    'whatsapp': '<i class="fa-brands fa-whatsapp" style="color:#25d366"></i>',
    'max': '<img src="{{ asset("images/max-icon.png") }}" alt="Max" class="platform-icon-img" width="22" height="22" loading="lazy" decoding="async">',
    'app': '<i class="fa-solid fa-mobile-screen" style="color:#a29bfe"></i>',
};

function getPlatformIcon(cat) {
    const raw = cat.icon && String(cat.icon).trim();
    if (raw) {
        if (raw.includes('<')) {
            return raw;
        }
        if (/^https?:\/\//i.test(raw)) {
            const safe = raw.replace(/"/g, '&quot;');
            return `<img src="${safe}" alt="" class="platform-icon-img" width="22" height="22" loading="lazy" decoding="async" referrerpolicy="no-referrer">`;
        }
    }
    return PLATFORM_ICONS[cat.linkType] || '<i class="fa-solid fa-globe" style="color:var(--purple-light)"></i>';
}

function renderPlatforms() {
    const grid = document.getElementById('platformGrid');
    grid.innerHTML = CATEGORIES.map(c =>
        `<div class="platform-btn" data-id="${c.id}" onclick="selectPlatform(${c.id})">
            <div class="icon">${getPlatformIcon(c)}</div>
            <span>${c.name}</span>
        </div>`
    ).join('');
}

function selectPlatform(id) {
    state.categoryId = id;
    state.serviceId = null;
    const cat = CATEGORIES.find(c => c.id === id);
    document.querySelectorAll('.platform-btn').forEach(b => b.classList.toggle('selected', +b.dataset.id === id));
    document.getElementById('step1-text').textContent = cat ? cat.name : '';
    document.getElementById('step1-summary').style.display = 'flex';
    renderServices(id);
    goToStep(2);
}

function renderServices(categoryId) {
    const cat = CATEGORIES.find(c => c.id === categoryId);
    const list = document.getElementById('serviceList');
    if (!cat || !cat.services.length) {
        list.innerHTML = '<div style="padding:16px;color:var(--text3);text-align:center">' + escapeHtml(HOME_I18N.noServices) + '</div>';
        return;
    }
    list.innerHTML = cat.services.map(s =>
        `<div class="service-card" data-id="${s.id}" onclick="selectService(${categoryId}, ${s.id})">
            <div class="service-radio"></div>
            <div class="service-info">
                <div class="service-name">${s.name}</div>
            </div>
            <div class="service-meta">
                <div class="service-price">$${s.pricePer1000.toFixed(2)}</div>
                <div class="service-minmax">${s.min.toLocaleString()} - ${s.max.toLocaleString()}</div>
            </div>
        </div>`
    ).join('');
}

function selectService(categoryId, serviceId) {
    state.categoryId = categoryId;
    state.serviceId = serviceId;
    const cat = CATEGORIES.find(c => c.id === categoryId);
    const svc = cat ? cat.services.find(s => s.id === serviceId) : null;
    document.querySelectorAll('.service-card').forEach(c => c.classList.toggle('selected', +c.dataset.id === serviceId));
    document.getElementById('step2-text').textContent = svc ? svc.name : '';
    document.getElementById('step2-summary').style.display = 'flex';

    if (svc) {
        document.getElementById('formServiceId').value = svc.id;
        document.getElementById('formCategoryId').value = categoryId;
        document.getElementById('orderQty').min = svc.min;
        document.getElementById('orderQty').max = svc.max;
        document.getElementById('orderQty').step = svc.step || 1;
        document.getElementById('orderQty').value = svc.min;
        document.getElementById('orderQty').placeholder = svc.min;
        document.getElementById('qtyHint').textContent = HOME_I18N.qtyHint.replace(':min', svc.min.toLocaleString()).replace(':max', svc.max.toLocaleString());
        document.getElementById('orderLink').placeholder = svc.placeholder || HOME_I18N.pasteLinkPlaceholder;
        updatePrice();
    }
    goToStep(3);
}

function goToStep(step) {
    if (step === 2 && !state.categoryId) return;
    if (step === 3 && !state.serviceId) return;
    state.currentStep = step;

    document.querySelectorAll('.order-step').forEach((el, i) => {
        el.classList.remove('active', 'done');
        if (i + 1 < step) el.classList.add('done');
        if (i + 1 === step) el.classList.add('active');
    });

    document.querySelectorAll('.step-item').forEach(el => {
        const s = +el.dataset.step;
        el.classList.remove('active', 'done');
        if (s < step) el.classList.add('done');
        if (s === step) el.classList.add('active');
    });
}

function updatePrice() {
    const cat = CATEGORIES.find(c => c.id === state.categoryId);
    const svc = cat ? cat.services.find(s => s.id === state.serviceId) : null;
    const qty = parseInt(document.getElementById('orderQty').value) || 0;
    const total = svc ? (qty / 1000 * svc.pricePer1000) : 0;
    document.getElementById('priceTotal').textContent = '$' + total.toFixed(2);
    document.getElementById('priceBreakdown').textContent = svc
        ? HOME_I18N.priceBreakdown.replace(':price', '$' + svc.pricePer1000.toFixed(2)).replace(':qty', qty.toLocaleString())
        : '—';
    validate();
}

function validate() {
    const link = document.getElementById('orderLink').value.trim();
    const qty = parseInt(document.getElementById('orderQty').value) || 0;
    const cat = CATEGORIES.find(c => c.id === state.categoryId);
    const svc = cat ? cat.services.find(s => s.id === state.serviceId) : null;
    let valid = true;

    document.getElementById('linkError').textContent = '';
    document.getElementById('qtyError').textContent = '';

    if (!link) valid = false;
    if (svc && (qty < svc.min || qty > svc.max)) {
        document.getElementById('qtyError').textContent = HOME_I18N.qtyBetween.replace(':min', svc.min.toLocaleString()).replace(':max', svc.max.toLocaleString());
        valid = false;
    }
    if (!state.serviceId) valid = false;

    document.getElementById('btnComplete').disabled = !valid;
    return valid;
}

document.getElementById('orderQty').addEventListener('input', updatePrice);
document.getElementById('orderLink').addEventListener('input', validate);

// Theme
function toggleTheme() {
    const html = document.documentElement;
    const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    localStorage.setItem('smm-theme', next);
    document.getElementById('themeIcon').className = next === 'dark' ? 'fa-solid fa-moon' : 'fa-solid fa-sun';
}
(function() {
    const saved = localStorage.getItem('smm-theme');
    if (saved === 'light' || saved === 'dark') {
        document.documentElement.setAttribute('data-theme', saved);
        if (saved === 'light') document.getElementById('themeIcon').className = 'fa-solid fa-sun';
    }
})();

// Services modal
function buildServicesTable(filter) {
    const tbody = document.getElementById('svcModalBody');
    const q = (filter || '').toLowerCase();
    let html = '';
    CATEGORIES.forEach(cat => {
        cat.services.forEach(svc => {
            const text = (cat.name + ' ' + svc.name + ' ' + (svc.description || '')).toLowerCase();
            if (q && !text.includes(q)) return;
            html += `<tr>
                <td>${getPlatformIcon(cat)} ${cat.name}</td>
                <td style="font-weight:500">${svc.name}</td>
                <td class="svc-rate">$${svc.pricePer1000.toFixed(2)}</td>
                <td>${svc.min.toLocaleString()}</td>
                <td>${svc.max.toLocaleString()}</td>
                <td><button class="svc-order-btn" onclick="orderFromModal(${cat.id},${svc.id})">${escapeHtml(HOME_I18N.order)}</button></td>
            </tr>`;
        });
    });
    tbody.innerHTML = html || '<tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text3)">' + escapeHtml(HOME_I18N.noServicesModal) + '</td></tr>';
}

function openServicesModal() {
    buildServicesTable('');
    document.getElementById('svcModalSearch').value = '';
    document.getElementById('servicesModal').classList.add('show');
}

function closeServicesModal() {
    document.getElementById('servicesModal').classList.remove('show');
}

function filterServicesModal(q) {
    buildServicesTable(q);
}

function orderFromModal(categoryId, serviceId) {
    closeServicesModal();
    selectPlatform(categoryId);
    setTimeout(() => selectService(categoryId, serviceId), 200);
    document.getElementById('step-1').scrollIntoView({ behavior: 'smooth' });
}

const IS_LOGGED_IN = {{ Auth::guard('client')->check() ? 'true' : 'false' }};

// Fast order
async function submitFastOrder() {
    const btn = document.getElementById('btnComplete');
    const errorEl = document.getElementById('orderError');
    errorEl.textContent = '';

    if (!validate()) return;

    const serviceId = document.getElementById('formServiceId').value;
    const categoryId = document.getElementById('formCategoryId').value;
    const link = document.getElementById('orderLink').value.trim();
    const qty = parseInt(document.getElementById('orderQty').value) || 0;

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> ' + escapeHtml(HOME_I18N.processing);

    // Logged-in client: create order from balance (no payment needed)
    if (IS_LOGGED_IN) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = @js(route('home.create-order'));
        form.innerHTML = '<input type="hidden" name="_token" value="{{ csrf_token() }}">' +
            '<input type="hidden" name="service_id" value="' + serviceId + '">' +
            '<input type="hidden" name="category_id" value="' + categoryId + '">' +
            '<input type="hidden" name="link" value="' + link.replace(/"/g, '&quot;') + '">' +
            '<input type="hidden" name="quantity" value="' + qty + '">';
        document.body.appendChild(form);
        form.submit();
        return;
    }

    // Guest: fast order + Heleket payment
    try {
        const res = await fetch('/api/fast-orders', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({
                category_id: parseInt(categoryId),
                service_id: parseInt(serviceId),
                targets: [{ link: link, quantity: qty }]
            })
        });

        const data = await res.json();

        if (!res.ok) {
            const errors = data.errors ? Object.values(data.errors).flat().join('; ') : (data.message || HOME_I18N.orderFailed);
            throw new Error(errors);
        }

        const uuid = data.fast_order?.uuid;
        if (!uuid) throw new Error(HOME_I18N.noUuid);

        // Set payment method
        const pmRes = await fetch(`/api/fast-orders/${uuid}/payment-method`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ payment_method: 'heleket' })
        });
        if (!pmRes.ok) {
            const pmData = await pmRes.json().catch(() => ({}));
            throw new Error(pmData.message || HOME_I18N.checkoutFailed);
        }

        // Payment
        @if(app()->environment('local', 'testing', 'staging'))
        const simRes = await fetch(`/api/fast-orders/${uuid}/simulate-payment-success`, {
            method: 'POST',
            headers: { 'Accept': 'application/json' }
        });
        const simData = await simRes.json().catch(() => ({}));
        if (!simRes.ok) throw new Error(simData.message || HOME_I18N.simulateFailed);
        if (simData.auto_login_url) { window.location.href = simData.auto_login_url; return; }
        throw new Error(HOME_I18N.noLoginUrl);
        @else
        const payRes = await fetch(`/api/payments/heleket/initiate`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ fast_order_uuid: uuid })
        });
        const payData = await payRes.json().catch(() => ({}));
        if (payData.pay_url) { window.location.href = payData.pay_url; return; }
        throw new Error(payData.message || HOME_I18N.paymentFailed);
        @endif

    } catch (err) {
        errorEl.textContent = err.message;
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-lock"></i> ' + escapeHtml(HOME_I18N.completeOrder);
    }
}

renderPlatforms();

// Mobile menu
function openMobileMenu() {
    document.getElementById('mobileMenu').classList.add('show');
    document.getElementById('mobileOverlay').classList.add('show');
    document.body.style.overflow = 'hidden';
}
function closeMobileMenu() {
    document.getElementById('mobileMenu').classList.remove('show');
    document.getElementById('mobileOverlay').classList.remove('show');
    document.body.style.overflow = '';
}
</script>
</body>
</html>
