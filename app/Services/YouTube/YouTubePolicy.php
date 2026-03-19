<?php

namespace App\Services\YouTube;

/**
 * Central policy for YouTube target types and allowed actions.
 * Uses config('youtube.allowed_actions') as single source of truth.
 */
class YouTubePolicy
{
    /**
     * Allowed actions per target type (video, live, channel).
     *
     * @return array<string, list<string>>
     */
    public static function allowedActionsByTargetType(): array
    {
        return config('youtube.allowed_actions', [
            'video' => ['view', 'react', 'comment', 'share'],
            'live' => ['view', 'react', 'comment-react', 'comment'],
            'channel' => ['subscribe'],
        ]);
    }

    /**
     * Whether the given action is allowed for the given target type.
     */
    public static function isActionAllowedForTargetType(string $action, string $targetType): bool
    {
        $allowed = self::allowedActionsByTargetType();
        $actions = $allowed[$targetType] ?? [];
        return in_array(strtolower($action), array_map('strtolower', $actions), true);
    }

    /**
     * Valid target types for business logic.
     *
     * @return list<string>
     */
    public static function targetTypes(): array
    {
        return array_keys(self::allowedActionsByTargetType());
    }

    /**
     * Validate action for target type; return error message or null if valid.
     */
    public static function validateActionForTargetType(string $action, string $targetType): ?string
    {
        $allowed = self::allowedActionsByTargetType();
        if (!isset($allowed[$targetType])) {
            return "Unknown target type: {$targetType}. Allowed types: " . implode(', ', self::targetTypes());
        }
        $action = strtolower($action);
        $allowedForType = array_map('strtolower', $allowed[$targetType]);
        if (!in_array($action, $allowedForType, true)) {
            return sprintf(
                "Action '%s' is not allowed for %s. Allowed for %s: %s",
                $action,
                $targetType,
                $targetType,
                implode(', ', $allowed[$targetType])
            );
        }
        return null;
    }
}
