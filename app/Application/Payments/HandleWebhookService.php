<?php

declare(strict_types=1);

namespace App\Application\Payments;

use App\Domain\Payments\PaymentStatus;
use App\Models\FastOrder;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Services\FastOrderService;
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
        private FastOrderService $fastOrderService,
    ) {}

    public function handle(string $provider, string $rawBody, array $headers, string $ip): void
    {
        $gateway = $this->resolver->resolve($provider);
        $event = $gateway->parseWebhook($rawBody, $headers, $ip);

        $payment = Payment::query()
            ->where('order_id', $event->orderId)
            ->where('provider', $provider)
            ->first();

        if (! $payment) {
            // Fallback: find by provider_ref (some providers send uuid but not order_id)
            $payment = Payment::query()
                ->where('provider_ref', $event->providerRef)
                ->where('provider', $provider)
                ->first();
        }

        if (! $payment) {
            return; // Unknown order, return OK to avoid retries
        }

        $eventHash = hash('sha256', $rawBody);

        DB::transaction(function () use ($payment, $event, $eventHash, $provider) {
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

            // Guest fast order: no client_id; convert draft after Heleket confirms payment
            if ($wasFirstPaid && ! $payment->client_id) {
                $meta = is_array($payment->meta) ? $payment->meta : [];
                $fastUuid = $meta['fast_order_uuid'] ?? null;
                if (is_string($fastUuid) && $fastUuid !== '') {
                    $fastOrder = FastOrder::query()->where('uuid', $fastUuid)->first();
                    if ($fastOrder) {
                        $expected = round((float) $fastOrder->total_amount, 2);
                        $paid = round((float) $payment->amount, 2);
                        if (abs($expected - $paid) > 0.05) {
                            Log::warning('Fast order payment amount mismatch; skipping conversion', [
                                'payment_id' => $payment->id,
                                'fast_order_uuid' => $fastUuid,
                                'expected' => $expected,
                                'paid' => $paid,
                            ]);
                        } else {
                            try {
                                $this->fastOrderService->markAsPaidAndConvert($fastOrder, $payment->provider_ref);
                                Log::info('Fast order converted via payment webhook', [
                                    'payment_id' => $payment->id,
                                    'fast_order_uuid' => $fastUuid,
                                ]);
                            } catch (\Throwable $e) {
                                Log::error('Fast order conversion failed in webhook', [
                                    'payment_id' => $payment->id,
                                    'fast_order_uuid' => $fastUuid,
                                    'error' => $e->getMessage(),
                                ]);
                                throw $e;
                            }
                        }
                    }
                }
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
