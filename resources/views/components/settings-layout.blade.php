<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Settings') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="flex flex-col lg:flex-row gap-6">
                <!-- Sidebar -->
                <aside class="lg:w-64 shrink-0">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <nav class="p-4">
                            <ul class="space-y-1">
                                <li>
                                    <a href="{{ route('settings.roles.index') }}" 
                                       class="flex items-center px-4 py-3 rounded-md text-sm font-medium transition {{ request()->routeIs('settings.roles.*') ? 'bg-indigo-50 text-indigo-700 border-l-4 border-indigo-600' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                        <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                        </svg>
                                        {{ __('Roles') }}
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ route('settings.invitations.index') }}" 
                                       class="flex items-center px-4 py-3 rounded-md text-sm font-medium transition {{ request()->routeIs('settings.invitations.*') ? 'bg-indigo-50 text-indigo-700 border-l-4 border-indigo-600' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                        <svg class="mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                        </svg>
                                        {{ __('Invitations') }}
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </aside>

                <!-- Main Content -->
                <main class="flex-1 min-w-0">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </div>
</x-app-layout>

