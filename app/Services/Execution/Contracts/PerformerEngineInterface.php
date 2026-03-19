<?php

namespace App\Services\Execution\Contracts;

/**
 * Performer-based engine contract: performer pulls task from backend, executes, reports back.
 * Each platform (Telegram, Max, etc.) can have its own engine. Resolver selects by category.link_driver.
 */
interface PerformerEngineInterface
{
    /**
     * Claim one or more tasks for the performer. Context typically includes account_identity (e.g. phone).
     *
     * @param array $context e.g. ['account_identity' => '...', 'limit' => 1]
     * @return array|null Task payload(s) for the performer, or null if no task available
     */
    public function claim(array $context = []): ?array;

    /**
     * Report task result by task id.
     *
     * @param string $taskId Task identifier (platform-specific, e.g. TelegramTask ULID)
     * @param array $result e.g. ['state' => 'done', 'ok' => true, 'error' => null, ...]
     * @return array e.g. ['ok' => true] or ['ok' => false, 'error' => '...']
     */
    public function report(string $taskId, array $result): array;

    /**
     * Check (mark as done) – some platforms use order_id + account_identity instead of task_id.
     *
     * @param string $id Task id or order id (platform-specific)
     * @param array $context e.g. ['account_identity' => '...']
     * @return array e.g. ['ok' => true] or ['ok' => false, 'error' => '...']
     */
    public function check(string $id, array $context = []): array;

    /**
     * Ignore (mark as failed/skipped) – some platforms use order_id + account_identity.
     *
     * @param string $id Task id or order id (platform-specific)
     * @param array $context e.g. ['account_identity' => '...']
     * @return array e.g. ['ok' => true] or ['ok' => false, 'error' => '...']
     */
    public function ignore(string $id, array $context = []): array;
}
