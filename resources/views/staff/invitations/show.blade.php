<x-settings-layout>
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 text-gray-900">
            <div class="mb-6">
                <h3 class="text-lg font-medium text-gray-900">{{ __('Invitation Created') }}</h3>
                <p class="mt-1 text-sm text-gray-600">{{ __('Share this invitation link with the user.') }}</p>
            </div>

            @if (session('status') === 'email-sent')
                <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-md">
                    <p class="text-sm text-green-800">{{ __('Invitation email sent successfully!') }}</p>
                </div>
            @endif

            <div class="mb-4">
                <x-input-label for="invitation-link" :value="__('Invitation Link')" />
                <div class="mt-1 flex rounded-md shadow-sm">
                    <input 
                        type="text" 
                        id="invitation-link" 
                        value="{{ $invitationLink }}" 
                        readonly 
                        class="block w-full rounded-none rounded-l-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    />
                    <button 
                        type="button" 
                        onclick="copyToClipboard()"
                        class="inline-flex items-center rounded-r-md border border-l-0 border-gray-300 bg-gray-50 px-3 text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        {{ __('Copy') }}
                    </button>
                </div>
                <p class="mt-1 text-xs text-gray-500">{{ __('This link expires in 48 hours.') }}</p>
            </div>

            <div class="mb-4 p-4 bg-gray-50 rounded-md">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm font-medium text-gray-700">{{ __('Email') }}</p>
                        <p class="mt-1 text-sm text-gray-900">{{ $invitation->email }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-700">{{ __('Role') }}</p>
                        <p class="mt-1 text-sm text-gray-900">{{ ucfirst($invitation->role) }}</p>
                    </div>
                    @if($invitation->name)
                        <div>
                            <p class="text-sm font-medium text-gray-700">{{ __('Name') }}</p>
                            <p class="mt-1 text-sm text-gray-900">{{ $invitation->name }}</p>
                        </div>
                    @endif
                    <div>
                        <p class="text-sm font-medium text-gray-700">{{ __('Expires At') }}</p>
                        <p class="mt-1 text-sm text-gray-900">{{ $invitation->expires_at->format('M d, Y H:i') }}</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between mt-6">
                <a href="{{ route('staff.settings.invitations.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
                    {{ __('Back to Invitations') }}
                </a>
                <form method="POST" action="{{ route('staff.settings.invitations.send-email', $invitation) }}">
                    @csrf
                    <x-primary-button>
                        {{ __('Send Invitation Email') }}
                    </x-primary-button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function copyToClipboard() {
            const linkInput = document.getElementById('invitation-link');
            linkInput.select();
            linkInput.setSelectionRange(0, 99999); // For mobile devices
            document.execCommand('copy');
            
            // Show feedback
            const button = event.target.closest('button');
            const originalText = button.textContent;
            button.textContent = '{{ __('Copied!') }}';
            setTimeout(() => {
                button.textContent = originalText;
            }, 2000);
        }
    </script>
</x-settings-layout>


