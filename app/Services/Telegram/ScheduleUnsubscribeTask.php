<?php

namespace App\Services\Telegram;

use App\Models\TelegramUnsubscribeTask;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class ScheduleUnsubscribeTask
{
    public function __construct(
        private TelegramActionDedupeService $dedupeService
    ) {}

    /**
     * Schedule an unsubscribe task after successful subscribe action.
     *
     * @param int $accountId
     * @param array $parsed Parsed link data from TelegramLinkParser
     * @param \App\Models\Service|string $serviceOrType Service model (preferred) or service type string
     * @param Model|null $subject Order or ClientServiceQuota
     * @return TelegramUnsubscribeTask|null Created task or null if not needed
     */
    public function schedule(int $accountId, array $parsed, \App\Models\Service|string $serviceOrType, ?Model $subject = null): ?TelegramUnsubscribeTask
    {
        // Get unsubscribe delay: prefer service.duration_days, fallback to config
        $unsubscribeAfterDays = null;
        if ($serviceOrType instanceof \App\Models\Service) {
            $unsubscribeAfterDays = $serviceOrType->duration_days;
        }

        // Fallback to config if service doesn't have duration_days
        if ($unsubscribeAfterDays === null) {
            $serviceType = $serviceOrType instanceof \App\Models\Service
                ? $serviceOrType->service_type
                : $serviceOrType;
            $unsubscribeAfterDays = $this->getUnsubscribeAfterDays($serviceType);
        }

        // Final fallback: config default (14 days)
        if ($unsubscribeAfterDays === null) {
            $unsubscribeAfterDays = (int) config('telegram.action_policies.subscribe.unsubscribe_after_days', 14);
        }

        // If no delay configured, don't schedule
        if ($unsubscribeAfterDays === null || $unsubscribeAfterDays <= 0) {
            return null;
        }

        // Normalize and hash link
        $linkHash = $this->dedupeService->normalizeAndHashLink($parsed);

        // Calculate due_at
        $dueAt = now()->addDays($unsubscribeAfterDays);

        try {
            // Create task (idempotent: unique constraint prevents duplicates)
            $task = TelegramUnsubscribeTask::firstOrCreate(
                [
                    'telegram_account_id' => $accountId,
                    'link_hash' => $linkHash,
                    'due_at' => $dueAt,
                ],
                [
                    'subject_type' => $subject ? get_class($subject) : null,
                    'subject_id' => $subject?->id,
                    'status' => 'pending',
                ]
            );

            if ($task->wasRecentlyCreated) {
                Log::info('Scheduled unsubscribe task', [
                    'task_id' => $task->id,
                    'account_id' => $accountId,
                    'link_hash' => $linkHash,
                    'due_at' => $dueAt->toDateTimeString(),
                    'service_type' => $serviceType,
                ]);
            }

            return $task;
        } catch (\Illuminate\Database\QueryException $e) {
            // Duplicate key error - task already exists, that's fine
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'unique_account_link_due')) {
                Log::debug('Unsubscribe task already exists', [
                    'account_id' => $accountId,
                    'link_hash' => $linkHash,
                    'due_at' => $dueAt->toDateTimeString(),
                ]);
                return null;
            }

            // Other error, re-throw
            Log::error('Failed to schedule unsubscribe task', [
                'account_id' => $accountId,
                'link_hash' => $linkHash,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get unsubscribe delay in days for a service type.
     *
     * @param string $serviceType
     * @return int|null Days to wait before unsubscribe, or null if not configured
     */
    private function getUnsubscribeAfterDays(string $serviceType): ?int
    {
        // First check service-type specific override
        $serviceTypeDelay = config("telegram.unsubscribe_delays.{$serviceType}");
        if ($serviceTypeDelay !== null) {
            return (int) $serviceTypeDelay;
        }

        // Fall back to subscribe action policy default
        $subscribePolicy = config('telegram.action_policies.subscribe', []);
        return isset($subscribePolicy['unsubscribe_after_days'])
            ? (int) $subscribePolicy['unsubscribe_after_days']
            : null;
    }
}
