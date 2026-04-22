<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientTransaction;
use App\Models\PromoCode;
use App\Models\PromoCodeUsage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PromoCodeService
{
    /**
     * Generate a unique promo code and store it.
     */
    public function create(array $data, int $createdBy): PromoCode
    {
        return PromoCode::create([
            'code' => $data['code'] ?? $this->generateUniqueCode(),
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $createdBy,
            'reward_value' => $data['reward_value'],
            'max_uses' => $data['max_uses'] ?? null,
            'max_uses_per_client' => $data['max_uses_per_client'] ?? 1,
            'expires_at' => $data['expires_at'] ?? null,
        ]);
    }

    /**
     * Apply a promo code to a client account with full validation and locking.
     *
     * @return array{success: bool, message: string, amount?: float}
     */
    public function apply(string $code, Client $client): array
    {
        $code = strtoupper(trim($code));

        $promo = PromoCode::where('code', $code)->first();

        if (!$promo) {
            return ['success' => false, 'message' => __('Invalid promo code.')];
        }

        if (!$promo->is_active) {
            return ['success' => false, 'message' => __('This promo code is inactive.')];
        }

        if ($promo->isExpired()) {
            return ['success' => false, 'message' => __('This promo code has expired.')];
        }

        // Use a transaction with row-level locking to prevent race conditions
        return DB::transaction(function () use ($promo, $client) {
            // Lock the promo code row for update
            $promo = PromoCode::where('id', $promo->id)->lockForUpdate()->first();

            if ($promo->isExhausted()) {
                return ['success' => false, 'message' => __('This promo code has reached its usage limit.')];
            }

            // Check per-client usage
            $clientUsages = PromoCodeUsage::where('promo_code_id', $promo->id)
                ->where('client_id', $client->id)
                ->count();

            if ($clientUsages >= $promo->max_uses_per_client) {
                return ['success' => false, 'message' => __('You have already used this promo code.')];
            }

            $amount = (float) $promo->reward_value;

            // Credit the client balance
            $client->increment('balance', $amount);

            // Record the transaction
            ClientTransaction::create([
                'client_id' => $client->id,
                'amount' => $amount,
                'type' => 'promo_code',
                'description' => "Promo code: {$promo->code}",
            ]);

            // Record usage
            PromoCodeUsage::create([
                'promo_code_id' => $promo->id,
                'client_id' => $client->id,
                'amount_credited' => $amount,
                'applied_at' => now(),
            ]);

            // Increment usage count
            $promo->increment('used_count');

            return [
                'success' => true,
                'message' => __('Promo code applied! $:amount has been added to your balance.', ['amount' => number_format($amount, 2)]),
                'amount' => $amount,
            ];
        });
    }

    public function generateUniqueCode(int $length = 8): string
    {
        do {
            $code = strtoupper(Str::random($length));
        } while (PromoCode::where('code', $code)->exists());

        return $code;
    }
}
