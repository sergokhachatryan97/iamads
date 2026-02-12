<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProviderTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'subject_type',
        'subject_id',
        'action',
        'account_id',
        'link_hash',
        'state',
        'ok',
        'error',
        'payload',
        'completed_at',
    ];

    protected $casts = [
        'ok' => 'boolean',
        'payload' => 'array',
        'completed_at' => 'datetime',
    ];

    /**
     * Check if task is already completed.
     */
    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    /**
     * Mark task as completed.
     */
    public function markCompleted(string $state, bool $ok, ?string $error = null, $payload = null): bool
    {
        $updated = self::query()
            ->whereKey($this->getKey())
            ->whereNull('completed_at')
            ->update([
                'state' => $state,
                'ok' => $ok,
                'error' => $error,
                'payload' => $payload,
                'completed_at' => now(),
            ]);

        return $updated === 1;
    }


    /**
     * Find or create a pending task record.
     */
    public static function findOrCreatePending(
        string $taskId,
        string $subjectType,
        int $subjectId,
        string $action,
        int $accountId,
        ?string $linkHash = null
    ): self {
        return self::firstOrCreate(
            [
                'task_id' => $taskId,
            ],
            [
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'action' => $action,
                'account_id' => $accountId,
                'link_hash' => $linkHash,
                'state' => 'pending',
            ]
        );
    }
}
