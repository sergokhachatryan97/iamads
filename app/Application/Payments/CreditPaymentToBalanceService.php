<?php

declare(strict_types=1);

namespace App\Application\Payments;

use App\Models\BalanceLedgerEntry;
use App\Models\Client;
use App\Models\ClientTransaction;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Credits client balance for a payment. Used by webhook handler and admin "mark as paid".
 * Idempotent: BalanceLedgerEntry has unique(payment_id, type) to prevent double credit.
 */
final class CreditPaymentToBalanceService
{
    public function credit(Payment $payment): bool
    {
        if (!$payment->client_id) {
            return false;
        }

        // Idempotency: already credited?
        if (BalanceLedgerEntry::query()
            ->where('payment_id', $payment->id)
            ->where('type', BalanceLedgerEntry::TYPE_CREDIT)
            ->exists()
        ) {
            return false;
        }

        return DB::transaction(function () use ($payment) {
            $client = Client::query()
                ->where('id', $payment->client_id)
                ->lockForUpdate()
                ->first();

            if (!$client) {
                return false;
            }

            $amount = (float) $payment->amount;
            $currency = $payment->currency ?? 'USD';

            BalanceLedgerEntry::create([
                'client_id' => $payment->client_id,
                'payment_id' => $payment->id,
                'amount_decimal' => $amount,
                'currency' => $currency,
                'type' => BalanceLedgerEntry::TYPE_CREDIT,
                'meta' => ['provider' => $payment->provider, 'provider_ref' => $payment->provider_ref],
            ]);

            ClientTransaction::create([
                'client_id' => $payment->client_id,
                'order_id' => null,
                'payment_id' => $payment->id,
                'amount' => $amount,
                'type' => ClientTransaction::TYPE_BALANCE_TOPUP,
                'description' => "Balance top-up via {$payment->provider}",
            ]);

            $client->balance = (float) $client->balance + $amount;
            $client->save();

            return true;
        });
    }
}
