<x-client-layout :title="__('Services')">
    @php
        $svcCurrentCategoryId = request('category_id', '');
        $svcCurrentFavoritesOnly = request('favorites_only') === '1' ? '1' : '0';
        $svcCurrentSearch = request('search', '');
        $svcCurrentMin = request('min', '');
        $svcCurrentMax = request('max', '');
        $svcFiltersOpenInitial = ($svcCurrentMin !== '' && $svcCurrentMin !== null) || ($svcCurrentMax !== '' && $svcCurrentMax !== null);
        $serviceConfig = [
            'filtersOpen' => $svcFiltersOpenInitial,
            'sort' => request('sort', 'id'),
            'dir' => request('dir', 'asc'),
            'favoriteServiceIds' => $favoriteServiceIds ?? [],
            'categoriesList' => isset($categoriesList) ? $categoriesList->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values()->all() : [],
            'activeCategoryId' => (string) $svcCurrentCategoryId,
            'activeFavoritesOnly' => $svcCurrentFavoritesOnly,
            'activeSearch' => $svcCurrentSearch,
        ];
        $serviceTabCounts = $serviceTabCounts ?? ['all' => 0, 'favorites' => 0, 'categories' => []];
    @endphp
    <style>
        /* Filter bar — same tokens as client orders (scoped to this page) */
        .client-services-page .client-orders-filter-panel {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 0.75rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            margin-bottom: 1rem;
            padding: 1rem;
            overflow: visible;
        }
        [data-theme="light"] .client-services-page .client-orders-filter-panel {
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
        }
        .client-services-page .client-orders-filter-panel .co-filter-toggle-idle {
            background: rgba(0, 0, 0, 0.22) !important;
            color: var(--text2) !important;
            border: 1px solid var(--border);
        }
        .client-services-page .client-orders-filter-panel .co-filter-toggle-idle:hover {
            background: rgba(108, 92, 231, 0.15) !important;
            color: var(--text) !important;
        }
        [data-theme="light"] .client-services-page .client-orders-filter-panel .co-filter-toggle-idle {
            background: rgba(0, 0, 0, 0.05) !important;
        }
        .client-services-page .client-orders-filter-panel .co-filter-toggle-active {
            background: var(--purple) !important;
            color: #fff !important;
            border: 1px solid var(--purple);
        }
        .client-services-page .client-orders-filter-panel .co-filter-tab-idle {
            color: var(--text3) !important;
            border: 1px solid transparent;
        }
        .client-services-page .client-orders-filter-panel .co-filter-tab-idle:hover {
            color: var(--text) !important;
            background: rgba(108, 92, 231, 0.1) !important;
        }
        .client-services-page .client-orders-filter-panel .co-filter-tab-active {
            background: var(--purple) !important;
            color: #fff !important;
            border-color: var(--purple) !important;
        }
        .client-services-page .client-orders-filter-panel .co-filter-tab-badge-idle {
            background: rgba(0, 0, 0, 0.25) !important;
            color: var(--text2) !important;
        }
        [data-theme="light"] .client-services-page .client-orders-filter-panel .co-filter-tab-badge-idle {
            background: rgba(0, 0, 0, 0.08) !important;
            color: var(--text2) !important;
        }
        .client-services-page .client-orders-filter-panel .co-filter-tab-badge-active {
            background: rgba(255, 255, 255, 0.25) !important;
            color: #fff !important;
        }
        .client-services-page .client-orders-filter-panel .co-filter-search {
            border-color: var(--border) !important;
            background: rgba(0, 0, 0, 0.22) !important;
            color: var(--text) !important;
        }
        .client-services-page .client-orders-filter-panel .co-filter-search::placeholder {
            color: var(--text3);
        }
        [data-theme="light"] .client-services-page .client-orders-filter-panel .co-filter-search {
            background: var(--card2) !important;
        }
        .client-services-page .client-orders-filter-panel .co-filter-search-icon {
            color: var(--text3) !important;
        }
        .client-services-page .client-orders-filter-panel .co-filter-search-clear {
            color: var(--text3) !important;
        }
        .client-services-page .client-orders-filter-panel .co-filter-search-clear:hover {
            background: rgba(108, 92, 231, 0.15) !important;
            color: var(--text) !important;
        }
        .client-services-page .client-orders-filter-panel .co-filter-advanced {
            border-top-color: var(--border) !important;
        }
        .client-services-page .client-orders-filter-panel .co-filter-label {
            color: var(--text3) !important;
        }
        .client-services-page .client-orders-filter-panel .co-filter-field {
            border-color: var(--border) !important;
            background: rgba(0, 0, 0, 0.22) !important;
            color: var(--text) !important;
        }
        [data-theme="light"] .client-services-page .client-orders-filter-panel .co-filter-field {
            background: var(--card2) !important;
        }
        .client-services-page .client-orders-filter-panel .co-filter-apply {
            background: var(--purple) !important;
            border: 1px solid var(--purple) !important;
            color: #fff !important;
            box-sizing: border-box;
        }
        .client-services-page .client-orders-filter-panel .co-filter-apply:hover {
            filter: brightness(1.06);
        }
        .client-services-page .client-orders-filter-panel .co-filter-reset {
            border: 1px solid var(--border) !important;
            background: rgba(0, 0, 0, 0.18) !important;
            color: var(--text2) !important;
            box-sizing: border-box;
        }
        .client-services-page .client-orders-filter-panel .co-filter-reset:hover {
            background: rgba(108, 92, 231, 0.12) !important;
            color: var(--text) !important;
        }
        [data-theme="light"] .client-services-page .client-orders-filter-panel .co-filter-reset {
            background: #fff !important;
        }
        [data-theme="dark"] .client-services-page .client-orders-filter-panel select.co-filter-field {
            color-scheme: dark;
        }
        [data-theme="dark"] .client-services-page .client-orders-filter-panel select.co-filter-field option {
            background-color: #1a1a2e;
            color: #ffffff;
        }

        .client-services-table-wrap {
            width: 100%;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 0.75rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
            overflow: hidden;
        }
        [data-theme="light"] .client-services-table-wrap {
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
        }
        .client-services-table-wrap .divide-y.divide-gray-200 > :not([hidden]) ~ :not([hidden]) {
            border-color: var(--border) !important;
        }
        .client-services-table-wrap thead.bg-gray-50 {
            background: rgba(0, 0, 0, 0.32) !important;
        }
        [data-theme="light"] .client-services-table-wrap thead.bg-gray-50 {
            background: var(--card2) !important;
        }
        .client-services-table-wrap thead th.bg-gray-50 {
            background: transparent !important;
        }
        .client-services-table-wrap thead .text-gray-500 {
            color: var(--text3) !important;
        }
        .client-services-table-wrap thead .text-gray-400 {
            color: var(--text3) !important;
        }
        .client-services-table-wrap thead .hover\:text-gray-700:hover {
            color: var(--text) !important;
        }
        [data-theme="dark"] .client-services-table-wrap thead .text-gray-300 {
            color: rgba(255, 255, 255, 0.35) !important;
        }
        [data-theme="light"] .client-services-table-wrap thead .text-gray-300 {
            color: #d1d5db !important;
        }
        .client-services-table-wrap thead .text-indigo-600 {
            color: var(--purple-light) !important;
        }
        .client-services-table-wrap tbody {
            background: var(--card) !important;
        }
        .client-services-table-wrap tbody > tr {
            border-color: var(--border) !important;
        }
        .client-services-table-wrap tbody.bg-white {
            background: var(--card) !important;
        }
        .client-services-table-wrap tr.bg-gray-50 {
            background: rgba(0, 0, 0, 0.25) !important;
        }
        [data-theme="light"] .client-services-table-wrap tr.bg-gray-50 {
            background: var(--card2) !important;
        }
        .client-services-table-wrap tr.bg-gray-50.border-b-2.border-gray-200 {
            border-color: var(--border) !important;
        }
        .client-services-table-wrap .text-gray-900 {
            color: var(--text) !important;
        }
        .client-services-table-wrap .text-gray-500 {
            color: var(--text2) !important;
        }
        .client-services-table-wrap .text-gray-400 {
            color: var(--text3) !important;
        }
        .client-services-table-wrap tbody tr.hover\:bg-gray-50:hover {
            background: rgba(108, 92, 231, 0.08) !important;
        }
        [data-theme="light"] .client-services-table-wrap tbody tr.hover\:bg-gray-50:hover {
            background: rgba(0, 0, 0, 0.04) !important;
        }
        .client-services-table-wrap .hover\:bg-gray-100:hover {
            background: rgba(108, 92, 231, 0.12) !important;
        }
        .client-services-table-wrap .text-indigo-600 {
            color: var(--purple-light) !important;
        }
        .client-services-table-wrap .border-indigo-600 {
            border-color: var(--purple) !important;
        }
        .client-services-table-wrap .hover\:bg-indigo-50:hover {
            background: rgba(108, 92, 231, 0.15) !important;
        }

        #client-service-modals-root .client-smm-modal-backdrop {
            background: rgba(0, 0, 0, 0.72) !important;
        }
        [data-theme="light"] #client-service-modals-root .client-smm-modal-backdrop {
            background: rgba(15, 23, 42, 0.45) !important;
        }
        #client-service-modals-root .client-smm-modal-panel {
            background: var(--card) !important;
            color: var(--text);
        }
        #client-service-modals-root .client-smm-modal-panel .text-gray-900,
        #client-service-modals-root .client-smm-modal-panel .text-gray-700,
        #client-service-modals-root .client-smm-modal-panel .text-gray-600,
        #client-service-modals-root .client-smm-modal-panel .text-gray-500 {
            color: var(--text) !important;
        }
        #client-service-modals-root .client-smm-modal-panel .border-gray-200,
        #client-service-modals-root .client-smm-modal-panel .border-gray-300 {
            border-color: var(--border) !important;
        }
        #client-service-modals-root .client-smm-modal-panel .bg-white {
            background: rgba(0, 0, 0, 0.2) !important;
            color: var(--text2) !important;
        }
        #client-service-modals-root .client-smm-modal-panel .hover\:bg-gray-50:hover {
            background: rgba(108, 92, 231, 0.12) !important;
        }
        [data-theme="light"] #client-service-modals-root .client-smm-modal-panel .bg-white,
        [data-theme="light"] #client-service-modals-root .client-smm-modal-panel .hover\:bg-gray-50:hover {
            background: var(--card2) !important;
        }
        #client-service-modals-root .client-smm-modal-panel .text-indigo-600 {
            color: var(--purple-light) !important;
        }
        #client-service-modals-root .client-smm-modal-panel .bg-indigo-600 {
            background: var(--purple) !important;
        }
        #client-service-modals-root .client-smm-modal-panel .hover\:bg-indigo-700:hover {
            filter: brightness(1.08);
        }
        #client-service-modals-root .client-smm-modal-panel .text-gray-400 {
            color: var(--text3) !important;
        }
        #client-service-modals-root .client-smm-modal-panel .hover\:text-gray-500:hover {
            color: var(--text2) !important;
        }

        /* ===== MOBILE SERVICE CARDS ===== */
        .cs-mobile-cards { display: none; }
        .cs-desktop-table { display: block; }

        .cs-cat-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 14px;
            background: var(--card2);
            border: 1px solid var(--border);
            border-radius: 10px;
            margin: 12px 0 6px;
        }
        .cs-cat-header:first-child { margin-top: 0; }
        .cs-cat-icon { font-size: 18px; flex-shrink: 0; color: var(--purple-light); }
        .cs-cat-name { font-size: 15px; font-weight: 700; color: var(--text); }
        .cs-svc-card {
            display: flex;
            flex-direction: column;
            gap: 4px;
            padding: 12px 14px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 10px;
            margin-bottom: 6px;
            cursor: pointer;
            transition: border-color 0.15s, background 0.15s;
        }
        .cs-svc-card:hover { border-color: rgba(108,92,231,0.3); }
        .cs-svc-card:active { background: rgba(108,92,231,0.06); }
        .cs-svc-card-top {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .cs-svc-fav {
            flex-shrink: 0;
            background: none;
            border: none;
            color: var(--text3);
            cursor: pointer;
            padding: 2px;
            transition: color 0.15s;
        }
        .cs-svc-fav-on { color: #f59e0b !important; }
        .cs-svc-name {
            flex: 1;
            min-width: 0;
            font-size: 13px;
            font-weight: 600;
            color: var(--text);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .cs-svc-arrow {
            flex-shrink: 0;
            font-size: 11px;
            color: var(--text3);
            transition: transform 0.15s;
        }
        .cs-svc-card:hover .cs-svc-arrow {
            transform: translateX(2px);
            color: var(--purple-light);
        }
        .cs-svc-card-bottom {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: var(--text2);
            padding-left: 26px;
        }
        .cs-svc-id { font-weight: 600; color: var(--text3); }
        .cs-svc-dot { color: var(--text3); font-size: 8px; }
        .cs-svc-rate { font-weight: 700; color: var(--teal); }
        .cs-svc-range { color: var(--text3); }
        .cs-mobile-empty {
            padding: 20px;
            text-align: center;
            color: var(--text3);
            font-size: 13px;
        }

        @media (max-width: 768px) {
            .cs-desktop-table { display: none !important; }
            .cs-mobile-cards { display: block !important; }
            .client-services-page .client-orders-filter-panel {
                padding: 10px; border-radius: 12px; margin-bottom: 10px;
            }
            .client-services-page .co-filter-search-box .co-filter-search {
                font-size: 14px !important;
                padding: 10px 12px 10px 36px !important;
                border-radius: 10px !important;
            }
            .client-services-page .co-filter-tabs button {
                font-size: 12px; padding: 6px 10px; border-radius: 8px;
            }
            .client-services-page .co-filter-advanced { margin-top: 10px !important; padding-top: 10px !important; }
            .client-services-page .co-filter-advanced form {
                flex-direction: column !important; gap: 10px !important;
            }
            .client-services-page .co-filter-advanced form > div { width: 100% !important; }
            .client-services-page .co-filter-field {
                font-size: 14px !important; padding: 10px 14px !important; border-radius: 10px !important;
            }
            .client-services-page .co-filter-apply,
            .client-services-page .co-filter-reset { height: 42px; font-size: 13px; border-radius: 10px; }
            .client-services-page { padding-top: 8px !important; padding-bottom: 8px !important; }
        }

        @media (max-width: 480px) {
            .client-services-page .client-orders-filter-panel { padding: 8px; border-radius: 10px; }
            .client-services-page .co-filter-search-box .co-filter-search {
                font-size: 14px !important; padding: 9px 10px 9px 34px !important;
            }
            .client-services-page .co-filter-tabs button { font-size: 11px; padding: 5px 8px; }
            .cs-cat-header { padding: 10px 12px; margin: 10px 0 4px; }
            .cs-cat-name { font-size: 14px; }
            .cs-svc-card { padding: 10px 12px; margin-bottom: 4px; }
            .cs-svc-name { font-size: 12px; }
            .cs-svc-card-bottom { font-size: 11px; padding-left: 24px; }
        }

        .client-services-loading .cs-loading-spinner {
            border-width: 2px;
            border-style: solid;
            border-color: var(--border);
            border-top-color: var(--purple);
            border-radius: 9999px;
        }
        .client-services-loading {
            color: var(--text2);
        }
    </style>
    <div class="client-services-page py-12 overflow-visible"
         x-data="serviceManagement(@js($serviceConfig))"
         @sort-table.window="sortTable($event.detail.column)"
         x-init="
             window.addEventListener('popstate', () => { window.location.reload(); });
             $nextTick(() => {
                 if (typeof this.updateActiveFilters === 'function') this.updateActiveFilters();
                 if (typeof this.initScrollSync === 'function') this.initScrollSync();
             });
         ">
        <div class="max-w-[95%] mx-auto sm:px-6 lg:px-8 overflow-visible">
            <div class="client-orders-filter-panel relative z-10"
                 @submit.capture.prevent="handleFormSubmit($event)">
                <form method="GET" action="{{ route('client.services.index') }}" id="filter-form" class="client-services-filter-form co-filter-row">
                    <input type="hidden" name="sort" :value="currentSort">
                    <input type="hidden" name="dir" :value="currentDir">
                    <input type="hidden" name="category_id" :value="activeCategoryId">
                    <input type="hidden" name="favorites_only" :value="activeFavoritesOnly">

                    {{-- Search + Filter toggle (same row) --}}
                    <div class="co-filter-search-row">
                        <button type="button"
                                @click="filtersOpen = !filtersOpen"
                                :class="filtersOpen ? 'co-filter-toggle-active' : 'co-filter-toggle-idle'"
                                class="co-filter-toggle-btn"
                                title="{{ __('Filters') }}"
                                :aria-expanded="filtersOpen">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                            </svg>
                        </button>
                        <div class="co-filter-search-box">
                            <div class="relative">
                                <svg class="co-filter-search-icon absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                                <input type="text" name="search" id="search" x-model="searchValue"
                                       @input.debounce.400ms="handleSearchDebounced()"
                                       placeholder="{{ __('Search by service name') }}"
                                       class="co-filter-search w-full rounded-full border py-2 pl-10 pr-10 text-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/40">
                                <button type="button" x-show="searchValue" x-cloak
                                        @click="searchValue = ''; syncSearchToForm(); performAjaxSearch();"
                                        class="co-filter-search-clear absolute right-3 top-1/2 -translate-y-1/2 rounded-full p-0.5 focus:outline-none focus:ring-2 focus:ring-purple-500">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Category tabs --}}
                    <div class="co-filter-tabs-row">
                        <div id="client-services-tabs-root" class="co-filter-tabs">
                            <button type="button"
                                    @click.prevent="selectServiceTab('all')"
                                    class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-medium transition-colors"
                                    :class="getServiceTab() === 'all' ? 'co-filter-tab-active' : 'co-filter-tab-idle'">
                                {{ __('All') }}
                                @if(($serviceTabCounts['all'] ?? 0) > 0)
                                    <span class="rounded-full px-1.5 py-0.5 text-xs font-bold"
                                          :class="getServiceTab() === 'all' ? 'co-filter-tab-badge-active' : 'co-filter-tab-badge-idle'">{{ number_format($serviceTabCounts['all']) }}</span>
                                @endif
                            </button>
                            <button type="button"
                                    @click.prevent="selectServiceTab('favorites')"
                                    class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-medium transition-colors"
                                    :class="getServiceTab() === 'favorites' ? 'co-filter-tab-active' : 'co-filter-tab-idle'">
                                {{ __('Favorites') }}
                                @if(($serviceTabCounts['favorites'] ?? 0) > 0)
                                    <span class="rounded-full px-1.5 py-0.5 text-xs font-bold"
                                          :class="getServiceTab() === 'favorites' ? 'co-filter-tab-badge-active' : 'co-filter-tab-badge-idle'">{{ number_format($serviceTabCounts['favorites']) }}</span>
                                @endif
                            </button>
                            @foreach($categoriesList ?? [] as $category)
                                @php $tabCount = $serviceTabCounts['categories'][$category->id] ?? 0; @endphp
                                <button type="button"
                                        @click.prevent="selectServiceTab('{{ $category->id }}')"
                                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-medium transition-colors max-w-[200px]"
                                        :class="getServiceTab() === '{{ $category->id }}' ? 'co-filter-tab-active' : 'co-filter-tab-idle'">
                                    <span class="truncate">{{ $category->name }}</span>
                                    @if($tabCount > 0)
                                        <span class="rounded-full px-1.5 py-0.5 text-xs font-bold shrink-0"
                                              :class="getServiceTab() === '{{ $category->id }}' ? 'co-filter-tab-badge-active' : 'co-filter-tab-badge-idle'">{{ number_format($tabCount) }}</span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </div>
                </form>

                <div x-show="filtersOpen"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 -translate-y-2"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100 translate-y-0"
                     x-transition:leave-end="opacity-0 -translate-y-2"
                     x-cloak
                     class="co-filter-advanced mt-4 border-t pt-4">
                    <form method="GET" action="{{ route('client.services.index') }}" class="client-services-filter-form flex w-full min-w-0 flex-wrap items-end gap-x-4 gap-y-3">
                        <input type="hidden" name="category_id" :value="activeCategoryId">
                        <input type="hidden" name="favorites_only" :value="activeFavoritesOnly">
                        <input type="hidden" name="search" :value="searchValue">
                        <input type="hidden" name="sort" :value="currentSort">
                        <input type="hidden" name="dir" :value="currentDir">
                        <div>
                            <label for="svc-filter-min" class="co-filter-label mb-1 block text-xs font-medium">{{ __('Min quantity') }}</label>
                            <input type="number" name="min" id="svc-filter-min" value="{{ $svcCurrentMin }}"
                                   min="0" step="1"
                                   class="co-filter-field w-full min-w-[120px] rounded-full border px-4 py-2 text-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/40">
                        </div>
                        <div>
                            <label for="svc-filter-max" class="co-filter-label mb-1 block text-xs font-medium">{{ __('Max quantity') }}</label>
                            <input type="number" name="max" id="svc-filter-max" value="{{ $svcCurrentMax }}"
                                   min="0" step="1"
                                   class="co-filter-field w-full min-w-[120px] rounded-full border px-4 py-2 text-sm focus:border-purple-500 focus:ring-2 focus:ring-purple-500/40">
                        </div>
                        <div class="flex w-full min-w-0 shrink-0 items-center justify-end gap-2 sm:w-auto sm:justify-start">
                            <button type="button" @click="performAjaxSearch()"
                                    class="co-filter-apply inline-flex h-10 shrink-0 items-center justify-center rounded-full px-5 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 focus:ring-offset-[var(--card)]">
                                {{ __('Apply') }}
                            </button>
                            <a href="{{ route('client.services.index') }}"
                               class="co-filter-reset inline-flex h-10 shrink-0 items-center justify-center gap-1.5 rounded-full px-5 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 focus:ring-offset-[var(--card)]">
                                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                                {{ __('Reset filter') }}
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <div id="services-list-container">
                @include('client.services.partials.services-list', [
                    'categories' => $categories,
                    'favoriteServiceIds' => $favoriteServiceIds ?? []
                ])
            </div>
        </div>
    </div>

    <script>
        function serviceManagement(config = {}) {
            return {
                filtersOpen: !!config.filtersOpen,
                currentSort: config.sort || 'id',
                currentDir: config.dir || 'asc',
                favoriteServices: [...(config.favoriteServiceIds || [])],
                categoriesList: config.categoriesList || [],
                activeCategoryId: String(config.activeCategoryId ?? ''),
                activeFavoritesOnly: config.activeFavoritesOnly === '1' || config.activeFavoritesOnly === 1 ? '1' : '0',
                searchValue: config.activeSearch ?? '',

                getServiceTab() {
                    if (this.activeFavoritesOnly === '1') return 'favorites';
                    if (this.activeCategoryId) return String(this.activeCategoryId);
                    return 'all';
                },
                syncSearchToForm() {
                    this.activeSearch = this.searchValue || '';
                },
                handleSearchDebounced() {
                    this.syncSearchToForm();
                    this.updateURL();
                    this.performAjaxSearch();
                },
                collectPayload() {
                    const payload = { search_by: 'service_name' };
                    document.querySelectorAll('.client-services-filter-form').forEach((form) => {
                        new FormData(form).forEach((val, key) => {
                            if (val !== '' && val !== null && val !== undefined) {
                                payload[key] = val;
                            }
                        });
                    });
                    payload.sort = this.currentSort;
                    payload.dir = this.currentDir;
                    payload.search = this.searchValue || '';
                    payload.category_id = this.activeCategoryId || '';
                    payload.favorites_only = this.activeFavoritesOnly || '0';
                    return payload;
                },
                updateActiveFilters() {
                    const form = document.getElementById('filter-form');
                    if (form) {
                        const searchInput = form.querySelector('input[name="search"]');
                        if (searchInput && searchInput.value !== this.searchValue) {
                            this.searchValue = searchInput.value || '';
                        }
                    }
                },
                selectServiceTab(tab) {
                    if (tab === 'all') {
                        this.selectFilter('', false);
                    } else if (tab === 'favorites') {
                        this.selectFilter('', true);
                    } else {
                        this.selectFilter(String(tab), false);
                    }
                },
                selectFilter(categoryId, favoritesOnly) {
                    this.activeCategoryId = categoryId ? String(categoryId) : '';
                    this.activeFavoritesOnly = favoritesOnly ? '1' : '0';
                    this.updateURL();
                    this.performAjaxSearch();
                },
                updateURL() {
                    const p = this.collectPayload();
                    const params = new URLSearchParams();
                    if (p.search) params.set('search', p.search);
                    if (p.category_id) params.set('category_id', p.category_id);
                    if (p.favorites_only === '1') params.set('favorites_only', '1');
                    if (p.min) params.set('min', p.min);
                    if (p.max) params.set('max', p.max);
                    if (p.sort && p.sort !== 'id') params.set('sort', p.sort);
                    if (p.dir && p.dir !== 'asc') params.set('dir', p.dir);
                    const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
                    window.history.pushState({}, '', newUrl);
                },
                sortTable(column) {
                    if (this.currentSort === column) {
                        this.currentDir = this.currentDir === 'asc' ? 'desc' : 'asc';
                    } else {
                        this.currentSort = column;
                        this.currentDir = 'asc';
                    }
                    this.performAjaxSearch();
                },
                initScrollSync() {},
                getCsrfToken() {
                    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                },
                handleFormSubmit(event) {
                    event.preventDefault();
                    this.performAjaxSearch();
                },
                performAjaxSearch() {
                    const payload = this.collectPayload();
                    const container = document.getElementById('services-list-container');
                    if (container) {
                        container.innerHTML = '<div class="text-center p-8 client-services-loading"><div class="inline-block animate-spin h-8 w-8 cs-loading-spinner"></div><p class="mt-2 text-sm">Searching...</p></div>';
                    }

                    fetch('{{ parse_url(route("client.services.search"), PHP_URL_PATH) }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.getCsrfToken()
                        },
                        body: JSON.stringify(payload)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (container) {
                            container.innerHTML = data.html;
                            if (window.Alpine) {
                                Alpine.initTree(container);
                            }
                            this.$nextTick(() => {
                                if (typeof this.initScrollSync === 'function') {
                                    this.initScrollSync();
                                }
                            });
                        }
                        this.updateURL();
                    })
                    .catch(error => {
                        console.error('Search error:', error);
                        if (container) {
                            container.innerHTML = '<div class="text-center p-8 text-sm client-services-loading" style="color: #f87171;">Error loading results. Please refresh the page.</div>';
                        }
                    });
                },
                toggleFavorite(serviceId) {
                    const sid = Number(serviceId);
                    const originalState = this.favoriteServices.some((id) => Number(id) === sid);

                    if (originalState) {
                        this.favoriteServices = this.favoriteServices.filter((id) => Number(id) !== sid);
                    } else if (!this.favoriteServices.some((id) => Number(id) === sid)) {
                        this.favoriteServices.push(sid);
                    }

                    fetch(`{{ route('client.services.favorite.toggle', ':id') }}`.replace(':id', serviceId), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.getCsrfToken()
                        }
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.ok) {
                            if (data.favorited) {
                                if (!this.favoriteServices.some((id) => Number(id) === sid)) {
                                    this.favoriteServices.push(sid);
                                }
                            } else {
                                this.favoriteServices = this.favoriteServices.filter((id) => Number(id) !== sid);
                            }
                        } else {
                            if (originalState) {
                                if (!this.favoriteServices.some((id) => Number(id) === sid)) {
                                    this.favoriteServices.push(sid);
                                }
                            } else {
                                this.favoriteServices = this.favoriteServices.filter((id) => Number(id) !== sid);
                            }
                            alert(data.error || 'An error occurred');
                        }
                    })
                    .catch(error => {
                        if (originalState) {
                            if (!this.favoriteServices.some((id) => Number(id) === sid)) {
                                this.favoriteServices.push(sid);
                            }
                        } else {
                            this.favoriteServices = this.favoriteServices.filter((id) => Number(id) !== sid);
                        }
                        console.error('Error toggling favorite:', error);
                        alert('Failed to update favorite. Please try again.');
                    });
                },
                openServiceModal(serviceId) {
                    window.dispatchEvent(new CustomEvent('open-modal', { detail: 'service-view-' + serviceId }));
                }
            };
        }
    </script>

    {{-- Service View Modal --}}
    <div id="client-service-modals-root">
    @foreach($categories as $category)
        @foreach($category->services->where('is_active', true) as $service)
            <x-modal name="service-view-{{ $service->id }}" maxWidth="2xl" theme="smm">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-gray-900">{{ $service->name }}</h3>
                        <button type="button" @click="$dispatch('close-modal', 'service-view-{{ $service->id }}')" class="text-gray-400 hover:text-gray-500">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <div class="space-y-6">
                        {{-- Service Details --}}
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Service ID') }}</label>
                                <p class="text-sm text-gray-900">{{ $service->id }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Rate per 1000') }}</label>
                                @php
                                    $defaultRate = $service->default_rate ?? $service->rate_per_1000 ?? 0;
                                    $customRate = $service->client_price ?? $defaultRate;
                                    $hasCustomRate = $service->has_custom_rate ?? false;
                                @endphp
                                @if($hasCustomRate && $customRate != 0 && $defaultRate != $customRate)
                                    <div class="flex flex-col">
                                        <span class="text-gray-500 line-through text-xs">${{ number_format($defaultRate, 2) }}</span>
                                        <span class="text-indigo-600 font-semibold text-sm">${{ number_format($customRate, 2) }}</span>
                                    </div>
                                @else
                                    <p class="text-sm text-gray-900 font-medium">${{ number_format($customRate, 2) }}</p>
                                @endif
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Min Quantity') }}</label>
                                <p class="text-sm text-gray-900">{{ number_format($service->min_quantity ?? 1) }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Max Quantity') }}</label>
                                <p class="text-sm text-gray-900">{{ number_format($service->max_quantity ?? 1) }}</p>
                            </div>
                        </div>

                        @if($service->description)
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Description') }}</label>
                                <p class="text-sm text-gray-600">{{ $service->description }}</p>
                            </div>
                        @endif

                        {{-- Create Order Button --}}
                        <div class="border-t border-gray-200 pt-6">
                            <div class="flex items-center justify-end gap-3">
                                <button type="button"
                                        @click="$dispatch('close-modal', 'service-view-{{ $service->id }}')"
                                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    {{ __('Close') }}
                                </button>
                                <a href="{{ route('client.orders.create', ['category_id' => $service->category_id, 'service_id' => $service->id, 'target_type' => $service->target_type]) }}"
                                   class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                    </svg>
                                    {{ __('Create Order') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </x-modal>
        @endforeach
    @endforeach
    </div>
</x-client-layout>

