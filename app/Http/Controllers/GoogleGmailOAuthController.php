<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Google\Client as GoogleClient;

class GoogleGmailOAuthController extends Controller
{
    private function makeClient(): GoogleClient
    {
        $cfgPath = config('telegram_mtproto.setup.2fa.gmail_credentials_path');

        // ✅ if config path is set, make it absolute if needed
        if ($cfgPath) {
            $credentialsPath = str_starts_with($cfgPath, DIRECTORY_SEPARATOR)
                ? $cfgPath
                : base_path($cfgPath);
        } else {
            // ✅ default
            $credentialsPath = storage_path('app/gmail-credentials.json');
        }

        $credentialsPath = realpath($credentialsPath) ?: $credentialsPath;

        if (!is_file($credentialsPath) || !is_readable($credentialsPath)) {
            abort(500, "Gmail credentials JSON not readable at: {$credentialsPath}");
        }

        $cfg = json_decode((string) file_get_contents($credentialsPath), true);
        if (!$cfg || !isset($cfg['web']['client_id'])) {
            abort(500, "Invalid gmail credentials json structure at: {$credentialsPath}");
        }

        $client = new GoogleClient();
        $client->setAuthConfig($cfg);
        $client->setScopes(['https://www.googleapis.com/auth/gmail.readonly']);
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setRedirectUri(route('oauth.google.callback'));

        return $client;
    }


    public function redirect()
    {
        $client = $this->makeClient();
        $authUrl = $client->createAuthUrl();
        return redirect()->away($authUrl);
    }

    /**
     * Step 2: Google redirects back here with ?code=...
     * We exchange it for tokens and store them in storage/app/gmail-token.json
     */
    public function callback(Request $request)
    {
        $code = $request->query('code');
        $err  = $request->query('error');
        if (!$request->query('code')) {
            return response()->json([
                'ok' => false,
                'query' => $request->query(),
                'full_url' => $request->fullUrl(),
            ], 400);
        }
        if ($err) {
            abort(400, "Google OAuth error: {$err}");
        }
        if (!$code) {
            abort(400, "Missing 'code' in callback");
        }

        $client = $this->makeClient();

        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            abort(500, 'Token exchange failed: ' . ($token['error_description'] ?? $token['error']));
        }


        $path = storage_path('app/gmail-token.json');

        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        file_put_contents($path, json_encode($token, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return response()->json([
            'ok' => true,
            'saved_to' => $path,
            'exists' => file_exists($path),
            'size' => file_exists($path) ? filesize($path) : null,
            'has_refresh_token' => !empty($token['refresh_token']),
            'cwd' => getcwd(),
        ]);

    }
}
