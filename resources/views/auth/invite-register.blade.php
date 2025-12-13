<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        <p class="mb-2">{{ __('You\'ve been invited to join') }} <strong>{{ config('app.name') }}</strong></p>
        <p class="text-xs text-gray-500">{{ __('This invitation link expires in 48 hours.') }}</p>
    </div>

    <form method="POST" action="{{ route('invitation.register', $token) }}">
        @csrf

        <!-- Email (Read-only) -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input 
                id="email" 
                class="block mt-1 w-full bg-gray-50" 
                type="email" 
                name="email" 
                value="{{ $invitation->email }}" 
                readonly 
                required 
            />
            <p class="mt-1 text-xs text-gray-500">{{ __('This email was provided in your invitation.') }}</p>
        </div>

        <!-- Name -->
        <div class="mt-4">
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input 
                id="name" 
                class="block mt-1 w-full" 
                type="text" 
                name="name" 
                :value="old('name', $invitation->name)" 
                required 
                autofocus 
                autocomplete="name" 
            />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input 
                id="password" 
                class="block mt-1 w-full"
                type="password"
                name="password"
                required 
                autocomplete="new-password" 
            />
            <x-input-error class="mt-2" :messages="$errors->get('password')" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
            <x-text-input 
                id="password_confirmation" 
                class="block mt-1 w-full"
                type="password"
                name="password_confirmation" 
                required 
                autocomplete="new-password" 
            />
            <x-input-error class="mt-2" :messages="$errors->get('password_confirmation')" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                {{ __('Create Profile') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>

