<x-client-smm-layout
    :title="__('contacts.title')"
    :client="$client"
    :contactTelegram="$contactTelegram"
>
    <div class="client-contacts-page px-4 pb-12 pt-2 sm:px-6 lg:px-8">
        <style>
            .client-contacts-page .cc-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 1.25rem;
                max-width: 1000px;
            }
            .client-contacts-page .cc-card {
                background: var(--card);
                border: 1px solid var(--border);
                border-radius: var(--radius);
                padding: 1.5rem 1.25rem;
                text-align: center;
                transition: border-color 0.2s, box-shadow 0.2s;
            }
            .client-contacts-page .cc-card:hover {
                border-color: rgba(108, 92, 231, 0.35);
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            }
            .client-contacts-page .cc-card--telegram {
                background: linear-gradient(145deg, #0088cc, #006699);
                border-color: rgba(255, 255, 255, 0.15);
                color: #fff;
            }
            .client-contacts-page .cc-card__icon {
                font-size: 2.25rem;
                margin-bottom: 1rem;
                color: var(--purple-light);
            }
            .client-contacts-page .cc-card--telegram .cc-card__icon {
                color: #fff;
            }
            .client-contacts-page .cc-card__label {
                font-size: 11px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.06em;
                color: var(--text3);
                margin-bottom: 0.5rem;
            }
            .client-contacts-page .cc-card--telegram .cc-card__label {
                color: rgba(255, 255, 255, 0.85);
            }
            .client-contacts-page .cc-card__value {
                font-size: 1rem;
                font-weight: 700;
                word-break: break-word;
                color: var(--text);
            }
            .client-contacts-page .cc-card__value a {
                color: var(--teal);
                text-decoration: none;
            }
            .client-contacts-page .cc-card__value a:hover {
                text-decoration: underline;
            }
            .client-contacts-page .cc-card--telegram .cc-card__value a {
                color: #fff;
            }
            .client-contacts-page .cc-card__muted {
                color: var(--text3);
                font-weight: 500;
                font-size: 0.9rem;
            }
            .client-contacts-page .cc-card--telegram .cc-card__muted {
                color: rgba(255, 255, 255, 0.8);
            }
            [data-theme="light"] .client-contacts-page .cc-card__value {
                color: var(--text);
            }
        </style>
        <p class="mb-6 max-w-2xl text-sm" style="color: var(--text2)">{{ __('contacts.intro') }}</p>
        <div class="cc-grid">
            <div class="cc-card">
                <div class="cc-card__icon" aria-hidden="true"><i class="far fa-envelope"></i></div>
                <div class="cc-card__label">{{ __('contacts.help_desk') }}</div>
                <div class="cc-card__value">
                    @if(filled($contactEmail))
                        <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>
                    @else
                        <span class="cc-card__muted">{{ __('contacts.not_available') }}</span>
                    @endif
                </div>
            </div>
            <div class="cc-card cc-card--telegram">
                <div class="cc-card__icon" aria-hidden="true"><i class="fab fa-telegram-plane"></i></div>
                <div class="cc-card__label">{{ __('contacts.telegram_bot') }}</div>
                <div class="cc-card__value" style="display:flex;flex-direction:column;gap:8px;">
                    @foreach(config('contact.telegram_support_list', []) as $support)
                        @if(!empty($support['username']))
                            <a href="https://t.me/{{ ltrim($support['username'], '@') }}" target="_blank" rel="noopener noreferrer"
                               style="display:flex;align-items:center;gap:8px;">
                                <span>{{ $support['flag'] }}</span>
                                <span>{{ $support['label'] }}</span>
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
            @if(filled($contactCooperationEmail))
                <div class="cc-card">
                    <div class="cc-card__icon" aria-hidden="true"><i class="far fa-handshake"></i></div>
                    <div class="cc-card__label">{{ __('contacts.cooperation') }}</div>
                    <div class="cc-card__value">
                        <a href="mailto:{{ $contactCooperationEmail }}">{{ $contactCooperationEmail }}</a>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-client-smm-layout>
