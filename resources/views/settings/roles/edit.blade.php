<x-settings-layout>
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 text-gray-900">
            <div class="mb-4">
                <a href="{{ route('settings.roles.index') }}" class="text-indigo-600 hover:text-indigo-900 text-sm">
                    ← {{ __('Back to Roles') }}
                </a>
            </div>

            <h3 class="text-lg font-medium text-gray-900 mb-6">{{ __('Edit Role') }}: {{ $role->name }}</h3>

            <form method="POST" action="{{ route('settings.roles.update', $role) }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">{{ __('Role Name') }}</h3>
                            <div class="text-sm text-gray-600 bg-gray-50 p-3 rounded-md">
                                {{ $role->name }}
                            </div>
                        </div>

                        <div class="mb-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">{{ __('Permissions') }}</h3>
                            
                            @if($permissions->isEmpty())
                                <p class="text-sm text-gray-500">{{ __('No permissions available.') }}</p>
                            @else
                                <div class="space-y-2">
                                    @foreach($permissions as $permission)
                                        <label class="flex items-center">
                                            <input 
                                                type="checkbox" 
                                                name="permissions[]" 
                                                value="{{ $permission->name }}"
                                                {{ $role->hasPermissionTo($permission) ? 'checked' : '' }}
                                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                            >
                                            <span class="ml-2 text-sm text-gray-700">{{ $permission->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @endif

                            @error('permissions')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            @error('permissions.*')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button>{{ __('Save') }}</x-primary-button>

                            @if (session('status') === 'role-updated')
                                <p
                                    x-data="{ show: true }"
                                    x-show="show"
                                    x-transition
                                    x-init="setTimeout(() => show = false, 2000)"
                                    class="text-sm text-gray-600"
                                >{{ __('Saved.') }}</p>
                            @endif
                        </div>
                    </form>
        </div>
    </div>
</x-settings-layout>

