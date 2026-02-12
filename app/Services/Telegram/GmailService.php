<?php

namespace App\Services\Telegram;

use Google_Client;
use Google_Service_Gmail;

/**
 * Service for interacting with Gmail API to retrieve Telegram 2FA confirmation codes.
 */
class GmailService
{
    private ?Google_Service_Gmail $service = null;

    /**
     * Initialize Gmail API client.
     */
    private function getService(): Google_Service_Gmail
    {
        if ($this->service) return $this->service;

        // 1) OAuth client credentials (client_id/secret)

        $cfgPath = config('telegram_mtproto.setup.2fa.gmail_credentials_path');


        if ($cfgPath) {
            $credentialsPath = str_starts_with($cfgPath, DIRECTORY_SEPARATOR)
                ? $cfgPath
                : base_path($cfgPath);
        } else {
            // ✅ default
            $credentialsPath = storage_path('app/gmail-credentials.json');
        }


        $credentialsPath = realpath($credentialsPath) ?: $credentialsPath;

        if (!is_readable($credentialsPath)) {
            throw new \RuntimeException("Gmail credentials not readable: {$credentialsPath}");
        }

        // 2) User token (access_token + refresh_token)
        $tokenPath = config('telegram_mtproto.setup.2fa.gmail_token_path')
            ?: storage_path('app/gmail-token.json');

        $tokenPath = realpath($tokenPath) ?: $tokenPath;
        if (!is_readable($tokenPath)) {
            throw new \RuntimeException("Gmail token not readable: {$tokenPath} (run OAuth redirect/callback first)");
        }

        $client = new Google_Client();
        $client->setAuthConfig($credentialsPath);
        $client->setScopes([Google_Service_Gmail::GMAIL_READONLY]);
        $client->setAccessType('offline');

        $token = json_decode(file_get_contents($tokenPath), true);
        if (!$token || empty($token['access_token'])) {
            throw new \RuntimeException("Invalid gmail-token.json structure: {$tokenPath}");
        }

        // ✅ սա է missing մասը (քեզ մոտ token:null էր դրա համար)
        $client->setAccessToken($token);

        // Refresh եթե access_token-ը expire է
        if ($client->isAccessTokenExpired()) {
            if (empty($token['refresh_token'])) {
                throw new \RuntimeException("Access token expired and refresh_token missing in {$tokenPath}");
            }
            $newToken = $client->fetchAccessTokenWithRefreshToken($token['refresh_token']);
            if (isset($newToken['error'])) {
                throw new \RuntimeException("Token refresh failed: " . ($newToken['error_description'] ?? $newToken['error']));
            }

            // keep refresh_token
            if (empty($newToken['refresh_token'])) {
                $newToken['refresh_token'] = $token['refresh_token'];
            }

            file_put_contents($tokenPath, json_encode($newToken, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $client->setAccessToken($newToken);
        }

        return $this->service = new Google_Service_Gmail($client);
    }

    public function findConfirmationCode(string $aliasEmail, int $maxAgeHours = 24): ?string
    {
        $service = $this->getService();

        $query = "newer_than:{$maxAgeHours}h to:{$aliasEmail}";

        $results = $service->users_messages->listUsersMessages('me', [
            'q' => $query,
            'maxResults' => 10,
        ]);


        $messages = $results->getMessages();
        if (!$messages) return null;

        foreach ($messages as $m) {
            $msg = $service->users_messages->get('me', $m->getId(), ['format' => 'full']);
            $code = $this->extractCodeFromMessage($msg);
            if ($code) return $code;
        }

        return null;
    }


    /**
     * Extract 5-6 digit confirmation code from Gmail message.
     *
     * @param \Google_Service_Gmail_Message $message
     * @return string|null
     */
    private function extractCodeFromMessage(\Google_Service_Gmail_Message $message): ?string
    {
        $body = $this->getMessageBody($message);

        if (empty($body)) {
            return null;
        }

        // Look for 5-6 digit codes (Telegram confirmation codes)
        // Pattern: standalone digits, possibly with spaces/dashes
        if (preg_match('/\b(\d{5,6})\b/', $body, $matches)) {
            return $matches[1];
        }

        // Alternative pattern: code in format like "12345" or "123 456"
        if (preg_match('/(?:code|confirmation|verification)[\s:]*(\d{5,6})/i', $body, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get message body text from Gmail message.
     *
     * @param \Google_Service_Gmail_Message $message
     * @return string
     */
    private function getMessageBody(\Google_Service_Gmail_Message $message): string
    {
        $payload = $message->getPayload();
        $body = '';

        if ($payload) {
            $parts = $payload->getParts();
            if ($parts) {
                foreach ($parts as $part) {
                    $mimeType = $part->getMimeType();
                    $data = $part->getBody()->getData();

                    if ($mimeType === 'text/plain' && $data) {
                        $body .= base64_decode(str_replace(['-', '_'], ['+', '/'], $data));
                    } elseif ($mimeType === 'text/html' && $data && empty($body)) {
                        // Fallback to HTML if plain text not available
                        $html = base64_decode(str_replace(['-', '_'], ['+', '/'], $data));
                        // Strip HTML tags for code extraction
                        $body .= strip_tags($html);
                    }
                }
            } else {
                // Single part message
                $data = $payload->getBody()?->getData();
                if ($data) {
                    $body = base64_decode(str_replace(['-', '_'], ['+', '/'], $data));
                }
            }
        }

        return $body;
    }
}

