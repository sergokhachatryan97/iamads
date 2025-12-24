<?php

namespace App\Services;

use App\Models\SubscriptionPlan;
use App\Repositories\SubscriptionPlanRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SubscriptionPlanService implements SubscriptionPlanServiceInterface
{
    public function __construct(
        private SubscriptionPlanRepositoryInterface $planRepository
    ) {
    }

    /**
     * Get all subscription plans.
     *
     * @return Collection
     */
    public function getAllPlans(): Collection
    {
        return $this->planRepository->getAll();
    }

    /**
     * Get all active subscription plans.
     *
     * @return Collection
     */
    public function getAllActivePlans(): Collection
    {
        return $this->planRepository->getAllActive();
    }

    /**
     * Find a subscription plan by ID.
     *
     * @param int $id
     * @return SubscriptionPlan|null
     */
    public function getPlanById(int $id): ?SubscriptionPlan
    {
        return $this->planRepository->findByIdWithRelations($id);
    }

    /**
     * Create a new subscription plan with prices and services.
     *
     * @param array $data
     * @return SubscriptionPlan
     */
    public function createPlan(array $data): SubscriptionPlan
    {
        return DB::transaction(function () use ($data) {
            $prices = $data['prices'] ?? [];
            $services = $data['services'] ?? [];

            // Remove prices and services from plan data
            unset($data['prices'], $data['services']);

            // Create the plan
            $plan = $this->planRepository->create($data);

            // Sync prices
            if (!empty($prices)) {
                $this->planRepository->syncPrices($plan, $prices);
            }

            // Sync services
            if (!empty($services)) {
                $this->planRepository->syncServices($plan, $services);
            }

            return $plan->fresh(['category', 'prices', 'planServices.service']);
        });
    }

    /**
     * Update a subscription plan with prices and services.
     *
     * @param SubscriptionPlan $plan
     * @param array $data
     * @return SubscriptionPlan
     */
    public function updatePlan(SubscriptionPlan $plan, array $data): SubscriptionPlan
    {
        return DB::transaction(function () use ($plan, $data) {
            $prices = $data['prices'] ?? null;
            $services = $data['services'] ?? null;

            // Remove prices and services from plan data
            unset($data['prices'], $data['services']);

            // Update the plan
            $this->planRepository->update($plan, $data);

            // Sync prices if provided
            if ($prices !== null) {
                $this->planRepository->syncPrices($plan, $prices);
            }

            // Sync services if provided
            if ($services !== null) {
                $this->planRepository->syncServices($plan, $services);
            }

            return $plan->fresh(['category', 'prices', 'planServices.service']);
        });
    }

    /**
     * Delete a subscription plan.
     *
     * @param SubscriptionPlan $plan
     * @return bool
     */
    public function deletePlan(SubscriptionPlan $plan): bool
    {
        return $this->planRepository->delete($plan);
    }
}
