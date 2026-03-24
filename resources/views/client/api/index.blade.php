<x-client-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('API Access') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="p-4 rounded-lg {{ session('status') === 'api-key-regenerated' ? 'bg-amber-50 border border-amber-200 text-amber-800' : 'bg-green-50 border border-green-200 text-green-800' }}">
                    <p class="text-sm">
                        @if (session('status') === 'api-enabled')
                            {{ __('API access enabled. Your API key is shown below.') }}
                        @elseif (session('status') === 'api-disabled')
                            {{ __('API access disabled.') }}
                        @elseif (session('status') === 'api-key-regenerated')
                            {{ __('API key regenerated. Your old key is no longer valid.') }}
                        @endif
                    </p>
                </div>
            @endif

            @if (session('error'))
                <div class="p-4 bg-red-50 border border-red-200 rounded-lg text-red-800 text-sm">
                    {{ session('error') }}
                </div>
            @endif

            <!-- API Status -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">{{ __('API Access') }}</h3>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">{{ __('Use the same account to place orders via API. Balance is shared between web and API orders.') }}</p>
                            <p class="mt-2 text-sm font-medium {{ $client->api_enabled ? 'text-green-600' : 'text-gray-500' }}">
                                {{ $client->api_enabled ? __('Enabled') : __('Disabled') }}
                            </p>
                        </div>
                        <form method="POST" action="{{ route('client.api.toggle') }}" class="inline">
                            @csrf
                            <input type="hidden" name="api_enabled" value="{{ $client->api_enabled ? '0' : '1' }}">
                            <button type="submit" class="px-4 py-2 rounded-md text-sm font-medium {{ $client->api_enabled ? 'bg-gray-200 text-gray-700 hover:bg-gray-300' : 'bg-indigo-600 text-white hover:bg-indigo-700' }}">
                                {{ $client->api_enabled ? __('Disable') : __('Enable API') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            @if ($client->api_enabled)
                <!-- API Key -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-2">{{ __('API Key') }}</h3>
                        <p class="text-sm text-gray-600 mb-4">{{ __('Use this key in the Authorization header: Bearer &lt;api_key&gt;') }}</p>
                        <div class="flex items-center gap-3 flex-wrap" x-data="{
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
                            }
                        }">
                            <code class="flex-1 min-w-0 px-3 py-2 bg-gray-100 rounded font-mono text-sm break-all" x-text="revealed && fullKey ? fullKey : '{{ $apiKeyMasked }}'"></code>
                            <button type="button"
                                    @click="if (!revealed && !fullKey) { loading=true; fetch('{{ route('client.api.reveal-key') }}', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }).then(r=>r.json()).then(d=>{fullKey=d.api_key;revealed=true;loading=false}).catch(()=>loading=false) } else { revealed=!revealed }"
                                    :disabled="loading"
                                    class="px-3 py-2 text-sm font-medium text-indigo-600 hover:text-indigo-800 disabled:opacity-50">
                                <span x-text="loading ? '...' : (revealed ? '{{ __('Hide') }}' : '{{ __('Show') }}')"></span>
                            </button>
                            <button type="button"
                                    @click="copyKey()"
                                    :disabled="loading"
                                    class="px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 disabled:opacity-50">
                                <span x-text="copied ? '{{ __('Copied!') }}' : '{{ __('Copy') }}'"></span>
                            </button>
                            <form method="POST" action="{{ route('client.api.regenerate') }}" class="inline" onsubmit="return confirm('{{ __('Regenerating will invalidate your current key. Continue?') }}');">
                                @csrf
                                <button type="submit" class="px-3 py-2 text-sm font-medium text-amber-600 hover:text-amber-800">{{ __('Regenerate') }}</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Base URL -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-2">{{ __('Base URL') }}</h3>
                        <code class="block px-3 py-2 bg-gray-100 rounded font-mono text-sm break-all">{{ $baseUrl }}</code>
                    </div>
                </div>

                <!-- Quick Start -->
{{--                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">--}}
{{--                    <div class="p-6">--}}
{{--                        <h3 class="text-lg font-medium text-gray-900 mb-4">{{ __('Quick Start') }}</h3>--}}

{{--                        <div class="space-y-6">--}}
{{--                            <div>--}}
{{--                                <h4 class="text-sm font-medium text-gray-700 mb-2">{{ __('1. Get services') }}</h4>--}}
{{--                                <pre class="p-4 bg-gray-900 text-gray-100 rounded text-xs overflow-x-auto"><code>GET {{ $baseUrl }}/services--}}
{{--Authorization: Bearer YOUR_API_KEY</code></pre>--}}
{{--                            </div>--}}

{{--                            <div>--}}
{{--                                <h4 class="text-sm font-medium text-gray-700 mb-2">{{ __('2. Create order') }}</h4>--}}
{{--                                <pre class="p-4 bg-gray-900 text-gray-100 rounded text-xs overflow-x-auto"><code>POST {{ $baseUrl }}/orders--}}
{{--Authorization: Bearer YOUR_API_KEY--}}
{{--Content-Type: application/json--}}

{{--{--}}
{{--  "external_order_id": "ORDER-1001",--}}
{{--  "service": 12,--}}
{{--  "link": "https://t.me/examplechannel",--}}
{{--  "quantity": 500--}}
{{--}</code></pre>--}}
{{--                            </div>--}}

{{--                            <div>--}}
{{--                                <h4 class="text-sm font-medium text-gray-700 mb-2">{{ __('3. Check status') }}</h4>--}}
{{--                                <pre class="p-4 bg-gray-900 text-gray-100 rounded text-xs overflow-x-auto"><code>GET {{ $baseUrl }}/orders/ORDER-1001--}}
{{--Authorization: Bearer YOUR_API_KEY</code></pre>--}}
{{--                            </div>--}}

{{--                            <div>--}}
{{--                                <h4 class="text-sm font-medium text-gray-700 mb-2">{{ __('4. Multiple statuses') }}</h4>--}}
{{--                                <pre class="p-4 bg-gray-900 text-gray-100 rounded text-xs overflow-x-auto"><code>POST {{ $baseUrl }}/orders/statuses--}}
{{--Authorization: Bearer YOUR_API_KEY--}}
{{--Content-Type: application/json--}}

{{--{--}}
{{--  "orders": ["ORDER-1001", "ORDER-1002"]--}}
{{--}</code></pre>--}}
{{--                            </div>--}}
{{--                        </div>--}}

{{--                        <p class="mt-4 text-sm text-gray-500">{{ __('Status values: validating, awaiting, in_progress, completed, canceled, failed') }}</p>--}}
{{--                    </div>--}}
{{--                </div>--}}

                <!-- Provider API (Socpanel / Perfect Panel) - Full Documentation -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-2">{{ __('Quick Start') }}</h3>
                        <p class="text-sm text-gray-600 mb-6">{{ __('Single-endpoint API for reseller panels and automation. Include your API key in the request body as') }} <code class="px-1 py-0.5 bg-gray-100 rounded text-xs">key</code>. {{ __('All requests use the same URL with different action values.') }}</p>

                        <div class="space-y-6">
                            <!-- Endpoint -->
                            <div>
                                <h4 class="text-sm font-semibold text-gray-800 mb-2">{{ __('Endpoint') }}</h4>
                                <pre class="p-4 bg-gray-900 text-gray-100 rounded text-xs overflow-x-auto"><code>POST {{ $providerApiUrl }}
Content-Type: application/json</code></pre>
                            </div>

                            <!-- Authentication -->
                            <div>
                                <h4 class="text-sm font-semibold text-gray-800 mb-2">{{ __('Authentication') }}</h4>
                                <p class="text-sm text-gray-600 mb-2">{{ __('Include your API key in every request body:') }}</p>
                                <pre class="p-4 bg-gray-50 border border-gray-200 rounded text-xs overflow-x-auto"><code>"key": "your_api_key"</code></pre>
                                <p class="text-sm text-gray-500 mt-1">{{ __('Your account must have API enabled and must not be suspended.') }}</p>
                            </div>

                            <!-- Action: services -->
                            <div>
                                <h4 class="text-sm font-semibold text-gray-800 mb-2">{{ __('Action: services') }} – {{ __('List Available Services') }}</h4>
                                <p class="text-sm text-gray-600 mb-2">{{ __('Request:') }}</p>
                                <pre class="p-4 bg-gray-50 border border-gray-200 rounded text-xs overflow-x-auto mb-2"><code>{"key": "your_api_key", "action": "services"}</code></pre>
                                <p class="text-sm text-gray-600 mb-1">{{ __('Response:') }}</p>
                                <pre class="p-4 bg-gray-50 border border-gray-200 rounded text-xs overflow-x-auto"><code>[
  {"service": 1, "name": "YouTube Views", "type": "default", "rate": "1.20", "min": "100", "max": "10000"},
  {"service": 2, "name": "Telegram Members", "type": "default", "rate": "2.50", "min": "50", "max": "5000"}
]</code></pre>
                                <p class="text-xs text-gray-500 mt-1">{{ __('Use the service ID from the response when creating orders.') }}</p>
                            </div>

                            <!-- Action: add -->
                            <div>
                                <h4 class="text-sm font-semibold text-gray-800 mb-2">{{ __('Action: add') }} – {{ __('Create Order') }}</h4>
                                <p class="text-sm text-gray-600 mb-2">{{ __('Request:') }}</p>
                                <pre class="p-4 bg-gray-50 border border-gray-200 rounded text-xs overflow-x-auto mb-3"><code>{
  "key": "your_api_key",
  "action": "add",
  "service": 123,
  "link": "https://example.com/your-content",
  "quantity": 1000,
  "speed_tier": "normal",
  "order": "YOUR-EXTERNAL-ID-123"
}</code></pre>
                                <div class="overflow-x-auto mb-2">
                                    <table class="min-w-full text-sm">
                                        <thead><tr class="border-b"><th class="text-left py-1 pr-4 font-medium text-gray-700">{{ __('Field') }}</th><th class="text-left py-1 pr-4 font-medium text-gray-700">{{ __('Required') }}</th><th class="text-left py-1 font-medium text-gray-700">{{ __('Description') }}</th></tr></thead>
                                        <tbody class="text-gray-600">
                                            <tr class="border-b border-gray-100"><td class="py-1.5 pr-4 font-mono text-xs">service</td><td class="py-1.5 pr-4">{{ __('Yes') }}</td><td class="py-1.5">{{ __('Service ID from services list') }}</td></tr>
                                            <tr class="border-b border-gray-100"><td class="py-1.5 pr-4 font-mono text-xs">link</td><td class="py-1.5 pr-4">{{ __('Yes') }}</td><td class="py-1.5">{{ __('Target URL (http/https auto-added if omitted)') }}</td></tr>
                                            <tr class="border-b border-gray-100"><td class="py-1.5 pr-4 font-mono text-xs">quantity</td><td class="py-1.5 pr-4">{{ __('Yes') }}</td><td class="py-1.5">{{ __('Order quantity within service min/max') }}</td></tr>
                                            <tr class="border-b border-gray-100"><td class="py-1.5 pr-4 font-mono text-xs">speed_tier</td><td class="py-1.5 pr-4">{{ __('No') }}</td><td class="py-1.5">{{ __('normal, fast, or super_fast. Default: normal') }}</td></tr>
                                            <tr class="border-b border-gray-100"><td class="py-1.5 pr-4 font-mono text-xs">order</td><td class="py-1.5 pr-4">{{ __('No') }}</td><td class="py-1.5">{{ __('Your external ID for tracking. Same value = no duplicate (idempotent)') }}</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                                <p class="text-sm text-gray-600 mb-1">{{ __('Response:') }}</p>
                                <pre class="p-4 bg-gray-50 border border-gray-200 rounded text-xs overflow-x-auto"><code>{"order": 123456}</code></pre>
                                <p class="text-xs text-gray-500 mt-1">{{ __('Returns internal order ID. Use it or your external order value for status checks.') }}</p>
                            </div>

                            <!-- Action: status -->
                            <div>
                                <h4 class="text-sm font-semibold text-gray-800 mb-2">{{ __('Action: status') }} – {{ __('Check Order Status') }}</h4>
                                <p class="text-sm text-gray-600 mb-2">{{ __('Request:') }}</p>
                                <pre class="p-4 bg-gray-50 border border-gray-200 rounded text-xs overflow-x-auto mb-2"><code>{"key": "your_api_key", "action": "status", "order": 123456}</code></pre>
                                <p class="text-xs text-gray-500 mb-2">{{ __('Use internal order ID (number) or your external order ID (string).') }}</p>
                                <p class="text-sm text-gray-600 mb-1">{{ __('Response:') }}</p>
                                <pre class="p-4 bg-gray-50 border border-gray-200 rounded text-xs overflow-x-auto mb-2"><code>{
  "charge": "1.20",
  "start_count": "5000",
  "status": "Completed",
  "remains": "0"
}</code></pre>
                                <p class="text-xs text-gray-500">{{ __('Status values: Processing, Pending, In progress, Completed, Canceled, Failed') }}</p>
                            </div>

                            <!-- Action: balance -->
                            <div>
                                <h4 class="text-sm font-semibold text-gray-800 mb-2">{{ __('Action: balance') }} – {{ __('Check Balance') }}</h4>
                                <p class="text-sm text-gray-600 mb-2">{{ __('Request:') }}</p>
                                <pre class="p-4 bg-gray-50 border border-gray-200 rounded text-xs overflow-x-auto mb-2"><code>{"key": "your_api_key", "action": "balance"}</code></pre>
                                <p class="text-sm text-gray-600 mb-1">{{ __('Response:') }}</p>
                                <pre class="p-4 bg-gray-50 border border-gray-200 rounded text-xs overflow-x-auto"><code>{"balance": "100.50", "currency": "USD"}</code></pre>
                            </div>

                            <!-- Error Responses -->
                            <div>
                                <h4 class="text-sm font-semibold text-gray-800 mb-2">{{ __('Error Responses') }}</h4>
                                <p class="text-sm text-gray-600 mb-2">{{ __('All errors return JSON with an error field:') }}</p>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-sm">
                                        <thead><tr class="border-b"><th class="text-left py-1 pr-4 font-medium text-gray-700">{{ __('Status') }}</th><th class="text-left py-1 font-medium text-gray-700">{{ __('Example') }}</th></tr></thead>
                                        <tbody class="text-gray-600">
                                            <tr class="border-b border-gray-100"><td class="py-1.5 pr-4">400</td><td class="py-1.5">Missing action / Unknown action</td></tr>
                                            <tr class="border-b border-gray-100"><td class="py-1.5 pr-4">401</td><td class="py-1.5">Missing or invalid API key</td></tr>
                                            <tr class="border-b border-gray-100"><td class="py-1.5 pr-4">403</td><td class="py-1.5">Account is suspended</td></tr>
                                            <tr class="border-b border-gray-100"><td class="py-1.5 pr-4">404</td><td class="py-1.5">Order not found</td></tr>
                                            <tr class="border-b border-gray-100"><td class="py-1.5 pr-4">422</td><td class="py-1.5">Insufficient balance. Please top up. / Service not found or inactive.</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- cURL Examples -->
                            <div>
                                <h4 class="text-sm font-semibold text-gray-800 mb-2">{{ __('Example cURL') }}</h4>
                                <pre class="p-4 bg-gray-900 text-gray-100 rounded text-xs overflow-x-auto"><code># List services
curl -X POST {{ $providerApiUrl }} -H "Content-Type: application/json" \
  -d '{"key":"your_api_key","action":"services"}'

# Create order
curl -X POST {{ $providerApiUrl }} -H "Content-Type: application/json" \
  -d '{"key":"your_api_key","action":"add","service":12,"link":"https://youtube.com/watch?v=xxx","quantity":500,"order":"MY-001"}'

# Check status
curl -X POST {{ $providerApiUrl }} -H "Content-Type: application/json" \
  -d '{"key":"your_api_key","action":"status","order":123456}'

# Check balance
curl -X POST {{ $providerApiUrl }} -H "Content-Type: application/json" \
  -d '{"key":"your_api_key","action":"balance"}'</code></pre>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-client-layout>
