<?php

namespace App\Services\YouTube;

use App\Models\Order;

/**
 * Resolves execution plan from order's provider_payload.execution_meta.
 * Supports single-action (legacy) and combo (multi-step) modes.
 */
class YouTubeExecutionPlanResolver
{
    public const MODE_SINGLE = 'single';
    public const MODE_COMBO = 'combo';

    public const COMMENT_MODE_NONE = 'none';
    public const COMMENT_MODE_RANDOM_POSITIVE = 'random_positive';
    public const COMMENT_MODE_CUSTOM = 'custom';

    /**
     * Resolve execution plan from order.
     *
     * @return array{
     *   mode: string,
     *   steps: list<string>,
     *   comment_mode: string,
     *   per_call: int,
     *   action: string,
     *   primary_action: string,
     * }
     */
    public static function resolve(Order $order): array
    {
        $payload = $order->provider_payload ?? [];
        $meta = is_array($payload['execution_meta'] ?? null) ? $payload['execution_meta'] : [];

        $mode = strtolower(trim((string) ($meta['mode'] ?? 'single')));
        $steps = $meta['steps'] ?? [];
        $action = strtolower(trim((string) ($meta['action'] ?? 'view')));
        $perCall = max(1, (int) ($meta['per_call'] ?? 1));
        $commentMode = strtolower(trim((string) ($meta['comment_mode'] ?? 'none')));

        if ($mode === self::MODE_COMBO && is_array($steps) && !empty($steps)) {
            $normalizedSteps = array_values(array_filter(array_map(
                fn ($s) => strtolower(trim((string) $s)),
                $steps
            )));
            if (!empty($normalizedSteps)) {
                $commentModeResolved = self::commentModeFromSteps($normalizedSteps);
                $primaryAction = self::primaryActionForCombo($normalizedSteps);
                return [
                    'mode' => self::MODE_COMBO,
                    'steps' => $normalizedSteps,
                    'comment_mode' => $commentModeResolved,
                    'per_call' => $perCall,
                    'action' => 'combo',
                    'primary_action' => $primaryAction,
                ];
            }
        }

        $action = $action !== '' ? $action : 'view';
        return [
            'mode' => self::MODE_SINGLE,
            'steps' => [$action],
            'comment_mode' => $action === 'comment' ? self::COMMENT_MODE_CUSTOM : self::COMMENT_MODE_NONE,
            'per_call' => $perCall,
            'action' => $action,
            'primary_action' => $action,
        ];
    }

    public static function commentModeFromSteps(array $steps): string
    {
        if (in_array('comment_custom', $steps, true)) {
            return self::COMMENT_MODE_CUSTOM;
        }
        if (in_array('comment_random_positive', $steps, true)) {
            return self::COMMENT_MODE_RANDOM_POSITIVE;
        }
        return self::COMMENT_MODE_NONE;
    }

    public static function stepsContainCommentCustom(array $steps): bool
    {
        return in_array('comment_custom', $steps, true);
    }

    public static function stepsContainSubscribe(array $steps): bool
    {
        return in_array('subscribe', $steps, true);
    }

    public static function primaryActionForCombo(array $steps): string
    {
        return $steps[0] ?? 'view';
    }

    /**
     * Composite action string for hasPerformed uniqueness (one claim per account+target+combo).
     */
    public static function compositeActionForLog(array $steps): string
    {
        return 'combo:' . implode('|', $steps);
    }

    /**
     * Steps that are one-per-account-per-target (like subscribe).
     * View and react in combo: one account can only do once per target across all orders.
     */
    public static function onePerAccountSteps(array $steps): array
    {
        $onePer = [];
        foreach ($steps as $step) {
            $s = strtolower(trim((string) $step));
            if ($s === 'view') {
                $onePer['view'] = true;
            } elseif ($s === 'like' || $s === 'react') {
                $onePer['react'] = true;
            }
        }
        return array_keys($onePer);
    }

    /**
     * Map steps to provider_action_logs action names.
     * Works for both single-action (steps = [action]) and combo (steps = [subscribe, view, like, ...]).
     * Used for unified conflict checks.
     *
     * @return array<string> Unique action names (subscribe, view, react, comment)
     */
    public static function stepsToActionNames(array $steps): array
    {
        $actions = [];
        foreach ($steps as $step) {
            $s = strtolower(trim((string) $step));
            if ($s === 'subscribe') {
                $actions['subscribe'] = true;
            } elseif ($s === 'view') {
                $actions['view'] = true;
            } elseif ($s === 'like' || $s === 'react') {
                $actions['react'] = true;
            } elseif ($s === 'comment' || in_array($s, ['comment_random_positive', 'comment_custom'], true)) {
                $actions['comment'] = true;
            }
        }
        return array_keys($actions);
    }

    /**
     * @deprecated Use stepsToActionNames instead
     */
    public static function comboStepsToActionNames(array $steps): array
    {
        return self::stepsToActionNames($steps);
    }
}
