<?php

namespace App\Console\Commands;

use App\Models\ClientServiceQuota;
use App\Services\SubscriptionPurchaseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RenewExpiredSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:renew-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Renew expired subscriptions that have auto-renew enabled';

    /**
     * Execute the console command.
     */
    public function handle(SubscriptionPurchaseService $purchaseService): int
    {
        $this->info('Starting subscription renewal process...');

        // Find all expired subscriptions with auto_renew = true
        // Group by client_id and subscription_id to get unique subscription instances
        $expiredSubscriptions = ClientServiceQuota::where('expires_at', '<=', now())
            ->where('auto_renew', true)
            ->select('client_id', 'subscription_id', DB::raw('MIN(id) as first_quota_id'))
            ->groupBy('client_id', 'subscription_id')
            ->get();

        if ($expiredSubscriptions->isEmpty()) {
            $this->info('No expired subscriptions with auto-renew enabled found.');
            return Command::SUCCESS;
        }

        $this->info("Found {$expiredSubscriptions->count()} expired subscription(s) to process.");

        $successCount = 0;
        $failCount = 0;

        foreach ($expiredSubscriptions as $expired) {
            // Check if there's already an active subscription for this client+plan
            // (to avoid duplicate renewals if the command runs multiple times)
            $hasActive = ClientServiceQuota::where('client_id', $expired->client_id)
                ->where('subscription_id', $expired->subscription_id)
                ->where('expires_at', '>', now())
                ->exists();

            if ($hasActive) {
                $this->warn("Client {$expired->client_id} already has an active subscription for plan {$expired->subscription_id}. Skipping.");
                continue;
            }

            // Get all unique links from expired quota rows for this subscription
            $expiredQuotas = ClientServiceQuota::where('client_id', $expired->client_id)
                ->where('subscription_id', $expired->subscription_id)
                ->where('expires_at', '<=', now())
                ->where('auto_renew', true)
                ->whereNotNull('link')
                ->distinct()
                ->pluck('link')
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            if (empty($expiredQuotas)) {
                $this->warn("Could not find expired quota with link for client {$expired->client_id}, plan {$expired->subscription_id}. Skipping.");
                $failCount++;
                continue;
            }

            $client = ClientServiceQuota::where('client_id', $expired->client_id)
                ->where('subscription_id', $expired->subscription_id)
                ->first()
                ->client;

            $this->info("Processing renewal for client {$client->id}, plan {$expired->subscription_id} with " . count($expiredQuotas) . " link(s)...");

            $success = $purchaseService->renewSubscription(
                $client,
                $expired->subscription_id,
                $expiredQuotas
            );

            if ($success) {
                $this->info("✓ Successfully renewed subscription for client {$client->id}, plan {$expired->subscription_id}");
                $successCount++;
            } else {
                $this->error("✗ Failed to renew subscription for client {$client->id}, plan {$expired->subscription_id}");
                $failCount++;
            }
        }

        $this->info("Renewal process completed. Success: {$successCount}, Failed: {$failCount}");

        return Command::SUCCESS;
    }
}
