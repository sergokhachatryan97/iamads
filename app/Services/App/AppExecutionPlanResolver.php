<?php

namespace App\Services\App;

use App\Models\Order;

/**
 * Resolves execution plan for app orders from provider_payload.execution_meta.
 * Supports single-action (download) and combo (download + positive_review / download + custom_review).
 */
class AppExecutionPlanResolver
{
    public const MODE_SINGLE = 'single';
    public const MODE_COMBO = 'combo';

    /**
     * Resolve execution plan from order.
     *
     * @return array{
     *   mode: string,
     *   steps: list<string>,
     *   action: string,
     *   per_call: int,
     * }
     */
    public static function resolve(Order $order): array
    {
        $payload = $order->provider_payload ?? [];
        $meta = is_array($payload['execution_meta'] ?? null) ? $payload['execution_meta'] : [];

        $mode = strtolower(trim((string) ($meta['mode'] ?? 'single')));
        $steps = $meta['steps'] ?? [];
        $action = strtolower(trim((string) ($meta['action'] ?? 'download')));
        $perCall = max(1, (int) ($meta['per_call'] ?? 1));

        if ($mode === self::MODE_COMBO && is_array($steps) && !empty($steps)) {
            $normalizedSteps = array_values(array_filter(array_map(
                fn ($s) => strtolower(trim((string) $s)),
                $steps
            )));
            if (!empty($normalizedSteps)) {
                return [
                    'mode' => self::MODE_COMBO,
                    'steps' => $normalizedSteps,
                    'action' => $action,
                    'per_call' => $perCall,
                ];
            }
        }

        return [
            'mode' => self::MODE_SINGLE,
            'steps' => [$action],
            'action' => $action,
            'per_call' => $perCall,
        ];
    }

    /**
     * Map steps to provider_action_logs action names.
     */
    public static function stepsToActionNames(array $steps): array
    {
        $actions = [];
        foreach ($steps as $step) {
            $s = strtolower(trim((string) $step));
            if ($s === 'download') {
                $actions['download'] = true;
            } elseif ($s === 'positive_review') {
                $actions['positive_review'] = true;
            } elseif ($s === 'custom_review') {
                $actions['custom_review'] = true;
            }
        }
        return array_keys($actions);
    }

    public static function compositeActionForLog(array $steps): string
    {
        return 'combo:' . implode('|', $steps);
    }
}
