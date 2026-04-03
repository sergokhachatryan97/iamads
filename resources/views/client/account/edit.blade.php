<x-client-layout :title="__('Settings')">
    <style>
        .settings-page { max-width: 760px; margin: 0 auto; }
        .settings-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 22px 24px 26px;
            margin-bottom: 20px;
        }
        .settings-card-head {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0 0 20px;
            font-size: 1rem;
            font-weight: 700;
            color: var(--text);
        }
        .settings-card-head i { color: var(--purple-light); font-size: 1.1rem; }
        .settings-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 18px 20px;
        }
        @media (min-width: 640px) {
            .settings-grid { grid-template-columns: 1fr 1fr; }
            .settings-span-2 { grid-column: span 2; }
        }
        .settings-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--text3);
            margin-bottom: 8px;
            letter-spacing: 0.02em;
        }
        .settings-input,
        .settings-select {
            width: 100%;
            padding: 12px 14px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: rgba(0, 0, 0, 0.28);
            color: var(--text);
            font-size: 14px;
            font-family: inherit;
            outline: none;
            transition: border-color 0.2s;
        }
        [data-theme="light"] .settings-input,
        [data-theme="light"] .settings-select { background: var(--card2, #f0f2f7); }
        .settings-input:focus,
        .settings-select:focus { border-color: var(--purple-light); }
        .settings-input::placeholder { color: var(--text3); }
        .settings-input:disabled { opacity: 0.75; cursor: not-allowed; }
        .settings-select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%238892a4'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 18px;
            padding-right: 40px;
        }
        .settings-error { margin-top: 6px; font-size: 12px; color: #ff7675; }
        .settings-actions { display: flex; align-items: center; gap: 14px; margin-top: 22px; flex-wrap: wrap; }
        .settings-btn {
            padding: 11px 22px;
            border-radius: 10px;
            border: none;
            background: var(--purple);
            color: #fff !important;
            font-size: 14px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            box-shadow: 0 4px 16px rgba(108, 92, 231, 0.35);
            transition: filter 0.15s;
        }
        .settings-btn:hover { filter: brightness(1.06); }
        .settings-hint { font-size: 12px; color: var(--text3); margin-top: 6px; line-height: 1.4; }
        .settings-alert {
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            border: 1px solid var(--border);
        }
        .settings-alert.ok { background: rgba(0, 184, 148, 0.12); border-color: rgba(0, 184, 148, 0.35); color: #00d9a5; }
        html[data-theme="light"] .settings-alert.ok { color: #006b57; }
    </style>

    @php
        $locales = config('locales', []);
        $currentLocale = app()->getLocale();
        $appTz = config('app.timezone');
        $tzOffset = now()->timezone($appTz)->format('P');
    @endphp

    <div class="settings-page">
        @if (session('status') === 'account-updated')
            <div class="settings-alert ok">{{ __('Your profile was updated.') }}</div>
        @endif
        @if (session('status') === 'password-updated')
            <div class="settings-alert ok">{{ __('Your password was updated.') }}</div>
        @endif

        {{-- Profile --}}
        <div class="settings-card">
            <h2 class="settings-card-head">
                <i class="fa-solid fa-user" aria-hidden="true"></i>
                {{ __('Profile') }}
            </h2>
            <form method="post" action="{{ route('client.account.update') }}">
                @csrf
                @method('patch')

                <div class="settings-grid">
                    <div>
                        <label class="settings-label" for="name">{{ __('Full name') }}</label>
                        <input id="name" name="name" type="text" class="settings-input" value="{{ old('name', $client->name) }}" required autocomplete="name" placeholder="{{ __('Full name') }}">
                        @error('name')
                            <p class="settings-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="settings-label" for="email">{{ __('Email') }}</label>
                        <input id="email" name="email" type="email" class="settings-input" value="{{ old('email', $client->email) }}" required autocomplete="username" placeholder="email@example.com">
                        @error('email')
                            <p class="settings-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="settings-label" for="settings_timezone">{{ __('Timezone') }}</label>
                        <select id="settings_timezone" class="settings-select" disabled aria-label="{{ __('Timezone') }}">
                            <option selected>UTC{{ $tzOffset }}</option>
                        </select>
                        <p class="settings-hint">{{ __('Application timezone') }}: {{ $appTz }}</p>
                    </div>
                    <div>
                        <label class="settings-label" for="settings_language">{{ __('Language') }}</label>
                        <select id="settings_language" class="settings-select" aria-label="{{ __('Language') }}" onchange="if (this.value) window.location.href = this.value">
                            @foreach ($locales as $code => $info)
                                <option value="{{ route('locale.switch', $code) }}" {{ $code === $currentLocale ? 'selected' : '' }}>
                                    {{ $info['native'] ?? $info['name'] ?? strtoupper($code) }}
                                </option>
                            @endforeach
                        </select>
                        <p class="settings-hint">{{ __('Changing language applies immediately.') }}</p>
                    </div>
                </div>

                <div class="settings-actions">
                    <button type="submit" class="settings-btn">{{ __('Save changes') }}</button>
                </div>
            </form>
        </div>

        {{-- Security --}}
        <div class="settings-card">
            <h2 class="settings-card-head">
                <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                {{ __('Security') }}
            </h2>
            <form method="post" action="{{ route('password.update') }}">
                @csrf
                @method('put')

                <div class="settings-grid">
                    <div class="settings-span-2">
                        <label class="settings-label" for="current_password">{{ __('Current password') }}</label>
                        <input id="current_password" name="current_password" type="password" class="settings-input" autocomplete="current-password" placeholder="{{ __('Enter current password') }}">
                        @error('current_password', 'updatePassword')
                            <p class="settings-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="settings-label" for="password">{{ __('New password') }}</label>
                        <input id="password" name="password" type="password" class="settings-input" autocomplete="new-password" placeholder="{{ __('Enter new password') }}">
                        @error('password', 'updatePassword')
                            <p class="settings-error">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="settings-label" for="password_confirmation">{{ __('Confirm new password') }}</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" class="settings-input" autocomplete="new-password" placeholder="{{ __('Confirm new password') }}">
                        @error('password_confirmation', 'updatePassword')
                            <p class="settings-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="settings-actions">
                    <button type="submit" class="settings-btn">{{ __('Update password') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-client-layout>
