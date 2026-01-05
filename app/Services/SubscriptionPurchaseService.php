<?php

namespace App\Services;

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
        // Normalize links to array
        $linksArray = is_array($links) ? $links : [$links];
        $linksArray = array_filter(array_map('trim', $linksArray)); // Remove empty and trim
        
        if (empty($linksArray)) {
            throw ValidationException::withMessages([
                'links' => ['At least one link is required.']
            ]);
        }

        DB::transaction(function () use ($client, $planId, $linksArray, $autoRenew) {
            // Lock client row for update
            $client = Client::lockForUpdate()->findOrFail($client->id);

            // Load plan (must be active)
            $plan = SubscriptionPlan::where('id', $planId)
                ->where('is_active', true)
                ->firstOrFail();

            // Load monthly price
            $monthlyPrice = SubscriptionPlanPrice::where('subscription_plan_id', $plan->id)
                ->where('billing_cycle', 'monthly')
                ->first();

            if (!$monthlyPrice || !$monthlyPrice->price || $monthlyPrice->price <= 0) {
                throw ValidationException::withMessages([
                    'plan' => ['This plan does not have a valid monthly price.']
                ]);
            }

            $price = (float) $monthlyPrice->price;

            // Prevent duplicate active purchase
            $existingActiveQuota = ClientServiceQuota::where('client_id', $client->id)
                ->where('subscription_id', $planId)
                ->where('expires_at', '>', now())
                ->first();

            if ($existingActiveQuota) {
                throw ValidationException::withMessages([
                    'plan' => ['You already have an active subscription for this plan.']
                ]);
            }

            // Balance check
            if ($client->balance < $price) {
                throw ValidationException::withMessages([
                    'balance' => ['Insufficient balance. Please top up.']
                ]);
            }

            // Deduct price from balance
            $client->balance -= $price;
            $client->save();

            // Load included services
            $planServices = SubscriptionPlanService::where('subscription_plan_id', $plan->id)
                ->get();

            if ($planServices->isEmpty()) {
                throw ValidationException::withMessages([
                    'plan' => ['This plan has no included services.']
                ]);
            }

            // Calculate expiration date (1 month from now)
            $expiresAt = now()->addMonth();

            // Create quota rows for each included service
            // If multiple links, divide quantity equally among all links
            $linkCount = count($linksArray);
            foreach ($planServices as $planService) {
                // Divide quantity equally among all links
                $quantityPerLink = $linkCount > 1 
                    ? (int) floor($planService->quantity / $linkCount)
                    : $planService->quantity;
                
                // Calculate remainder for the first link(s) if division is not exact
                $remainder = $linkCount > 1 
                    ? $planService->quantity % $linkCount 
                    : 0;

                foreach ($linksArray as $index => $link) {
                    // Add remainder to first links if division is not exact
                    $finalQuantity = $quantityPerLink + ($index < $remainder ? 1 : 0);
                    
                    ClientServiceQuota::create([
                        'client_id' => $client->id,
                        'subscription_id' => $plan->id,
                        'service_id' => $planService->service_id,
                        'quantity_left' => $finalQuantity,
                        'orders_left' => null,
                        'link' => $link,
                        'expires_at' => $expiresAt,
                        'auto_renew' => $autoRenew,
                    ]);
                }
            }

            // Create ledger record
            ClientTransaction::create([
                'client_id' => $client->id,
                'order_id' => null,
                'amount' => -$price,
                'type' => ClientTransaction::TYPE_SUBSCRIPTION_CHARGE,
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
        // Normalize links to array
        $linksArray = is_array($links) ? $links : [$links];
        $linksArray = array_filter(array_map('trim', $linksArray));
        
        if (empty($linksArray)) {
            \Log::error("Subscription renewal failed: No links provided for client {$client->id}, plan {$planId}");
            return false;
        }

        try {
            DB::transaction(function () use ($client, $planId, $linksArray) {
                // Lock client row for update
                $client = Client::lockForUpdate()->findOrFail($client->id);

                // Load plan (must be active)
                $plan = SubscriptionPlan::where('id', $planId)
                    ->where('is_active', true)
                    ->first();

                if (!$plan) {
                    throw new \Exception("Subscription plan {$planId} not found or inactive.");
                }

                // Load monthly price
                $monthlyPrice = SubscriptionPlanPrice::where('subscription_plan_id', $plan->id)
                    ->where('billing_cycle', 'monthly')
                    ->first();

                if (!$monthlyPrice || !$monthlyPrice->price || $monthlyPrice->price <= 0) {
                    throw new \Exception("Subscription plan {$planId} does not have a valid monthly price.");
                }

                $price = (float) $monthlyPrice->price;

                // Balance check
                if ($client->balance < $price) {
                    throw new \Exception("Insufficient balance for client {$client->id}. Required: {$price}, Available: {$client->balance}");
                }

                // Load included services
                $planServices = SubscriptionPlanService::where('subscription_plan_id', $plan->id)
                    ->get();

                if ($planServices->isEmpty()) {
                    throw new \Exception("Subscription plan {$planId} has no included services.");
                }

                // Deduct price from balance
                $client->balance -= $price;
                $client->save();

                // Calculate expiration date (1 month from now)
                $expiresAt = now()->addMonth();

                // Get the auto_renew flag from the expired quota (should be true, but check first)
                $expiredQuota = ClientServiceQuota::where('client_id', $client->id)
                    ->where('subscription_id', $planId)
                    ->where('expires_at', '<=', now())
                    ->where('auto_renew', true)
                    ->first();

                $autoRenew = $expiredQuota ? $expiredQuota->auto_renew : false;

                // Create quota rows for each included service
                // If multiple links, divide quantity equally among all links
                $linkCount = count($linksArray);
                foreach ($planServices as $planService) {
                    // Divide quantity equally among all links
                    $quantityPerLink = $linkCount > 1 
                        ? (int) floor($planService->quantity / $linkCount)
                        : $planService->quantity;
                    
                    // Calculate remainder for the first link(s) if division is not exact
                    $remainder = $linkCount > 1 
                        ? $planService->quantity % $linkCount 
                        : 0;

                    foreach ($linksArray as $index => $link) {
                        // Add remainder to first links if division is not exact
                        $finalQuantity = $quantityPerLink + ($index < $remainder ? 1 : 0);
                        
                        ClientServiceQuota::create([
                            'client_id' => $client->id,
                            'subscription_id' => $plan->id,
                            'service_id' => $planService->service_id,
                            'quantity_left' => $finalQuantity,
                            'orders_left' => null,
                            'link' => $link,
                            'expires_at' => $expiresAt,
                            'auto_renew' => $autoRenew, // Keep the auto_renew setting
                        ]);
                    }
                }

                // Create ledger record
                ClientTransaction::create([
                    'client_id' => $client->id,
                    'order_id' => null,
                    'amount' => -$price,
                    'type' => ClientTransaction::TYPE_SUBSCRIPTION_CHARGE,
                    'description' => "Auto-renewal subscription plan_id={$plan->id}",
                ]);
            });

            return true;
        } catch (\Exception $e) {
            // Log the error and return false
            \Log::error('Failed to renew subscription', [
                'client_id' => $client->id,
                'plan_id' => $planId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}

