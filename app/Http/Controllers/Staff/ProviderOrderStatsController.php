<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\ProviderOrderStatsRequest;
use App\Models\ProviderOrder;
use App\Models\ProviderService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProviderOrderStatsController extends Controller
{
    public function index(ProviderOrderStatsRequest $request): View
    {
        $filters = $this->normalizeFilters($request);

        $baseQuery = ProviderOrder::query()->where('provider_code', '<>', 'socpanel')->completed()->filter($filters);
        $baseQueryWithoutFailed = ProviderOrder::query()->where('provider_code', '<>', 'socpanel')->withoutFailed()->filter($filters);
        $baseQueryPartial = ProviderOrder::query()->where('provider_code', '<>', 'socpanel')->partial()->filter($filters);

        $totalCharge = (float) (clone $baseQuery)->selectRaw('COALESCE(SUM(CAST(charge AS DECIMAL(20,4))), 0) as total')->value('total');
        $totalChargeWithoutFailed = (float) (clone $baseQueryWithoutFailed)->selectRaw('COALESCE(SUM(CAST(charge AS DECIMAL(20,4))), 0) as total')->value('total');
        $totalChargePartial = (float) (clone $baseQueryPartial)->selectRaw('COALESCE(SUM(CAST(charge AS DECIMAL(20,4))), 0) as total')->value('total');

        $totalsCompleted = (clone $baseQuery)->selectRaw('COALESCE(SUM(COALESCE(quantity, 0)), 0) as qty, COALESCE(SUM(COALESCE(remains, 0)), 0) as rem')->first();
        $totalQuantity = (int) ($totalsCompleted->qty ?? 0);
        $totalRemains = (int) ($totalsCompleted->rem ?? 0);
        $totalDifference = $totalQuantity - $totalRemains;

        $totalsWithoutFailed = (clone $baseQueryWithoutFailed)->selectRaw('COALESCE(SUM(COALESCE(quantity, 0)), 0) as qty, COALESCE(SUM(COALESCE(remains, 0)), 0) as rem')->first();
        $totalQuantityWithoutFailed = (int) ($totalsWithoutFailed->qty ?? 0);
        $totalRemainsWithoutFailed = (int) ($totalsWithoutFailed->rem ?? 0);
        $totalDifferenceWithoutFailed = $totalQuantityWithoutFailed - $totalRemainsWithoutFailed;

        $totalsPartial = (clone $baseQueryPartial)->selectRaw('COALESCE(SUM(COALESCE(quantity, 0)), 0) as qty, COALESCE(SUM(COALESCE(remains, 0)), 0) as rem')->first();
        $totalQuantityPartial = (int) ($totalsPartial->qty ?? 0);
        $totalRemainsPartial = (int) ($totalsPartial->rem ?? 0);
        $totalDifferencePartial = $totalQuantityPartial - $totalRemainsPartial;

        $byUser = (clone $baseQuery)
            ->select([
                'user_login',
                'user_remote_id',
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('COALESCE(SUM(COALESCE(quantity, 0)), 0) as total_quantity'),
                DB::raw('COALESCE(SUM(COALESCE(remains, 0)), 0) as total_remains'),
                DB::raw('COALESCE(SUM(COALESCE(quantity, 0)), 0) - COALESCE(SUM(COALESCE(remains, 0)), 0) as total_difference'),
                DB::raw('COALESCE(SUM(CAST(charge AS DECIMAL(20,4))), 0) as total_charge'),
                DB::raw('COALESCE(AVG(CAST(charge AS DECIMAL(20,4))), 0) as avg_charge'),
            ])
            ->groupBy('user_login', 'user_remote_id')
            ->orderByDesc('total_charge')
            ->paginate(20, ['*'], 'user_page')
            ->withQueryString();

        $byUserByService = (clone $baseQuery)
            ->select([
                'user_login',
                'user_remote_id',
                'remote_service_id',
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('COALESCE(SUM(COALESCE(quantity, 0)), 0) as total_quantity'),
                DB::raw('COALESCE(SUM(COALESCE(remains, 0)), 0) as total_remains'),
                DB::raw('COALESCE(SUM(COALESCE(quantity, 0)), 0) - COALESCE(SUM(COALESCE(remains, 0)), 0) as total_difference'),
                DB::raw('COALESCE(SUM(CAST(charge AS DECIMAL(20,4))), 0) as total_charge'),
            ])
            ->groupBy('user_login', 'user_remote_id', 'remote_service_id')
            ->orderByDesc('total_charge')
            ->get();

        $byService = (clone $baseQuery)
            ->select([
                'remote_service_id',
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('COALESCE(SUM(COALESCE(quantity, 0)), 0) as total_quantity'),
                DB::raw('COALESCE(SUM(COALESCE(remains, 0)), 0) as total_remains'),
                DB::raw('COALESCE(SUM(COALESCE(quantity, 0)), 0) - COALESCE(SUM(COALESCE(remains, 0)), 0) as total_difference'),
                DB::raw('COALESCE(SUM(CAST(charge AS DECIMAL(20,4))), 0) as total_charge'),
            ])
            ->groupBy('remote_service_id')
            ->orderByDesc('total_charge')
            ->paginate(20, ['*'], 'service_page')
            ->withQueryString();

        $byUserWithoutFailed = (clone $baseQueryWithoutFailed)
            ->select([
                'user_login',
                'user_remote_id',
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('COALESCE(SUM(COALESCE(quantity, 0)), 0) as total_quantity'),
                DB::raw('COALESCE(SUM(COALESCE(remains, 0)), 0) as total_remains'),
                DB::raw('COALESCE(SUM(COALESCE(quantity, 0)), 0) - COALESCE(SUM(COALESCE(remains, 0)), 0) as total_difference'),
                DB::raw('COALESCE(SUM(CAST(charge AS DECIMAL(20,4))), 0) as total_charge'),
                DB::raw('COALESCE(AVG(CAST(charge AS DECIMAL(20,4))), 0) as avg_charge'),
            ])
            ->groupBy('user_login', 'user_remote_id')
            ->orderByDesc('total_charge')
            ->paginate(20, ['*'], 'user_page_wf')
            ->withQueryString();

        $byServiceWithoutFailed = (clone $baseQueryWithoutFailed)
            ->select([
                'remote_service_id',
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('COALESCE(SUM(COALESCE(quantity, 0)), 0) as total_quantity'),
                DB::raw('COALESCE(SUM(COALESCE(remains, 0)), 0) as total_remains'),
                DB::raw('COALESCE(SUM(COALESCE(quantity, 0)), 0) - COALESCE(SUM(COALESCE(remains, 0)), 0) as total_difference'),
                DB::raw('COALESCE(SUM(CAST(charge AS DECIMAL(20,4))), 0) as total_charge'),
            ])
            ->groupBy('remote_service_id')
            ->orderByDesc('total_charge')
            ->paginate(20, ['*'], 'service_page_wf')
            ->withQueryString();

        $byUserByServiceWithoutFailed = (clone $baseQueryWithoutFailed)
            ->select([
                'user_login',
                'user_remote_id',
                'remote_service_id',
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('COALESCE(SUM(COALESCE(quantity, 0)), 0) as total_quantity'),
                DB::raw('COALESCE(SUM(COALESCE(remains, 0)), 0) as total_remains'),
                DB::raw('COALESCE(SUM(COALESCE(quantity, 0)), 0) - COALESCE(SUM(COALESCE(remains, 0)), 0) as total_difference'),
                DB::raw('COALESCE(SUM(CAST(charge AS DECIMAL(20,4))), 0) as total_charge'),
            ])
            ->groupBy('user_login', 'user_remote_id', 'remote_service_id')
            ->orderByDesc('total_charge')
            ->get();

        $byUserPartial = (clone $baseQueryPartial)
            ->select([
                'user_login',
                'user_remote_id',
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('COALESCE(SUM(COALESCE(quantity, 0)), 0) as total_quantity'),
                DB::raw('COALESCE(SUM(COALESCE(remains, 0)), 0) as total_remains'),
                DB::raw('COALESCE(SUM(COALESCE(quantity, 0)), 0) - COALESCE(SUM(COALESCE(remains, 0)), 0) as total_difference'),
                DB::raw('COALESCE(SUM(CAST(charge AS DECIMAL(20,4))), 0) as total_charge'),
                DB::raw('COALESCE(AVG(CAST(charge AS DECIMAL(20,4))), 0) as avg_charge'),
            ])
            ->groupBy('user_login', 'user_remote_id')
            ->orderByDesc('total_charge')
            ->paginate(20, ['*'], 'user_page_partial')
            ->withQueryString();

        $byServicePartial = (clone $baseQueryPartial)
            ->select([
                'remote_service_id',
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('COALESCE(SUM(COALESCE(quantity, 0)), 0) as total_quantity'),
                DB::raw('COALESCE(SUM(COALESCE(remains, 0)), 0) as total_remains'),
                DB::raw('COALESCE(SUM(COALESCE(quantity, 0)), 0) - COALESCE(SUM(COALESCE(remains, 0)), 0) as total_difference'),
                DB::raw('COALESCE(SUM(CAST(charge AS DECIMAL(20,4))), 0) as total_charge'),
            ])
            ->groupBy('remote_service_id')
            ->orderByDesc('total_charge')
            ->paginate(20, ['*'], 'service_page_partial')
            ->withQueryString();

        $byUserByServicePartial = (clone $baseQueryPartial)
            ->select([
                'user_login',
                'user_remote_id',
                'remote_service_id',
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('COALESCE(SUM(COALESCE(quantity, 0)), 0) as total_quantity'),
                DB::raw('COALESCE(SUM(COALESCE(remains, 0)), 0) as total_remains'),
                DB::raw('COALESCE(SUM(COALESCE(quantity, 0)), 0) - COALESCE(SUM(COALESCE(remains, 0)), 0) as total_difference'),
                DB::raw('COALESCE(SUM(CAST(charge AS DECIMAL(20,4))), 0) as total_charge'),
            ])
            ->groupBy('user_login', 'user_remote_id', 'remote_service_id')
            ->orderByDesc('total_charge')
            ->get();

        $serviceNames = ProviderService::query()
            ->select('remote_service_id', 'name')
            ->whereNotNull('remote_service_id')
            ->orderBy('remote_service_id')
            ->get()
            ->groupBy('remote_service_id')
            ->map(fn ($rows) => $rows->first()->name)
            ->all();

        $chartTopUsers = (clone $baseQuery)
            ->select([
                'user_login',
                'user_remote_id',
                DB::raw('COALESCE(SUM(CAST(charge AS DECIMAL(20,4))), 0) as total_charge'),
            ])
            ->groupBy('user_login', 'user_remote_id')
            ->orderByDesc('total_charge')
            ->limit(10)
            ->get();

        $chartTopServices = (clone $baseQuery)
            ->select([
                'remote_service_id',
                DB::raw('COALESCE(SUM(CAST(charge AS DECIMAL(20,4))), 0) as total_charge'),
            ])
            ->groupBy('remote_service_id')
            ->orderByDesc('total_charge')
            ->limit(10)
            ->get();

        $chartTopUsersData = $chartTopUsers->map(fn ($r) => [
            'label' => $r->user_login ?: (string) $r->user_remote_id ?: '—',
            'total_charge' => (float) $r->total_charge,
        ])->all();
        $chartTopServicesData = $chartTopServices->map(fn ($r) => [
            'label' => $r->remote_service_id ?? '—',
            'total_charge' => (float) $r->total_charge,
        ])->all();

        $distinctServiceIds = ProviderOrder::query()
            ->whereNotNull('remote_service_id')
            ->where('remote_service_id', '!=', '')
            ->distinct()
            ->orderBy('remote_service_id')
            ->pluck('remote_service_id', 'remote_service_id')
            ->all();

        $distinctProviderCodes = ProviderOrder::query()
            ->distinct()
            ->orderBy('provider_code')
            ->pluck('provider_code', 'provider_code')
            ->all();

        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;

        return view('staff.provider-order-stats.index', [
            'byUser' => $byUser,
            'byUserByService' => $byUserByService,
            'byService' => $byService,
            'byUserWithoutFailed' => $byUserWithoutFailed,
            'byServiceWithoutFailed' => $byServiceWithoutFailed,
            'byUserByServiceWithoutFailed' => $byUserByServiceWithoutFailed,
            'byUserPartial' => $byUserPartial,
            'byServicePartial' => $byServicePartial,
            'byUserByServicePartial' => $byUserByServicePartial,
            'serviceNames' => $serviceNames,
            'chartTopUsers' => $chartTopUsers,
            'chartTopServices' => $chartTopServices,
            'chartTopUsersData' => $chartTopUsersData,
            'chartTopServicesData' => $chartTopServicesData,
            'distinctServiceIds' => $distinctServiceIds,
            'distinctProviderCodes' => $distinctProviderCodes,
            'filters' => $filters,
            'totalCharge' => $totalCharge,
            'totalChargeWithoutFailed' => $totalChargeWithoutFailed,
            'totalChargePartial' => $totalChargePartial,
            'totalQuantity' => $totalQuantity,
            'totalRemains' => $totalRemains,
            'totalDifference' => $totalDifference,
            'totalQuantityWithoutFailed' => $totalQuantityWithoutFailed,
            'totalRemainsWithoutFailed' => $totalRemainsWithoutFailed,
            'totalDifferenceWithoutFailed' => $totalDifferenceWithoutFailed,
            'totalQuantityPartial' => $totalQuantityPartial,
            'totalRemainsPartial' => $totalRemainsPartial,
            'totalDifferencePartial' => $totalDifferencePartial,
            'inputDateFrom' => $request->filled('date_from') ? $request->date_from : ($dateFrom ? $dateFrom->format('Y-m-d') : ''),
            'inputDateTo' => $request->filled('date_to') ? $request->date_to : ($dateTo ? $dateTo->format('Y-m-d') : ''),
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
        $tab = $validated['tab'] ?? 'without_failed';

        $baseQuery = ProviderOrder::query()->where('provider_code', '<>', 'socpanel')->filter($filters);
        $baseQuery = match ($tab) {
            'completed' => $baseQuery->completed(),
            'partial' => $baseQuery->partial(),
            default => $baseQuery->withoutFailed(),
        };

        $totals = (clone $baseQuery)->selectRaw(
            'COALESCE(SUM(COALESCE(quantity, 0)), 0) as total_quantity, ' .
            'COALESCE(SUM(COALESCE(remains, 0)), 0) as total_remains, ' .
            'COALESCE(SUM(CAST(charge AS DECIMAL(20,4))), 0) as total_charge'
        )->first();
        $totalQuantity = (int) ($totals->total_quantity ?? 0);
        $totalRemains = (int) ($totals->total_remains ?? 0);
        $totalDifference = $totalQuantity - $totalRemains;
        $totalCharge = (float) ($totals->total_charge ?? 0);

        return response()->streamDownload(function () use ($baseQuery, $totalQuantity, $totalRemains, $totalDifference, $totalCharge) {
            $out = fopen('php://output', 'w');
            fprintf($out, "\xEF\xBB\xBF");

            $rows = (clone $baseQuery)
                ->select([
                    'user_login',
                    'user_remote_id',
                    'remote_service_id',
                    DB::raw('COUNT(*) as orders_count'),
                    DB::raw('COALESCE(SUM(COALESCE(quantity, 0)), 0) as total_quantity'),
                    DB::raw('COALESCE(SUM(COALESCE(remains, 0)), 0) as total_remains'),
                    DB::raw('COALESCE(SUM(COALESCE(quantity, 0)), 0) - COALESCE(SUM(COALESCE(remains, 0)), 0) as total_difference'),
                    DB::raw('COALESCE(SUM(CAST(charge AS DECIMAL(20,4))), 0) as total_charge'),
                ])
                ->groupBy('user_login', 'user_remote_id', 'remote_service_id')
                ->orderByDesc('total_charge')
                ->get();

            fputcsv($out, ['User', 'Service', 'Order', 'Quantity', 'Remains', 'Difference', 'Total']);
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
            fputcsv($out, []);
            fputcsv($out, ['TOTAL', '', '', $totalQuantity, $totalRemains, $totalDifference, $totalCharge]);

            fclose($out);
        }, $filename = $this->exportFilename($validated), [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function exportFilename(array $validated): string
    {
        $base = 'provider-order-stats';
        $dateFrom = isset($validated['date_from']) ? \Carbon\Carbon::parse($validated['date_from'])->format('Y-m-d') : null;
        $dateTo = isset($validated['date_to']) ? \Carbon\Carbon::parse($validated['date_to'])->format('Y-m-d') : null;
        if ($dateFrom && $dateTo) {
            return $base . '-' . $dateFrom . '-to-' . $dateTo . '.csv';
        }
        if ($dateFrom) {
            return $base . '-from-' . $dateFrom . '.csv';
        }
        if ($dateTo) {
            return $base . '-to-' . $dateTo . '.csv';
        }
        return $base . '-' . date('Y-m-d-His') . '.csv';
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
        if (!empty($input['provider_code'])) {
            $filters['provider_code'] = $input['provider_code'];
        }
        if (isset($input['remote_service_id']) && $input['remote_service_id'] !== '') {
            $filters['remote_service_id'] = $input['remote_service_id'];
        }
        if (!empty($input['user_login'])) {
            $filters['user_login'] = $input['user_login'];
        }
        if (!empty($input['user_remote_id'])) {
            $filters['user_remote_id'] = $input['user_remote_id'];
        }
        return $filters;
    }
}
