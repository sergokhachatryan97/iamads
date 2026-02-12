<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\ProviderOrder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Socpanel moderation: list provider orders (super admin only).
 */
class SocpanelModerationController extends Controller
{
    private const SORTABLE_COLUMNS = [
        'id', 'provider_code', 'remote_order_id', 'remote_service_id',
        'status', 'charge', 'remains', 'fetched_at', 'updated_at',
    ];

    public function index(Request $request): View
    {
        $sort = $request->input('sort', 'id');
        $dir = strtolower($request->input('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        if (!in_array($sort, self::SORTABLE_COLUMNS, true)) {
            $sort = 'id';
        }

        $query = ProviderOrder::query()->orderBy($sort, $dir);

        if ($request->filled('provider_code')) {
            $query->where('provider_code', $request->provider_code);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('remote_status')) {
            if ($request->remote_status === '__null__') {
                $query->whereNull('remote_status');
            } else {
                $query->where('remote_status', $request->remote_status);
            }
        }

        if ($request->filled('service_id') && $request->service_id !== '') {
            $query->where('remote_service_id', $request->service_id);
        }

        if ($request->filled('user_login')) {
            $term = trim($request->user_login);
            if ($term !== '') {
                $query->where('user_login', 'like', '%' . $term . '%');
            }
        }

        if ($request->filled('user_remote_id')) {
            $term = trim($request->user_remote_id);
            if ($term !== '') {
                $query->where('user_remote_id', $term);
            }
        }

        if ($request->filled('date_from')) {
            try {
                $dateFrom = \Carbon\Carbon::parse($request->date_from)->startOfDay();
                $query->where('created_at', '>=', $dateFrom);
            } catch (\Throwable) {
                // ignore invalid date
            }
        }
        if ($request->filled('date_to')) {
            try {
                $dateTo = \Carbon\Carbon::parse($request->date_to)->endOfDay();
                $query->where('created_at', '<=', $dateTo);
            } catch (\Throwable) {
                // ignore invalid date
            }
        }

        if ($request->filled('search')) {
            $searchInput = trim(preg_replace('/\s+/', ' ', $request->search));
            $tokens = array_filter(preg_split('/[\s,]+/', $searchInput, -1, PREG_SPLIT_NO_EMPTY));
            $ids = array_unique(array_values(array_filter($tokens, fn ($t) => is_numeric($t) && (string)(int)$t === (string)$t)));

            if (count($ids) > 0) {
                $query->whereIn('remote_order_id', $ids);
            } else {
                $term = '%' . $searchInput . '%';
                $query->where(function ($q) use ($term) {
                    $q->where('link', 'like', $term)
                        ->orWhere('remote_order_id', 'like', $term)
                        ->orWhere('remote_service_id', 'like', $term)
                        ->orWhere('user_login', 'like', $term)
                        ->orWhere('provider_last_error', 'like', $term);
                });
            }
        }

        $orders = $query->paginate(50)->withQueryString();

        $serviceIds = ProviderOrder::query()
            ->select('remote_service_id')
            ->whereNotNull('remote_service_id')
            ->where('remote_service_id', '!=', '')
            ->distinct()
            ->orderBy('remote_service_id')
            ->pluck('remote_service_id', 'remote_service_id')
            ->all();

        $remoteStatuses = ProviderOrder::query()
            ->select('remote_status')
            ->whereNotNull('remote_status')
            ->where('remote_status', '!=', '')
            ->distinct()
            ->orderBy('remote_status')
            ->pluck('remote_status', 'remote_status')
            ->all();

        return view('staff.socpanel-moderation.index', [
            'orders' => $orders,
            'statuses' => $this->statuses(),
            'remoteStatuses' => $remoteStatuses,
            'serviceIds' => $serviceIds,
            'sort' => $sort,
            'dir' => $dir,
        ]);
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
