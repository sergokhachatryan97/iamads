<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\OrderStatsRequest;
use App\Models\Client;
use App\Models\Order;
use App\Models\Service;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderStatsController extends Controller
{
    /**
     * Base query with staff client filter and filters applied.
     */
    private function baseQuery(OrderStatsRequest $request, array $filters)
    {
        $user = Auth::guard('staff')->user();
        $isSuperAdmin = $user->hasRole('super_admin');

        $query = Order::query()->filter($filters);

        if (!$isSuperAdmin) {
            $query->whereHas('client', function ($q) use ($user) {
                $q->where('staff_id', $user->id);
            });
        }

        return $query;
    }

    public function index(OrderStatsRequest $request): View
    {
        $filters = $this->normalizeFilters($request);

        $baseQuery = $this->baseQuery($request, $filters);
        $baseCompleted = (clone $baseQuery)->where('status', Order::STATUS_COMPLETED);
        $baseFailed = (clone $baseQuery)->where(function ($q) {
            $q->where('status', Order::STATUS_FAIL)
                ->orWhereNotNull('provider_last_error');
        });
        $baseWithoutFailed = (clone $baseQuery)
            ->where('status', '!=', Order::STATUS_FAIL)
            ->where(function ($q) {
                $q->whereNull('provider_last_error')->orWhere('provider_last_error', '');
            });

        // Totals for "All" tab
        $totalsAll = (clone $baseQuery)->selectRaw(
            'COUNT(*) as orders_count, ' .
            'COALESCE(SUM(COALESCE(quantity, 0)), 0) as total_quantity, ' .
            'COALESCE(SUM(COALESCE(remains, 0)), 0) as total_remains, ' .
            'COALESCE(SUM(COALESCE(delivered, 0)), 0) as total_delivered, ' .
            'COALESCE(SUM(CAST(charge AS DECIMAL(20,4))), 0) as total_charge'
        )->first();

        $totalsCompleted = (clone $baseCompleted)->selectRaw(
            'COUNT(*) as orders_count, ' .
            'COALESCE(SUM(COALESCE(quantity, 0)), 0) as total_quantity, ' .
            'COALESCE(SUM(COALESCE(remains, 0)), 0) as total_remains, ' .
            'COALESCE(SUM(COALESCE(delivered, 0)), 0) as total_delivered, ' .
            'COALESCE(SUM(CAST(charge AS DECIMAL(20,4))), 0) as total_charge'
        )->first();

        $totalsFailed = (clone $baseFailed)->selectRaw(
            'COUNT(*) as orders_count, ' .
            'COALESCE(SUM(COALESCE(quantity, 0)), 0) as total_quantity, ' .
            'COALESCE(SUM(COALESCE(remains, 0)), 0) as total_remains, ' .
            'COALESCE(SUM(COALESCE(delivered, 0)), 0) as total_delivered, ' .
            'COALESCE(SUM(CAST(charge AS DECIMAL(20,4))), 0) as total_charge'
        )->first();

        $totalsWithoutFailed = (clone $baseWithoutFailed)->selectRaw(
            'COUNT(*) as orders_count, ' .
            'COALESCE(SUM(COALESCE(quantity, 0)), 0) as total_quantity, ' .
            'COALESCE(SUM(COALESCE(remains, 0)), 0) as total_remains, ' .
            'COALESCE(SUM(COALESCE(delivered, 0)), 0) as total_delivered, ' .
            'COALESCE(SUM(CAST(charge AS DECIMAL(20,4))), 0) as total_charge'
        )->first();

        $selectClient = [
            'client_id',
            DB::raw('COUNT(*) as orders_count'),
            DB::raw('COALESCE(SUM(COALESCE(quantity, 0)), 0) as total_quantity'),
            DB::raw('COALESCE(SUM(COALESCE(remains, 0)), 0) as total_remains'),
            DB::raw('COALESCE(SUM(COALESCE(delivered, 0)), 0) as total_delivered'),
            DB::raw('COALESCE(SUM(CAST(charge AS DECIMAL(20,4))), 0) as total_charge'),
        ];
        $selectService = [
            'service_id',
            DB::raw('COUNT(*) as orders_count'),
            DB::raw('COALESCE(SUM(COALESCE(quantity, 0)), 0) as total_quantity'),
            DB::raw('COALESCE(SUM(COALESCE(remains, 0)), 0) as total_remains'),
            DB::raw('COALESCE(SUM(COALESCE(delivered, 0)), 0) as total_delivered'),
            DB::raw('COALESCE(SUM(CAST(charge AS DECIMAL(20,4))), 0) as total_charge'),
        ];

        $selectClientService = [
            'client_id',
            'service_id',
            DB::raw('COUNT(*) as orders_count'),
            DB::raw('COALESCE(SUM(COALESCE(quantity, 0)), 0) as total_quantity'),
            DB::raw('COALESCE(SUM(COALESCE(remains, 0)), 0) as total_remains'),
            DB::raw('COALESCE(SUM(COALESCE(delivered, 0)), 0) as total_delivered'),
            DB::raw('COALESCE(SUM(CAST(charge AS DECIMAL(20,4))), 0) as total_charge'),
        ];

        // By client / By service per tab
        $byClient = (clone $baseQuery)->select($selectClient)->groupBy('client_id')->orderByDesc('total_charge')
            ->paginate(20, ['*'], 'client_page')->withQueryString();
        $byService = (clone $baseQuery)->select($selectService)->groupBy('service_id')->orderByDesc('total_charge')
            ->paginate(20, ['*'], 'service_page')->withQueryString();
        $byClientByService = (clone $baseQuery)->select($selectClientService)
            ->groupBy('client_id', 'service_id')->orderByDesc('total_charge')->get();

        $byClientCompleted = (clone $baseCompleted)->select($selectClient)->groupBy('client_id')->orderByDesc('total_charge')
            ->paginate(20, ['*'], 'client_page_c')->withQueryString();
        $byServiceCompleted = (clone $baseCompleted)->select($selectService)->groupBy('service_id')->orderByDesc('total_charge')
            ->paginate(20, ['*'], 'service_page_c')->withQueryString();
        $byClientByServiceCompleted = (clone $baseCompleted)->select($selectClientService)
            ->groupBy('client_id', 'service_id')->orderByDesc('total_charge')->get();

        $byClientFailed = (clone $baseFailed)->select($selectClient)->groupBy('client_id')->orderByDesc('total_charge')
            ->paginate(20, ['*'], 'client_page_f')->withQueryString();
        $byServiceFailed = (clone $baseFailed)->select($selectService)->groupBy('service_id')->orderByDesc('total_charge')
            ->paginate(20, ['*'], 'service_page_f')->withQueryString();
        $byClientByServiceFailed = (clone $baseFailed)->select($selectClientService)
            ->groupBy('client_id', 'service_id')->orderByDesc('total_charge')->get();

        // Error statistics: orders with provider_last_error, grouped by error
        $byError = (clone $baseQuery)
            ->whereNotNull('provider_last_error')
            ->where('provider_last_error', '!=', '')
            ->select([
                'provider_last_error',
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('COALESCE(SUM(CAST(charge AS DECIMAL(20,4))), 0) as total_charge'),
            ])
            ->groupBy('provider_last_error')
            ->orderByDesc('orders_count')
            ->limit(50)
            ->get();

        $clientIds = collect()
            ->merge($byClient->pluck('client_id'))
            ->merge($byClientCompleted->pluck('client_id'))
            ->merge($byClientFailed->pluck('client_id'))
            ->merge($byClientByService->pluck('client_id'))
            ->unique()->filter()->all();
        $serviceIds = collect()
            ->merge($byService->pluck('service_id'))
            ->merge($byServiceCompleted->pluck('service_id'))
            ->merge($byServiceFailed->pluck('service_id'))
            ->merge($byClientByService->pluck('service_id'))
            ->unique()->filter()->all();

        $clients = Client::query()->whereIn('id', $clientIds)->get()->keyBy('id');
        $services = Service::query()->whereIn('id', $serviceIds)->get()->keyBy('id');

        $statuses = [
            'all' => __('All Statuses'),
            Order::STATUS_COMPLETED => __('Completed'),
            Order::STATUS_INVALID_LINK => __('Invalid Link'),
            Order::STATUS_IN_PROGRESS => __('In Progress'),
            Order::STATUS_PROCESSING => __('Processing'),
            Order::STATUS_PARTIAL => __('Partial'),
            Order::STATUS_PENDING => __('Pending'),
            Order::STATUS_AWAITING => __('Awaiting'),
            Order::STATUS_CANCELED => __('Canceled'),
        ];

        $allServices = Service::query()->orderBy('name')->get(['id', 'name']);

        // Chart data: top clients, services, errors by charge/count
        $chartTopClients = (clone $baseQuery)
            ->select(['client_id', DB::raw('COALESCE(SUM(CAST(charge AS DECIMAL(20,4))), 0) as total_charge')])
            ->groupBy('client_id')
            ->orderByDesc('total_charge')
            ->limit(10)
            ->get();
        $chartTopServices = (clone $baseQuery)
            ->select(['service_id', DB::raw('COALESCE(SUM(CAST(charge AS DECIMAL(20,4))), 0) as total_charge')])
            ->groupBy('service_id')
            ->orderByDesc('total_charge')
            ->limit(10)
            ->get();
        $chartTopErrors = (clone $baseQuery)
            ->whereNotNull('provider_last_error')
            ->where('provider_last_error', '!=', '')
            ->select(['provider_last_error', DB::raw('COUNT(*) as orders_count')])
            ->groupBy('provider_last_error')
            ->orderByDesc('orders_count')
            ->limit(10)
            ->get();

        $chartClientIds = $chartTopClients->pluck('client_id')->all();
        $chartServiceIds = $chartTopServices->pluck('service_id')->all();
        $chartClients = Client::query()->whereIn('id', $chartClientIds)->get()->keyBy('id');
        $chartServices = Service::query()->whereIn('id', $chartServiceIds)->get()->keyBy('id');

        $chartTopClientsData = $chartTopClients->map(function ($r) use ($chartClients) {
            $c = $chartClients[$r->client_id] ?? null;
            return [
                'label' => $c ? ($c->email ?? $c->name ?? 'client#' . $r->client_id) : 'client#' . $r->client_id,
                'total_charge' => (float) $r->total_charge,
            ];
        })->all();
        $chartTopServicesData = $chartTopServices->map(function ($r) use ($chartServices) {
            $s = $chartServices[$r->service_id] ?? null;
            return [
                'label' => $s ? $s->name : ('service#' . $r->service_id),
                'total_charge' => (float) $r->total_charge,
            ];
        })->all();
        $chartTopErrorsData = $chartTopErrors->map(fn ($r) => [
            'label' => \Illuminate\Support\Str::limit($r->provider_last_error, 40),
            'orders_count' => (int) $r->orders_count,
        ])->all();

        // Time-series data for area/line charts (SQLite + MySQL compatible)
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;
        $hoursDiff = $dateFrom && $dateTo ? $dateFrom->diffInHours($dateTo) : 24;
        $periodFormat = $this->getPeriodFormat($hoursDiff <= 48);

        $chartOrdersOverTime = (clone $baseQuery)
            ->selectRaw("{$periodFormat} as period, COUNT(*) as cnt")
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->period => (int) $r->cnt])
            ->all();

        $chartCompletedOverTime = (clone $baseCompleted)
            ->selectRaw("{$periodFormat} as period, COUNT(*) as cnt")
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->period => (int) $r->cnt])
            ->all();

        // Orders by service over time (top 6 services)
        $topServiceIdsForChart = $chartTopServices->pluck('service_id')->take(6)->all();
        $chartOrdersByServiceOverTime = [];
        if (!empty($topServiceIdsForChart)) {
            $byServiceTime = (clone $baseQuery)
                ->whereIn('service_id', $topServiceIdsForChart)
                ->selectRaw("{$periodFormat} as period, service_id, COUNT(*) as cnt")
                ->groupBy('period', 'service_id')
                ->orderBy('period')
                ->get();
            foreach ($topServiceIdsForChart as $sid) {
                $svc = $chartServices[$sid] ?? null;
                $chartOrdersByServiceOverTime[] = [
                    'label' => $svc ? $svc->name : ('service#' . $sid),
                    'data' => $byServiceTime->where('service_id', $sid)->mapWithKeys(fn ($r) => [$r->period => (int) $r->cnt])->all(),
                ];
            }
        }

        // Errors over time (top 6 error types)
        $topErrorsForChart = $chartTopErrors->take(6);
        $chartErrorsOverTime = [];
        foreach ($topErrorsForChart as $err) {
            $chartErrorsOverTime[] = [
                'label' => \Illuminate\Support\Str::limit($err->provider_last_error, 30),
                'error' => $err->provider_last_error,
            ];
        }
        if (!empty($chartErrorsOverTime)) {
            $errorMessages = array_column($chartErrorsOverTime, 'error');
            $byErrorTime = (clone $baseQuery)
                ->whereNotNull('provider_last_error')
                ->whereIn('provider_last_error', $errorMessages)
                ->selectRaw("{$periodFormat} as period, provider_last_error, COUNT(*) as cnt")
                ->groupBy('period', 'provider_last_error')
                ->orderBy('period')
                ->get();
            foreach ($chartErrorsOverTime as $i => $row) {
                $chartErrorsOverTime[$i]['data'] = $byErrorTime->where('provider_last_error', $row['error'])
                    ->mapWithKeys(fn ($r) => [$r->period => (int) $r->cnt])->all();
            }
        }

        // Error pie chart: label, value, percent
        $totalErrors = $byError->sum('orders_count');
        $chartErrorPieData = $byError->take(15)->map(function ($r) use ($totalErrors) {
            $pct = $totalErrors > 0 ? round(100 * $r->orders_count / $totalErrors, 1) : 0;
            return [
                'label' => \Illuminate\Support\Str::limit($r->provider_last_error),
                'value' => (int) $r->orders_count,
                'percent' => $pct,
            ];
        })->all();

        $allPeriods = array_unique(array_merge(
            array_keys($chartOrdersOverTime),
            array_keys($chartCompletedOverTime)
        ));
        sort($allPeriods);

        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;

        return view('staff.order-stats.index', [
            'totalsAll' => $totalsAll,
            'totalsCompleted' => $totalsCompleted,
            'totalsFailed' => $totalsFailed,
            'totalsWithoutFailed' => $totalsWithoutFailed,
            'byClient' => $byClient,
            'byService' => $byService,
            'byClientCompleted' => $byClientCompleted,
            'byServiceCompleted' => $byServiceCompleted,
            'byClientFailed' => $byClientFailed,
            'byServiceFailed' => $byServiceFailed,
            'byClientByService' => $byClientByService,
            'byClientByServiceCompleted' => $byClientByServiceCompleted,
            'byClientByServiceFailed' => $byClientByServiceFailed,
            'byError' => $byError,
            'clients' => $clients,
            'services' => $services,
            'statuses' => $statuses,
            'allServices' => $allServices,
            'filters' => $filters,
            'chartTopClientsData' => $chartTopClientsData,
            'chartTopServicesData' => $chartTopServicesData,
            'chartTopErrorsData' => $chartTopErrorsData,
            'chartOrdersOverTime' => $chartOrdersOverTime,
            'chartCompletedOverTime' => $chartCompletedOverTime,
            'chartOrdersByServiceOverTime' => $chartOrdersByServiceOverTime,
            'chartErrorsOverTime' => $chartErrorsOverTime,
            'chartErrorPieData' => $chartErrorPieData,
            'allPeriods' => $allPeriods,
            'inputDateFrom' => $request->filled('date_from') ? $request->date_from : ($dateFrom ? $dateFrom->format('Y-m-d') : ''),
            'inputDateTo' => $request->filled('date_to') ? $request->date_to : ($dateTo ? $dateTo->format('Y-m-d') : ''),
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', 'string', 'max:50'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'tab' => ['nullable', 'string', 'in:all,completed,failed'],
        ]);
        $filters = $this->filtersFromArray($validated);
        $tab = $validated['tab'] ?? 'all';

        $baseQuery = Order::query()->filter($filters);
        $user = Auth::guard('staff')->user();
        $isSuperAdmin = $user->hasRole('super_admin');
        if (!$isSuperAdmin) {
            $baseQuery->whereHas('client', function ($q) use ($user) {
                $q->where('staff_id', $user->id);
            });
        }

        if ($tab === 'completed') {
            $baseQuery->where('status', Order::STATUS_COMPLETED);
        } elseif ($tab === 'failed') {
            $baseQuery->where(function ($q) {
                $q->where('status', Order::STATUS_FAIL)->orWhereNotNull('provider_last_error');
            });
        }

        $totals = (clone $baseQuery)->selectRaw(
            'COUNT(*) as orders_count, ' .
            'COALESCE(SUM(COALESCE(quantity, 0)), 0) as total_quantity, ' .
            'COALESCE(SUM(COALESCE(remains, 0)), 0) as total_remains, ' .
            'COALESCE(SUM(COALESCE(delivered, 0)), 0) as total_delivered, ' .
            'COALESCE(SUM(CAST(charge AS DECIMAL(20,4))), 0) as total_charge'
        )->first();

        $clientIds = (clone $baseQuery)->pluck('client_id')->unique()->filter()->all();
        $serviceIds = (clone $baseQuery)->pluck('service_id')->unique()->filter()->all();
        $clients = Client::query()->whereIn('id', $clientIds)->get()->keyBy('id');
        $services = Service::query()->whereIn('id', $serviceIds)->get()->keyBy('id');

        return response()->streamDownload(function () use ($baseQuery, $totals, $clients, $services) {
            $out = fopen('php://output', 'w');
            fprintf($out, "\xEF\xBB\xBF");

            $rows = (clone $baseQuery)
                ->select([
                    'client_id',
                    'service_id',
                    DB::raw('COUNT(*) as orders_count'),
                    DB::raw('COALESCE(SUM(COALESCE(quantity, 0)), 0) as total_quantity'),
                    DB::raw('COALESCE(SUM(COALESCE(remains, 0)), 0) as total_remains'),
                    DB::raw('COALESCE(SUM(COALESCE(delivered, 0)), 0) as total_delivered'),
                    DB::raw('COALESCE(SUM(CAST(charge AS DECIMAL(20,4))), 0) as total_charge'),
                ])
                ->groupBy('client_id', 'service_id')
                ->orderByDesc('total_charge')
                ->get();

            fputcsv($out, ['Client', 'Service', 'Orders', 'Quantity', 'Remains', 'Delivered', 'Total charge']);
            foreach ($rows as $row) {
                $client = $clients[$row->client_id] ?? null;
                $service = $services[$row->service_id] ?? null;
                fputcsv($out, [
                    $client ? ($client->email ?? $client->name ?? 'client#' . $row->client_id) : ('client#' . $row->client_id),
                    $service ? $service->name : ('service#' . $row->service_id),
                    $row->orders_count,
                    $row->total_quantity,
                    $row->total_remains,
                    $row->total_delivered,
                    $row->total_charge,
                ]);
            }
            fputcsv($out, []);
            fputcsv($out, ['TOTAL', '', $totals->orders_count ?? 0, $totals->total_quantity ?? 0, $totals->total_remains ?? 0, $totals->total_delivered ?? 0, $totals->total_charge ?? 0]);

            fclose($out);
        }, $filename = $this->exportFilename($validated), [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function exportFilename(array $validated): string
    {
        $base = 'order-stats';
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

    /**
     * Get period format SQL for time-series grouping (SQLite + MySQL).
     */
    private function getPeriodFormat(bool $byHour): string
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            return $byHour ? "strftime('%Y-%m-%d %H:00', created_at)" : "date(created_at)";
        }
        if ($driver === 'pgsql') {
            return $byHour ? "TO_CHAR(created_at, 'YYYY-MM-DD HH24:00')" : "DATE(created_at)";
        }
        return $byHour ? "DATE_FORMAT(created_at, '%Y-%m-%d %H:00')" : "DATE(created_at)";
    }

    private function normalizeFilters(OrderStatsRequest $request): array
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
        if (!empty($input['status']) && $input['status'] !== 'all') {
            $filters['status'] = $input['status'];
        }
        if (!empty($input['service_id'])) {
            $filters['service_id'] = (int) $input['service_id'];
        }
        if (!empty($input['client_id'])) {
            $filters['client_id'] = (int) $input['client_id'];
        }
        return $filters;
    }
}
