<?php

namespace App\Services;

use App\Models\Category;
use App\Repositories\CategoryRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class CategoryService implements CategoryServiceInterface
{
    public function __construct(
        private CategoryRepositoryInterface $categoryRepository
    ) {
    }

    /**
     * Get all categories with their services.
     *
     * @param array $filters
     * @return Collection
     */
    public function getAllCategoriesWithServices(array $filters = []): Collection
    {
        return $this->categoryRepository->getAllWithServices($filters);
    }

    /**
     * Get all categories.
     *
     * @return Collection
     */
    public function getAllCategories(): Collection
    {
        return $this->categoryRepository->getAll();
    }

    /**
     * Find a category by ID.
     *
     * @param int $id
     * @return Category|null
     */
    public function getCategoryById(int $id): ?Category
    {
        return $this->categoryRepository->findById($id);
    }

    /**
     * Create a new category.
     *
     * @param array $data
     * @return Category
     */
    public function createCategory(array $data): Category
    {
        return $this->categoryRepository->create($data);
    }

    /**
     * Update a category.
     *
     * @param Category $category
     * @param array $data
     * @return bool
     */
    public function updateCategory(Category $category, array $data): bool
    {
        return $this->categoryRepository->update($category, $data);
    }

    /**
     * Delete a category.
     *
     * @param Category $category
     * @return bool
     */
    public function deleteCategory(Category $category): bool
    {
        return $this->categoryRepository->delete($category);
    }

    /**
     * Toggle category status (enable/disable).
     *
     * @param Category $category
     * @return bool
     */
    public function toggleCategoryStatus(Category $category): bool
    {
        $category->status = !$category->status;
        return $category->save();
    }
}

