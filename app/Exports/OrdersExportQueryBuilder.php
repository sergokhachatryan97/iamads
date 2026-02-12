<?php

namespace App\Exports;

use App\Helpers\OrderQueryBuilder;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class OrdersExportQueryBuilder implements ExportQueryBuilderInterface
{
    /**
     * Build the query based on filters.
     */
    public function build(array $filters): Builder
    {
        $user = Auth::guard('staff')->user();
        $isSuperAdmin = $user && $user->hasRole('super_admin');
        $staffId = $user ? $user->id : null;

        // Build base query
        $query = Order::query();

        // Apply staff permissions
        if (!$isSuperAdmin && $staffId) {
            $query->whereHas('client', function ($q) use ($staffId) {
                $q->where('staff_id', $staffId);
            });
        }

        // Apply filters
        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (!empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        if (!empty($filters['staff_id'])) {
            $query->whereHas('client', function ($q) use ($filters) {
                $q->where('staff_id', $filters['staff_id']);
            });
        }

        if (!empty($filters['service_id'])) {
            $query->where('service_id', $filters['service_id']);
        }

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['provider_id'])) {
            $query->where('provider_id', $filters['provider_id']);
        }

        if (!empty($filters['mode'])) {
            $query->where('mode', $filters['mode']);
        }

        // Eager load relationships to avoid N+1
        $query->with(['service', 'client.staff', 'category', 'creator']);

        return $query;
    }

    /**
     * Get column headings for the export.
     */
    public function headings(array $columns): array
    {
        $labels = config('exports.modules.orders.allowed_columns', []);
        $headings = [];

        foreach ($columns as $column) {
            $headings[] = $labels[$column] ?? ucfirst(str_replace('_', ' ', $column));
        }

        return $headings;
    }

    /**
     * Map a model row to export data.
     */
    public function mapRow($model, array $columns): array
    {
        $row = [];

        foreach ($columns as $column) {
            $row[] = $this->getColumnValue($model, $column);
        }

        return $row;
    }

    /**
     * Get the value for a specific column.
     */
    protected function getColumnValue(Order $order, string $column): mixed
    {
        return match ($column) {
            'id' => $order->id,
            'created_at' => $order->created_at?->format('Y-m-d H:i:s'),
            'client_name' => $order->client?->name ?? "Client #{$order->client_id}",
            'staff_name' => $order->client?->staff?->name ?? '—',
            'link' => $order->link ?? '—',
            'charge' => number_format($order->charge, 2),
            'cost' => $order->cost !== null ? number_format($order->cost, 2) : '—',
            'start_count' => $order->start_count ?? '—',
            'quantity' => number_format($order->quantity),
            'delivered' => number_format($order->delivered ?? 0),
            'remains' => number_format($order->remains),
            'service_name' => $order->service?->name ?? 'N/A',
            'category_name' => $order->category?->name ?? 'N/A',
            'status' => ucfirst(str_replace('_', ' ', $order->status)),
            'mode' => ucfirst($order->mode ?? 'manual'),
            'provider_order_id' => $order->provider_order_id ?? '—',
            default => $order->getAttribute($column) ?? '—',
        };
    }
}

