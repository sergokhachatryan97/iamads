<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\ClientLoginLogServiceInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class ClientSocialAuthController extends Controller
{
    /**
     * Allowed social authentication providers.
     */
    private const ALLOWED_PROVIDERS = ['google', 'apple', 'yandex', 'telegram'];

    public function __construct(
        private ClientLoginLogServiceInterface $clientLoginLogService
    ) {
    }

    /**
     * Redirect to the provider's authentication page.
     */
    public function redirect(string $provider): RedirectResponse
    {
        if (!in_array($provider, self::ALLOWED_PROVIDERS)) {
            abort(404);
        }

        // Telegram doesn't use OAuth redirect, it uses widget
        if ($provider === 'telegram') {
            abort(404);
        }

        return Socialite::driver($provider)->redirect();
    }

    /**
     * Handle the provider callback.
     */
    public function callback(string $provider, Request $request): RedirectResponse
    {
        if (!in_array($provider, self::ALLOWED_PROVIDERS)) {
            abort(404);
        }

        // Handle Telegram separately
        if ($provider === 'telegram') {
            return $this->telegramCallback($request);
        }

        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();

            return $this->handleSocialUser(
                $provider,
                $socialUser->getId(),
                $socialUser->getEmail(),
                $socialUser->getName(),
                $socialUser->getAvatar()
            );
        } catch (\Exception $e) {
            \Log::error('Social auth callback error: ' . $e->getMessage());
            return redirect()->route('register')
                ->withErrors(['email' => 'Authentication failed. Please try again.']);
        }
    }

    /**
     * Handle Telegram login callback.
     */
    private function telegramCallback(Request $request): RedirectResponse
    {
        $data = $request->only(['id', 'first_name', 'last_name', 'username', 'photo_url', 'auth_date', 'hash']);

        // Verify required fields
        if (empty($data['id']) || empty($data['auth_date']) || empty($data['hash'])) {
            return redirect()->route('register')
                ->withErrors(['email' => 'Invalid Telegram authentication data.']);
        }

        // Verify signature
        if (!$this->verifyTelegramSignature($data)) {
            return redirect()->route('register')
                ->withErrors(['email' => 'Telegram authentication signature is invalid.']);
        }

        // Verify auth_date is recent (within 1 day)
        $authDate = (int) $data['auth_date'];
        if (time() - $authDate > 86400) {
            return redirect()->route('register')
                ->withErrors(['email' => 'Telegram authentication has expired. Please try again.']);
        }

        // Extract user data
        $providerId = (string) $data['id'];
        $firstName = $data['first_name'] ?? '';
        $lastName = $data['last_name'] ?? '';
        $name = trim($firstName . ' ' . $lastName) ?: 'Unnamed';
        $username = $data['username'] ?? null;
        $avatar = $data['photo_url'] ?? null;

        // Telegram doesn't provide email, so create a placeholder
        $email = $username ? "{$username}@telegram.local" : "{$providerId}@telegram.local";

        return $this->handleSocialUser('telegram', $providerId, $email, $name, $avatar);
    }

    /**
     * Verify Telegram login signature.
     */
    private function verifyTelegramSignature(array $data): bool
    {
        $hash = $data['hash'];
        unset($data['hash']);

        // Build data_check_string from all fields except hash, sorted by key
        ksort($data);
        $dataCheckString = [];
        foreach ($data as $key => $value) {
            $dataCheckString[] = "{$key}={$value}";
        }
        $dataCheckString = implode("\n", $dataCheckString);

        // Secret key = SHA256 hash of bot token
        $botToken = config('services.telegram.bot_token');
        if (!$botToken) {
            return false;
        }
        $secretKey = hash('sha256', $botToken, true);

        // Calculate check hash
        $checkHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        // Timing-safe comparison
        return hash_equals($checkHash, $hash);
    }

    /**
     * Handle social user authentication and registration.
     */
    private function handleSocialUser(
        string $provider,
        string $providerId,
        ?string $email,
        ?string $name,
        ?string $avatar
    ): RedirectResponse {
        // Check if client exists with this provider + provider_id
        $client = Client::where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();

        // If not found and email exists, try to find by email
        if (!$client && $email) {
            $client = Client::where('email', $email)->first();

            // If found but doesn't have provider_id, link the account
            // If it already has a different provider, don't link - will create new below
            if ($client && empty($client->provider_id)) {
                $client->update([
                    'provider' => $provider,
                    'provider_id' => $providerId,
                    'avatar' => $avatar ?? $client->avatar,
                ]);
            } elseif ($client && $client->provider_id) {
                // Email exists but with different provider - don't link, will create new
                $client = null;
            }
        }

        // If still not found, create new client
        if (!$client) {
            // Generate placeholder email if none provided
            if (!$email) {
                $email = "{$providerId}@{$provider}.local";
            }

            // Ensure email is unique
            $originalEmail = $email;
            $counter = 1;
            while (Client::where('email', $email)->exists()) {
                $email = $originalEmail . '.' . $counter;
                $counter++;
            }

            $client = Client::create([
                'name' => $name ?: 'Unnamed',
                'email' => $email,
                'password' => Hash::make(Str::random(60)), // Random strong password
                'provider' => $provider,
                'provider_id' => $providerId,
                'avatar' => $avatar,
                'staff_id' => null,
                'balance' => 0,
                'spent' => 0,
                'discount' => 0,
                'status' => 'active',
            ]);
        }

        // Check if client is suspended
        if ($client->status === 'suspended') {
            return redirect()->route('login')
                ->withErrors(['email' => 'Your account is suspended. You cannot access the panel.']);
        }

        // Login the client
        Auth::guard('client')->login($client);

        // Update last_auth timestamp
        $client->update(['last_auth' => now()]);

        // Create login log entry
        $this->clientLoginLogService->createLoginLogFromRequest($client, request());

        return redirect()->intended(route('dashboard', absolute: false));
    }

}
