<x-settings-layout>
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 text-gray-900">
            <div class="mb-6">
                <h3 class="text-lg font-medium text-gray-900">{{ __('Create Invitation') }}</h3>
                <p class="mt-1 text-sm text-gray-600">{{ __('Send an invitation to a new user to join the platform.') }}</p>
            </div>

            <form method="POST" action="{{ route('staff.settings.invitations.store') }}">
                @csrf

                <!-- Email -->
                <div class="mb-4">
                    <x-input-label for="email" :value="__('Email')" />
                    <x-text-input
                        id="email"
                        class="block mt-1 w-full"
                        type="email"
                        name="email"
                        :value="old('email')"
                        required
                        autofocus
                        autocomplete="email"
                    />
                    <x-input-error class="mt-2" :messages="$errors->get('email')" />
                </div>

                <!-- Role -->
                <div class="mb-4">
                    <x-custom-select
                        name="role"
                        id="role"
                        :value="old('role')"
                        :label="__('Role')"
                        placeholder="{{ __('Select a role') }}"
                        :options="collect($allowedRoles)->mapWithKeys(fn($role) => [$role => ucfirst($role)])->toArray()"
                    />
                    <x-input-error class="mt-2" :messages="$errors->get('role')" />
                </div>

                <!-- Name (Optional) -->
                <div class="mb-4">
                    <x-input-label for="name" :value="__('Name (Optional)')" />
                    <x-text-input
                        id="name"
                        class="block mt-1 w-full"
                        type="text"
                        name="name"
                        :value="old('name')"
                        autocomplete="name"
                    />
                    <p class="mt-1 text-xs text-gray-500">{{ __('The invited user can change this during registration.') }}</p>
                    <x-input-error class="mt-2" :messages="$errors->get('name')" />
                </div>

                <div class="flex items-center justify-end mt-6">
                    <a href="{{ route('staff.settings.invitations.index') }}" class="mr-4 text-sm text-gray-600 hover:text-gray-900">
                        {{ __('Cancel') }}
                    </a>
                    <x-primary-button>
                        {{ __('Create Invitation') }}
                    </x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-settings-layout>


