<?php

namespace App\Helpers;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class OrderQueryBuilder
{
    /**
     * Build base query for orders with filters and permissions.
     *
     * @param array $filters
     * @param bool $isSuperAdmin
     * @param int|null $staffId
     * @return Builder
     */
    public static function buildQuery(array $filters = [], ?bool $isSuperAdmin = null, ?int $staffId = null): Builder
    {
        $query = Order::query()
            ->with(['service', 'client', 'category', 'subscription', 'creator']);

        // Apply staff/client permissions
        if ($isSuperAdmin === null) {
            $user = Auth::guard('staff')->user();
            $isSuperAdmin = $user && $user->hasRole('super_admin');
            $staffId = $staffId ?? ($user ? $user->id : null);
        }

        if (!$isSuperAdmin && $staffId) {
            $query->whereHas('client', function ($q) use ($staffId) {
                $q->where('staff_id', $staffId);
            });
        }

        // Status filter
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        // Search filter (search in link, order ID, client name)
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('link', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($clientQuery) use ($search) {
                        $clientQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Service filter
        if (!empty($filters['service_id'])) {
            $query->where('service_id', $filters['service_id']);
        }

        // Category filter
        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        // Date range filters
        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query;
    }

    /**
     * Build query for bulk actions with select_all logic.
     *
     * @param bool $selectAll
     * @param array $selectedIds
     * @param array $excludedIds
     * @param array $filters
     * @param bool|null $isSuperAdmin
     * @param int|null $staffId
     * @return Builder
     */
    public static function buildBulkQuery(
        bool $selectAll,
        array $selectedIds = [],
        array $excludedIds = [],
        array $filters = [],
        ?bool $isSuperAdmin = null,
        ?int $staffId = null
    ): Builder {
        if ($selectAll) {
            // Build query from filters
            $query = self::buildQuery($filters, $isSuperAdmin, $staffId);

            // Exclude manually unselected orders
            if (!empty($excludedIds)) {
                $query->whereNotIn('id', $excludedIds);
            }
        } else {
            // Build query for specific IDs
            $query = self::buildQuery([], $isSuperAdmin, $staffId);
            $query->whereIn('id', $selectedIds);
        }

        return $query;
    }

    /**
     * Count orders matching the query without loading them.
     *
     * @param Builder $query
     * @return int
     */
    public static function countMatching(Builder $query): int
    {
        return (clone $query)->count();
    }
}

