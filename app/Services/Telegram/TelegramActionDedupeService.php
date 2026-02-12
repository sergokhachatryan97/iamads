<?php

namespace App\Services\Telegram;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TelegramActionDedupeService
{
    /**
     * Normalize and hash a Telegram link from parsed data.
     *
     * @param array $parsed Parsed link data from TelegramLinkParser
     * @return string Hash string (64 chars)
     */
    public function normalizeAndHashLink(array $parsed): string
    {
        $kind = $parsed['kind'] ?? 'unknown';
        $normalized = '';

        switch ($kind) {

            case 'bot_start':
            case 'bot_startgroup':
            case 'bot_start_with_referral':
            case 'bot_startgroup_with_referral':
                $username = strtolower($parsed['username'] ?? '');
                $start = (string) ($parsed['start'] ?? '');
                $normalized = "b:{$username}:{$start}";
                break;

            case 'public_username':
                $username = strtolower($parsed['username'] ?? '');
                $normalized = "u:{$username}";
                break;

            case 'invite':
                $normalized = "i:" . ($parsed['hash'] ?? '');
                break;

            case 'public_post':
                $username = strtolower($parsed['username'] ?? '');
                $postId = (int) ($parsed['post_id'] ?? 0);
                $normalized = "p:{$username}:{$postId}";
                break;

            default:
                $normalized = "r:" . strtolower(trim($parsed['raw'] ?? ''));
                break;
        }

        return hash('sha256', $normalized);
    }

    /**
     * Try to log an action once. Returns true if logged (new), false if duplicate.
     *
     * @param int $accountId
     * @param string $linkHash
     * @param string $action
     * @param Model|null $subject Order or ClientServiceQuota
     * @param array|null $meta Optional metadata
     * @return bool True if action was logged (first time), false if duplicate
     */
    public function tryLogOnce(int $accountId, string $linkHash, string $action, ?Model $subject = null, ?array $meta = null): bool
    {
        try {
            DB::table('telegram_action_logs')->insert([
                'telegram_account_id' => $accountId,
                'link_hash' => $linkHash,
                'action' => $action,
                'subject_type' => $subject ? get_class($subject) : null,
                'subject_id' => $subject?->id,
                'performed_at' => now(),
                'reversed_at' => null,
                'meta' => $meta ? json_encode($meta) : null,
            ]);

            return true;
        } catch (\Illuminate\Database\QueryException $e) {
            // Check if it's a duplicate key error
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'unique_account_link_action')) {
                // Duplicate: this account already performed this action on this link
                return false;
            }

            // Other database error, re-throw
            Log::error('Failed to log Telegram action', [
                'account_id' => $accountId,
                'link_hash' => $linkHash,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Mark an action as reversed (e.g., unsubscribe reversing subscribe).
     *
     * @param int $accountId
     * @param string $linkHash
     * @param string $action
     * @return bool True if marked, false if not found
     */
    public function markReversed(int $accountId, string $linkHash, string $action): bool
    {
        $updated = DB::table('telegram_action_logs')
            ->where('telegram_account_id', $accountId)
            ->where('link_hash', $linkHash)
            ->where('action', $action)
            ->whereNull('reversed_at')
            ->update(['reversed_at' => now()]);

        return $updated > 0;
    }

    /**
     * Check if an account has actively performed an action on a link (not reversed).
     *
     * @param int $accountId
     * @param string $linkHash
     * @param string $action
     * @return bool True if actively performed (not reversed), false otherwise
     */
    public function hasActivePerformed(int $accountId, string $linkHash, string $action): bool
    {
        return DB::table('telegram_action_logs')
            ->where('telegram_account_id', $accountId)
            ->where('link_hash', $linkHash)
            ->where('action', $action)
            ->whereNull('reversed_at')
            ->exists();
    }

    /**
     * Get account IDs that have actively performed an action on a link (not reversed).
     *
     * @param string $linkHash
     * @param string $action
     * @return array<int> Array of account IDs
     */
    public function getAccountsThatActivePerformed(string $linkHash, string $action): array
    {
        return DB::table('telegram_action_logs')
            ->where('link_hash', $linkHash)
            ->where('action', $action)
            ->whereNull('reversed_at')
            ->pluck('telegram_account_id')
            ->toArray();
    }

    /**
     * Get account IDs that have already performed an action on a link (including reversed).
     * This is for backward compatibility.
     *
     * @param string $linkHash
     * @param string $action
     * @return array<int> Array of account IDs
     */
    public function getAccountsThatPerformed(string $linkHash, string $action): array
    {
        return DB::table('telegram_action_logs')
            ->where('link_hash', $linkHash)
            ->where('action', $action)
            ->pluck('telegram_account_id')
            ->toArray();
    }
}
