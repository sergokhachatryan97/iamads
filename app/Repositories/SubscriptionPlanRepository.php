<?php

namespace App\Repositories;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Collection;

class SubscriptionPlanRepository implements SubscriptionPlanRepositoryInterface
{
    /**
     * Get all subscription plans with their relationships.
     *
     * @return Collection
     */
    public function getAll(): Collection
    {
        return SubscriptionPlan::with(['category', 'prices', 'planServices.service'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get all active subscription plans.
     *
     * @return Collection
     */
    public function getAllActive(): Collection
    {
        return SubscriptionPlan::with(['category', 'prices', 'planServices.service'])
            ->where('is_active', true)
            ->orderByRaw("
            CASE preview_variant
                WHEN 1 THEN 1
                WHEN 2 THEN 2
                WHEN 3 THEN 3
                ELSE 4
            END
        ")
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Find a subscription plan by ID.
     *
     * @param int $id
     * @return SubscriptionPlan|null
     */
    public function findById(int $id): ?SubscriptionPlan
    {
        return SubscriptionPlan::find($id);
    }

    /**
     * Find a subscription plan by ID with relationships.
     *
     * @param int $id
     * @return SubscriptionPlan|null
     */
    public function findByIdWithRelations(int $id): ?SubscriptionPlan
    {
        return SubscriptionPlan::with(['category', 'prices', 'planServices.service'])
            ->find($id);
    }

    /**
     * Create a new subscription plan.
     *
     * @param array $data
     * @return SubscriptionPlan
     */
    public function create(array $data): SubscriptionPlan
    {
        return SubscriptionPlan::create($data);
    }

    /**
     * Update a subscription plan.
     *
     * @param SubscriptionPlan $plan
     * @param array $data
     * @return bool
     */
    public function update(SubscriptionPlan $plan, array $data): bool
    {
        return $plan->update($data);
    }

    /**
     * Delete a subscription plan.
     *
     * @param SubscriptionPlan $plan
     * @return bool
     */
    public function delete(SubscriptionPlan $plan): bool
    {
        return $plan->delete();
    }

    /**
     * Sync prices for a subscription plan.
     *
     * @param SubscriptionPlan $plan
     * @param array $prices
     * @return void
     */
    public function syncPrices(SubscriptionPlan $plan, array $prices): void
    {
        // Delete existing prices
        $plan->prices()->delete();

        // Create new prices
        foreach ($prices as $priceData) {
            $plan->prices()->create($priceData);
        }
    }

    /**
     * Sync services for a subscription plan.
     *
     * @param SubscriptionPlan $plan
     * @param array $services
     * @return void
     */
    public function syncServices(SubscriptionPlan $plan, array $services): void
    {
        // Delete existing services
        $plan->planServices()->delete();

        // Create new services
        foreach ($services as $serviceData) {
            $plan->planServices()->create($serviceData);
        }
    }
}
