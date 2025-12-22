<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Service;

class PricingService
{
    /**
     * Calculate the price for a service for a specific client.
     *
     * Priority:
     * 1. If client has custom rate for this service, use it (ignore discount)
     * 2. Otherwise, apply personal discount to default service price
     *
     * @param Service $service The service to price
     * @param Client $client The client to calculate price for
     * @return float The final price (minimum 0)
     */
    public function priceForClient(Service $service, Client $client): float
    {
        $defaultPrice = (float) ($service->rate_per_1000 ?? 0);
        $clientRates = is_array($client->rates) ? $client->rates : [];

        // Check if client has a custom rate for this service
        if (isset($clientRates[$service->id])) {
            $customRate = $clientRates[$service->id];

            if (isset($customRate['type']) && isset($customRate['value'])) {
                if ($customRate['type'] === 'fixed') {
                    // Fixed price in USD
                    return max(0, (float) $customRate['value']);
                } elseif ($customRate['type'] === 'percent') {
                    // Percentage of default price
                    $percentage = (float) $customRate['value'];
                    return max(0, $defaultPrice * ($percentage / 100));
                }
            }
        }

        // No custom rate - apply personal discount
        $discount = (float) ($client->discount ?? 0);

        if ($discount <= 0) {
            // No discount
            return max(0, $defaultPrice);
        } elseif ($discount >= 100) {
            // 100% discount = free
            return 0;
        } else {
            // Apply percentage discount
            return max(0, $defaultPrice * (1 - ($discount / 100)));
        }
    }
}
