@php
    $currentSort = request('sort', 'created_at');
    $currentDir = request('dir', 'desc');
    $isSuperAdmin = auth()->guard('staff')->check() && auth()->guard('staff')->user()->hasRole('super_admin');
@endphp

@if($clients->count() > 0)
    <div class="w-full overflow-x-auto -mx-4 sm:mx-0" style="overflow-y: visible; -webkit-overflow-scrolling: touch; position: relative; z-index: 0;">
        <div class="inline-block min-w-full align-middle">
            <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-2 sm:px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sticky left-0 bg-gray-50 z-10">
                        <input type="checkbox" id="select-all" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    </th>

                    {{-- Sortable Column Helper --}}
                    {{-- Sortable: ID --}}
                    @php
                        $sortField = 'id';
                        $newDir = ($currentSort === $sortField && $currentDir === 'asc') ? 'desc' : 'asc';
                        $sortUrl = route('staff.clients.index', array_merge(request()->except(['sort', 'dir', 'page']), ['sort' => $sortField, 'dir' => $newDir]));
                        $isActive = $currentSort === $sortField;
                        $direction = $isActive ? $currentDir : null;
                    @endphp
                    <th scope="col" class="px-2 sm:px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[60px]">
                        <a href="{{ $sortUrl }}" class="flex items-center gap-1 hover:text-gray-700">
                            {{ __('ID') }}
                            @include('staff.clients.partials.sort-indicator', ['isActive' => $isActive, 'direction' => $direction])
                        </a>
                    </th>

                    {{-- Sortable: Name --}}
                    @php
                        $sortField = 'name';
                        $newDir = ($currentSort === $sortField && $currentDir === 'asc') ? 'desc' : 'asc';
                        $sortUrl = route('staff.clients.index', array_merge(request()->except(['sort', 'dir', 'page']), ['sort' => $sortField, 'dir' => $newDir]));
                        $isActive = $currentSort === $sortField;
                        $direction = $isActive ? $currentDir : null;
                    @endphp
                    <th scope="col" class="px-2 sm:px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[150px]">
                        <a href="{{ $sortUrl }}" class="flex items-center gap-1 hover:text-gray-700">
                            {{ __('Name') }}
                            @include('staff.clients.partials.sort-indicator', ['isActive' => $isActive, 'direction' => $direction])
                        </a>
                    </th>

                    {{-- Sortable: Email --}}
                    @php
                        $sortField = 'email';
                        $newDir = ($currentSort === $sortField && $currentDir === 'asc') ? 'desc' : 'asc';
                        $sortUrl = route('staff.clients.index', array_merge(request()->except(['sort', 'dir', 'page']), ['sort' => $sortField, 'dir' => $newDir]));
                        $isActive = $currentSort === $sortField;
                        $direction = $isActive ? $currentDir : null;
                    @endphp
                    <th scope="col" class="px-2 sm:px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[180px]">
                        <a href="{{ $sortUrl }}" class="flex items-center gap-1 hover:text-gray-700">
                            {{ __('Email') }}
                            @include('staff.clients.partials.sort-indicator', ['isActive' => $isActive, 'direction' => $direction])
                        </a>
                    </th>

                    {{-- Sortable: Balance --}}
                    @php
                        $sortField = 'balance';
                        $newDir = ($currentSort === $sortField && $currentDir === 'asc') ? 'desc' : 'asc';
                        $sortUrl = route('staff.clients.index', array_merge(request()->except(['sort', 'dir', 'page']), ['sort' => $sortField, 'dir' => $newDir]));
                        $isActive = $currentSort === $sortField;
                        $direction = $isActive ? $currentDir : null;
                    @endphp
                    <th scope="col" class="px-2 sm:px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[120px]">
                        <a href="{{ $sortUrl }}" class="flex items-center gap-1 hover:text-gray-700">
                            {{ __('Balance') }}
                            @include('staff.clients.partials.sort-indicator', ['isActive' => $isActive, 'direction' => $direction])
                        </a>
                    </th>

                    {{-- Sortable: Spent --}}
                    @php
                        $sortField = 'spent';
                        $newDir = ($currentSort === $sortField && $currentDir === 'asc') ? 'desc' : 'asc';
                        $sortUrl = route('staff.clients.index', array_merge(request()->except(['sort', 'dir', 'page']), ['sort' => $sortField, 'dir' => $newDir]));
                        $isActive = $currentSort === $sortField;
                        $direction = $isActive ? $currentDir : null;
                    @endphp
                    <th scope="col" class="px-2 sm:px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[120px]">
                        <a href="{{ $sortUrl }}" class="flex items-center gap-1 hover:text-gray-700">
                            {{ __('Spent') }}
                            @include('staff.clients.partials.sort-indicator', ['isActive' => $isActive, 'direction' => $direction])
                        </a>
                    </th>

                    {{-- Non-sortable: Staff Member --}}
                    <th scope="col" class="px-2 sm:px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[150px]">
                        {{ __('Staff Member') }}
                    </th>

                    {{-- Sortable: Last Auth --}}
                    @php
                        $sortField = 'last_auth';
                        $newDir = ($currentSort === $sortField && $currentDir === 'asc') ? 'desc' : 'asc';
                        $sortUrl = route('staff.clients.index', array_merge(request()->except(['sort', 'dir', 'page']), ['sort' => $sortField, 'dir' => $newDir]));
                        $isActive = $currentSort === $sortField;
                        $direction = $isActive ? $currentDir : null;
                    @endphp
                    <th scope="col" class="px-2 sm:px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[120px]">
                        <a href="{{ $sortUrl }}" class="flex items-center gap-1 hover:text-gray-700">
                            {{ __('Last Auth') }}
                            @include('staff.clients.partials.sort-indicator', ['isActive' => $isActive, 'direction' => $direction])
                        </a>
                    </th>

                    {{-- Sortable: Created At --}}
                    @php
                        $sortField = 'created_at';
                        $newDir = ($currentSort === $sortField && $currentDir === 'asc') ? 'desc' : 'asc';
                        $sortUrl = route('staff.clients.index', array_merge(request()->except(['sort', 'dir', 'page']), ['sort' => $sortField, 'dir' => $newDir]));
                        $isActive = $currentSort === $sortField;
                        $direction = $isActive ? $currentDir : null;
                    @endphp
                    <th scope="col" class="px-2 sm:px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[140px]">
                        <a href="{{ $sortUrl }}" class="flex items-center gap-1 hover:text-gray-700">
                            {{ __('Created At') }}
                            @include('staff.clients.partials.sort-indicator', ['isActive' => $isActive, 'direction' => $direction])
                        </a>
                    </th>

                    {{-- Actions Column --}}
                    <th scope="col" class="px-2 sm:px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider sticky right-0 bg-gray-50 z-10 min-w-[80px]">
                        {{ __('Actions') }}
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach($clients as $client)
                    <tr class="hover:bg-gray-50">
                        <td class="px-2 sm:px-3 py-2 whitespace-nowrap sticky left-0 bg-white z-10">
                            <input type="checkbox" name="client_ids[]" value="{{ $client->id }}" class="client-checkbox rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        </td>
                        <td class="px-2 sm:px-3 py-2 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">{{ $client->id }}</div>
                        </td>
                        <td class="px-2 sm:px-3 py-2 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">{{ $client->name }}</div>
                        </td>
                        <td class="px-2 sm:px-3 py-2 whitespace-nowrap">
                            <div class="text-sm text-gray-900 truncate max-w-xs">{{ $client->email }}</div>
                        </td>
                        <td class="px-2 sm:px-3 py-2 whitespace-nowrap">
                            <div class="text-sm font-semibold text-gray-900">${{ $client->balance }}</div>
                        </td>
                        <td class="px-2 sm:px-3 py-2 whitespace-nowrap">
                            <div class="text-sm text-gray-900">${{ $client->spent }}</div>
                        </td>
                        <td class="px-2 sm:px-3 py-2 whitespace-nowrap">
                            <div class="text-sm text-gray-900">
                                @if($client->staff)
                                    {{ $client->staff->name }}
                                @else
                                    <span class="text-gray-400 italic">{{ __('No Staff Assigned') }}</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-2 sm:px-3 py-2 whitespace-nowrap text-sm text-gray-500">
                            @if($client->last_auth)
                                @php
                                    $lastAuth = $client->last_auth instanceof \Carbon\Carbon
                                        ? $client->last_auth
                                        : \Carbon\Carbon::parse($client->last_auth);
                                @endphp
                                {{ $lastAuth->format('Y-m-d') }}
                            @else
                                <span class="text-gray-400">{{ __('Never') }}</span>
                            @endif
                        </td>
                        <td class="px-2 sm:px-3 py-2 whitespace-nowrap text-sm text-gray-500">
                            {{ $client->created_at->format('Y-m-d H:i') }}
                        </td>
                        <td class="px-1 sm:px-3 py-2 whitespace-nowrap text-right text-sm font-medium relative right-0 bg-white">
                            <x-table-actions-dropdown :id="$client->id">
                                <a
                                    href="{{ route('staff.clients.edit', $client) }}"
                                    @click.stop
                                    class="block w-full text-left px-4 py-2 text-sm text-indigo-700 hover:bg-indigo-50"
                                >
                                    {{ __('Edit Discount & Rates') }}
                                </a>
                                @if($isSuperAdmin)
                                    @if(!$client->staff_id)
                                        <button
                                            type="button"
                                            @click.stop="open = false; setTimeout(() => { if (typeof window.handleAssignStaff === 'function') { window.handleAssignStaff({{ $client->id }}, {{ json_encode($client->name) }}, null); } }, 100);"
                                            class="block w-full text-left px-4 py-2 text-sm text-indigo-700 hover:bg-indigo-50"
                                        >
                                            {{ __('Assign Staff') }}
                                        </button>
                                    @else
                                        <button
                                            type="button"
                                            @click.stop="open = false; setTimeout(() => { if (typeof window.handleAssignStaff === 'function') { window.handleAssignStaff({{ $client->id }}, {{ json_encode($client->name) }}, {{ $client->staff_id }}); } }, 100);"
                                            class="block w-full text-left px-4 py-2 text-sm text-indigo-700 hover:bg-indigo-50"
                                        >
                                            {{ __('Change Staff') }}
                                        </button>
                                    @endif
                                @endif
                                <form method="POST" action="{{ route('staff.clients.destroy', $client) }}" class="block" onsubmit="return confirm('{{ __('Are you sure you want to delete this client?') }}');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" @click.stop class="block w-full text-left px-4 py-2 text-sm text-red-700 hover:bg-red-50">
                                        {{ __('Delete Client') }}
                                    </button>
                                </form>
                            </x-table-actions-dropdown>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </div>

    <div class="mt-6 pagination-links">
        {{ $clients->links() }}
    </div>
@else
    <div class="text-center py-12">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
        </svg>
        <h3 class="mt-2 text-sm font-medium text-gray-900">{{ __('No clients found') }}</h3>
        <p class="mt-1 text-sm text-gray-500">{{ __('Try adjusting your search or filters.') }}</p>
    </div>
@endif
