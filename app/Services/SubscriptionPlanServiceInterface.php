<?php

namespace App\Services;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Collection;

interface SubscriptionPlanServiceInterface
{
    /**
     * Get all subscription plans.
     *
     * @return Collection
     */
    public function getAllPlans(): Collection;

    /**
     * Get all active subscription plans.
     *
     * @return Collection
     */
    public function getAllActivePlans(): Collection;

    /**
     * Find a subscription plan by ID.
     *
     * @param int $id
     * @return SubscriptionPlan|null
     */
    public function getPlanById(int $id): ?SubscriptionPlan;

    /**
     * Create a new subscription plan with prices and services.
     *
     * @param array $data
     * @return SubscriptionPlan
     */
    public function createPlan(array $data): SubscriptionPlan;

    /**
     * Update a subscription plan with prices and services.
     *
     * @param SubscriptionPlan $plan
     * @param array $data
     * @return SubscriptionPlan
     */
    public function updatePlan(SubscriptionPlan $plan, array $data): SubscriptionPlan;

    /**
     * Delete a subscription plan.
     *
     * @param SubscriptionPlan $plan
     * @return bool
     */
    public function deletePlan(SubscriptionPlan $plan): bool;
}
