@php
    $currentSort = request('sort', 'id');
    $currentDir = request('dir', 'asc');
    $favoriteServiceIds = $favoriteServiceIds ?? [];
@endphp

{{-- ==================== DESKTOP TABLE ==================== --}}
<div class="client-services-table-wrap shadow-sm sm:rounded-lg w-full min-w-0 cs-desktop-table">
    <div class="overflow-x-auto w-full min-w-0">
        <table class="w-full min-w-full table-fixed divide-y divide-gray-200">
            <colgroup>
                <col class="w-20">
                <col class="w-16">
                <col>
                <col class="w-32">
                <col class="w-24">
                <col class="w-24">
                <col class="w-28">
            </colgroup>
            <thead class="bg-gray-50 sticky top-0">
                <tr>
                    <th scope="col" class="px-4 py-1.5 text-center text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap bg-gray-50">
                        Favorite
                    </th>
                    <th scope="col" class="px-4 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap bg-gray-50">
                        <button type="button" @click="$dispatch('sort-table', { column: 'id' })" class="flex items-center gap-1 hover:text-gray-700 focus:outline-none whitespace-nowrap w-full text-left -ml-4 pl-4">
                            ID
                            <span class="flex flex-col">
                                <svg class="w-3 h-3 {{ $currentSort === 'id' && $currentDir === 'asc' ? 'text-indigo-600' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" /></svg>
                                <svg class="w-3 h-3 -mt-1 {{ $currentSort === 'id' && $currentDir === 'desc' ? 'text-indigo-600' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                            </span>
                        </button>
                    </th>
                    <th scope="col" class="px-4 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">
                        <button type="button" @click="$dispatch('sort-table', { column: 'name' })" class="flex items-center gap-1 hover:text-gray-700 focus:outline-none w-full text-left -ml-4 pl-4">
                            Service
                            <span class="flex flex-col">
                                <svg class="w-3 h-3 {{ $currentSort === 'name' && $currentDir === 'asc' ? 'text-indigo-600' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" /></svg>
                                <svg class="w-3 h-3 -mt-1 {{ $currentSort === 'name' && $currentDir === 'desc' ? 'text-indigo-600' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                            </span>
                        </button>
                    </th>
                    <th scope="col" class="px-4 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap bg-gray-50">
                        <button type="button" @click="$dispatch('sort-table', { column: 'rate_per_1000' })" class="flex items-center gap-1 hover:text-gray-700 focus:outline-none whitespace-nowrap w-full text-left -ml-4 pl-4">
                            Rate
                            <span class="flex flex-col">
                                <svg class="w-3 h-3 {{ $currentSort === 'rate_per_1000' && $currentDir === 'asc' ? 'text-indigo-600' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" /></svg>
                                <svg class="w-3 h-3 -mt-1 {{ $currentSort === 'rate_per_1000' && $currentDir === 'desc' ? 'text-indigo-600' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                            </span>
                        </button>
                    </th>
                    <th scope="col" class="px-4 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap bg-gray-50">
                        <button type="button" @click="$dispatch('sort-table', { column: 'min_quantity' })" class="flex items-center gap-1 hover:text-gray-700 focus:outline-none whitespace-nowrap w-full text-left -ml-4 pl-4">
                            Min
                            <span class="flex flex-col">
                                <svg class="w-3 h-3 {{ $currentSort === 'min_quantity' && $currentDir === 'asc' ? 'text-indigo-600' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" /></svg>
                                <svg class="w-3 h-3 -mt-1 {{ $currentSort === 'min_quantity' && $currentDir === 'desc' ? 'text-indigo-600' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                            </span>
                        </button>
                    </th>
                    <th scope="col" class="px-4 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap bg-gray-50">
                        <button type="button" @click="$dispatch('sort-table', { column: 'max_quantity' })" class="flex items-center gap-1 hover:text-gray-700 focus:outline-none whitespace-nowrap w-full text-left -ml-4 pl-4">
                            Max
                            <span class="flex flex-col">
                                <svg class="w-3 h-3 {{ $currentSort === 'max_quantity' && $currentDir === 'asc' ? 'text-indigo-600' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" /></svg>
                                <svg class="w-3 h-3 -mt-1 {{ $currentSort === 'max_quantity' && $currentDir === 'desc' ? 'text-indigo-600' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                            </span>
                        </button>
                    </th>
                    <th scope="col" class="px-4 py-1.5 text-center text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap bg-gray-50">
                        View
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @if($categories->isEmpty())
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">{{ __('No services found matching your search criteria.') }}</td></tr>
                @else
                    @foreach($categories as $category)
                        <tr class="bg-gray-50 border-b-2 border-gray-200">
                            <td colspan="7" class="px-6 py-2">
                                <div class="flex items-center gap-3">
                                    @if($category->icon)
                                        <span class="text-xl">
                                            @if(Str::startsWith($category->icon, '<svg')) {!! $category->icon !!}
                                            @elseif(Str::startsWith($category->icon, 'data:')) <img src="{{ $category->icon }}" alt="icon" class="w-5 h-5 object-contain">
                                            @elseif(Str::startsWith($category->icon, 'fas ') || Str::startsWith($category->icon, 'far ') || Str::startsWith($category->icon, 'fab ') || Str::startsWith($category->icon, 'fal ') || Str::startsWith($category->icon, 'fad ')) <i class="{{ $category->icon }}"></i>
                                            @else <span class="inline-block">{{ $category->icon }}</span>
                                            @endif
                                        </span>
                                    @endif
                                    <h3 class="text-lg font-semibold text-gray-900">{{ $category->name }}</h3>
                                </div>
                            </td>
                        </tr>
                        @php $activeServices = $category->services->where('is_active', true); @endphp
                        @if($activeServices->isEmpty())
                            <tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">{{ __('No services in this category.') }}</td></tr>
                        @else
                            @foreach($activeServices as $service)
                                @php
                                    $defaultRate = $service->default_rate ?? $service->rate_per_1000 ?? 0;
                                    $customRate = $service->client_price ?? $defaultRate;
                                    $hasCustomRate = $service->has_custom_rate ?? false;
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 whitespace-nowrap text-center">
                                        <button type="button" @click="toggleFavorite({{ $service->id }})" class="inline-flex items-center justify-center p-1.5 rounded-md hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors" :class="favoriteServices.includes({{ $service->id }}) ? 'text-yellow-500' : 'text-gray-400'">
                                            <svg class="w-5 h-5" :fill="favoriteServices.includes({{ $service->id }}) ? 'currentColor' : 'none'" :stroke="favoriteServices.includes({{ $service->id }}) ? 'none' : 'currentColor'" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path></svg>
                                        </button>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">{{ $service->id }}</td>
                                    <td class="px-4 py-2 text-sm font-medium text-gray-900">
                                        <div class="flex items-center gap-2">
                                            @if($service->icon ?? null)
                                                <span class="text-base flex-shrink-0">
                                                    @if(Str::startsWith($service->icon, '<svg')) <span class="inline-block w-4 h-4">{!! $service->icon !!}</span>
                                                    @elseif(Str::startsWith($service->icon, 'data:')) <img src="{{ $service->icon }}" alt="icon" class="w-4 h-4 object-contain">
                                                    @elseif(Str::startsWith($service->icon, 'fas ') || Str::startsWith($service->icon, 'far ') || Str::startsWith($service->icon, 'fab ') || Str::startsWith($service->icon, 'fal ') || Str::startsWith($service->icon, 'fad ')) <i class="{{ $service->icon }}"></i>
                                                    @else <span class="inline-block">{{ $service->icon }}</span>
                                                    @endif
                                                </span>
                                            @endif
                                            <span>{{ $service->name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900 font-medium">
                                        @if($hasCustomRate && $customRate != 0 && $defaultRate != $customRate)
                                            <div class="flex flex-col">
                                                <span class="text-gray-500 line-through text-xs">${{ number_format($defaultRate, 2) }}</span>
                                                <span class="text-indigo-600 font-semibold">${{ number_format($customRate, 2) }}</span>
                                            </div>
                                        @else
                                            <span>${{ number_format($customRate, 2) }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">{{ number_format($service->min_quantity ?? $service->min_order ?? 1) }}</td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">{{ number_format($service->max_quantity ?? $service->max_order ?? 1) }}</td>
                                    <td class="px-4 py-2 whitespace-nowrap text-center">
                                        <button type="button" @click="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'service-view-{{ $service->id }}' }))" class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-indigo-600 border border-indigo-600 rounded-md hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-indigo-500">{{ __('View') }}</button>
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    @endforeach
                @endif
            </tbody>
        </table>
    </div>
</div>

{{-- ==================== MOBILE CARDS ==================== --}}
<div class="cs-mobile-cards">
    @if($categories->isEmpty())
        <div class="cs-mobile-empty">{{ __('No services found matching your search criteria.') }}</div>
    @else
        @foreach($categories as $category)
            {{-- Category header --}}
            <div class="cs-cat-header">
                @if($category->icon)
                    <span class="cs-cat-icon">
                        @if(Str::startsWith($category->icon, '<svg')) {!! $category->icon !!}
                        @elseif(Str::startsWith($category->icon, 'data:')) <img src="{{ $category->icon }}" alt="" style="width:20px;height:20px;object-fit:contain;">
                        @elseif(Str::startsWith($category->icon, 'fas ') || Str::startsWith($category->icon, 'far ') || Str::startsWith($category->icon, 'fab ') || Str::startsWith($category->icon, 'fal ') || Str::startsWith($category->icon, 'fad ')) <i class="{{ $category->icon }}"></i>
                        @else {{ $category->icon }}
                        @endif
                    </span>
                @endif
                <span class="cs-cat-name">{{ $category->name }}</span>
            </div>

            @php $activeServices = $category->services->where('is_active', true); @endphp
            @if($activeServices->isEmpty())
                <div class="cs-mobile-empty" style="margin-bottom:8px;">{{ __('No services in this category.') }}</div>
            @else
                @foreach($activeServices as $service)
                    @php
                        $defaultRate = $service->default_rate ?? $service->rate_per_1000 ?? 0;
                        $customRate = $service->client_price ?? $defaultRate;
                        $hasCustomRate = $service->has_custom_rate ?? false;
                    @endphp
                    <div class="cs-svc-card" onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'service-view-{{ $service->id }}' }))">
                        <div class="cs-svc-card-top">
                            <button type="button" @click.stop="toggleFavorite({{ $service->id }})" class="cs-svc-fav" :class="favoriteServices.includes({{ $service->id }}) ? 'cs-svc-fav-on' : ''">
                                <svg width="16" height="16" :fill="favoriteServices.includes({{ $service->id }}) ? 'currentColor' : 'none'" :stroke="favoriteServices.includes({{ $service->id }}) ? 'none' : 'currentColor'" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                            </button>
                            <span class="cs-svc-name">{{ $service->name }}</span>
                            <i class="fa-solid fa-chevron-right cs-svc-arrow"></i>
                        </div>
                        <div class="cs-svc-card-bottom">
                            <span class="cs-svc-id">{{ $service->id }}</span>
                            <span class="cs-svc-dot">·</span>
                            <span class="cs-svc-rate">
                                @if($hasCustomRate && $customRate != 0 && $defaultRate != $customRate)
                                    <s style="opacity:0.5;font-weight:400;">${{ number_format($defaultRate, 2) }}</s>
                                    ${{ number_format($customRate, 2) }}
                                @else
                                    ${{ number_format($customRate, 2) }}
                                @endif
                            </span>
                            <span class="cs-svc-dot">·</span>
                            <span class="cs-svc-range">{{ number_format($service->min_quantity ?? $service->min_order ?? 1) }}-{{ number_format($service->max_quantity ?? $service->max_order ?? 1) }}</span>
                        </div>
                    </div>
                @endforeach
            @endif
        @endforeach
    @endif
</div>
