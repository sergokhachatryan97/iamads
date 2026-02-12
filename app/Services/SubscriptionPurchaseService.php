<?php

namespace App\Services;

use App\Jobs\InspectTelegramQuotaLinkJob;
use App\Models\Client;
use App\Models\ClientServiceQuota;
use App\Models\ClientTransaction;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPrice;
use App\Models\SubscriptionPlanService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubscriptionPurchaseService
{
    /**
     * Purchase a monthly subscription plan from client balance.
     *
     * @param Client $client
     * @param int $planId
     * @param string|array $links Single link string or array of links
     * @param bool $autoRenew
     * @return void
     * @throws ValidationException
     */
    public function purchaseMonthlyFromBalance(Client $client, int $planId, string|array $links, bool $autoRenew = false): void
    {
        $linksArray = is_array($links) ? $links : [$links];
        $linksArray = array_values(array_filter(array_map('trim', $linksArray)));

        if (empty($linksArray)) {
            throw ValidationException::withMessages([
                'links' => ['At least one link is required.']
            ]);
        }

        // (Optional բայց լավ) normalize + dedupe links այստեղ
        // Եթե հիմա չես ուզում parser բերել այստեղ, գոնե պարզ unique արա։
        $linksArray = array_values(array_unique($linksArray));

        DB::transaction(function () use ($client, $planId, $linksArray, $autoRenew) {
            $client = Client::query()->lockForUpdate()->findOrFail($client->id);

            $plan = SubscriptionPlan::query()
                ->whereKey($planId)
                ->where('is_active', true)
                ->firstOrFail();

            $monthlyPrice = SubscriptionPlanPrice::query()
                ->where('subscription_plan_id', $plan->id)
                ->where('billing_cycle', 'monthly')
                ->first();

            if (!$monthlyPrice || (float)$monthlyPrice->price <= 0) {
                throw ValidationException::withMessages([
                    'plan' => ['This plan does not have a valid monthly price.']
                ]);
            }

            $price = (float) $monthlyPrice->price;

            // Prevent duplicate active purchase (lock rows to avoid race)
            $existingActiveQuota = ClientServiceQuota::query()
                ->where('client_id', $client->id)
                ->where('subscription_id', $plan->id)
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->exists();

            if ($existingActiveQuota) {
                throw ValidationException::withMessages([
                    'plan' => ['You already have an active subscription for this plan.']
                ]);
            }

            if ((float)$client->balance < $price) {
                throw ValidationException::withMessages([
                    'balance' => ['Insufficient balance. Please top up.']
                ]);
            }

            $client->balance = (float)$client->balance - $price;
            $client->save();

            $planServices = SubscriptionPlanService::query()
                ->where('subscription_plan_id', $plan->id)
                ->get();

            if ($planServices->isEmpty()) {
                throw ValidationException::withMessages([
                    'plan' => ['This plan has no included services.']
                ]);
            }

            $expiresAt = now()->addMonth();
            $linkCount = count($linksArray);

            foreach ($planServices as $planService) {
                $totalQty = (int) $planService->quantity;

                // avoid division by zero / weird cases
                if ($totalQty <= 0) {
                    continue;
                }

                $quantityPerLink = $linkCount > 1 ? intdiv($totalQty, $linkCount) : $totalQty;
                $remainder = $linkCount > 1 ? ($totalQty % $linkCount) : 0;

                foreach ($linksArray as $index => $link) {
                    $finalQuantity = $quantityPerLink + ($index < $remainder ? 1 : 0);

                    // IMPORTANT: skip zero quotas
                    if ($finalQuantity <= 0) {
                        continue;
                    }

                    $quota = ClientServiceQuota::create([
                        'client_id' => $client->id,
                        'subscription_id' => $plan->id,
                        'service_id' => $planService->service_id,
                        'quantity_left' => $finalQuantity,
                        'orders_left' => null,
                        'link' => $link,
                        'expires_at' => $expiresAt,
                        'auto_renew' => $autoRenew,
                    ]);

                    InspectTelegramQuotaLinkJob::dispatch($quota->id)->afterCommit();
                }
            }

            ClientTransaction::create([
                'client_id' => $client->id,
                'order_id' => null,
                'amount' => -$price,
                'type' => ClientTransaction::TYPE_SUBSCRIPTION_CHARGE,
                // optional metadata fields եթե ունես
                // 'meta' => ['plan_id' => $plan->id, 'links_count' => $linkCount, 'expires_at' => $expiresAt->toDateTimeString()],
            ]);
        });
    }

    /**
     * Renew an expired subscription automatically.
     *
     * @param Client $client
     * @param int $planId
     * @param string $link
     * @return bool True if renewal was successful, false otherwise
     * @throws \Exception
     */
    public function renewSubscription(Client $client, int $planId, string|array $links): bool
    {
        $linksArray = is_array($links) ? $links : [$links];
        $linksArray = array_values(array_filter(array_map('trim', $linksArray)));

        if (empty($linksArray)) {
            \Log::error("Subscription renewal failed: No links provided", [
                'client_id' => $client->id,
                'plan_id' => $planId,
            ]);
            return false;
        }

        $linksArray = array_values(array_unique($linksArray));

        try {
            DB::transaction(function () use ($client, $planId, $linksArray) {
                $client = Client::query()->lockForUpdate()->findOrFail($client->id);

                $plan = SubscriptionPlan::query()
                    ->whereKey($planId)
                    ->where('is_active', true)
                    ->first();

                if (!$plan) {
                    throw new \RuntimeException("Subscription plan {$planId} not found or inactive.");
                }

                $monthlyPrice = SubscriptionPlanPrice::query()
                    ->where('subscription_plan_id', $plan->id)
                    ->where('billing_cycle', 'monthly')
                    ->first();

                $price = (float) ($monthlyPrice->price ?? 0);
                if ($price <= 0) {
                    throw new \RuntimeException("Subscription plan {$planId} does not have a valid monthly price.");
                }

                // Prevent duplicate active renewal (important)
                $alreadyActive = ClientServiceQuota::query()
                    ->where('client_id', $client->id)
                    ->where('subscription_id', $plan->id)
                    ->where('expires_at', '>', now())
                    ->lockForUpdate()
                    ->exists();

                if ($alreadyActive) {
                    throw new \RuntimeException("Renewal blocked: client already has an active subscription for plan {$plan->id}.");
                }

                if ((float) $client->balance < $price) {
                    throw new \RuntimeException("Insufficient balance. Required: {$price}, Available: {$client->balance}");
                }

                $planServices = SubscriptionPlanService::query()
                    ->where('subscription_plan_id', $plan->id)
                    ->get();

                if ($planServices->isEmpty()) {
                    throw new \RuntimeException("Subscription plan {$planId} has no included services.");
                }

                // Get auto_renew from the last expired quota (if any)
                $expiredQuota = ClientServiceQuota::query()
                    ->where('client_id', $client->id)
                    ->where('subscription_id', $plan->id)
                    ->where('expires_at', '<=', now())
                    ->orderByDesc('expires_at')
                    ->lockForUpdate()
                    ->first();

                $autoRenew = (bool) ($expiredQuota?->auto_renew ?? false);

                // Deduct price
                $client->balance = (float) $client->balance - $price;
                $client->save();

                $expiresAt = now()->addMonth();
                $linkCount = count($linksArray);

                foreach ($planServices as $planService) {
                    $totalQty = (int) $planService->quantity;
                    if ($totalQty <= 0) {
                        continue;
                    }

                    $quantityPerLink = $linkCount > 1 ? intdiv($totalQty, $linkCount) : $totalQty;
                    $remainder = $linkCount > 1 ? ($totalQty % $linkCount) : 0;

                    foreach ($linksArray as $index => $link) {
                        $finalQuantity = $quantityPerLink + ($index < $remainder ? 1 : 0);

                        // IMPORTANT: skip zero quotas
                        if ($finalQuantity <= 0) {
                            continue;
                        }

                        $quota = ClientServiceQuota::create([
                            'client_id' => $client->id,
                            'subscription_id' => $plan->id,
                            'service_id' => $planService->service_id,
                            'quantity_left' => $finalQuantity,
                            'orders_left' => null,
                            'link' => $link,
                            'expires_at' => $expiresAt,
                            'auto_renew' => $autoRenew,
                        ]);

                        InspectTelegramQuotaLinkJob::dispatch($quota->id)->afterCommit();
                    }
                }

                ClientTransaction::create([
                    'client_id' => $client->id,
                    'order_id' => null,
                    'amount' => -$price,
                    'type' => ClientTransaction::TYPE_SUBSCRIPTION_CHARGE,
                    'description' => "Auto-renewal subscription plan_id={$plan->id}",
                ]);
            });

            return true;
        } catch (\Throwable $e) {
            \Log::error('Failed to renew subscription', [
                'client_id' => $client->id,
                'plan_id' => $planId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}

