<?php

declare(strict_types=1);

namespace App\Application\Payments;

use App\Domain\Payments\PaymentStatus;

/**
 * Lightweight state machine: defines allowed status transitions.
 */
final class PaymentStateMachine
{
    /** @var array<string, array<string>> */
    private static array $allowedTransitions = [
        'new' => ['pending', 'failed', 'expired'],
        'pending' => ['paid', 'failed', 'expired'],
        'paid' => ['refunded'],
        'failed' => [],
        'expired' => [],
        'refunded' => [],
    ];

    public static function canTransition(PaymentStatus $from, PaymentStatus $to): bool
    {
        $allowed = self::$allowedTransitions[$from->value] ?? [];
        return in_array($to->value, $allowed, true);
    }

    public static function transition(PaymentStatus $current, PaymentStatus $target): PaymentStatus
    {
        if (!self::canTransition($current, $target)) {
            throw new \DomainException(
                "Invalid payment status transition: {$current->value} -> {$target->value}"
            );
        }
        return $target;
    }
}
