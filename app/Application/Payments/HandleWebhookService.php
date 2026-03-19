<?php

declare(strict_types=1);

namespace App\Application\Payments;

use App\Domain\Payments\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Idempotent webhook handler. Uses event_hash to deduplicate.
 * Credits balance ONLY when payment transitions to PAID (never on redirect).
 */
final class HandleWebhookService
{
    public function __construct(
        private PaymentGatewayResolver $resolver,
        private CreditPaymentToBalanceService $creditService,
    ) {}

    public function handle(string $provider, string $rawBody, array $headers, string $ip): void
    {
        $gateway = $this->resolver->resolve($provider);
        $event = $gateway->parseWebhook($rawBody, $headers, $ip);

        $payment = Payment::query()
            ->where('order_id', $event->orderId)
            ->where('provider', $provider)
            ->first();

        if (!$payment) {
            // Fallback: find by provider_ref (some providers send uuid but not order_id)
            $payment = Payment::query()
                ->where('provider_ref', $event->providerRef)
                ->where('provider', $provider)
                ->first();
        }

        if (!$payment) {
            return; // Unknown order, return OK to avoid retries
        }

        $eventHash = hash('sha256', $rawBody);

        DB::transaction(function () use ($payment, $event, $eventHash, $rawBody, $provider) {
            $payment = Payment::query()
                ->where('id', $payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $exists = PaymentEvent::query()
                ->where('event_hash', $eventHash)
                ->exists();

            if ($exists) {
                return; // Idempotent: already processed
            }

            $currentStatus = PaymentStatus::from($payment->status);
            $newStatus = $event->status;

            try {
                $finalStatus = PaymentStateMachine::transition($currentStatus, $newStatus);
            } catch (\DomainException) {
                return; // Invalid transition, ignore (e.g. already paid)
            }

            $payment->status = $finalStatus->value;
            $wasFirstPaid = false;
            if ($finalStatus === PaymentStatus::PAID) {
                $wasFirstPaid = $payment->paid_at === null;
                $payment->paid_at = $payment->paid_at ?? now();
            }
            $payment->save();

            // Credit balance ONLY on first PAID transition (idempotent via paid_at check)
            if ($wasFirstPaid && $payment->client_id) {
                $this->creditService->credit($payment);
                Log::info('Payment balance credited via webhook', [
                    'payment_id' => $payment->id,
                    'client_id' => $payment->client_id,
                    'amount' => $payment->amount,
                ]);
            }

            PaymentEvent::create([
                'payment_id' => $payment->id,
                'provider' => $provider,
                'provider_ref' => $event->providerRef,
                'event_hash' => $eventHash,
                'status' => $event->status->value,
                'payload' => $event->raw,
            ]);
        });
    }
}
