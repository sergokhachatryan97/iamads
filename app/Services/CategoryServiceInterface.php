<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

interface CategoryServiceInterface
{
    /**
     * Get all categories with their services.
     *
     * @param array $filters
     * @return Collection
     */
    public function getAllCategoriesWithServices(array $filters = []): Collection;

    /**
     * Get all categories.
     *
     * @return Collection
     */
    public function getAllCategories(): Collection;

    /**
     * Find a category by ID.
     *
     * @param int $id
     * @return Category|null
     */
    public function getCategoryById(int $id): ?Category;

    /**
     * Create a new category.
     *
     * @param array $data
     * @return Category
     */
    public function createCategory(array $data): Category;

    /**
     * Update a category.
     *
     * @param Category $category
     * @param array $data
     * @return bool
     */
    public function updateCategory(Category $category, array $data): bool;

    /**
     * Delete a category.
     *
     * @param Category $category
     * @return bool
     */
    public function deleteCategory(Category $category): bool;

    /**
     * Toggle category status (enable/disable).
     *
     * @param Category $category
     * @return bool
     */
    public function toggleCategoryStatus(Category $category): bool;
}

