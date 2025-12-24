<?php

namespace App\Repositories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

interface CategoryRepositoryInterface
{
    /**
     * Get all categories with their services.
     *
     * @param array $filters
     * @return Collection
     */
    public function getAllWithServices(array $filters = []): Collection;

    /**
     * Get all categories ordered by name.
     *
     * @param bool $forClient If true, only return enabled categories
     * @return Collection
     */
    public function getAll(bool $forClient = false): Collection;

    /**
     * Find a category by ID.
     *
     * @param int $id
     * @return Category|null
     */
    public function findById(int $id): ?Category;

    /**
     * Create a new category.
     *
     * @param array $data
     * @return Category
     */
    public function create(array $data): Category;

    /**
     * Update a category.
     *
     * @param Category $category
     * @param array $data
     * @return bool
     */
    public function update(Category $category, array $data): bool;

    /**
     * Delete a category.
     *
     * @param Category $category
     * @return bool
     */
    public function delete(Category $category): bool;
}

