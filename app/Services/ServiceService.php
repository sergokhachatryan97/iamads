<?php

namespace App\Services;

use App\Models\Service;
use App\Repositories\ServiceRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ServiceService implements ServiceServiceInterface
{
    public function __construct(
        private ServiceRepositoryInterface $serviceRepository
    ) {
    }

    /**
     * Get all services with their category.
     *
     * @param array $filters
     * @return Collection
     */
    public function getAllServicesWithCategory(array $filters = []): Collection
    {
        return $this->serviceRepository->getAllWithCategory($filters);
    }

    /**
     * Get all services.
     *
     * @return Collection
     */
    public function getAllServices(): Collection
    {
        return $this->serviceRepository->getAll();
    }

    /**
     * Get services by category ID.
     *
     * @param int $categoryId
     * @param bool $activeOnly
     * @return Collection
     */
    public function getServicesByCategoryId(int $categoryId, bool $activeOnly = false): Collection
    {
        return $this->serviceRepository->getByCategoryId($categoryId, $activeOnly);
    }

    /**
     * Find a service by ID.
     *
     * @param int $id
     * @return Service|null
     */
    public function getServiceById(int $id): ?Service
    {
        return $this->serviceRepository->findById($id);
    }

    /**
     * Create a new service.
     *
     * @param array $data
     * @return Service
     */
    public function createService(array $data): Service
    {
        return $this->serviceRepository->create($data);
    }

    /**
     * Update a service.
     *
     * @param Service $service
     * @param array $data
     * @return bool
     */
    public function updateService(Service $service, array $data): bool
    {
        return $this->serviceRepository->update($service, $data);
    }

    /**
     * Delete a service.
     * This performs a soft delete and cleans up related data.
     *
     * @param Service $service
     * @return bool
     */
    public function deleteService(Service $service): bool
    {
        // Detach all client favorites before deleting
        $service->favoritedByClients()->detach();
        
        // Perform soft delete
        return $this->serviceRepository->delete($service);
    }

    /**
     * Toggle service status (enable/disable).
     *
     * @param Service $service
     * @return bool
     */
    public function toggleServiceStatus(Service $service): bool
    {
        $service->is_active = !$service->is_active;
        return $service->save();
    }

    /**
     * Duplicate a service.
     *
     * @param Service $service
     * @return Service
     */
    public function duplicateService(Service $service): Service
    {
        $data = $service->toArray();
        
        // Remove fields that shouldn't be copied
        unset(
            $data['id'],
            $data['created_at'],
            $data['updated_at'],
            $data['deleted_at'] // Exclude soft delete timestamp
        );
        
        // Append " (Copy)" to the service name
        $data['name'] = $data['name'] . ' (Copy)';
        
        // Ensure the duplicated service is active by default
        $data['is_active'] = true;
        
        return $this->serviceRepository->create($data);
    }

    /**
     * Restore a soft-deleted service.
     *
     * @param Service $service
     * @return bool
     */
    public function restoreService(Service $service): bool
    {
        return $this->serviceRepository->restore($service);
    }
}

