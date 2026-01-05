<?php

namespace App\Exports;

use App\Models\ClientServiceQuota;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class SubscriptionsExportQueryBuilder implements ExportQueryBuilderInterface
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
        $query = ClientServiceQuota::query();

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

        if (!empty($filters['expires_from'])) {
            $query->whereDate('expires_at', '>=', $filters['expires_from']);
        }

        if (!empty($filters['expires_to'])) {
            $query->whereDate('expires_at', '<=', $filters['expires_to']);
        }

        if (!empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        if (!empty($filters['staff_id'])) {
            $query->whereHas('client', function ($q) use ($filters) {
                $q->where('staff_id', $filters['staff_id']);
            });
        }

        if (!empty($filters['subscription_id'])) {
            $query->where('subscription_id', $filters['subscription_id']);
        }

        if (!empty($filters['service_id'])) {
            $query->where('service_id', $filters['service_id']);
        }

        if (isset($filters['auto_renew']) && $filters['auto_renew'] !== '') {
            $query->where('auto_renew', (bool) $filters['auto_renew']);
        }

        // Filter by active/expired
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'active') {
                $query->where('expires_at', '>', now());
            } elseif ($filters['status'] === 'expired') {
                $query->where('expires_at', '<=', now());
            }
        }

        // Eager load relationships to avoid N+1
        $query->with(['client.staff', 'subscription', 'service']);

        return $query;
    }

    /**
     * Get column headings for the export.
     */
    public function headings(array $columns): array
    {
        $labels = config('exports.modules.subscriptions.allowed_columns', []);
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
    protected function getColumnValue(ClientServiceQuota $quota, string $column): mixed
    {
        return match ($column) {
            'id' => $quota->id,
            'created_at' => $quota->created_at?->format('Y-m-d H:i:s'),
            'client_name' => $quota->client?->name ?? "Client #{$quota->client_id}",
            'staff_name' => $quota->client?->staff?->name ?? '—',
            'subscription_name' => $quota->subscription?->name ?? '—',
            'service_name' => $quota->service?->name ?? 'N/A',
            'orders_left' => $quota->orders_left !== null ? number_format($quota->orders_left) : '—',
            'quantity_left' => $quota->quantity_left !== null ? number_format($quota->quantity_left) : '—',
            'link' => $quota->link ?? '—',
            'auto_renew' => $quota->auto_renew ? 'Yes' : 'No',
            'expires_at' => $quota->expires_at?->format('Y-m-d H:i:s'),
            'status' => $quota->expires_at && $quota->expires_at->isFuture() ? 'Active' : 'Expired',
            default => $quota->getAttribute($column) ?? '—',
        };
    }
}

