<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="4;url={{ url()->current() }}">
    <title>{{ __('common.fast_order_completing') }} — {{ config('app.name') }}</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #0a0a0f; color: #e2e8f0; min-height: 100vh; margin: 0; display: flex; align-items: center; justify-content: center; padding: 24px; text-align: center; }
        p { max-width: 320px; line-height: 1.5; font-size: 15px; color: #8892a4; }
        .spinner { width: 36px; height: 36px; border: 3px solid rgba(108,92,231,0.2); border-top-color: #6c5ce7; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 20px; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div>
        <div class="spinner" aria-hidden="true"></div>
        <p>{{ __('common.fast_order_completing_hint') }}</p>
    </div>
</body>
</html>
