<?php

namespace App\Repositories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Collection;

interface ServiceRepositoryInterface
{
    /**
     * Get all services with their category.
     *
     * @param array $filters
     * @return Collection
     */
    public function getAllWithCategory(array $filters = []): Collection;

    /**
     * Get all services.
     *
     * @return Collection
     */
    public function getAll(): Collection;

    /**
     * Get services by category ID.
     *
     * @param int $categoryId
     * @param bool $activeOnly
     * @return Collection
     */
    public function getByCategoryId(int $categoryId, bool $activeOnly = false): Collection;

    /**
     * Find a service by ID.
     *
     * @param int $id
     * @return Service|null
     */
    public function findById(int $id): ?Service;

    /**
     * Create a new service.
     *
     * @param array $data
     * @return Service
     */
    public function create(array $data): Service;

    /**
     * Update a service.
     *
     * @param Service $service
     * @param array $data
     * @return bool
     */
    public function update(Service $service, array $data): bool;

    /**
     * Delete a service.
     *
     * @param Service $service
     * @return bool
     */
    public function delete(Service $service): bool;

    /**
     * Restore a soft-deleted service.
     *
     * @param Service $service
     * @return bool
     */
    public function restore(Service $service): bool;
}

