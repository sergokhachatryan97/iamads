<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Create Order') }}</h2>
            <a href="{{ route('staff.orders.index') }}" class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-white bg-gray-700 border border-gray-300 rounded-md hover:bg-gray-800 transition-colors shadow-sm">
                <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                {{ __('Back to Orders') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            @if($errors->any())
                <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                    @foreach($errors->all() as $error)
                        <p class="text-sm text-red-700">{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900" @client-changed.window="fetchClientRates($event.detail?.clientId || '')" x-data="{
                    purpose: '{{ old('order_purpose', 'refill') }}',
                    categoryId: '{{ old('category_id', '') }}',
                    serviceId: '{{ old('service_id', '') }}',
                    targets: [{ link: '', quantity: 1000 }],
                    categories: @js($categories->map(fn ($c) => [
                        'id' => $c->id, 'name' => $c->name,
                        'icon' => $c->icon ?? '',
                        'iconType' => !$c->icon ? 'none' : (str_starts_with($c->icon, 'data:') || str_starts_with($c->icon, 'http') ? 'img' : (str_starts_with($c->icon, '<svg') ? 'svg' : ((preg_match('/^fa[srlbdt] /', $c->icon)) ? 'fa' : 'text'))),
                        'services' => $c->services->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'min_quantity' => $s->min_quantity ?? 100, 'max_quantity' => $s->max_quantity ?? 100000, 'rate_per_1000' => (float) ($s->rate_per_1000 ?? 0), 'default_rate' => (float) ($s->rate_per_1000 ?? 0)]),
                    ])),
                    clientRatesLoading: false,
                    get selectedService() {
                        if (!this.serviceId) return null;
                        for (const c of this.categories) { const s = c.services.find(s => s.id == this.serviceId); if (s) return s; }
                        return null;
                    },
                    addTarget() { this.targets.push({ link: '', quantity: this.selectedService?.min_quantity || 1000 }); },
                    removeTarget(i) { if (this.targets.length > 1) this.targets.splice(i, 1); },
                    async fetchClientRates(clientId) {
                        if (!clientId) {
                            this.categories.forEach(c => c.services.forEach(s => { s.rate_per_1000 = s.default_rate; }));
                            return;
                        }
                        this.clientRatesLoading = true;
                        try {
                            const resp = await fetch(`{{ route('staff.orders.client-rates') }}?client_id=${clientId}`);
                            const data = await resp.json();
                            if (data.rates) {
                                this.categories.forEach(c => c.services.forEach(s => {
                                    s.rate_per_1000 = data.rates[s.id] !== undefined ? data.rates[s.id] : s.default_rate;
                                }));
                            }
                        } catch (e) {
                            console.error('Error fetching client rates:', e);
                        } finally {
                            this.clientRatesLoading = false;
                        }
                    },
                }">
                    <form method="POST" action="{{ route('staff.orders.store') }}">
                        @csrf

                        <!-- Order Purpose -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-3">{{ __('Order Purpose') }}</label>
                            <div class="flex gap-4">
                                <label class="relative flex items-center gap-3 px-4 py-3 border rounded-lg cursor-pointer transition-colors"
                                    :class="purpose === 'refill' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-300 hover:bg-gray-50'">
                                    <input type="radio" name="order_purpose" value="refill" x-model="purpose" class="text-indigo-600 focus:ring-indigo-500">
                                    <div>
                                        <div class="text-sm font-semibold text-gray-900">{{ __('Refill') }}</div>
                                        <div class="text-xs text-gray-500">{{ __('Refill order for a client') }}</div>
                                    </div>
                                </label>
                                <label class="relative flex items-center gap-3 px-4 py-3 border rounded-lg cursor-pointer transition-colors"
                                    :class="purpose === 'test' ? 'border-yellow-500 bg-yellow-50' : 'border-gray-300 hover:bg-gray-50'">
                                    <input type="radio" name="order_purpose" value="test" x-model="purpose" class="text-yellow-600 focus:ring-yellow-500">
                                    <div>
                                        <div class="text-sm font-semibold text-gray-900">{{ __('Test') }}</div>
                                        <div class="text-xs text-gray-500">{{ __('Test order, no balance charge') }}</div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Client (searchable) -->
                        <div class="mb-6 relative" x-data="{
                            cOpen: false, cSearch: '',
                            clientId: '{{ old('client_id', '') }}',
                            clients: @js($clients->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'email' => $c->email, 'balance' => number_format((float)$c->balance, 2)])),
                            get selectedClient() { return this.clients.find(c => String(c.id) === String(this.clientId)); },
                            get filteredClients() { const q = this.cSearch.toLowerCase(); if (!q) return this.clients; return this.clients.filter(c => c.name.toLowerCase().includes(q) || c.email.toLowerCase().includes(q) || String(c.id).includes(q)); },
                            pickClient(id) { this.clientId = String(id); this.cOpen = false; this.cSearch = ''; $dispatch('client-changed', { clientId: id }); }
                        }" @click.outside="cOpen = false">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ __('Client') }}
                                <span x-show="purpose === 'refill'" class="text-red-500">*</span>
                                <span x-show="purpose === 'test'" class="text-gray-400 text-xs">({{ __('optional') }})</span>
                            </label>
                            <input type="hidden" name="client_id" :value="clientId">
                            <button type="button" @click="cOpen = !cOpen; $nextTick(() => cOpen && $refs.cInput.focus())"
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-md shadow-sm text-sm text-left bg-white hover:border-indigo-400 flex items-center justify-between gap-2 transition-colors"
                                :class="cOpen ? 'ring-2 ring-indigo-500 border-indigo-500' : ''">
                                <span class="truncate" :class="clientId ? 'text-gray-900' : 'text-gray-400'"
                                    x-text="clientId ? '#' + selectedClient?.id + ' — ' + selectedClient?.name + ' ($' + selectedClient?.balance + ')' : '{{ __('Select client...') }}'"></span>
                                <svg class="w-4 h-4 text-gray-400 flex-shrink-0 transition-transform" :class="cOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="cOpen" x-transition.opacity x-cloak class="absolute z-50 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg overflow-hidden">
                                <div class="p-2 border-b border-gray-100">
                                    <input type="text" x-ref="cInput" x-model="cSearch" @click.stop placeholder="{{ __('Search by name, email or ID...') }}"
                                        class="w-full px-3 py-2 border border-gray-200 rounded-md text-sm focus:ring-indigo-500 focus:border-indigo-500 bg-gray-50">
                                </div>
                                <div class="overflow-y-auto" style="max-height: 250px;">
                                    <button type="button" @click="pickClient('')" class="w-full px-3 py-2 text-sm text-left hover:bg-indigo-50" :class="!clientId ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-600'">{{ __('No client') }}</button>
                                    <template x-for="c in filteredClients" :key="c.id">
                                        <button type="button" @click="pickClient(c.id)" class="w-full px-3 py-2.5 text-sm text-left hover:bg-indigo-50 flex items-center justify-between gap-2" :class="String(clientId) === String(c.id) ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-700'">
                                            <span class="flex items-center gap-2 min-w-0"><span class="text-xs font-bold text-indigo-400" x-text="'ID' + c.id"></span><span class="truncate" x-text="c.name"></span><span class="text-xs text-gray-400 truncate" x-text="c.email"></span></span>
                                            <span class="text-xs font-semibold text-green-600 flex-shrink-0" x-text="'$' + c.balance"></span>
                                        </button>
                                    </template>
                                    <div x-show="filteredClients.length === 0" class="px-3 py-3 text-sm text-gray-400 text-center">{{ __('No clients found') }}</div>
                                </div>
                            </div>
                            @error('client_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <!-- Social Platform (searchable) -->
                        <div class="mb-6 relative" x-data="{ pOpen: false, pSearch: '',
                            get filteredPlatforms() { const q = this.pSearch.toLowerCase(); if (!q) return categories; return categories.filter(c => c.name.toLowerCase().includes(q)); },
                            pickPlatform(id) { categoryId = String(id); serviceId = ''; this.pOpen = false; this.pSearch = ''; }
                        }" @click.outside="pOpen = false">
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Social Media') }} <span class="text-red-500">*</span></label>
                            <button type="button" @click="pOpen = !pOpen; $nextTick(() => pOpen && $refs.pInput.focus())"
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-md shadow-sm text-sm text-left bg-white hover:border-indigo-400 flex items-center justify-between gap-2 transition-colors"
                                :class="pOpen ? 'ring-2 ring-indigo-500 border-indigo-500' : ''">
                                @php $iconSnippet = '
                                    <template x-if="__CAT__?.iconType === \'img\'"><img :src="__CAT__?.icon" class="w-5 h-5 object-contain flex-shrink-0"></template>
                                    <template x-if="__CAT__?.iconType === \'svg\'"><span class="w-5 h-5 flex-shrink-0 inline-flex items-center" x-html="__CAT__?.icon"></span></template>
                                    <template x-if="__CAT__?.iconType === \'fa\'"><i :class="__CAT__?.icon" class="w-5 text-center flex-shrink-0"></i></template>
                                    <template x-if="__CAT__?.iconType === \'text\'"><span class="w-5 text-center flex-shrink-0" x-text="__CAT__?.icon"></span></template>
                                '; @endphp
                                <span class="flex items-center gap-2 truncate" :class="categoryId ? 'text-gray-900' : 'text-gray-400'">
                                    {!! str_replace('__CAT__', 'categories.find(c => c.id == categoryId)', $iconSnippet) !!}
                                    <span x-text="categoryId ? categories.find(c => c.id == categoryId)?.name : '{{ __('Select platform...') }}'"></span>
                                </span>
                                <svg class="w-4 h-4 text-gray-400 flex-shrink-0 transition-transform" :class="pOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="pOpen" x-transition.opacity x-cloak class="absolute z-50 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg overflow-hidden">
                                <div class="p-2 border-b border-gray-100">
                                    <input type="text" x-ref="pInput" x-model="pSearch" @click.stop placeholder="{{ __('Search platform...') }}"
                                        class="w-full px-3 py-2 border border-gray-200 rounded-md text-sm focus:ring-indigo-500 focus:border-indigo-500 bg-gray-50">
                                </div>
                                <div class="overflow-y-auto" style="max-height: 250px;">
                                    <template x-for="cat in filteredPlatforms" :key="cat.id">
                                        <button type="button" @click="pickPlatform(cat.id)" class="w-full px-3 py-2.5 text-sm text-left hover:bg-indigo-50 flex items-center gap-2" :class="String(categoryId) === String(cat.id) ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-700'">
                                            {!! str_replace('__CAT__', 'cat', $iconSnippet) !!}
                                            <span x-text="cat.name"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <!-- Service (grouped, searchable) — only shows when platform selected -->
                        <div class="mb-6 relative" x-show="categoryId" x-data="{ sOpen: false, sSearch: '',
                            get selectedServiceName() { if (!serviceId) return ''; for (const c of categories) { const s = c.services.find(s => String(s.id) === String(serviceId)); if (s) return s.name; } return ''; },
                            get filteredCats() { const q = this.sSearch.toLowerCase(); let src = categories.filter(c => String(c.id) === String(categoryId)); if (!q) return src; return src.map(c => ({...c, services: c.services.filter(s => s.name.toLowerCase().includes(q) || String(s.id).includes(q))})).filter(c => c.services.length > 0); },
                            pickSvc(svc) { serviceId = String(svc.id); categoryId = String(categories.find(c => c.services.some(s => s.id == svc.id))?.id || ''); this.sOpen = false; this.sSearch = ''; }
                        }" @click.outside="sOpen = false">
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Service') }} <span class="text-red-500">*</span></label>
                            <input type="hidden" name="service_id" :value="serviceId">
                            <input type="hidden" name="category_id" :value="categoryId">
                            <button type="button" @click="sOpen = !sOpen; $nextTick(() => sOpen && $refs.sInput.focus())"
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-md shadow-sm text-sm text-left bg-white hover:border-indigo-400 flex items-center justify-between gap-2 transition-colors"
                                :class="sOpen ? 'ring-2 ring-indigo-500 border-indigo-500' : ''">
                                <span class="flex items-center gap-2 min-w-0">
                                    <span x-show="serviceId" class="text-xs font-bold text-indigo-500 flex-shrink-0" x-text="'ID ' + serviceId"></span>
                                    <span class="truncate" :class="serviceId ? 'text-gray-900' : 'text-gray-400'" x-text="serviceId ? selectedServiceName : '{{ __('Select service...') }}'"></span>
                                </span>
                                <svg class="w-4 h-4 text-gray-400 flex-shrink-0 transition-transform" :class="sOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="sOpen" x-transition.opacity x-cloak class="absolute z-50 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg overflow-hidden">
                                <div class="p-2 border-b border-gray-100">
                                    <input type="text" x-ref="sInput" x-model="sSearch" @click.stop placeholder="{{ __('Search services...') }}"
                                        class="w-full px-3 py-2 border border-gray-200 rounded-md text-sm focus:ring-indigo-500 focus:border-indigo-500 bg-gray-50">
                                </div>
                                <div class="overflow-y-auto" style="max-height: 350px;">
                                    <template x-for="cat in filteredCats" :key="cat.id">
                                        <div>
                                            <div class="px-3 py-1.5 text-xs font-bold text-indigo-600 uppercase tracking-wider bg-gray-50 border-y border-gray-100 sticky top-0" x-text="cat.name"></div>
                                            <template x-for="svc in cat.services" :key="svc.id">
                                                <button type="button" @click="pickSvc(svc)" class="w-full px-3 py-2.5 text-sm text-left hover:bg-indigo-50 flex items-center justify-between gap-3 transition-colors" :class="String(serviceId) === String(svc.id) ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-700'">
                                                    <span class="flex items-center gap-2 min-w-0"><span class="text-xs font-bold text-indigo-400 flex-shrink-0" x-text="'ID ' + svc.id"></span><span x-text="svc.name"></span></span>
                                                    <span class="text-xs font-semibold flex-shrink-0 px-2 py-0.5 rounded-full border"
                                                        :class="svc.rate_per_1000 !== svc.default_rate ? 'text-orange-600 border-orange-200 bg-orange-50' : 'text-teal-600 border-teal-200 bg-teal-50'"
                                                        x-text="'$' + svc.rate_per_1000.toFixed(2) + ' / 1000'"></span>
                                                </button>
                                            </template>
                                        </div>
                                    </template>
                                    <div x-show="filteredCats.length === 0" class="px-3 py-4 text-sm text-gray-400 text-center">{{ __('No matching services') }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Links + Quantity (multi-row) -->
                        <div class="mb-6" x-data="{
                            fileLinksCount: 0,
                            handleFile(event) {
                                const file = event.target.files[0];
                                if (!file) return;
                                const reader = new FileReader();
                                reader.onload = (e) => {
                                    const lines = e.target.result.split(/\r?\n/).map(l => l.trim()).filter(l => l.length > 0);
                                    if (lines.length === 0) return;
                                    const qty = targets[0]?.quantity || selectedService?.min_quantity || 1000;
                                    targets.splice(0, targets.length, ...lines.map(link => ({ link, quantity: qty })));
                                    this.fileLinksCount = lines.length;
                                };
                                reader.readAsText(file);
                                event.target.value = '';
                            }
                        }">
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-sm font-medium text-gray-700">{{ __('Links') }} <span class="text-red-500">*</span></label>
                                <div class="flex items-center gap-2">
                                    <input type="file" accept=".txt,.csv" @change="handleFile($event)" class="hidden" x-ref="fileInput">
                                    <div class="flex flex-col items-end">
                                        <button type="button" @click="$refs.fileInput.click()"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-800 hover:bg-indigo-50 rounded-md transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                            {{ __('Upload File') }}
                                        </button>
                                        <span class="text-[10px] text-gray-400 mt-0.5">.txt, .csv — {{ __('one link per line') }}</span>
                                    </div>
                                    <button type="button" @click="addTarget()"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-800 hover:bg-indigo-50 rounded-md transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                        {{ __('Add Link') }}
                                    </button>
                                </div>
                            </div>

                            <!-- File upload info bar -->
                            <div x-show="fileLinksCount > 0" x-cloak class="mb-3 flex items-center justify-between p-2 bg-green-50 border border-green-200 rounded-lg">
                                <p class="text-sm text-green-700 font-medium">
                                    <span x-text="fileLinksCount"></span> {{ __('link(s) loaded from file') }}
                                </p>
                                <button type="button" @click="fileLinksCount = 0; targets.splice(0, targets.length, { link: '', quantity: selectedService?.min_quantity || 1000 });"
                                    class="text-xs text-red-500 hover:text-red-700 font-medium" title="{{ __('Clear') }}">
                                    <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    {{ __('Clear') }}
                                </button>
                            </div>

                            <div class="space-y-3" :class="fileLinksCount > 0 ? 'max-h-60 overflow-y-auto pr-1' : ''">
                                <template x-for="(target, index) in targets" :key="index">
                                    <div class="flex items-start gap-2">
                                        <div class="flex-1">
                                            <input type="text" :name="'targets[' + index + '][link]'" x-model="target.link" required
                                                placeholder="https://t.me/channel"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                        </div>
                                        <div class="w-28">
                                            <input type="number" :name="'targets[' + index + '][quantity]'" x-model.number="target.quantity" required min="1"
                                                :min="selectedService?.min_quantity || 1"
                                                :max="selectedService?.max_quantity || 1000000"
                                                placeholder="{{ __('Qty') }}"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                        </div>
                                        <button type="button" @click="removeTarget(index); fileLinksCount = Math.max(0, fileLinksCount - 1);" x-show="targets.length > 1"
                                            class="mt-1 p-1.5 text-gray-400 hover:text-red-500 transition-colors" title="{{ __('Remove') }}">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                </template>
                            </div>
                            <p class="mt-2 text-xs text-gray-500" x-show="selectedService">
                                {{ __('Min') }}: <span x-text="selectedService?.min_quantity"></span> |
                                {{ __('Max') }}: <span x-text="selectedService?.max_quantity"></span>
                            </p>
                        </div>

                        <!-- Info boxes -->
                        <div x-show="purpose === 'test'" class="mb-6 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                            <p class="text-sm text-yellow-800"><strong>{{ __('Test Order') }}:</strong> {{ __('No balance will be deducted. The order will be marked as test.') }}</p>
                        </div>
                        <div x-show="purpose === 'refill'" class="mb-6 p-3 bg-indigo-50 border border-indigo-200 rounded-lg">
                            <p class="text-sm text-indigo-800"><strong>{{ __('Refill Order') }}:</strong> {{ __('No balance will be deducted. The order will be assigned to the selected client.') }}</p>
                        </div>

                        <!-- Submit -->
                        <div class="flex items-center justify-end gap-3">
                            <a href="{{ route('staff.orders.index') }}" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">{{ __('Cancel') }}</a>
                            <button type="submit" class="inline-flex items-center px-6 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors">{{ __('Create Order') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
