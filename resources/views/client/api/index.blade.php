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
                        <div class="flex items-center gap-3" x-data="{ revealed: false, fullKey: null, loading: false }">
                            <code class="flex-1 px-3 py-2 bg-gray-100 rounded font-mono text-sm break-all" x-text="revealed && fullKey ? fullKey : '{{ $apiKeyMasked }}'"></code>
                            <button type="button"
                                    @click="if (!revealed && !fullKey) { loading=true; fetch('{{ route('client.api.reveal-key') }}', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }).then(r=>r.json()).then(d=>{fullKey=d.api_key;revealed=true;loading=false}).catch(()=>loading=false) } else { revealed=!revealed }"
                                    :disabled="loading"
                                    class="px-3 py-2 text-sm font-medium text-indigo-600 hover:text-indigo-800 disabled:opacity-50">
                                <span x-text="loading ? '...' : (revealed ? '{{ __('Hide') }}' : '{{ __('Show') }}')"></span>
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
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">{{ __('Quick Start') }}</h3>

                        <div class="space-y-6">
                            <div>
                                <h4 class="text-sm font-medium text-gray-700 mb-2">{{ __('1. Get services') }}</h4>
                                <pre class="p-4 bg-gray-900 text-gray-100 rounded text-xs overflow-x-auto"><code>GET {{ $baseUrl }}/services
Authorization: Bearer YOUR_API_KEY</code></pre>
                            </div>

                            <div>
                                <h4 class="text-sm font-medium text-gray-700 mb-2">{{ __('2. Create order') }}</h4>
                                <pre class="p-4 bg-gray-900 text-gray-100 rounded text-xs overflow-x-auto"><code>POST {{ $baseUrl }}/orders
Authorization: Bearer YOUR_API_KEY
Content-Type: application/json

{
  "external_order_id": "ORDER-1001",
  "service": 12,
  "link": "https://t.me/examplechannel",
  "quantity": 500
}</code></pre>
                            </div>

                            <div>
                                <h4 class="text-sm font-medium text-gray-700 mb-2">{{ __('3. Check status') }}</h4>
                                <pre class="p-4 bg-gray-900 text-gray-100 rounded text-xs overflow-x-auto"><code>GET {{ $baseUrl }}/orders/ORDER-1001
Authorization: Bearer YOUR_API_KEY</code></pre>
                            </div>

                            <div>
                                <h4 class="text-sm font-medium text-gray-700 mb-2">{{ __('4. Multiple statuses') }}</h4>
                                <pre class="p-4 bg-gray-900 text-gray-100 rounded text-xs overflow-x-auto"><code>POST {{ $baseUrl }}/orders/statuses
Authorization: Bearer YOUR_API_KEY
Content-Type: application/json

{
  "orders": ["ORDER-1001", "ORDER-1002"]
}</code></pre>
                            </div>
                        </div>

                        <p class="mt-4 text-sm text-gray-500">{{ __('Status values: validating, awaiting, in_progress, completed, canceled, failed') }}</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-client-layout>
