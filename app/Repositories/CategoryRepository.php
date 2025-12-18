<?php

namespace App\Repositories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

class CategoryRepository implements CategoryRepositoryInterface
{
    /**
     * Get all categories with their services (excluding soft-deleted services).
     *
     * @param array $filters
     * @return Collection
     */
    public function getAllWithServices(array $filters = []): Collection
    {
        $query = Category::with(['services' => function ($serviceQuery) use ($filters) {
            // Handle deleted services
            if (!empty($filters['show_deleted']) && $filters['show_deleted'] === '1') {
                $serviceQuery->onlyTrashed();
            } else {
                $serviceQuery->whereNull('deleted_at');
            }
            
            // Apply service filters
            $this->applyServiceFilters($serviceQuery, $filters);
        }]);

        // Filter by category_id if provided
        if (!empty($filters['category_id'])) {
            $query->where('id', $filters['category_id']);
        }

        // Filter categories by service search if searching by category name
        if (!empty($filters['search']) && ($filters['search_by'] ?? 'all') === 'category_name') {
            $search = trim($filters['search']);
            $query->where('name', 'like', "%{$search}%");
        }

        $categories = $query->orderBy('created_at', 'desc')->get();

        // Filter out categories that have no matching services after filtering
        // This ensures we only show categories that have services matching the search criteria
        // Always filter out empty categories when showing deleted services
        // Don't filter if only category_id is set (we want to show the category even if empty)
        $shouldFilterEmpty = !empty($filters['show_deleted']) && $filters['show_deleted'] === '1';
        
        if ($shouldFilterEmpty || ((!empty($filters['search']) || !empty($filters['min']) || !empty($filters['max']) || 
            (!empty($filters['status']) && $filters['status'] !== 'all')) && empty($filters['category_id']))) {
            $categories = $categories->filter(function ($category) {
                return $category->services->isNotEmpty();
            })->values();
        }

        return $categories;
    }

    /**
     * Apply filters to services query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters
     * @return void
     */
    private function applyServiceFilters($query, array $filters): void
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
                    case 'all':
                    default:
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('id', 'like', "%{$search}%");
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

        // Sorting
        $allowedSorts = ['id', 'name', 'service_type', 'mode', 'min_quantity', 'max_quantity', 'rate_per_1000', 'is_active', 'created_at'];
        $sort = $filters['sort'] ?? 'id';
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'id';
        }

        $dir = ($filters['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sort, $dir);
    }

    /**
     * Get all categories ordered by creation date (newest first).
     *
     * @return Collection
     */
    public function getAll(): Collection
    {
        return Category::orderBy('created_at', 'desc')->get();
    }

    /**
     * Find a category by ID.
     *
     * @param int $id
     * @return Category|null
     */
    public function findById(int $id): ?Category
    {
        return Category::find($id);
    }

    /**
     * Create a new category.
     *
     * @param array $data
     * @return Category
     */
    public function create(array $data): Category
    {
        return Category::create($data);
    }

    /**
     * Update a category.
     *
     * @param Category $category
     * @param array $data
     * @return bool
     */
    public function update(Category $category, array $data): bool
    {
        return $category->update($data);
    }

    /**
     * Delete a category.
     *
     * @param Category $category
     * @return bool
     */
    public function delete(Category $category): bool
    {
        return $category->delete();
    }
}

