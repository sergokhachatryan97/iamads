<?php

namespace App\Support;

class TelegramExecutionPolicy
{
    /**
     * Determine link type from inspection result.
     *
     * @param array $inspectionResult
     * @return string
     */
    public static function linkTypeFromInspection(array $inspectionResult): string
    {
        $parsed = $inspectionResult['parsed'] ?? [];

        // Check parsed kind first
        if (isset($parsed['kind'])) {
            if ($parsed['kind'] === 'invite') {
                return 'invite';
            }
            if ($parsed['kind'] === 'public_post') {
                return 'public_post';
            }
        }

        // Use chat_type from inspection result
        $chatType = $inspectionResult['chat_type'] ?? null;

        if ($chatType === 'channel') {
            return 'channel';
        }

        if ($chatType === 'supergroup' || $chatType === 'group') {
            return 'group';
        }

        if ($chatType === 'bot') {
            return 'bot';
        }

        if ($chatType === 'private') {
            return 'user';
        }

        // Fallback
        return 'unknown';
    }

    /**
     * Get execution policy for service type and link type.
     *
     * @param string $serviceType
     * @param string $linkType
     * @return array|null Returns {action, interval_seconds, per_call} or null
     */
    public static function policyFor(string $serviceType, string $linkType): ?array
    {
        $policyMap = config('telegram.execution_policy_map', []);

        return $policyMap[$serviceType][$linkType] ?? null;
    }
}
