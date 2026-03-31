<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\ProviderOrderStatsRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProviderOrderStatsController extends Controller
{
    private const STATS_CACHE_TTL = 60;

    private const OPTIONS_CACHE_TTL = 600;

    private const CHARGE_EXPR = 'COALESCE(CAST(charge AS DECIMAL(20,4)), 0)';

    public function index(ProviderOrderStatsRequest $request): View
    {
        $filters = $this->normalizeFilters($request);
        $tab = $this->resolveTab($request->input('tab'));
        $cacheKey = $this->statsCacheKey($filters, $tab);

        // Totals: cached 60s — heaviest query, rarely needs real-time
        $totals = Cache::remember($cacheKey, self::STATS_CACHE_TTL, function () use ($filters, $tab) {
            return $this->getTotals($filters, $tab);
        });

        // Paginated: DB::table — no model hydration overhead
        $byUser = $this->getByUser($filters, $tab);
        $byService = $this->getByService($filters, $tab);
        $byUserByService = $this->getByUserByService($filters, $tab);

        // Dropdown options: cached 10min
        [$serviceNames, $distinctServiceIds, $distinctProviderCodes] = $this->getDropdownOptions();

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
        $totals = $this->getTotals($filters, $tab);
        $e = self::CHARGE_EXPR;

        return response()->streamDownload(function () use ($filters, $tab, $totals, $e) {
            $out = fopen('php://output', 'w');
            fprintf($out, "\xEF\xBB\xBF");
            fputcsv($out, ['User', 'Service', 'Orders', 'Quantity', 'Remains', 'Difference', 'Total']);

            $this->baseQuery($filters, $tab)
                ->select([
                    'user_login',
                    'user_remote_id',
                    'remote_service_id',
                    DB::raw('COUNT(*) as orders_count'),
                    DB::raw('SUM(COALESCE(quantity, 0)) as total_quantity'),
                    DB::raw('SUM(COALESCE(remains, 0)) as total_remains'),
                    DB::raw("SUM(COALESCE(quantity, 0)) - SUM(COALESCE(remains, 0)) as total_difference"),
                    DB::raw("SUM({$e}) as total_charge"),
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
            fputcsv($out, ['TOTAL', '', '', $totals['total_quantity'], $totals['total_remains'], $totals['total_difference'], $totals['total_charge']]);
            fclose($out);
        }, $this->exportFilename($validated), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    // =========================================================================
    //  Query builder — raw DB::table, no Eloquent model overhead
    // =========================================================================

    private function baseQuery(array $filters, string $tab)
    {
        $q = DB::table('provider_orders')
            ->where('provider_code', '<>', 'socpanel');

        // Date filter
        if (! empty($filters['date_from'])) {
            $q->where('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $q->where('created_at', '<=', $filters['date_to']);
        }

        // Optional filters
        if (! empty($filters['provider_code'])) {
            $q->where('provider_code', $filters['provider_code']);
        }
        if (isset($filters['remote_service_id']) && $filters['remote_service_id'] !== '') {
            $q->where('remote_service_id', $filters['remote_service_id']);
        }
        if (isset($filters['user_login']) && $filters['user_login'] !== '') {
            $q->where('user_login', $filters['user_login']);
        }
        if (isset($filters['user_remote_id']) && $filters['user_remote_id'] !== '') {
            $q->where('user_remote_id', $filters['user_remote_id']);
        }

        // Tab filter
        return match ($tab) {
            'completed' => $q->where('remote_status', 'completed'),
            'partial' => $q->where(fn ($q) => $q->where('remote_status', 'partial')->orWhere('status', 'partial')),
            default => $q->whereNotIn('status', ['fail', 'failed', 'canceled']),
        };
    }

    // =========================================================================
    //  Aggregates
    // =========================================================================

    private function getTotals(array $filters, string $tab): array
    {
        $e = self::CHARGE_EXPR;

        $row = $this->baseQuery($filters, $tab)
            ->selectRaw("
                COUNT(*) as orders_count,
                COALESCE(SUM(COALESCE(quantity, 0)), 0) as total_quantity,
                COALESCE(SUM(COALESCE(remains, 0)), 0) as total_remains,
                COALESCE(SUM({$e}), 0) as total_charge
            ")
            ->first();

        $qty = (int) ($row->total_quantity ?? 0);
        $rem = (int) ($row->total_remains ?? 0);

        return [
            'orders_count' => (int) ($row->orders_count ?? 0),
            'total_quantity' => $qty,
            'total_remains' => $rem,
            'total_difference' => $qty - $rem,
            'total_charge' => (float) ($row->total_charge ?? 0),
        ];
    }

    private function getByUser(array $filters, string $tab)
    {
        $e = self::CHARGE_EXPR;

        return $this->baseQuery($filters, $tab)
            ->select([
                'user_login',
                'user_remote_id',
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('SUM(COALESCE(quantity, 0)) as total_quantity'),
                DB::raw('SUM(COALESCE(remains, 0)) as total_remains'),
                DB::raw("SUM(COALESCE(quantity, 0)) - SUM(COALESCE(remains, 0)) as total_difference"),
                DB::raw("SUM({$e}) as total_charge"),
                DB::raw("AVG({$e}) as avg_charge"),
            ])
            ->groupBy('user_login', 'user_remote_id')
            ->orderByDesc('total_charge')
            ->paginate(20, ['*'], 'user_page_' . $tab)
            ->withQueryString();
    }

    private function getByService(array $filters, string $tab)
    {
        $e = self::CHARGE_EXPR;

        return $this->baseQuery($filters, $tab)
            ->select([
                'remote_service_id',
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('SUM(COALESCE(quantity, 0)) as total_quantity'),
                DB::raw('SUM(COALESCE(remains, 0)) as total_remains'),
                DB::raw("SUM(COALESCE(quantity, 0)) - SUM(COALESCE(remains, 0)) as total_difference"),
                DB::raw("SUM({$e}) as total_charge"),
            ])
            ->groupBy('remote_service_id')
            ->orderByDesc('total_charge')
            ->paginate(20, ['*'], 'service_page_' . $tab)
            ->withQueryString();
    }

    private function getByUserByService(array $filters, string $tab)
    {
        $e = self::CHARGE_EXPR;

        return $this->baseQuery($filters, $tab)
            ->select([
                'user_login',
                'user_remote_id',
                'remote_service_id',
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('SUM(COALESCE(quantity, 0)) as total_quantity'),
                DB::raw('SUM(COALESCE(remains, 0)) as total_remains'),
                DB::raw("SUM(COALESCE(quantity, 0)) - SUM(COALESCE(remains, 0)) as total_difference"),
                DB::raw("SUM({$e}) as total_charge"),
            ])
            ->groupBy('user_login', 'user_remote_id', 'remote_service_id')
            ->orderByDesc('total_charge')
            ->paginate(50, ['*'], 'breakdown_page_' . $tab)
            ->withQueryString();
    }

    // =========================================================================
    //  Dropdown options — cached
    // =========================================================================

    private function getDropdownOptions(): array
    {
        $serviceNames = Cache::remember('prov_stats:service_names', self::OPTIONS_CACHE_TTL, fn () =>
            DB::table('provider_services')
                ->whereNotNull('remote_service_id')
                ->select('remote_service_id', DB::raw('MIN(name) as name'))
                ->groupBy('remote_service_id')
                ->pluck('name', 'remote_service_id')
                ->all()
        );

        $distinctServiceIds = Cache::remember('prov_stats:service_ids', self::OPTIONS_CACHE_TTL, fn () =>
            DB::table('provider_orders')
                ->whereNotNull('remote_service_id')
                ->where('remote_service_id', '!=', '')
                ->distinct()
                ->pluck('remote_service_id', 'remote_service_id')
                ->all()
        );

        $distinctProviderCodes = Cache::remember('prov_stats:provider_codes', self::OPTIONS_CACHE_TTL, fn () =>
            DB::table('provider_orders')
                ->distinct()
                ->pluck('provider_code', 'provider_code')
                ->all()
        );

        return [$serviceNames, $distinctServiceIds, $distinctProviderCodes];
    }

    // =========================================================================
    //  Helpers
    // =========================================================================

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

        if (! empty($input['date_from'])) {
            $filters['date_from'] = \Carbon\Carbon::parse($input['date_from'])->startOfDay();
        }
        if (! empty($input['date_to'])) {
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

    private function statsCacheKey(array $filters, string $tab): string
    {
        $parts = [$tab];
        foreach (['date_from', 'date_to', 'provider_code', 'remote_service_id', 'user_login', 'user_remote_id'] as $key) {
            $val = $filters[$key] ?? '';
            $parts[] = $val instanceof \DateTimeInterface ? $val->format('Y-m-d') : (string) $val;
        }

        return 'prov_stats:totals:' . md5(implode('|', $parts));
    }

    private function exportFilename(array $validated): string
    {
        $base = 'provider-order-stats';
        $from = isset($validated['date_from']) ? \Carbon\Carbon::parse($validated['date_from'])->format('Y-m-d') : null;
        $to = isset($validated['date_to']) ? \Carbon\Carbon::parse($validated['date_to'])->format('Y-m-d') : null;

        if ($from && $to) {
            return "{$base}-{$from}-to-{$to}.csv";
        }

        return "{$base}-" . ($from ?? $to ?? now()->format('Y-m-d-His')) . '.csv';
    }
}
