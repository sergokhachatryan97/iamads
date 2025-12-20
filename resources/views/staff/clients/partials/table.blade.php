@php
    $currentSort = request('sort', 'created_at');
    $currentDir = request('dir', 'desc');
    $isSuperAdmin = auth()->guard('staff')->check() && auth()->guard('staff')->user()->hasRole('super_admin');
@endphp

@if($clients->count() > 0)
    <div id="clients-table-scroll" class="w-full overflow-x-auto -mx-4 sm:mx-0" style="overflow-y: visible; -webkit-overflow-scrolling: touch; position: relative; z-index: 0;">
        <div class="inline-block min-w-full align-middle" style="position: relative; isolation: isolate;">
            <table class="min-w-full divide-y divide-gray-200" style="position: relative;">
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
                    @php
                        // Check if client is suspended - handle null/empty status as active
                        $isSuspended = !empty($client->status) && strtolower($client->status) === 'suspended';
                    @endphp
                    <tr class="{{ $isSuspended ? 'bg-gray-100 opacity-75' : 'hover:bg-gray-50' }}" style="position: relative;">
                        <td class="px-2 sm:px-3 py-2 whitespace-nowrap sticky left-0 {{ $isSuspended ? 'bg-gray-100' : 'bg-white' }} z-10" onclick="event.stopPropagation();">
                            <input type="checkbox" name="client_ids[]" value="{{ $client->id }}" class="client-checkbox rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" onclick="event.stopPropagation();">
                        </td>
                        @if($isSuspended)
                            {{-- Disabled style for suspended rows --}}
                            <td class="px-2 sm:px-3 py-2 whitespace-nowrap cursor-not-allowed">
                                <div class="text-sm font-medium text-gray-500">{{ $client->id }}</div>
                            </td>
                            <td class="px-2 sm:px-3 py-2 whitespace-nowrap cursor-not-allowed">
                                <div class="text-sm font-medium text-gray-500">{{ $client->name }}</div>
                            </td>
                            <td class="px-2 sm:px-3 py-2 whitespace-nowrap cursor-not-allowed">
                                <div class="text-sm text-gray-500 truncate max-w-xs">{{ $client->email }}</div>
                            </td>
                            <td class="px-2 sm:px-3 py-2 whitespace-nowrap cursor-not-allowed">
                                <div class="text-sm font-semibold text-gray-500">${{ $client->balance }}</div>
                            </td>
                            <td class="px-2 sm:px-3 py-2 whitespace-nowrap cursor-not-allowed">
                                <div class="text-sm text-gray-500">${{ $client->spent }}</div>
                            </td>
                            <td class="px-2 sm:px-3 py-2 whitespace-nowrap cursor-not-allowed">
                                <div class="text-sm text-gray-500">
                                    @if($client->staff)
                                        {{ $client->staff->name }}
                                    @else
                                        <span class="text-gray-400 italic">{{ __('No Staff Assigned') }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-2 sm:px-3 py-2 whitespace-nowrap text-sm text-gray-400 cursor-not-allowed">
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
                            <td class="px-2 sm:px-3 py-2 whitespace-nowrap text-sm text-gray-400 cursor-not-allowed">
                                {{ $client->created_at->format('Y-m-d H:i') }}
                            </td>
                        @else
                            <td class="px-2 sm:px-3 py-2 whitespace-nowrap cursor-pointer" onclick="window.location.href='{{ route('staff.clients.edit', $client) }}'">
                                <div class="text-sm font-medium text-gray-900">{{ $client->id }}</div>
                            </td>
                            <td class="px-2 sm:px-3 py-2 whitespace-nowrap cursor-pointer" onclick="window.location.href='{{ route('staff.clients.edit', $client) }}'">
                                <div class="text-sm font-medium text-gray-900">{{ $client->name }}</div>
                            </td>
                            <td class="px-2 sm:px-3 py-2 whitespace-nowrap cursor-pointer" onclick="window.location.href='{{ route('staff.clients.edit', $client) }}'">
                                <div class="text-sm text-gray-900 truncate max-w-xs">{{ $client->email }}</div>
                            </td>
                            <td class="px-2 sm:px-3 py-2 whitespace-nowrap cursor-pointer" onclick="window.location.href='{{ route('staff.clients.edit', $client) }}'">
                                <div class="text-sm font-semibold text-gray-900">${{ $client->balance }}</div>
                            </td>
                            <td class="px-2 sm:px-3 py-2 whitespace-nowrap cursor-pointer" onclick="window.location.href='{{ route('staff.clients.edit', $client) }}'">
                                <div class="text-sm text-gray-900">${{ $client->spent }}</div>
                            </td>
                            <td class="px-2 sm:px-3 py-2 whitespace-nowrap cursor-pointer" onclick="window.location.href='{{ route('staff.clients.edit', $client) }}'">
                                <div class="text-sm text-gray-900">
                                    @if($client->staff)
                                        {{ $client->staff->name }}
                                    @else
                                        <span class="text-gray-400 italic">{{ __('No Staff Assigned') }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-2 sm:px-3 py-2 whitespace-nowrap text-sm text-gray-500 cursor-pointer" onclick="window.location.href='{{ route('staff.clients.edit', $client) }}'">
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
                            <td class="px-2 sm:px-3 py-2 whitespace-nowrap text-sm text-gray-500 cursor-pointer" onclick="window.location.href='{{ route('staff.clients.edit', $client) }}'">
                                {{ $client->created_at->format('Y-m-d H:i') }}
                            </td>
                        @endif

                        {{-- Actions column (outside the if/else for status) --}}
                        <td class="px-1 sm:px-3 py-2 whitespace-nowrap text-right text-sm font-medium sticky right-0 {{ $isSuspended ? 'bg-gray-100' : 'bg-white' }}" onclick="event.stopPropagation();" style="position: sticky; right: 0; z-index: 10;">
                            @if($isSuspended)
                                {{-- Show Activate button directly for suspended clients (no Actions dropdown) --}}
                                <form method="POST" action="{{ route('staff.clients.activate', $client) }}" class="inline-block" id="activate-form-{{ $client->id }}">
                                    @csrf
                                    <button type="button"
                                            onclick="if(typeof showActivateConfirm === 'function') { showActivateConfirm({{ $client->id }}, {{ json_encode($client->name) }}); }"
                                            class="inline-flex items-center px-3 py-1 text-sm font-medium text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                        {{ __('Activate') }}
                                    </button>
                                </form>
                            @else
                                {{-- Show Actions dropdown for active clients --}}
                                <div
                                    class="relative"
                                    x-data="{
                                        uid: 'client-actions-{{ $client->id }}',
                                        open: false,
                                        top: 0,
                                        left: 0,
                                        width: 192, // w-48 = 12rem = 192px

                                        updatePos() {
                                            const btn = this.$refs.btn;
                                            if (!btn) return;

                                            const r = btn.getBoundingClientRect();
                                            this.top = r.bottom + 8;           // mt-2
                                            this.left = r.right - this.width;  // align right edge
                                        },

                                        toggle() {
                                            // Before opening, tell all other dropdowns to close
                                            if (!this.open) {
                                                window.dispatchEvent(new CustomEvent('actions-dropdown-opened', { detail: { uid: this.uid } }));
                                            }

                                            this.open = !this.open;

                                            if (this.open) {
                                                this.$nextTick(() => this.updatePos());
                                            }
                                        },

                                        close() {
                                            this.open = false;
                                        },

                                        init() {
                                            // Update position on horizontal scroll of the table wrapper
                                            const scroller = document.getElementById('clients-table-scroll');
                                            if (scroller) {
                                                scroller.addEventListener('scroll', () => {
                                                    if (this.open) this.updatePos();
                                                }, { passive: true });
                                            }

                                            // Close if another dropdown was opened
                                            window.addEventListener('actions-dropdown-opened', (e) => {
                                                if (!e.detail) return;
                                                if (e.detail.uid !== this.uid) {
                                                    this.close();
                                                }
                                            });
                                        }
                                    }"
                                    @click.outside="close()"
                                    @keydown.escape.window="close()"
                                    @scroll.window.capture="if(open) updatePos()"
                                    @resize.window="if(open) updatePos()"
                                    style="position: relative; z-index: 20;"
                                    @click.stop
                                >
                                    <button
                                        type="button"
                                        x-ref="btn"
                                        @click.stop="toggle()"
                                        class="inline-flex items-center px-3 py-1 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    >
                                        {{ __('Actions') }}
                                        <svg class="ml-1 -mr-1 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                        </svg>
                                    </button>

                                    <template x-teleport="body">
                                        <div
                                            x-show="open"
                                            x-cloak
                                            x-transition:enter="transition ease-out duration-100"
                                            x-transition:enter-start="transform opacity-0 scale-95"
                                            x-transition:enter-end="transform opacity-100 scale-100"
                                            x-transition:leave="transition ease-in duration-75"
                                            x-transition:leave-start="transform opacity-100 scale-100"
                                            x-transition:leave-end="transform opacity-0 scale-95"
                                            class="fixed w-48 bg-white rounded-md shadow-lg py-1 border border-gray-200"
                                            :style="`top:${top}px; left:${left}px; z-index:99999;`"
                                            @click.stop
                                        >
                                            <a href="{{ route('staff.clients.edit', $client) }}" @click.stop class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                {{ __('Edit') }}
                                            </a>

                                            <form method="POST" action="{{ route('staff.clients.suspend', $client) }}" class="block" id="suspend-form-{{ $client->id }}">
                                                @csrf
                                                <button type="button"
                                                        @click.stop="close(); showSuspendConfirm({{ $client->id }}, {{ json_encode($client->name) }})"
                                                        class="block w-full text-left px-4 py-2 text-sm text-yellow-600 hover:bg-gray-100">
                                                    {{ __('Suspend user') }}
                                                </button>
                                            </form>

                                            @if($isSuperAdmin)
                                                @if(!$client->staff_id)
                                                    <button
                                                        type="button"
                                                        @click.stop="close(); setTimeout(() => { if (typeof window.handleAssignStaff === 'function') { window.handleAssignStaff({{ $client->id }}, {{ json_encode($client->name) }}, null); } }, 100);"
                                                        class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                                    >
                                                        {{ __('Assign Staff') }}
                                                    </button>
                                                @else
                                                    <button
                                                        type="button"
                                                        @click.stop="close(); setTimeout(() => { if (typeof window.handleAssignStaff === 'function') { window.handleAssignStaff({{ $client->id }}, {{ json_encode($client->name) }}, {{ $client->staff_id }}); } }, 100);"
                                                        class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                                    >
                                                        {{ __('Change Staff') }}
                                                    </button>
                                                @endif
                                            @endif
                                        </div>
                                    </template>
                                </div>
                            @endif
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
