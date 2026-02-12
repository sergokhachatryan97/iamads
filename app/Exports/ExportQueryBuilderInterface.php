<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;

interface ExportQueryBuilderInterface
{
    /**
     * Build the query based on filters.
     *
     * @param array $filters
     * @return Builder
     */
    public function build(array $filters): Builder;

    /**
     * Get column headings for the export.
     *
     * @param array $columns Column keys
     * @return array Array of heading labels
     */
    public function headings(array $columns): array;

    /**
     * Map a model row to export data.
     *
     * @param mixed $model The model instance
     * @param array $columns Column keys to include
     * @return array Array of values matching the columns
     */
    public function mapRow($model, array $columns): array;
}

