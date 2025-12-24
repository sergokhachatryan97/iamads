<?php

namespace App\Repositories;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Collection;

interface SubscriptionPlanRepositoryInterface
{
    /**
     * Get all subscription plans with their relationships.
     *
     * @return Collection
     */
    public function getAll(): Collection;

    /**
     * Get all active subscription plans.
     *
     * @return Collection
     */
    public function getAllActive(): Collection;

    /**
     * Find a subscription plan by ID.
     *
     * @param int $id
     * @return SubscriptionPlan|null
     */
    public function findById(int $id): ?SubscriptionPlan;

    /**
     * Find a subscription plan by ID with relationships.
     *
     * @param int $id
     * @return SubscriptionPlan|null
     */
    public function findByIdWithRelations(int $id): ?SubscriptionPlan;

    /**
     * Create a new subscription plan.
     *
     * @param array $data
     * @return SubscriptionPlan
     */
    public function create(array $data): SubscriptionPlan;

    /**
     * Update a subscription plan.
     *
     * @param SubscriptionPlan $plan
     * @param array $data
     * @return bool
     */
    public function update(SubscriptionPlan $plan, array $data): bool;

    /**
     * Delete a subscription plan.
     *
     * @param SubscriptionPlan $plan
     * @return bool
     */
    public function delete(SubscriptionPlan $plan): bool;

    /**
     * Sync prices for a subscription plan.
     *
     * @param SubscriptionPlan $plan
     * @param array $prices
     * @return void
     */
    public function syncPrices(SubscriptionPlan $plan, array $prices): void;

    /**
     * Sync services for a subscription plan.
     *
     * @param SubscriptionPlan $plan
     * @param array $services
     * @return void
     */
    public function syncServices(SubscriptionPlan $plan, array $services): void;
}
