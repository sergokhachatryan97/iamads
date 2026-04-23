<?php

use App\Models\Client;
use App\Models\ClientTransaction;
use App\Models\Order;
use App\Services\PricingService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Fix orders that have charge=0 due to rounding bug (round(..., 2) on low rates).
 * Recalculates the correct charge, updates the order, deducts from client balance,
 * and creates a transaction record. Skips staff orders (refill/test).
 */
return new class extends Migration
{
    public function up(): void
    {
        $pricingService = app(PricingService::class);

        $orders = Order::query()
            ->where('charge', 0)
            ->whereNotIn('status', [Order::STATUS_CANCELED, Order::STATUS_FAIL])
            ->where('source', '!=', Order::SOURCE_STAFF)
            ->whereNotIn('order_purpose', [Order::PURPOSE_REFILL, Order::PURPOSE_TEST])
            ->with(['service', 'client'])
            ->get();

        $fixed = 0;
        $skipped = 0;

        foreach ($orders as $order) {
            if (!$order->service || !$order->client) {
                $skipped++;
                Log::warning("[fix-zero-charge] Skipped order #{$order->id}: missing service or client");
                continue;
            }

            $effectiveRate = (float) $pricingService->priceForClient($order->service, $order->client);

            if ($effectiveRate <= 0) {
                $skipped++;
                continue;
            }

            $correctCharge = round(($order->quantity / 1000) * $effectiveRate, 4);

            if ($correctCharge <= 0) {
                $skipped++;
                continue;
            }

            DB::transaction(function () use ($order, $correctCharge) {
                // Update order charge
                $order->charge = $correctCharge;
                $order->save();

                // Deduct from client balance (only for non-completed/non-canceled — they already ran)
                $client = Client::lockForUpdate()->find($order->client_id);
                if ($client) {
                    $client->balance -= $correctCharge;
                    $client->spent = (float) $client->spent + $correctCharge;
                    $client->save();
                }

                // Create transaction record
                ClientTransaction::create([
                    'client_id' => $order->client_id,
                    'order_id' => $order->id,
                    'amount' => -$correctCharge,
                    'type' => ClientTransaction::TYPE_ORDER_CHARGE,
                    'description' => 'Charge correction for order #' . $order->id . ' (was $0 due to rounding)',
                ]);
            });

            $fixed++;
            Log::info("[fix-zero-charge] Fixed order #{$order->id}: client={$order->client_id} service={$order->service_id} qty={$order->quantity} charge={$correctCharge}");
        }

        Log::info("[fix-zero-charge] Done. Fixed: {$fixed}, Skipped: {$skipped}");
    }

    public function down(): void
    {
        // Cannot reliably reverse balance deductions — check logs for details.
    }
};

