@php
    $currentSort = request('sort', 'id');
    $currentDir = request('dir', 'asc');
    $favoriteServiceIds = $favoriteServiceIds ?? [];
@endphp

<div class="bg-white shadow-sm sm:rounded-lg">
    <div class="overflow-x-auto">
        <table class="w-full divide-y divide-gray-200">
            <colgroup>
                <col class="w-20">
                <col class="w-16">
                <col>
                <col class="w-32">
                <col class="w-24">
                <col class="w-24">
                <col class="w-40">
                <col style="min-width: 200px;">
                <col class="w-24">
            </colgroup>
            <thead class="bg-gray-50 sticky top-0">
                <tr>
                    <th scope="col" class="px-4 py-1.5 text-center text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap bg-gray-50">
                        Favorite
                    </th>
                    <th scope="col" class="px-4 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap bg-gray-50">
                        <button type="button"
                                @click="$dispatch('sort-table', { column: 'id' })"
                                class="flex items-center gap-1 hover:text-gray-700 focus:outline-none whitespace-nowrap w-full text-left -ml-4 pl-4">
                            ID
                            <span class="flex flex-col">
                                <svg class="w-3 h-3 {{ $currentSort === 'id' && $currentDir === 'asc' ? 'text-indigo-600' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />
                                </svg>
                                <svg class="w-3 h-3 -mt-1 {{ $currentSort === 'id' && $currentDir === 'desc' ? 'text-indigo-600' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </span>
                        </button>
                    </th>
                    <th scope="col" class="px-4 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">
                        <button type="button"
                                @click="$dispatch('sort-table', { column: 'name' })"
                                class="flex items-center gap-1 hover:text-gray-700 focus:outline-none w-full text-left -ml-4 pl-4">
                            Service
                            <span class="flex flex-col">
                                <svg class="w-3 h-3 {{ $currentSort === 'name' && $currentDir === 'asc' ? 'text-indigo-600' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />
                                </svg>
                                <svg class="w-3 h-3 -mt-1 {{ $currentSort === 'name' && $currentDir === 'desc' ? 'text-indigo-600' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </span>
                        </button>
                    </th>
                    <th scope="col" class="px-4 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap bg-gray-50">
                        <button type="button"
                                @click="$dispatch('sort-table', { column: 'rate_per_1000' })"
                                class="flex items-center gap-1 hover:text-gray-700 focus:outline-none whitespace-nowrap w-full text-left -ml-4 pl-4">
                            Rate
                            <span class="flex flex-col">
                                <svg class="w-3 h-3 {{ $currentSort === 'rate_per_1000' && $currentDir === 'asc' ? 'text-indigo-600' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />
                                </svg>
                                <svg class="w-3 h-3 -mt-1 {{ $currentSort === 'rate_per_1000' && $currentDir === 'desc' ? 'text-indigo-600' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </span>
                        </button>
                    </th>
                    <th scope="col" class="px-4 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap bg-gray-50">
                        <button type="button"
                                @click="$dispatch('sort-table', { column: 'min_quantity' })"
                                class="flex items-center gap-1 hover:text-gray-700 focus:outline-none whitespace-nowrap w-full text-left -ml-4 pl-4">
                            Min
                            <span class="flex flex-col">
                                <svg class="w-3 h-3 {{ $currentSort === 'min_quantity' && $currentDir === 'asc' ? 'text-indigo-600' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />
                                </svg>
                                <svg class="w-3 h-3 -mt-1 {{ $currentSort === 'min_quantity' && $currentDir === 'desc' ? 'text-indigo-600' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </span>
                        </button>
                    </th>
                    <th scope="col" class="px-4 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap bg-gray-50">
                        <button type="button"
                                @click="$dispatch('sort-table', { column: 'max_quantity' })"
                                class="flex items-center gap-1 hover:text-gray-700 focus:outline-none whitespace-nowrap w-full text-left -ml-4 pl-4">
                            Max
                            <span class="flex flex-col">
                                <svg class="w-3 h-3 {{ $currentSort === 'max_quantity' && $currentDir === 'asc' ? 'text-indigo-600' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />
                                </svg>
                                <svg class="w-3 h-3 -mt-1 {{ $currentSort === 'max_quantity' && $currentDir === 'desc' ? 'text-indigo-600' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </span>
                        </button>
                    </th>
                    <th scope="col" class="px-4 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap bg-gray-50">
                        Average time
                        <svg class="inline-block w-4 h-4 ml-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </th>
                    <th scope="col" class="px-4 py-1.5 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">
                        Description
                    </th>
                    <th scope="col" class="px-4 py-1.5 text-center text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap bg-gray-50">
                        View
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @if($categories->isEmpty())
                    <tr>
                        <td colspan="9" class="px-4 py-8 text-center text-gray-500">
                            {{ __('No services found matching your search criteria.') }}
                        </td>
                    </tr>
                @else
                    @foreach($categories as $category)
                        {{-- Category Header Row --}}
                        <tr class="bg-gray-50 border-b-2 border-gray-200">
                            <td colspan="9" class="px-6 py-2">
                                <div class="flex items-center gap-3">
                                    @if($category->icon)
                                        <span class="text-xl">
                                                @if(Str::startsWith($category->icon, '<svg'))
                                                {!! $category->icon !!}
                                            @elseif(Str::startsWith($category->icon, 'data:'))
                                                <img src="{{ $category->icon }}" alt="icon" class="w-5 h-5 object-contain">
                                            @elseif(Str::startsWith($category->icon, 'fas ') || Str::startsWith($category->icon, 'far ') || Str::startsWith($category->icon, 'fab ') || Str::startsWith($category->icon, 'fal ') || Str::startsWith($category->icon, 'fad '))
                                                <i class="{{ $category->icon }}"></i>
                                            @else
                                                <span class="inline-block">{{ $category->icon }}</span>
                                            @endif
                                            </span>
                                    @endif
                                    <h3 class="text-lg font-semibold text-gray-900">{{ $category->name }}</h3>
                                </div>
                            </td>
                        </tr>

                        {{-- Service Rows --}}
                        @php
                            $activeServices = $category->services->where('is_active', true);
                        @endphp
                        @if($activeServices->isEmpty())
                            <tr>
                                <td colspan="9" class="px-4 py-6 text-center text-gray-500">
                                    {{ __('No services in this category.') }}
                                </td>
                            </tr>
                        @else
                            @foreach($activeServices as $service)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 whitespace-nowrap text-center">
                                        <button type="button"
                                                @click="toggleFavorite({{ $service->id }})"
                                                class="inline-flex items-center justify-center p-1.5 rounded-md hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                                                :class="favoriteServices.includes({{ $service->id }}) ? 'text-yellow-500' : 'text-gray-400'">
                                            <svg class="w-5 h-5" :fill="favoriteServices.includes({{ $service->id }}) ? 'currentColor' : 'none'" :stroke="favoriteServices.includes({{ $service->id }}) ? 'none' : 'currentColor'" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                                            </svg>
                                        </button>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">
                                        {{ $service->id }}
                                    </td>
                                    <td class="px-4 py-2 text-sm font-medium text-gray-900">
                                        {{ $service->name }}
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900 font-medium">
                                        @php
                                            $displayPrice = $service->client_price ?? $service->rate_per_1000 ?? $service->rate ?? 0;
                                        @endphp
                                        ${{ number_format($displayPrice, 2) }}
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">
                                        {{ number_format($service->min_quantity ?? $service->min_order ?? 1) }}
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">
                                        {{ number_format($service->max_quantity ?? $service->max_order ?? 1) }}
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">
                                        {{ __('2 hours 30 minutes') }}
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-500">
                                        @if($service->description)
                                            <div class="max-w-xs lg:max-w-md xl:max-w-lg truncate" title="{{ e($service->description) }}">
                                                {{ Str::limit($service->description, 50) }}
                                            </div>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-center">
                                        <button type="button"
                                                @click="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'service-view-{{ $service->id }}' }))"
                                                class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-indigo-600 border border-indigo-600 rounded-md hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                            {{ __('View') }}
                                        </button>
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
