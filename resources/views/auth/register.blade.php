<x-guest-layout>
    <style>
        /* Style Telegram widget container */
        #telegram-button-container {
            position: relative;
        }
        
        /* Hide Telegram widget iframe but keep it functional */
        #telegram-button-container iframe[src*="telegram.org"] {
            opacity: 0.01 !important; /* Slightly visible to ensure it receives events */
            pointer-events: auto !important;
            cursor: pointer !important;
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100% !important;
            border: none !important;
            z-index: 30 !important;
            background: transparent !important;
        }
        
        /* Make visual button non-interactive */
        #telegram-button-visual {
            pointer-events: none !important;
            z-index: 1 !important;
        }
        
        /* Make widget wrapper interactive */
        #telegram-widget-wrapper {
            pointer-events: auto !important;
            z-index: 30 !important;
        }
    </style>
    
    <!-- Social Login Buttons -->
    <div class="mb-6">
        <div class="relative">
            <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-gray-300"></div>
            </div>
            <div class="relative flex justify-center text-sm">
                <span class="px-2 bg-white text-gray-500">Or continue with</span>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-2 gap-3">
            <!-- Google -->
            <a href="{{ route('auth.social.redirect', 'google') }}" 
               class="w-full inline-flex justify-center items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                <svg class="w-5 h-5" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
                <span class="ml-2">Google</span>
            </a>

            <!-- Apple -->
            <a href="{{ route('auth.social.redirect', 'apple') }}" 
               class="w-full inline-flex justify-center items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M17.05 20.28c-.98.95-2.05.88-3.08.4-1.09-.5-2.08-.48-3.24 0-1.44.62-2.2.44-3.06-.4C1.79 15.25 4.23 7.8 9.03 7.45c1.35.08 2.29.74 3.08.8 1.18-.24 2.31-.93 3.57-.84 1.51.12 2.65.72 3.4 1.8-3.12 1.87-2.38 5.98.48 7.13-.57 1.5-1.31 2.99-2.54 4.09l.01.01zm-2.82-17.3c.58 1.08.18 2.42-.8 3.16-.98.75-2.32.48-3.02-.45-.63-.92-.23-2.18.73-2.91 1.05-.83 2.35-.57 3.09.2z"/>
                </svg>
                <span class="ml-2">Apple</span>
            </a>

            <!-- Yandex -->
            <a href="{{ route('auth.social.redirect', 'yandex') }}" 
               class="w-full inline-flex justify-center items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="#FC3F1D">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1.41 16.5v-5.5H7.5l4.5-6.5v5.5h3L10.59 18.5z"/>
                </svg>
                <span class="ml-2">Yandex</span>
            </a>

            <!-- Telegram -->
            <div class="w-full relative" id="telegram-button-container" style="height: 38px; overflow: hidden; cursor: pointer;">
                <!-- Custom styled button (always visible) -->
                <div class="w-full h-full inline-flex justify-center items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" id="telegram-button-visual">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="#0088cc">
                        <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221l-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.446 1.394c-.14.18-.357.295-.6.295-.002 0-.003 0-.005 0l.213-3.054 5.56-5.022c.24-.213-.054-.334-.373-.12l-6.87 4.326-2.96-.924c-.64-.203-.658-.64.135-.954l11.566-4.458c.538-.196 1.006.128.832.941z"/>
                    </svg>
                    <span class="ml-2">Telegram</span>
                </div>
                <!-- Telegram widget (invisible but clickable) -->
                <div class="absolute inset-0" id="telegram-widget-wrapper" style="z-index: 20;">
                    <script async src="https://telegram.org/js/telegram-widget.js?22" 
                            data-telegram-login="{{ config('services.telegram.bot_username') }}" 
                            data-size="medium" 
                            data-onauth="onTelegramAuth(user)" 
                            data-request-access="write"
                            data-userpic="false"
                            data-corner-radius="6"></script>
                </div>
                <script type="text/javascript">
                    function onTelegramAuth(user) {
                        // Create a form and submit it to the callback URL
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '{{ route('auth.social.callback', 'telegram') }}';
                        
                        // Add CSRF token
                        const csrfInput = document.createElement('input');
                        csrfInput.type = 'hidden';
                        csrfInput.name = '_token';
                        csrfInput.value = '{{ csrf_token() }}';
                        form.appendChild(csrfInput);
                        
                        // Add all user data
                        for (const key in user) {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = key;
                            input.value = user[key];
                            form.appendChild(input);
                        }
                        
                        document.body.appendChild(form);
                        form.submit();
                    }
                    
                    // Ensure Telegram widget is clickable - use MutationObserver to catch when iframe loads
                    (function() {
                        let attempts = 0;
                        const maxAttempts = 30;
                        
                        function setupTelegramWidget() {
                            attempts++;
                            const container = document.getElementById('telegram-button-container');
                            const widgetWrapper = document.getElementById('telegram-widget-wrapper');
                            const iframe = container ? container.querySelector('iframe[src*="telegram.org"]') : null;
                            
                            if (iframe) {
                                // Make iframe nearly invisible but still clickable
                                iframe.style.setProperty('opacity', '0.01', 'important');
                                iframe.style.setProperty('pointer-events', 'auto', 'important');
                                iframe.style.setProperty('cursor', 'pointer', 'important');
                                iframe.style.setProperty('position', 'absolute', 'important');
                                iframe.style.setProperty('top', '0', 'important');
                                iframe.style.setProperty('left', '0', 'important');
                                iframe.style.setProperty('width', '100%', 'important');
                                iframe.style.setProperty('height', '100%', 'important');
                                iframe.style.setProperty('border', 'none', 'important');
                                iframe.style.setProperty('z-index', '30', 'important');
                                iframe.style.setProperty('background', 'transparent', 'important');
                                
                                // Make wrapper clickable
                                if (widgetWrapper) {
                                    widgetWrapper.style.setProperty('pointer-events', 'auto', 'important');
                                    widgetWrapper.style.setProperty('cursor', 'pointer', 'important');
                                    widgetWrapper.style.setProperty('z-index', '30', 'important');
                                }
                                
                                console.log('Telegram widget configured successfully');
                                return true;
                            } else if (attempts < maxAttempts) {
                                setTimeout(setupTelegramWidget, 200);
                            }
                            return false;
                        }
                        
                        // Start setup
                        if (document.readyState === 'loading') {
                            document.addEventListener('DOMContentLoaded', setupTelegramWidget);
                        } else {
                            setupTelegramWidget();
                        }
                        
                        // Use MutationObserver to catch dynamically added iframes
                        const container = document.getElementById('telegram-button-container');
                        if (container) {
                            const observer = new MutationObserver(function(mutations) {
                                mutations.forEach(function(mutation) {
                                    mutation.addedNodes.forEach(function(node) {
                                        if (node.tagName === 'IFRAME' && node.src && node.src.includes('telegram.org')) {
                                            setTimeout(setupTelegramWidget, 100);
                                        } else if (node.querySelectorAll) {
                                            const iframes = node.querySelectorAll('iframe[src*="telegram.org"]');
                                            if (iframes.length > 0) {
                                                setTimeout(setupTelegramWidget, 100);
                                            }
                                        }
                                    });
                                });
                            });
                            
                            observer.observe(container, { 
                                childList: true, 
                                subtree: true 
                            });
                            
                            // Also observe the wrapper
                            const wrapper = document.getElementById('telegram-widget-wrapper');
                            if (wrapper) {
                                observer.observe(wrapper, { 
                                    childList: true, 
                                    subtree: true 
                                });
                            }
                        }
                    })();
                </script>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-text-input id="password_confirmation" class="block mt-1 w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="ms-4">
                {{ __('Register') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
