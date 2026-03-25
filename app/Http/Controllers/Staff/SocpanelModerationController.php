<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\ProviderOrder;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SocpanelModerationController extends Controller
{
    private const SORTABLE_COLUMNS = [
        'id',
        'provider_code',
        'remote_order_id',
        'remote_service_id',
        'status',
        'charge',
        'remains',
        'fetched_at',
        'updated_at',
        'created_at',
    ];

    public function index(Request $request): View
    {
        $sort = $request->input('sort', 'id');
        $dir = strtolower($request->input('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        if (! in_array($sort, self::SORTABLE_COLUMNS, true)) {
            $sort = 'id';
        }

        $query = ProviderOrder::query()
            ->select([
                'id',
                'provider_code',
                'remote_order_id',
                'remote_service_id',
                'status',
                'remote_status',
                'charge',
                'remains',
                'link',
                'user_login',
                'user_remote_id',
                'fetched_at',
                'created_at',
                'updated_at',
                'provider_last_error',
            ])
            ->orderBy($sort, $dir);

        if ($request->filled('provider_code')) {
            $providerCode = trim((string) $request->provider_code);

            if ($providerCode !== '') {
                $query->where('provider_code', $providerCode);
            }
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

        if ($request->filled('service_id')) {
            $serviceId = trim((string) $request->service_id);

            if ($serviceId !== '') {
                $query->where('remote_service_id', $serviceId);
            }
        }

        if ($request->filled('user_login')) {
            $userLogin = trim((string) $request->user_login);

            if ($userLogin !== '') {
                $query->where('user_login', 'like', '%' . $userLogin . '%');
            }
        }

        if ($request->filled('user_remote_id')) {
            $userRemoteId = trim((string) $request->user_remote_id);

            if ($userRemoteId !== '') {
                $query->where('user_remote_id', $userRemoteId);
            }
        }

        if ($request->filled('date_from')) {
            try {
                $dateFrom = Carbon::parse($request->date_from)->startOfDay();
                $query->where('created_at', '>=', $dateFrom);
            } catch (\Throwable) {
                // ignore invalid date
            }
        }

        if ($request->filled('date_to')) {
            try {
                $dateTo = Carbon::parse($request->date_to)->endOfDay();
                $query->where('created_at', '<=', $dateTo);
            } catch (\Throwable) {
                // ignore invalid date
            }
        }

        if ($request->filled('search')) {
            $searchInput = trim((string) preg_replace('/\s+/', ' ', (string) $request->search));

            if ($searchInput !== '') {
                $tokens = array_filter(
                    preg_split('/[\s,]+/', $searchInput, -1, PREG_SPLIT_NO_EMPTY)
                );

                $ids = array_values(array_unique(array_filter(
                    $tokens,
                    static fn ($token) => ctype_digit((string) $token)
                )));

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

        $orders = $query->paginate(50)->withQueryString();

        $serviceIds = Cache::remember('staff.socpanel_moderation.service_ids', 600, function () {
            return ProviderOrder::query()
                ->select('remote_service_id')
                ->whereNotNull('remote_service_id')
                ->where('remote_service_id', '!=', '')
                ->distinct()
                ->orderBy('remote_service_id')
                ->pluck('remote_service_id', 'remote_service_id')
                ->all();
        });

        $remoteStatuses = Cache::remember('staff.socpanel_moderation.remote_statuses', 600, function () {
            return ProviderOrder::query()
                ->select('remote_status')
                ->whereNotNull('remote_status')
                ->where('remote_status', '!=', '')
                ->distinct()
                ->orderBy('remote_status')
                ->pluck('remote_status', 'remote_status')
                ->all();
        });

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
