<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\ProviderOrderStatsRequest;
use App\Models\ProviderOrder;
use App\Models\ProviderService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProviderOrderStatsController extends Controller
{
    public function index(ProviderOrderStatsRequest $request): View
    {
        $filters = $this->normalizeFilters($request);
        $tab = $this->resolveTab($request->get('tab'));

        $baseQuery = $this->buildTabQuery($filters, $tab);

        $totals = $this->getTotals($baseQuery);
        $byUser = $this->getByUser($baseQuery, $tab);
        $byService = $this->getByService($baseQuery, $tab);
        $byUserByService = $this->getByUserByService($baseQuery, $tab);

        $serviceNames = Cache::remember('provider_service_names:v1', 600, function () {
            return ProviderService::query()
                ->whereNotNull('remote_service_id')
                ->select('remote_service_id', DB::raw('MIN(name) as name'))
                ->groupBy('remote_service_id')
                ->pluck('name', 'remote_service_id')
                ->all();
        });

        $distinctServiceIds = Cache::remember('provider_order_distinct_service_ids:v1', 300, function () {
            return ProviderOrder::query()
                ->whereNotNull('remote_service_id')
                ->where('remote_service_id', '!=', '')
                ->orderBy('remote_service_id')
                ->distinct()
                ->pluck('remote_service_id', 'remote_service_id')
                ->all();
        });

        $distinctProviderCodes = Cache::remember('provider_order_distinct_provider_codes:v1', 300, function () {
            return ProviderOrder::query()
                ->orderBy('provider_code')
                ->distinct()
                ->pluck('provider_code', 'provider_code')
                ->all();
        });

        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;

        return view('staff.provider-order-stats.index', [
            'tab' => $tab,
            'totals' => $totals,
            'byUser' => $byUser,
            'byService' => $byService,
            'byUserByService' => $byUserByService,
            'serviceNames' => $serviceNames,
            'distinctServiceIds' => $distinctServiceIds,
            'distinctProviderCodes' => $distinctProviderCodes,
            'filters' => $filters,
            'inputDateFrom' => $request->filled('date_from')
                ? $request->date_from
                : ($dateFrom ? $dateFrom->format('Y-m-d') : ''),
            'inputDateTo' => $request->filled('date_to')
                ? $request->date_to
                : ($dateTo ? $dateTo->format('Y-m-d') : ''),
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'provider_code' => ['nullable', 'string', 'max:50'],
            'remote_service_id' => ['nullable', 'string', 'max:50'],
            'user_login' => ['nullable', 'string', 'max:255'],
            'user_remote_id' => ['nullable', 'string', 'max:50'],
            'tab' => ['nullable', 'string', 'in:completed,without_failed,partial'],
        ]);

        $filters = $this->filtersFromArray($validated);
        $tab = $this->resolveTab($validated['tab'] ?? null);
        $baseQuery = $this->buildTabQuery($filters, $tab);

        $totals = $this->getTotals($baseQuery);

        return response()->streamDownload(function () use ($baseQuery, $totals) {
            $out = fopen('php://output', 'w');
            fprintf($out, "\xEF\xBB\xBF");

            fputcsv($out, ['User', 'Service', 'Orders', 'Quantity', 'Remains', 'Difference', 'Total']);

            (clone $baseQuery)
                ->select([
                    'user_login',
                    'user_remote_id',
                    'remote_service_id',
                    DB::raw('COUNT(*) as orders_count'),
                    DB::raw('SUM(COALESCE(quantity, 0)) as total_quantity'),
                    DB::raw('SUM(COALESCE(remains, 0)) as total_remains'),
                    DB::raw('SUM(COALESCE(quantity, 0)) - SUM(COALESCE(remains, 0)) as total_difference'),
                    DB::raw('SUM(' . $this->chargeExpression() . ') as total_charge'),
                ])
                ->groupBy('user_login', 'user_remote_id', 'remote_service_id')
                ->orderByDesc('total_charge')
                ->chunk(1000, function ($rows) use ($out) {
                    foreach ($rows as $row) {
                        fputcsv($out, [
                            $row->user_login ?: $row->user_remote_id ?: '—',
                            $row->remote_service_id ?? '—',
                            $row->orders_count,
                            $row->total_quantity,
                            $row->total_remains,
                            $row->total_difference,
                            $row->total_charge,
                        ]);
                    }
                });

            fputcsv($out, []);
            fputcsv($out, [
                'TOTAL',
                '',
                '',
                $totals['total_quantity'],
                $totals['total_remains'],
                $totals['total_difference'],
                $totals['total_charge'],
            ]);

            fclose($out);
        }, $this->exportFilename($validated), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function buildTabQuery(array $filters, string $tab)
    {
        $query = ProviderOrder::query()
            ->where('provider_code', '<>', 'socpanel')
            ->filter($filters);

        return match ($tab) {
            'completed' => $query->completed(),
            'partial' => $query->partial(),
            default => $query->withoutFailed(),
        };
    }

    private function getTotals($query): array
    {
        $row = (clone $query)
            ->selectRaw('
                COUNT(*) as orders_count,
                COALESCE(SUM(COALESCE(quantity, 0)), 0) as total_quantity,
                COALESCE(SUM(COALESCE(remains, 0)), 0) as total_remains,
                COALESCE(SUM(' . $this->chargeExpression() . '), 0) as total_charge
            ')
            ->first();

        $totalQuantity = (int) ($row->total_quantity ?? 0);
        $totalRemains = (int) ($row->total_remains ?? 0);

        return [
            'orders_count' => (int) ($row->orders_count ?? 0),
            'total_quantity' => $totalQuantity,
            'total_remains' => $totalRemains,
            'total_difference' => $totalQuantity - $totalRemains,
            'total_charge' => (float) ($row->total_charge ?? 0),
        ];
    }

    private function getByUser($query, string $tab)
    {
        return (clone $query)
            ->select([
                'user_login',
                'user_remote_id',
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('SUM(COALESCE(quantity, 0)) as total_quantity'),
                DB::raw('SUM(COALESCE(remains, 0)) as total_remains'),
                DB::raw('SUM(COALESCE(quantity, 0)) - SUM(COALESCE(remains, 0)) as total_difference'),
                DB::raw('SUM(' . $this->chargeExpression() . ') as total_charge'),
                DB::raw('AVG(' . $this->chargeExpression() . ') as avg_charge'),
            ])
            ->groupBy('user_login', 'user_remote_id')
            ->orderByDesc('total_charge')
            ->paginate(20, ['*'], 'user_page_' . $tab)
            ->withQueryString();
    }

    private function getByService($query, string $tab)
    {
        return (clone $query)
            ->select([
                'remote_service_id',
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('SUM(COALESCE(quantity, 0)) as total_quantity'),
                DB::raw('SUM(COALESCE(remains, 0)) as total_remains'),
                DB::raw('SUM(COALESCE(quantity, 0)) - SUM(COALESCE(remains, 0)) as total_difference'),
                DB::raw('SUM(' . $this->chargeExpression() . ') as total_charge'),
            ])
            ->groupBy('remote_service_id')
            ->orderByDesc('total_charge')
            ->paginate(20, ['*'], 'service_page_' . $tab)
            ->withQueryString();
    }

    private function getByUserByService($query, string $tab)
    {
        return (clone $query)
            ->select([
                'user_login',
                'user_remote_id',
                'remote_service_id',
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('SUM(COALESCE(quantity, 0)) as total_quantity'),
                DB::raw('SUM(COALESCE(remains, 0)) as total_remains'),
                DB::raw('SUM(COALESCE(quantity, 0)) - SUM(COALESCE(remains, 0)) as total_difference'),
                DB::raw('SUM(' . $this->chargeExpression() . ') as total_charge'),
            ])
            ->groupBy('user_login', 'user_remote_id', 'remote_service_id')
            ->orderByDesc('total_charge')
            ->paginate(50, ['*'], 'breakdown_page_' . $tab)
            ->withQueryString();
    }

    private function chargeExpression(): string
    {
        return 'COALESCE(CAST(charge AS DECIMAL(20,4)), 0)';
    }

    private function resolveTab(?string $tab): string
    {
        return in_array($tab, ['completed', 'without_failed', 'partial'], true)
            ? $tab
            : 'without_failed';
    }

    private function normalizeFilters(ProviderOrderStatsRequest $request): array
    {
        $filters = $request->filters();

        if (empty($filters['date_from']) && empty($filters['date_to'])) {
            $filters['date_from'] = now()->startOfDay();
            $filters['date_to'] = now()->endOfDay();
        }

        return $filters;
    }

    private function filtersFromArray(array $input): array
    {
        $filters = [];

        if (!empty($input['date_from'])) {
            $filters['date_from'] = \Carbon\Carbon::parse($input['date_from'])->startOfDay();
        }

        if (!empty($input['date_to'])) {
            $filters['date_to'] = \Carbon\Carbon::parse($input['date_to'])->endOfDay();
        }

        if (empty($filters['date_from']) && empty($filters['date_to'])) {
            $filters['date_from'] = now()->startOfDay();
            $filters['date_to'] = now()->endOfDay();
        }

        foreach (['provider_code', 'remote_service_id', 'user_login', 'user_remote_id'] as $field) {
            if (isset($input[$field]) && $input[$field] !== '') {
                $filters[$field] = $input[$field];
            }
        }

        return $filters;
    }

    private function exportFilename(array $validated): string
    {
        $base = 'provider-order-stats';
        $dateFrom = isset($validated['date_from']) ? \Carbon\Carbon::parse($validated['date_from'])->format('Y-m-d') : null;
        $dateTo = isset($validated['date_to']) ? \Carbon\Carbon::parse($validated['date_to'])->format('Y-m-d') : null;

        if ($dateFrom && $dateTo) {
            return "{$base}-{$dateFrom}-to-{$dateTo}.csv";
        }

        if ($dateFrom) {
            return "{$base}-from-{$dateFrom}.csv";
        }

        if ($dateTo) {
            return "{$base}-to-{$dateTo}.csv";
        }

        return "{$base}-" . now()->format('Y-m-d-His') . '.csv';
    }
}
