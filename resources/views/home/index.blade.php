<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'SMM Tool') }} — Social Media Growth Services</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <meta property="og:title" content="SMMTool - Best SMM Panel" />
    <meta property="og:description" content="Grow your social media fast" />
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
        .logo { display: flex; flex-direction: column; line-height: 1.1; flex-shrink: 0; }
        .logo-name { font-size: 18px; font-weight: 800; background: linear-gradient(135deg, var(--purple), var(--teal)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .logo-slogan { font-size: 9px; color: var(--text2); letter-spacing: 0.5px; }
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

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .nav-links, .btn-signin, .direct-badge { display: none; }
            .footer-grid { grid-template-columns: 1fr; }
            .stats-bar-inner { gap: 8px; }
            .stat-pill { min-width: 100px; padding: 12px 10px; }
            .stat-value { font-size: 18px; }
            .how-arrow { display: none; }
            .platform-grid { grid-template-columns: repeat(auto-fit, minmax(72px, max-content)); justify-content: center; }
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <div class="navbar-inner">
      <a href="/" class="logo">
        <span class="logo-name">{{ config('app.name', 'SMM Tool') }}</span>
        <span class="logo-slogan">Social Media Growth</span>
      </a>
      <ul class="nav-links">
        <li><a href="#" onclick="openServicesModal(); return false;">{{ __('Services') }}</a></li>
        <li><a href="#how-it-works">{{ __('How It Works') }}</a></li>
        <li><a href="#why-us">{{ __('Why Choose Us') }}</a></li>
        @if($contactTelegram)
          <li><a href="https://t.me/{{ $contactTelegram }}" target="_blank">{{ __('Support') }}</a></li>
        @endif
      </ul>
      <div class="nav-right">
        <span class="direct-badge"><i class="fa-solid fa-check-circle"></i> {{ __('Direct Provider · No Resellers') }}</span>
        <button class="theme-toggle" id="themeToggle" onclick="toggleTheme()" title="Toggle theme"><i class="fa-solid fa-moon" id="themeIcon"></i></button>
        <x-language-dropdown variant="landing" />
        @auth('client')
          <a href="{{ route('client.orders.index') }}" class="btn-signup">{{ __('Dashboard') }}</a>
        @else
          <a href="{{ route('login') }}" class="btn-signin">{{ __('Sign In') }}</a>
          <a href="{{ route('register') }}" class="btn-signup">{{ __('Sign Up') }}</a>
        @endauth
      </div>
    </div>
</nav>

<!-- HERO + ORDER -->
<section class="hero-order-section">
    <div class="hero-compact">
        <h1 id="heroTitle">{{ __('SMM Services Direct Provider') }}</h1>
        <h2>#1 for Completions &middot; Fast and Trusted</h2>
        <p>{{ __('Order followers, likes, views, and engagement services in a few clicks.') }}</p>
    </div>

    <div class="fast-order-wrap">
        <!-- Steps indicator -->
        <div class="steps-indicator">
            <div class="step-item active" data-step="1"><div class="step-num">1</div><div class="step-label">{{ __('Platform') }}</div></div>
            <div class="step-arrow"><i class="fa-solid fa-chevron-right"></i></div>
            <div class="step-item" data-step="2"><div class="step-num">2</div><div class="step-label">{{ __('Service') }}</div></div>
            <div class="step-arrow"><i class="fa-solid fa-chevron-right"></i></div>
            <div class="step-item" data-step="3"><div class="step-num">3</div><div class="step-label">{{ __('Order') }}</div></div>
        </div>

        <!-- Step 1: Platform (= Category) -->
        <div class="order-step active" id="step-1">
            <div class="order-step-header" onclick="goToStep(1)">
                <div class="order-step-num">1</div>
                <div class="order-step-title">{{ __('Choose Platform') }}</div>
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
                <div class="order-step-title">{{ __('Select Service') }}</div>
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
                <div class="order-step-title">{{ __('Complete Order') }}</div>
            </div>
            <div class="order-step-body">
                <div class="order-step-inner">
                    <div class="order-form" id="orderForm">
                        <input type="hidden" id="formServiceId">
                        <input type="hidden" id="formCategoryId">
                        <div class="form-group">
                            <label class="form-label">{{ __('Your Link') }}</label>
                            <input class="form-input" type="text" id="orderLink" placeholder="{{ __('Paste your link here...') }}">
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
                                <div class="price-label">{{ __('Total Price') }}</div>
                                <div style="font-size:11px;color:var(--text3);margin-top:1px" id="priceBreakdown">&mdash;</div>
                            </div>
                            <div class="price-total" id="priceTotal">$0.00</div>
                        </div>
                        <button type="button" class="btn-complete" id="btnComplete" disabled onclick="submitFastOrder()">
                            <i class="fa-solid fa-lock"></i> {{ __('Complete Order') }}
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
        <div class="stat-pill"><div class="stat-value">5 Years</div><div class="stat-label">{{ __('In the Market') }}</div></div>
        <div class="stat-pill"><div class="stat-value">24/7</div><div class="stat-label">{{ __('Support') }}</div></div>
        <div class="stat-pill"><div class="stat-value">200K+</div><div class="stat-label">{{ __('Happy Clients') }}</div></div>
    </div>
</div>

<!-- WHY CHOOSE US -->
<section class="section" id="why-us">
    <div class="section-inner">
        <div class="section-header">
            <div class="section-tag">{{ __('Why Choose Us') }}</div>
            <h2 class="section-title">{{ __('The #1 SMM Panel') }}</h2>
            <p class="section-subtitle">{{ __('Trusted by hundreds of thousands of marketers worldwide.') }}</p>
        </div>
        <div class="features-grid">
            <div class="feature-card"><div class="feature-icon"><i class="fa-solid fa-bolt"></i></div><h4>{{ __('Instant Delivery') }}</h4><p>{{ __('Orders start within seconds. Direct supplier pipeline.') }}</p></div>
            <div class="feature-card"><div class="feature-icon"><i class="fa-solid fa-shield-halved"></i></div><h4>{{ __('100% Safe') }}</h4><p>{{ __('Platform-compliant methods only. Your accounts stay protected.') }}</p></div>
            <div class="feature-card"><div class="feature-icon" style="background:rgba(0,210,211,0.1)"><i class="fa-solid fa-dollar-sign" style="color:var(--teal)"></i></div><h4>{{ __('Lowest Prices') }}</h4><p>{{ __('Direct sourcing cuts middlemen. Best rates guaranteed.') }}</p></div>
            <div class="feature-card"><div class="feature-icon"><i class="fa-solid fa-headset"></i></div><h4>{{ __('24/7 Support') }}</h4><p>{{ __('Real humans, real answers. We respond in minutes.') }}</p></div>
            <div class="feature-card"><div class="feature-icon" style="background:rgba(0,210,211,0.1)"><i class="fa-solid fa-globe" style="color:var(--teal)"></i></div><h4>{{ __('All Platforms') }}</h4><p>{{ __('Multiple social media platforms, hundreds of services.') }}</p></div>
            <div class="feature-card"><div class="feature-icon"><i class="fa-solid fa-code"></i></div><h4>{{ __('Reseller API') }}</h4><p>{{ __('Full REST API for agencies. Build your own panel.') }}</p></div>
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
            <div class="how-step"><div class="how-step-icon"><i class="fa-solid fa-mobile-screen"></i></div><h4>{{ __('Choose Platform') }}</h4><p>{{ __('Pick your social media platform') }}</p></div>
            <div class="how-arrow"><i class="fa-solid fa-arrow-right"></i></div>
            <div class="how-step"><div class="how-step-icon"><i class="fa-solid fa-list"></i></div><h4>{{ __('Pick Service') }}</h4><p>{{ __('Select the service you need') }}</p></div>
            <div class="how-arrow"><i class="fa-solid fa-arrow-right"></i></div>
            <div class="how-step"><div class="how-step-icon"><i class="fa-solid fa-rocket"></i></div><h4>{{ __('Get Results') }}</h4><p>{{ __('Enter link, set quantity, order') }}</p></div>
        </div>
    </div>
</section>

<!-- TESTIMONIALS -->
<section class="section" id="testimonials">
    <div class="section-inner">
      <div class="section-header">
        <div class="section-tag">Reviews</div>
        <h2 class="section-title">What Our Clients Say</h2>
      </div>
      <div class="testimonials-grid">
        <div class="testimonial-card">
          <div class="testimonial-stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
          <p class="testimonial-text">Excellent service! My YouTube channel grew from 500 to 10K subscribers in two weeks. The quality is surprisingly good and most followers look real. Definitely coming back for more.</p>
          <div class="testimonial-author"><div class="testimonial-avatar">JM</div><div><div class="testimonial-name">Jake M.</div><div class="testimonial-role">YouTuber, 45K subs</div></div></div>
        </div>
        <div class="testimonial-card">
          <div class="testimonial-stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
          <p class="testimonial-text">We manage 30+ client accounts and this tool saved us hours every week. The API integration took about 20 minutes. Prices are way better than what we were paying before.</p>
          <div class="testimonial-author"><div class="testimonial-avatar">SR</div><div><div class="testimonial-name">Sarah R.</div><div class="testimonial-role">Agency Owner</div></div></div>
        </div>
        <div class="testimonial-card">
          <div class="testimonial-stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
          <p class="testimonial-text">Needed Telegram members for a crypto project. Got 15K members delivered in 3 days. Support responded within 5 minutes when I had a question. Very impressed with the speed.</p>
          <div class="testimonial-author"><div class="testimonial-avatar">AK</div><div><div class="testimonial-name">Andrei K.</div><div class="testimonial-role">Crypto Project Manager</div></div></div>
        </div>
        <div class="testimonial-card">
          <div class="testimonial-stars">&#9733;&#9733;&#9733;&#9733;&#9734;</div>
          <p class="testimonial-text">Good service overall. Instagram likes came through fast. Had a small drop after a week but the auto-refill kicked in. Fair pricing and the dashboard is clean. Would recommend for small businesses.</p>
          <div class="testimonial-author"><div class="testimonial-avatar">LP</div><div><div class="testimonial-name">Lisa P.</div><div class="testimonial-role">Small Business Owner</div></div></div>
        </div>
        <div class="testimonial-card">
          <div class="testimonial-stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
          <p class="testimonial-text">Been using SMM panels for years and this one has the best price-to-quality ratio. TikTok views are super cheap and they deliver fast. My go-to panel now.</p>
          <div class="testimonial-author"><div class="testimonial-avatar">DT</div><div><div class="testimonial-name">David T.</div><div class="testimonial-role">TikTok Creator</div></div></div>
        </div>
        <div class="testimonial-card">
          <div class="testimonial-stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
          <p class="testimonial-text">What I love is the crypto payment option. No need to share card details. Ordered Spotify plays and they looked legit in my analytics. Great for independent artists.</p>
          <div class="testimonial-author"><div class="testimonial-avatar">MH</div><div><div class="testimonial-name">Maya H.</div><div class="testimonial-role">Independent Musician</div></div></div>
        </div>
      </div>
    </div>
</section>

<!-- FOOTER -->
<footer>
    <div class="footer-inner">
      <div class="footer-grid">
        <div class="footer-brand">
          <a href="/" class="logo"><span class="logo-name" style="font-size:20px">{{ config('app.name', 'SMM Tool') }}</span></a>
          <p>The fastest, most reliable SMM panel. Direct provider, no resellers. Professional social media growth tools.</p>
          <div class="footer-social">
            @if($contactTelegram)
              <a href="https://t.me/{{ $contactTelegram }}" target="_blank" title="Telegram"><i class="fa-brands fa-telegram"></i></a>
            @endif
            <a href="#" title="WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
            <a href="#" title="Facebook"><i class="fa-brands fa-facebook"></i></a>
          </div>
        </div>
        <div class="footer-col">
          <h5>Company</h5>
          <ul>
            <li><a href="#"><i class="fa-solid fa-list"></i> Services</a></li>
            <li><a href="#why-us"><i class="fa-solid fa-building"></i> About Us</a></li>
            <li><a href="#"><i class="fa-solid fa-pen"></i> Blog</a></li>
            <li><a href="#"><i class="fa-solid fa-code"></i> API</a></li>
          </ul>
        </div>
        <div class="footer-col">
          <h5>Support</h5>
          <ul>
            @if($contactTelegram)
              <li><a href="https://t.me/{{ $contactTelegram }}" target="_blank"><i class="fa-brands fa-telegram"></i> Telegram</a></li>
            @endif
            <li><a href="#"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a></li>
            <li><a href="#"><i class="fa-solid fa-file-contract"></i> Terms of Service</a></li>
            <li><a href="#"><i class="fa-solid fa-shield-halved"></i> Privacy Policy</a></li>
          </ul>
        </div>
      </div>
      <div class="footer-bottom">
        <div class="footer-copy">&copy; {{ date('Y') }} {{ config('app.name', 'SMM Tool') }}. All rights reserved. Powered by {{ config('app.name', 'SMMTOOL') }}</div>
        <div class="footer-legal">
          <a href="#">Privacy Policy</a>
          <a href="#">Cookies</a>
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
    @if($contactTelegram)
        <a href="https://t.me/{{ $contactTelegram }}" target="_blank" class="float-btn float-btn-support" title="Support"><i class="fa-brands fa-telegram"></i></a>
    @endif
</div>

@if($errors->any())
    <div style="position:fixed;top:70px;right:20px;z-index:9999;background:var(--danger);color:#fff;padding:12px 20px;border-radius:8px;font-size:13px;max-width:400px;">
        @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<script>
const CATEGORIES = @json($categoriesForJs);
let state = { categoryId: null, serviceId: null, currentStep: 1 };

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
    'max': '<img src="https://cs1.socpanel.com/cs1/project_images/QdFxuI4RczNFlG54eXfJOcMUCdX3gHjeCjn2TLM2.jpg" alt="" class="platform-icon-img" width="22" height="22" loading="lazy" decoding="async" referrerpolicy="no-referrer">',
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
        list.innerHTML = '<div style="padding:16px;color:var(--text3);text-align:center">No services available</div>';
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
        document.getElementById('qtyHint').textContent = `Min: ${svc.min.toLocaleString()} · Max: ${svc.max.toLocaleString()}`;
        document.getElementById('orderLink').placeholder = svc.placeholder || 'Paste your link here...';
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
    document.getElementById('priceBreakdown').textContent = svc ? `$${svc.pricePer1000.toFixed(2)} per 1K × ${qty.toLocaleString()}` : '—';
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
        document.getElementById('qtyError').textContent = `Quantity must be between ${svc.min.toLocaleString()} and ${svc.max.toLocaleString()}`;
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
                <td><button class="svc-order-btn" onclick="orderFromModal(${cat.id},${svc.id})">Order</button></td>
            </tr>`;
        });
    });
    tbody.innerHTML = html || '<tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text3)">No services found</td></tr>';
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
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';

    try {
        // 1) Create fast order draft
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
            const errors = data.errors ? Object.values(data.errors).flat().join('; ') : (data.message || 'Order failed');
            throw new Error(errors);
        }

        const uuid = data.fast_order?.uuid;
        if (!uuid) throw new Error('No order UUID returned');

        // 2) Set payment method
        const pmRes = await fetch(`/api/fast-orders/${uuid}/payment-method`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ payment_method: 'heleket' })
        });
        const pmData = await pmRes.json().catch(() => ({}));
        if (!pmRes.ok) {
            const msg = pmData.message || (pmData.errors ? Object.values(pmData.errors).flat().join('; ') : null) || 'Could not start checkout';
            throw new Error(msg);
        }

        // 3) Payment
        @if(app()->environment('local', 'testing', 'staging'))
        // DEV MODE: simulate payment (no real charge)
        const simRes = await fetch(`/api/fast-orders/${uuid}/simulate-payment-success`, {
            method: 'POST',
            headers: { 'Accept': 'application/json' }
        });
        const simData = await simRes.json().catch(() => ({}));

        if (!simRes.ok) {
            throw new Error(simData.message || 'Simulate payment failed');
        }

        const loginUrl = simData.auto_login_url;
        if (loginUrl) {
            window.location.href = loginUrl;
        } else {
            throw new Error('No login URL returned');
        }
        @else
        // PRODUCTION: initiate real Heleket payment
        const payRes = await fetch(`/api/payments/heleket/initiate`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ fast_order_uuid: uuid })
        });
        const payData = await payRes.json().catch(() => ({}));

        if (payData.pay_url) {
            window.location.href = payData.pay_url;
            return;
        }

        const payErr = payData.message || (payData.errors ? Object.values(payData.errors).flat().join('; ') : null) || 'Payment could not be started';
        throw new Error(payErr);
        @endif

    } catch (err) {
        errorEl.textContent = err.message;
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-lock"></i> {{ __("Complete Order") }}';
    }
}

renderPlatforms();
</script>
</body>
</html>
