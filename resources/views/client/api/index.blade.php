<x-client-layout :title="__('API Documentation')">
    <style>
        .api-doc-page { max-width: 920px; margin: 0 auto; }
        .api-doc-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius, 12px);
            padding: 22px 24px;
            margin-bottom: 20px;
        }
        .api-doc-card-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1rem;
            font-weight: 700;
            color: var(--text);
            margin: 0 0 16px;
        }
        .api-doc-card-title .key-icon { color: #e8c547; font-size: 1.1rem; }
        .api-doc-alert {
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            border: 1px solid var(--border);
        }
        .api-doc-alert.ok { background: rgba(0, 184, 148, 0.12); border-color: rgba(0, 184, 148, 0.35); color: #00d9a5; }
        .api-doc-alert.warn { background: rgba(253, 203, 110, 0.12); border-color: rgba(253, 203, 110, 0.35); color: #fdcb6e; }
        .api-doc-alert.err { background: rgba(225, 112, 85, 0.12); border-color: rgba(225, 112, 85, 0.35); color: #fab1a0; }
        html[data-theme="light"] .api-doc-alert.ok { color: #006b57; }
        html[data-theme="light"] .api-doc-alert.warn { color: #b8860b; }
        html[data-theme="light"] .api-doc-alert.err { color: #c0392b; }
        .api-doc-key-row {
            display: flex;
            flex-wrap: wrap;
            align-items: stretch;
            gap: 10px;
        }
        .api-doc-key-input {
            flex: 1;
            min-width: 200px;
            font-family: ui-monospace, monospace;
            font-size: 13px;
            padding: 12px 14px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: rgba(0, 0, 0, 0.35);
            color: var(--text);
        }
        html[data-theme="light"] .api-doc-key-input { background: var(--card2, #f0f2f7); }
        .api-doc-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            border: none;
            transition: filter 0.15s, transform 0.15s;
        }
        .api-doc-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .api-doc-btn-purple {
            background: var(--purple, #6c5ce7);
            color: #fff !important;
            box-shadow: 0 4px 14px rgba(108, 92, 231, 0.35);
        }
        .api-doc-btn-purple:hover:not(:disabled) { filter: brightness(1.06); }
        .api-doc-base-url {
            margin-top: 14px;
            font-size: 13px;
            color: var(--text2);
        }
        .api-doc-base-url code {
            display: inline-block;
            margin-top: 6px;
            padding: 6px 12px;
            border-radius: 8px;
            background: rgba(108, 92, 231, 0.15);
            border: 1px solid rgba(108, 92, 231, 0.3);
            color: var(--teal);
            font-family: ui-monospace, monospace;
            font-size: 12px;
            word-break: break-all;
        }
        .api-doc-enable-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }
        .api-doc-endpoint-head { margin-bottom: 16px; }
        .api-doc-method {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.06em;
            background: rgba(0, 210, 211, 0.2);
            color: var(--teal);
            margin-right: 10px;
            vertical-align: middle;
        }
        .api-doc-path {
            font-family: ui-monospace, monospace;
            font-size: 15px;
            font-weight: 700;
            color: var(--text);
        }
        .api-doc-endpoint-desc {
            margin-top: 8px;
            font-size: 14px;
            color: var(--text2);
            line-height: 1.5;
        }
        .api-doc-table-wrap { overflow-x: auto; margin: 16px 0; }
        .api-doc-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .api-doc-table th {
            text-align: left;
            padding: 10px 12px;
            color: var(--text3);
            font-weight: 700;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            border-bottom: 1px solid var(--border);
            background: var(--card2, #16213e);
        }
        .api-doc-table td {
            padding: 12px;
            border-bottom: 1px solid var(--border);
            color: var(--text2);
            vertical-align: top;
        }
        .api-doc-table tr:last-child td { border-bottom: none; }
        .api-doc-param { font-family: ui-monospace, monospace; color: var(--purple-light); font-weight: 600; }
        .api-doc-req {
            display: inline-block;
            margin-left: 8px;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            background: rgba(225, 112, 85, 0.2);
            color: #ff7675;
        }
        .api-doc-section-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text3);
            margin: 20px 0 10px;
        }
        .api-doc-code {
            margin: 0;
            padding: 16px 18px;
            border-radius: 10px;
            background: #0a0a12;
            border: 1px solid var(--border);
            font-family: ui-monospace, monospace;
            font-size: 12px;
            line-height: 1.55;
            color: #e2e8f0;
            overflow-x: auto;
            white-space: pre-wrap;
            word-break: break-word;
        }
        html[data-theme="light"] .api-doc-code {
            background: #1a1a2e;
            color: #e2e8f0;
        }
        .api-doc-muted { font-size: 13px; color: var(--text3); margin-bottom: 12px; line-height: 1.5; }
        .api-doc-regen {
            margin-top: 12px;
            font-size: 13px;
        }
        .api-doc-regen button {
            background: none;
            border: none;
            color: #fdcb6e;
            cursor: pointer;
            font-weight: 600;
            padding: 0;
            font-family: inherit;
            text-decoration: underline;
        }
        .api-doc-regen button:hover { color: #ffeaa7; }
        .api-doc-subcard {
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid var(--border);
        }
        .api-doc-subcard h4 {
            margin: 0 0 10px;
            font-size: 14px;
            font-weight: 700;
            color: var(--text);
        }
        .api-doc-status { font-size: 14px; font-weight: 600; margin: 0; color: var(--text3); }
        .api-doc-status.is-on { color: var(--teal); }
    </style>

    <div class="api-doc-page">
        @if (session('status'))
            <div class="api-doc-alert {{ session('status') === 'api-key-regenerated' ? 'warn' : 'ok' }}">
                @if (session('status') === 'api-enabled')
                    {{ __('API access enabled. Your API key is shown below.') }}
                @elseif (session('status') === 'api-disabled')
                    {{ __('API access disabled.') }}
                @elseif (session('status') === 'api-key-regenerated')
                    {{ __('API key regenerated. Your old key is no longer valid.') }}
                @endif
            </div>
        @endif

        @if (session('error'))
            <div class="api-doc-alert err">{{ session('error') }}</div>
        @endif

        <div class="api-doc-card">
            <h2 class="api-doc-card-title">
                <i class="fa-solid fa-plug" style="color:var(--purple-light)" aria-hidden="true"></i>
                {{ __('API Access') }}
            </h2>
            <p class="api-doc-muted">{{ __('Use the same account to place orders via API. Balance is shared between web and API orders.') }}</p>
            <div class="api-doc-enable-row">
                <p class="api-doc-status {{ $client->api_enabled ? 'is-on' : '' }}">
                    {{ $client->api_enabled ? __('Enabled') : __('Disabled') }}
                </p>
                <form method="POST" action="{{ route('client.api.toggle') }}">
                    @csrf
                    <input type="hidden" name="api_enabled" value="{{ $client->api_enabled ? '0' : '1' }}">
                    <button type="submit" class="api-doc-btn api-doc-btn-purple">
                        {{ $client->api_enabled ? __('Disable') : __('Enable API') }}
                    </button>
                </form>
            </div>
        </div>

        @if ($client->api_enabled)
            <div class="api-doc-card" x-data="{
                revealed: false,
                fullKey: null,
                loading: false,
                copied: false,
                async copyKey() {
                    let key = this.fullKey;
                    if (!key) {
                        this.loading = true;
                        try {
                            const r = await fetch('{{ route('client.api.reveal-key') }}', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                            const d = await r.json();
                            key = d.api_key;
                            this.fullKey = key;
                        } catch (e) { this.loading = false; return; }
                        this.loading = false;
                    }
                    if (key) {
                        await navigator.clipboard.writeText(key);
                        this.copied = true;
                        setTimeout(() => this.copied = false, 2000);
                    }
                },
                toggleReveal() {
                    if (!this.revealed && !this.fullKey) {
                        this.loading = true;
                        fetch('{{ route('client.api.reveal-key') }}', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                            .then(r => r.json())
                            .then(d => { this.fullKey = d.api_key; this.revealed = true; this.loading = false; })
                            .catch(() => { this.loading = false; });
                    } else {
                        this.revealed = !this.revealed;
                    }
                }
            }">
                <h2 class="api-doc-card-title">
                    <i class="fa-solid fa-key key-icon" aria-hidden="true"></i>
                    {{ __('Your API Key') }}
                </h2>
                <p class="api-doc-muted m-0 mb-3">{{ __('Use this key in the request body as') }} <code class="api-doc-param" style="font-size:12px">key</code>. {{ __('Bearer header is not required for the provider-style endpoint below.') }}</p>
                <div class="api-doc-key-row">
                    <code class="api-doc-key-input" x-text="revealed && fullKey ? fullKey : '{{ $apiKeyMasked ?? '••••••••••••••••' }}'"></code>
                    <button type="button" class="api-doc-btn api-doc-btn-purple" @click="toggleReveal()" :disabled="loading">
                        <i class="fa-solid" :class="revealed ? 'fa-eye-slash' : 'fa-eye'" aria-hidden="true"></i>
                        <span x-text="loading ? '…' : (revealed ? '{{ __('Hide') }}' : '{{ __('Show') }}')"></span>
                    </button>
                    <button type="button" class="api-doc-btn api-doc-btn-purple" @click="copyKey()" :disabled="loading">
                        <i class="fa-regular fa-copy" aria-hidden="true"></i>
                        <span x-text="copied ? '{{ __('Copied!') }}' : '{{ __('Copy') }}'"></span>
                    </button>
                </div>
                <div class="api-doc-base-url">
                    {{ __('Base URL') }}:
                    <code>{{ $baseUrl }}</code>
                </div>
                <div class="api-doc-regen">
                    <form method="POST" action="{{ route('client.api.regenerate') }}" class="inline" onsubmit="return confirm('{{ __('Regenerating will invalidate your current key. Continue?') }}');">
                        @csrf
                        <button type="submit">{{ __('Regenerate key') }}</button>
                    </form>
                </div>
            </div>

            <div class="api-doc-card">
                <div class="api-doc-endpoint-head">
                    <span class="api-doc-method">POST</span>
                    <span class="api-doc-path">{{ parse_url($providerApiUrl, PHP_URL_PATH) ?: '/api/v2' }}</span>
                    <p class="api-doc-endpoint-desc">{{ __('Single URL for all actions. Send JSON with an') }} <code class="api-doc-param" style="font-size:12px">action</code> {{ __('field (e.g. add, services, status, balance).') }}</p>
                </div>

                <div class="api-doc-subcard">
                    <h4><span class="api-doc-method" style="margin-right:8px">POST</span> {{ __('action: add') }} — {{ __('Create a new order for any service.') }}</h4>
                    <div class="api-doc-table-wrap">
                        <table class="api-doc-table">
                            <thead>
                                <tr>
                                    <th>{{ __('Parameter') }}</th>
                                    <th>{{ __('Type') }}</th>
                                    <th>{{ __('Description') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><span class="api-doc-param">key</span> <span class="api-doc-req">{{ __('required') }}</span></td>
                                    <td>string</td>
                                    <td>{{ __('Your API key') }}</td>
                                </tr>
                                <tr>
                                    <td><span class="api-doc-param">action</span> <span class="api-doc-req">{{ __('required') }}</span></td>
                                    <td>string</td>
                                    <td><code class="api-doc-param" style="font-size:11px">add</code></td>
                                </tr>
                                <tr>
                                    <td><span class="api-doc-param">service</span> <span class="api-doc-req">{{ __('required') }}</span></td>
                                    <td>integer</td>
                                    <td>{{ __('Service ID from the services list') }}</td>
                                </tr>
                                <tr>
                                    <td><span class="api-doc-param">link</span> <span class="api-doc-req">{{ __('required') }}</span></td>
                                    <td>string</td>
                                    <td>{{ __('Target URL (http/https auto-added if omitted)') }}</td>
                                </tr>
                                <tr>
                                    <td><span class="api-doc-param">quantity</span> <span class="api-doc-req">{{ __('required') }}</span></td>
                                    <td>integer</td>
                                    <td>{{ __('Order quantity within service min/max') }}</td>
                                </tr>
                                <tr>
                                    <td><span class="api-doc-param">speed_tier</span></td>
                                    <td>string</td>
                                    <td>{{ __('Optional: normal, fast, or super_fast. Default: normal') }}</td>
                                </tr>
                                <tr>
                                    <td><span class="api-doc-param">order</span></td>
                                    <td>string</td>
                                    <td>{{ __('Your external ID for tracking (idempotent)') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="api-doc-section-label">{{ __('Example request') }}</div>
                    <pre class="api-doc-code"><code>curl -X POST {{ $providerApiUrl }} \
  -H "Content-Type: application/json" \
  -d '{{ '{"key":"YOUR_API_KEY","action":"add","service":12,"link":"https://example.com/target","quantity":500,"order":"MY-001"}' }}'</code></pre>
                </div>

                <div class="api-doc-subcard">
                    <h4>{{ __('Action: services') }} — {{ __('List available services') }}</h4>
                    <p class="api-doc-muted">{{ __('Request body:') }} <code class="api-doc-param" style="font-size:12px">{"key":"…","action":"services"}</code></p>
                    <pre class="api-doc-code"><code>curl -X POST {{ $providerApiUrl }} -H "Content-Type: application/json" \
  -d '{"key":"YOUR_API_KEY","action":"services"}'</code></pre>
                </div>

                <div class="api-doc-subcard">
                    <h4>{{ __('Action: status') }} — {{ __('Check order status') }}</h4>
                    <p class="api-doc-muted">{{ __('Request:') }} <code class="api-doc-param" style="font-size:12px">order</code> {{ __('= internal ID (number) or your external ID (string).') }}</p>
                    <pre class="api-doc-code"><code>curl -X POST {{ $providerApiUrl }} -H "Content-Type: application/json" \
  -d '{"key":"YOUR_API_KEY","action":"status","order":123456}'</code></pre>
                </div>

                <div class="api-doc-subcard">
                    <h4>{{ __('Action: balance') }} — {{ __('Check balance') }}</h4>
                    <pre class="api-doc-code"><code>curl -X POST {{ $providerApiUrl }} -H "Content-Type: application/json" \
  -d '{"key":"YOUR_API_KEY","action":"balance"}'</code></pre>
                </div>

                <div class="api-doc-subcard">
                    <h4>{{ __('Error responses') }}</h4>
                    <p class="api-doc-muted">{{ __('Errors return JSON with an error message.') }}</p>
                    <div class="api-doc-table-wrap">
                        <table class="api-doc-table">
                            <thead>
                                <tr>
                                    <th>{{ __('HTTP status') }}</th>
                                    <th>{{ __('Example') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td>400</td><td>{{ __('Missing action / Unknown action') }}</td></tr>
                                <tr><td>401</td><td>{{ __('Missing or invalid API key') }}</td></tr>
                                <tr><td>403</td><td>{{ __('Account is suspended') }}</td></tr>
                                <tr><td>404</td><td>{{ __('Order not found') }}</td></tr>
                                <tr><td>422</td><td>{{ __('Insufficient balance / Service not found or inactive') }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-client-layout>
