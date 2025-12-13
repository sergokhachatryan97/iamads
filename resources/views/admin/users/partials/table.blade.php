@if($users->count() > 0)
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        {{ __('Avatar') }}
                    </th>
                    <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <a href="#" data-sort="name" class="sort-link flex items-center gap-1 hover:text-gray-700">
                            {{ __('Name') }}
                            <span class="sort-indicator" data-sort="name">
                                @if(request('sort') === 'name')
                                    @if(request('dir') === 'asc')
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                        </svg>
                                    @else
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    @endif
                                @endif
                            </span>
                        </a>
                    </th>
                    <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <a href="#" data-sort="email" class="sort-link flex items-center gap-1 hover:text-gray-700">
                            {{ __('Email') }}
                            <span class="sort-indicator" data-sort="email">
                                @if(request('sort') === 'email')
                                    @if(request('dir') === 'asc')
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                        </svg>
                                    @else
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    @endif
                                @endif
                            </span>
                        </a>
                    </th>
                    <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        {{ __('Role(s)') }}
                    </th>
                    <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        {{ __('Email Verified') }}
                    </th>
                    <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <a href="#" data-sort="created_at" class="sort-link flex items-center gap-1 hover:text-gray-700">
                            {{ __('Created At') }}
                            <span class="sort-indicator" data-sort="created_at">
                                @if(!request('sort') || request('sort') === 'created_at')
                                    @if(request('dir') === 'desc' || (!request('sort') && !request('dir')))
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    @else
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                        </svg>
                                    @endif
                                @endif
                            </span>
                        </a>
                    </th>
                    @if(auth()->user()->hasRole('super_admin'))
                        <th scope="col" class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ __('Actions') }}
                        </th>
                    @endif
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach($users as $user)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2 whitespace-nowrap">
                            <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="h-6 w-6 rounded-full">
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">{{ $user->name }}</div>
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap">
                            <div class="text-sm text-gray-900">{{ $user->email }}</div>
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap">
                            <div class="flex flex-wrap gap-1">
                                @foreach($user->roles as $role)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                        {{ ucfirst($role->name) }}
                                    </span>
                                @endforeach
                                @if($user->roles->isEmpty())
                                    <span class="text-xs text-gray-500">{{ __('No role') }}</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap">
                            @if($user->email_verified_at)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    {{ __('Yes') }}
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    {{ __('No') }}
                                </span>
                            @endif
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-500">
                            {{ $user->created_at->format('Y-m-d H:i') }}
                        </td>
                        @if(auth()->user()->hasRole('super_admin'))
                            <td class="px-3 py-2 whitespace-nowrap text-right text-sm font-medium">
                                <div x-data="{ open: false }" class="relative inline-block text-left" x-id="['dropdown-{{ $user->id }}']">
                                    <button
                                        type="button"
                                        @click="open = !open"
                                        @keydown.escape="open = false"
                                        class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                        :aria-expanded="open"
                                        :aria-controls="$id('dropdown-{{ $user->id }}')"
                                    >
                                        {{ __('Actions') }}
                                        <svg class="ml-2 -mr-0.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </button>

                                    <div
                                        x-show="open"
                                        x-cloak
                                        @click.away="open = false"
                                        x-transition:enter="transition ease-out duration-100"
                                        x-transition:enter-start="transform opacity-0 scale-95"
                                        x-transition:enter-end="transform opacity-100 scale-100"
                                        x-transition:leave="transition ease-in duration-75"
                                        x-transition:leave-start="transform opacity-100 scale-100"
                                        x-transition:leave-end="transform opacity-0 scale-95"
                                        :id="$id('dropdown-{{ $user->id }}')"
                                        class="absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50"
                                    >
                                        <div class="py-1">
                                            @if(!$user->hasVerifiedEmail())
                                                <form method="POST" action="{{ route('admin.users.resend-verification', $user) }}" class="block">
                                                    @csrf
                                                    <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                        {{ __('Resend Verification Email') }}
                                                    </button>
                                                </form>
                                            @endif

                                            @if($user->id !== auth()->id())
                                                <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="block" onsubmit="return confirm('{{ __('Are you sure you want to delete this user?') }}');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-red-700 hover:bg-red-50">
                                                        {{ __('Delete User') }}
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="mt-6 pagination-links">
        {{ $users->links() }}
    </div>
@else
    <!-- Empty State -->
    <div class="text-center py-12">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
        </svg>
        <h3 class="mt-2 text-sm font-medium text-gray-900">{{ __('No users found') }}</h3>
        <p class="mt-1 text-sm text-gray-500">
            {{ __('Try adjusting your search or filters.') }}
        </p>
    </div>
@endif

