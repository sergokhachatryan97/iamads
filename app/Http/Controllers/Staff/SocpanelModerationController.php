<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SocpanelModerationController extends Controller
{
    private const SORTABLE_COLUMNS = [
        'id', 'provider_code', 'remote_order_id', 'remote_service_id',
        'status', 'charge', 'remains', 'fetched_at', 'updated_at', 'created_at',
    ];

    private const OPTIONS_CACHE_TTL = 600;

    public function index(Request $request): View
    {
        $sort = in_array($request->input('sort'), self::SORTABLE_COLUMNS, true)
            ? $request->input('sort')
            : 'id';
        $dir = strtolower($request->input('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = DB::table('provider_orders')
            ->select([
                'id', 'provider_code', 'remote_order_id', 'remote_service_id',
                'status', 'remote_status', 'charge', 'remains', 'link',
                'user_login', 'user_remote_id', 'fetched_at',
                'created_at', 'updated_at', 'provider_last_error',
            ])
            ->orderBy($sort, $dir);

        $this->applyFilters($query, $request);

        $orders = $query->paginate(50)->withQueryString();

        [$serviceIds, $remoteStatuses] = $this->getDropdownOptions();

        return view('staff.socpanel-moderation.index', [
            'orders' => $orders,
            'statuses' => $this->statuses(),
            'remoteStatuses' => $remoteStatuses,
            'serviceIds' => $serviceIds,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('provider_code') && trim($request->input('provider_code')) !== '') {
            $query->where('provider_code', trim($request->input('provider_code')));
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('remote_status')) {
            $val = $request->input('remote_status');
            $val === '__null__'
                ? $query->whereNull('remote_status')
                : $query->where('remote_status', $val);
        }

        if ($request->filled('service_id') && trim($request->input('service_id')) !== '') {
            $query->where('remote_service_id', trim($request->input('service_id')));
        }

        if ($request->filled('user_login') && trim($request->input('user_login')) !== '') {
            $query->where('user_login', 'like', '%' . trim($request->input('user_login')) . '%');
        }

        if ($request->filled('user_remote_id') && trim($request->input('user_remote_id')) !== '') {
            $query->where('user_remote_id', trim($request->input('user_remote_id')));
        }

        if ($request->filled('date_from')) {
            try {
                $query->where('created_at', '>=', Carbon::parse($request->input('date_from'))->startOfDay());
            } catch (\Throwable) {
            }
        }

        if ($request->filled('date_to')) {
            try {
                $query->where('created_at', '<=', Carbon::parse($request->input('date_to'))->endOfDay());
            } catch (\Throwable) {
            }
        }

        if ($request->filled('search')) {
            $searchInput = trim((string) preg_replace('/\s+/', ' ', $request->input('search')));

            if ($searchInput !== '') {
                $tokens = preg_split('/[\s,]+/', $searchInput, -1, PREG_SPLIT_NO_EMPTY);
                $ids = array_values(array_unique(array_filter($tokens, fn ($t) => ctype_digit((string) $t))));

                if (! empty($ids)) {
                    $query->whereIn('remote_order_id', $ids);
                } else {
                    $term = '%' . $searchInput . '%';
                    $query->where(function ($q) use ($term) {
                        $q->where('link', 'like', $term)
                            ->orWhere('remote_order_id', 'like', $term)
                            ->orWhere('remote_service_id', 'like', $term)
                            ->orWhere('user_login', 'like', $term)
                            ->orWhere('provider_code', 'like', $term);
                    });
                }
            }
        }
    }

    private function getDropdownOptions(): array
    {
        $serviceIds = Cache::remember('socpanel_mod:service_ids', self::OPTIONS_CACHE_TTL, fn () =>
            DB::table('provider_orders')
                ->whereNotNull('remote_service_id')
                ->where('remote_service_id', '!=', '')
                ->distinct()
                ->pluck('remote_service_id', 'remote_service_id')
                ->all()
        );

        $remoteStatuses = Cache::remember('socpanel_mod:remote_statuses', self::OPTIONS_CACHE_TTL, fn () =>
            DB::table('provider_orders')
                ->whereNotNull('remote_status')
                ->where('remote_status', '!=', '')
                ->distinct()
                ->pluck('remote_status', 'remote_status')
                ->all()
        );

        return [$serviceIds, $remoteStatuses];
    }

    private function statuses(): array
    {
        return [
            'all' => __('All'),
            'canceled' => __('Canceled'),
            'completed' => __('Completed'),
            'validating' => __('Validating'),
            'fail' => __('Fail'),
            'ok' => __('OK'),
            'failed' => __('Failed'),
        ];
    }
}
