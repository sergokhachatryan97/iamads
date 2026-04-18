<?php

declare(strict_types=1);

namespace App\Application\Payments;

use App\Models\Client;
use App\Models\ClientTransaction;
use App\Models\Payment;
use App\Models\UiText;
use Illuminate\Support\Facades\DB;

final class ReferralBonusService
{
    public function creditIfEligible(Payment $payment): void
    {
        $client = Client::find($payment->client_id);
        if (!$client || !$client->referred_by) {
            return;
        }

        $percent = $this->getBonusPercent();
        if ($percent <= 0) {
            return;
        }

        $bonusAmount = round((float) $payment->amount * ($percent / 100), 4);
        if ($bonusAmount <= 0) {
            return;
        }

        // Idempotency: check if we already credited a referral bonus for this payment
        $exists = ClientTransaction::query()
            ->where('client_id', $client->referred_by)
            ->where('payment_id', $payment->id)
            ->where('type', ClientTransaction::TYPE_REFERRAL_BONUS)
            ->exists();

        if ($exists) {
            return;
        }

        DB::transaction(function () use ($client, $payment, $bonusAmount) {
            $referrer = Client::query()
                ->where('id', $client->referred_by)
                ->lockForUpdate()
                ->first();

            if (!$referrer) {
                return;
            }

            ClientTransaction::create([
                'client_id' => $referrer->id,
                'order_id' => null,
                'payment_id' => $payment->id,
                'amount' => $bonusAmount,
                'type' => ClientTransaction::TYPE_REFERRAL_BONUS,
                'description' => "Referral bonus from client #{$client->id}",
            ]);

            $referrer->balance = (float) $referrer->balance + $bonusAmount;
            $referrer->save();
        });
    }

    public function getBonusPercent(): float
    {
        $setting = UiText::where('key', 'referral_bonus_percent')
            ->where('is_active', true)
            ->first();

        return $setting ? (float) $setting->value : 0;
    }
}
