<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Edit User Role') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="mb-6">
                        <a href="{{ route('staff.users.index') }}" class="text-indigo-600 hover:text-indigo-900 text-sm">
                            ← {{ __('Back to Managers') }}
                        </a>
                    </div>

                    <div class="mb-6 flex items-center gap-4">
                        <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="h-12 w-12 rounded-full">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ $user->name }}</p>
                            <p class="text-sm text-gray-500">{{ $user->email }}</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('staff.users.update', $user) }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-6">
                            <x-custom-select
                                name="role"
                                id="role"
                                :value="old('role', $user->roles->first()?->name ?? '')"
                                :label="__('Role')"
                                placeholder="{{ __('Select a role') }}"
                                :options="collect($roles)->mapWithKeys(fn($role) => [$role->name => ucfirst($role->name)])->toArray()"
                            />
                            <x-input-error class="mt-2" :messages="$errors->get('role')" />
                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button>{{ __('Save') }}</x-primary-button>
                            <a href="{{ route('staff.users.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
                                {{ __('Cancel') }}
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
