<?php

namespace App\Services;

use App\Models\Service;
use Illuminate\Database\Eloquent\Collection;

interface ServiceServiceInterface
{
    /**
     * Get all services with their category.
     *
     * @param array $filters
     * @return Collection
     */
    public function getAllServicesWithCategory(array $filters = []): Collection;

    /**
     * Get all services.
     *
     * @return Collection
     */
    public function getAllServices(): Collection;

    /**
     * Get services by category ID.
     *
     * @param int $categoryId
     * @return Collection
     */
    public function getServicesByCategoryId(int $categoryId): Collection;

    /**
     * Find a service by ID.
     *
     * @param int $id
     * @return Service|null
     */
    public function getServiceById(int $id): ?Service;

    /**
     * Create a new service.
     *
     * @param array $data
     * @return Service
     */
    public function createService(array $data): Service;

    /**
     * Update a service.
     *
     * @param Service $service
     * @param array $data
     * @return bool
     */
    public function updateService(Service $service, array $data): bool;

    /**
     * Delete a service.
     *
     * @param Service $service
     * @return bool
     */
    public function deleteService(Service $service): bool;

    /**
     * Toggle service status (enable/disable).
     *
     * @param Service $service
     * @return bool
     */
    public function toggleServiceStatus(Service $service): bool;

    /**
     * Duplicate a service.
     *
     * @param Service $service
     * @return Service
     */
    public function duplicateService(Service $service): Service;

    /**
     * Restore a soft-deleted service.
     *
     * @param Service $service
     * @return bool
     */
    public function restoreService(Service $service): bool;
}

