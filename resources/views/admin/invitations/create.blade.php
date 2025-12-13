<x-settings-layout>
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 text-gray-900">
            <div class="mb-4">
                <a href="{{ route('settings.invitations.index') }}" class="text-indigo-600 hover:text-indigo-900 text-sm">
                    ← {{ __('Back to Invitations') }}
                </a>
            </div>

            <h3 class="text-lg font-medium text-gray-900 mb-6">{{ __('Send Invitation') }}</h3>

            <form method="POST" action="{{ route('settings.invitations.store') }}">
                @csrf

                <!-- Email -->
                <div class="mb-4">
                    <x-input-label for="email" :value="__('Email')" />
                    <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus />
                    <x-input-error class="mt-2" :messages="$errors->get('email')" />
                </div>

                <!-- Role -->
                <div class="mb-4">
                    <x-input-label for="role" :value="__('Role')" />
                    <select id="role" name="role" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                        <option value="">{{ __('Select a role') }}</option>
                        @foreach($allowedRoles as $role)
                            <option value="{{ $role }}" {{ old('role') === $role ? 'selected' : '' }}>
                                {{ ucfirst($role) }}
                            </option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('role')" />
                </div>

                <!-- Name (Optional) -->
                <div class="mb-4">
                    <x-input-label for="name" :value="__('Name (Optional)')" />
                    <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" />
                    <p class="mt-1 text-xs text-gray-500">{{ __('Optional: Pre-fill the name for the registration form') }}</p>
                    <x-input-error class="mt-2" :messages="$errors->get('name')" />
                </div>

                <div class="flex items-center gap-4">
                    <x-primary-button>{{ __('Create Invitation') }}</x-primary-button>

                    @if (session('status') === 'invitation-sent')
                        <p
                            x-data="{ show: true }"
                            x-show="show"
                            x-transition
                            x-init="setTimeout(() => show = false, 2000)"
                            class="text-sm text-gray-600"
                        >{{ __('Invitation created successfully!') }}</p>
                    @endif
                </div>
            </form>
        </div>
    </div>
</x-settings-layout>

