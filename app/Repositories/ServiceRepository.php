<?php

namespace App\Repositories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Collection;

class ServiceRepository implements ServiceRepositoryInterface
{
    /**
     * Get all services with their category.
     *
     * @param array $filters
     * @return Collection
     */
    public function getAllWithCategory(array $filters = []): Collection
    {
        $query = Service::with('category');

        // Handle deleted services
        if (!empty($filters['show_deleted']) && $filters['show_deleted'] === '1') {
            $query->onlyTrashed();
        }

        // Apply filters
        $this->applyFilters($query, $filters);

        return $query->orderBy('name')->get();
    }

    /**
     * Apply filters to the query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters
     * @return void
     */
    private function applyFilters($query, array $filters): void
    {
        // Search filter
        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $searchBy = $filters['search_by'] ?? 'all';

            $query->where(function ($q) use ($search, $searchBy) {
                switch ($searchBy) {
                    case 'service_name':
                        $q->where('name', 'like', "%{$search}%");
                        break;
                    case 'service_id':
                        if (is_numeric($search)) {
                            $q->where('id', $search);
                        }
                        break;
                    case 'category_name':
                        $q->whereHas('category', function ($categoryQuery) use ($search) {
                            $categoryQuery->where('name', 'like', "%{$search}%");
                        });
                        break;
                    case 'all':
                    default:
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('id', 'like', "%{$search}%")
                            ->orWhereHas('category', function ($categoryQuery) use ($search) {
                                $categoryQuery->where('name', 'like', "%{$search}%");
                            });
                        break;
                }
            });
        }

        // Min/Max filter (for quantity)
        if (!empty($filters['min'])) {
            $query->where('min_quantity', '>=', (int) $filters['min']);
        }
        if (!empty($filters['max'])) {
            $query->where('max_quantity', '<=', (int) $filters['max']);
        }

        // Status filter
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('is_active', $filters['status'] === 'active' ? 1 : 0);
        }
    }

    /**
     * Get all services.
     *
     * @return Collection
     */
    public function getAll(): Collection
    {
        return Service::orderByDesc('created_at')->get();
    }

    /**
     * Get services by category ID.
     *
     * @param int $categoryId
     * @param bool $activeOnly
     * @return Collection
     */
    public function getByCategoryId(int $categoryId, bool $activeOnly = false): Collection
    {
        $query = Service::where('category_id', $categoryId);
        
        if ($activeOnly) {
            $query->where('is_active', true);
        }
        
        return $query->orderBy('name')->get();
    }

    /**
     * Find a service by ID.
     *
     * @param int $id
     * @return Service|null
     */
    public function findById(int $id): ?Service
    {
        return Service::find($id);
    }

    /**
     * Create a new service.
     *
     * @param array $data
     * @return Service
     */
    public function create(array $data): Service
    {
        return Service::create($data);
    }

    /**
     * Update a service.
     *
     * @param Service $service
     * @param array $data
     * @return bool
     */
    public function update(Service $service, array $data): bool
    {
        return $service->update($data);
    }

    /**
     * Delete a service.
     *
     * @param Service $service
     * @return bool
     */
    public function delete(Service $service): bool
    {
        return $service->delete();
    }

    /**
     * Restore a soft-deleted service.
     *
     * @param Service $service
     * @return bool
     */
    public function restore(Service $service): bool
    {
        return $service->restore();
    }
}

