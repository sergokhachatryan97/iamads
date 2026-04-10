<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Uniqueness control: one execution per (account, target, action) across providers.
 * Used before execution (skip if already performed) and after success (record).
 */
class ProviderActionLogService
{
    public const PROVIDER_TELEGRAM = 'telegram';
    public const PROVIDER_YOUTUBE = 'youtube';
    public const PROVIDER_APP = 'app';

    /**
     * Check if this account has already performed this action on this target.
     */
    public function hasPerformed(string $provider, string $accountIdentifier, string $targetHash, string $action): bool
    {
        return DB::table('provider_action_logs')
            ->where('provider', $provider)
            ->where('account_identifier', $accountIdentifier)
            ->where('target_hash', $targetHash)
            ->where('action', $action)
            ->exists();
    }

    /**
     * Check if this account has performed ANY of the given actions on this target.
     * Single query — replaces N hasPerformed() calls in hot paths like claim conflict checks.
     *
     * @param  array<string>  $actions
     */
    public function hasPerformedAny(string $provider, string $accountIdentifier, string $targetHash, array $actions): bool
    {
        if (empty($actions)) {
            return false;
        }

        return DB::table('provider_action_logs')
            ->where('provider', $provider)
            ->where('account_identifier', $accountIdentifier)
            ->where('target_hash', $targetHash)
            ->whereIn('action', $actions)
            ->exists();
    }

    /**
     * Record that this account performed this action on this target.
     * Uses DB unique constraint; duplicate insert will throw.
     *
     * @return bool True if inserted, false if duplicate (already existed)
     */
    public function recordPerformed(string $provider, string $accountIdentifier, string $targetHash, string $action): bool
    {
        try {
            DB::table('provider_action_logs')->insert([
                'provider' => $provider,
                'account_identifier' => $accountIdentifier,
                'target_hash' => $targetHash,
                'action' => $action,
                'created_at' => now(),
            ]);
            return true;
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'provider_action_logs_unique')) {
                return false;
            }
            Log::error('ProviderActionLogService::recordPerformed failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
