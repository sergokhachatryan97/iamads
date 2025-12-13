<x-settings-layout>
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 text-gray-900">
            <div class="mb-4">
                <a href="{{ route('settings.invitations.index') }}" class="text-indigo-600 hover:text-indigo-900 text-sm">
                    ← {{ __('Back to Invitations') }}
                </a>
            </div>

            <h3 class="text-lg font-medium text-gray-900 mb-6">{{ __('Invitation Created') }}</h3>

            <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                <p class="text-sm text-green-800">
                    <strong>{{ __('Success!') }}</strong> {{ __('Invitation has been created. Copy the link below or send it via email.') }}
                </p>
            </div>

            <!-- Invitation Details -->
            <div class="mb-6">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Email') }}</label>
                    <p class="text-sm text-gray-900">{{ $invitation->email }}</p>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Role') }}</label>
                    <p class="text-sm text-gray-900">{{ ucfirst($invitation->role) }}</p>
                </div>
                @if($invitation->name)
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Name') }}</label>
                    <p class="text-sm text-gray-900">{{ $invitation->name }}</p>
                </div>
                @endif
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Expires At') }}</label>
                    <p class="text-sm text-gray-900">{{ $invitation->expires_at->format('Y-m-d H:i:s') }}</p>
                </div>
            </div>

            <!-- Invitation Link -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Invitation Link') }}</label>
                <div class="flex items-center gap-2">
                    <input 
                        type="text" 
                        id="invitation-link" 
                        value="{{ $invitationLink }}" 
                        readonly 
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm bg-gray-50"
                    />
                    <button 
                        type="button"
                        onclick="copyInvitationLink()"
                        class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                    >
                        {{ __('Copy') }}
                    </button>
                </div>
                <p class="mt-2 text-xs text-gray-500">{{ __('Click the copy button to copy the invitation link to your clipboard.') }}</p>
            </div>

            <!-- Send Email Button -->
            <div class="flex items-center gap-4">
                <form method="POST" action="{{ route('settings.invitations.send-email', $invitation) }}">
                    @csrf
                    <x-primary-button type="submit">
                        {{ __('Send Invitation Email') }}
                    </x-primary-button>
                </form>

                @if (session('status') === 'email-sent')
                    <p
                        x-data="{ show: true }"
                        x-show="show"
                        x-transition
                        x-init="setTimeout(() => show = false, 3000)"
                        class="text-sm text-green-600"
                    >{{ __('Email sent successfully!') }}</p>
                @endif
            </div>
        </div>
    </div>

    <script>
        function copyInvitationLink() {
            const linkInput = document.getElementById('invitation-link');
            linkInput.select();
            linkInput.setSelectionRange(0, 99999); // For mobile devices
            
            try {
                navigator.clipboard.writeText(linkInput.value);
                
                // Show feedback
                const button = event.target;
                const originalText = button.textContent;
                button.textContent = '{{ __('Copied!') }}';
                button.classList.add('bg-green-600', 'hover:bg-green-700');
                
                setTimeout(() => {
                    button.textContent = originalText;
                    button.classList.remove('bg-green-600', 'hover:bg-green-700');
                }, 2000);
            } catch (err) {
                // Fallback for older browsers
                document.execCommand('copy');
                alert('{{ __('Link copied to clipboard!') }}');
            }
        }
    </script>
</x-settings-layout>

