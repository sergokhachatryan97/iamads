<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StoreSubscriptionPlanRequest;
use App\Http\Requests\Staff\UpdateSubscriptionPlanRequest;
use App\Models\SubscriptionPlan;
use App\Models\UiText;
use App\Services\CategoryServiceInterface;
use App\Services\ServiceServiceInterface;
use App\Services\SubscriptionPlanServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SubscriptionPlanController extends Controller
{
    public function __construct(
        private SubscriptionPlanServiceInterface $planService,
        private CategoryServiceInterface $categoryService,
        private ServiceServiceInterface $serviceService
    ) {}

    public function index(): View
    {
        return view('staff.subscriptions.index', [
            'plans' => $this->planService->getAllPlans(),
        ]);
    }

    public function create(): View
    {
        $categories = $this->categoryService->getAllCategories();
        $servicesByCategory = $this->buildServicesByCategory($categories->pluck('id')->all());

        // old selected services (if validation failed)
        $initialSelectedServices = $this->mapOldSelectedServices($servicesByCategory);

        return view('staff.subscriptions.form', [
            'mode' => 'create',
            'plan' => null,
            'categories' => $categories,
            'servicesByCategory' => $servicesByCategory,

            'initialCategoryId' => (string) old('category_id', ''),
            'monthlyPrice' => (string) old('prices.monthly.price', ''),
            'monthlyEnabled' => (bool) old('prices.monthly.enabled', true),
            'initialSelectedServices' => $initialSelectedServices,
            'monthlyPriceDb' => null, // optional
        ]);
    }

    public function edit(SubscriptionPlan $subscriptionPlan): View
    {
        $plan = $this->planService->getPlanById($subscriptionPlan->id);
        if (!$plan) abort(404);

        $categories = $this->categoryService->getAllCategories();
        $servicesByCategory = $this->buildServicesByCategory($categories->pluck('id')->all());

        $monthlyPriceDb = $plan->prices->where('billing_cycle', 'monthly')->first()?->price;
        $initialCategoryId = (string) old('category_id', (string) $plan->category_id);

        // selected services for edit: old() > db
        $initialSelectedServices = $this->mapSelectedServicesForEdit(
            servicesByCategory: $servicesByCategory,
            initialCategoryId: $initialCategoryId,
            plan: $plan
        );

        return view('staff.subscriptions.form', [
            'mode' => 'edit',
            'plan' => $plan,
            'categories' => $categories,
            'servicesByCategory' => $servicesByCategory,

            'initialCategoryId' => $initialCategoryId,
            'monthlyPrice' => (string) old('prices.monthly.price', $monthlyPriceDb ?? ''),
            'monthlyEnabled' => (bool) old('prices.monthly.enabled', $monthlyPriceDb !== null),
            'initialSelectedServices' => $initialSelectedServices,
            'monthlyPriceDb' => $monthlyPriceDb, // optional
        ]);
    }

    /**
     * Validation fail case (create/edit): build initialSelectedServices from old('services')
     */
    private function mapOldSelectedServices(array $servicesByCategory): array
    {
        $oldServices = old('services');
        if (!is_array($oldServices)) return [];

        // build lookup: serviceId => details (id,name,min,max)
        $lookup = [];
        foreach ($servicesByCategory as $catServices) {
            foreach ($catServices as $svc) {
                $lookup[(string) $svc['id']] = $svc;
            }
        }

        return collect($oldServices)->map(function ($row) use ($lookup) {
            $sid = (string) ($row['service_id'] ?? '');
            if (!$sid || !isset($lookup[$sid])) return null;

            $svc = $lookup[$sid];
            $min = (int) ($svc['min_quantity'] ?? 1);

            return [
                'id' => (int) $svc['id'],
                'name' => $svc['name'],
                'quantity' => (int) (($row['quantity'] ?? $min)),
                'min_quantity' => $min,
                'max_quantity' => $svc['max_quantity'] ?? null,
            ];
        })->filter()->values()->all();
    }

    /**
     * Edit: old('services') > DB planServices, with name/min/max from active services in selected category
     */
    private function mapSelectedServicesForEdit(array $servicesByCategory, string $initialCategoryId, $plan): array
    {
        $available = collect($servicesByCategory[$initialCategoryId] ?? []);
        $lookup = $available->keyBy(fn ($s) => (string) $s['id']);

        $oldServices = old('services');
        if (is_array($oldServices)) {
            return collect($oldServices)->map(function ($row) use ($lookup) {
                $sid = (string) ($row['service_id'] ?? '');
                $svc = $lookup->get($sid);
                if (!$sid || !$svc) return null;

                $min = (int) ($svc['min_quantity'] ?? 1);

                return [
                    'id' => (int) $svc['id'],
                    'name' => $svc['name'],
                    'quantity' => (int) (($row['quantity'] ?? $min)),
                    'min_quantity' => $min,
                    'max_quantity' => $svc['max_quantity'] ?? null,
                ];
            })->filter()->values()->all();
        }

        return $plan->planServices->map(function ($ps) use ($lookup) {
            $sid = (string) $ps->service_id;
            $svc = $lookup->get($sid);
            if (!$svc) return null;

            return [
                'id' => (int) $ps->service_id,
                'name' => $svc['name'],
                'quantity' => (int) $ps->quantity,
                'min_quantity' => (int) ($svc['min_quantity'] ?? 1),
                'max_quantity' => $svc['max_quantity'] ?? null,
            ];
        })->filter()->values()->all();
    }

    public function store(StoreSubscriptionPlanRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $data['prices'] = $this->transformPrices($data);
        $data['services'] = $this->transformServices($data);
        $data['is_active'] = $request->has('is_active') && $request->boolean('is_active');
        $data['preview_features'] = $this->cleanPreviewFeatures($data);

        $this->planService->createPlan($data);

        return redirect()
            ->route('staff.subscriptions.index')
            ->with('status', 'subscription-plan-created');
    }

    public function update(UpdateSubscriptionPlanRequest $request, SubscriptionPlan $subscriptionPlan): RedirectResponse
    {
        $data = $request->validated();

        $data['prices'] = $this->transformPrices($data);
        $data['services'] = $this->transformServices($data); // empty array means remove all
        $data['is_active'] = $request->has('is_active') && $request->boolean('is_active');
        $data['preview_features'] = $this->cleanPreviewFeatures($data);

        $this->planService->updatePlan($subscriptionPlan, $data);

        return redirect()
            ->route('staff.subscriptions.index')
            ->with('status', 'subscription-plan-updated');
    }

    public function destroy(SubscriptionPlan $subscriptionPlan): RedirectResponse
    {
        $this->planService->deletePlan($subscriptionPlan);

        return redirect()
            ->route('staff.subscriptions.index')
            ->with('status', 'subscription-plan-deleted');
    }

    public function getServicesByCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'target_type' => ['nullable', 'string', 'in:bot,channel,group'],
        ]);

        $categoryId = (int) $validated['category_id'];
        $targetType = $validated['target_type'] ?? null;

        if (!$categoryId) {
            return response()->json(['services' => []]);
        }

        $services = $this->serviceService
            ->getServicesByCategoryId($categoryId, true)
            ->when($targetType, function ($collection) use ($targetType) {
                return $collection->filter(function ($service) use ($targetType) {
                    return $service->target_type === $targetType;
                });
            })
            ->map(fn ($s) => $this->formatService($s))
            ->values();

        return response()->json(['services' => $services]);
    }

    public function editHeader(): View
    {
        return view('staff.subscriptions.edit-header', [
            'uiText' => UiText::where('key', 'client.subscriptions.header')->first(),
        ]);
    }

    public function updateHeader(Request $request): RedirectResponse
    {
        $request->validate([
            'value' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        UiText::updateOrCreate(
            ['key' => 'client.subscriptions.header'],
            [
                'value' => $request->input('value', ''),
                'is_active' => $request->has('is_active') && $request->boolean('is_active'),
            ]
        );

        return redirect()
            ->route('staff.subscriptions.index')
            ->with('status', 'client-header-text-updated');
    }

    /**
     * ✅ 1 query -> grouped by category_id.
     */
    private function buildServicesByCategory(array $categoryIds): array
    {
        $services = $this->serviceService->getActiveServicesByCategoryIds($categoryIds);

        $servicesByCategory = $services
            ->groupBy('category_id')
            ->mapWithKeys(function ($items, $categoryId) {
                return [
                    (string) $categoryId => $items->map(fn ($s) => $this->formatService($s))->values()->all(),
                ];
            })
            ->all();

        foreach ($categoryIds as $id) {
            $servicesByCategory[(string) $id] ??= [];
        }

        return $servicesByCategory;
    }

    private function formatService($service): array
    {
        return [
            'id' => $service->id,
            'name' => $service->name,
            'min_quantity' => (int) ($service->min_quantity ?? 1),
            'max_quantity' => $service->max_quantity ?? null,
        ];
    }

    private function transformPrices(array $data): array
    {
        $enabled = !empty($data['prices']['monthly']['enabled']);
        $price = $data['prices']['monthly']['price'] ?? null;

        if ($enabled && $price !== null && (float) $price > 0) {
            return [[
                'billing_cycle' => 'monthly',
                'price' => (float) $price,
            ]];
        }

        return [];
    }

    private function transformServices(array $data): array
    {
        $services = [];

        if (!isset($data['services']) || !is_array($data['services'])) {
            return $services;
        }

        foreach ($data['services'] as $row) {
            $serviceId = $row['service_id'] ?? null;
            $qty = $row['quantity'] ?? null;

            if (!$serviceId || !$qty) continue;

            $qty = (int) $qty;
            if ($qty <= 0) continue;

            $services[] = [
                'service_id' => (int) $serviceId,
                'quantity' => $qty,
            ];
        }

        return $services;
    }

    private function cleanPreviewFeatures(array $data): array
    {
        if (!isset($data['preview_features']) || !is_array($data['preview_features'])) {
            return [];
        }

        $clean = array_filter($data['preview_features'], fn ($f) => is_string($f) && trim($f) !== '');

        return array_values(array_map('trim', $clean));
    }

    /**
     * Display client subscriptions with progress tracking (for staff).
     */
    public function clientSubscriptions(Request $request): View
    {
        $user = Auth::guard('staff')->user();
        $isSuperAdmin = $user->hasRole('super_admin');

        // Get client filter if provided
        $clientId = $request->get('client_id');

        // Build query for active subscription quotas
        $quotasQuery = \App\Models\ClientServiceQuota::where('expires_at', '>', now())
            ->with(['subscription', 'service', 'client']);

        // Filter by staff member (unless super admin)
        if (!$isSuperAdmin) {
            $quotasQuery->whereHas('client', function ($q) use ($user) {
                $q->where('staff_id', $user->id);
            });
        }

        // Filter by client if specified
        if ($clientId) {
            $quotasQuery->where('client_id', $clientId);
        }

        $quotas = $quotasQuery->get()->groupBy('subscription_id');

        // Build subscription progress data (similar to client view)
        $subscriptions = [];
        foreach ($quotas as $subscriptionId => $quotaGroup) {
            $plan = $quotaGroup->first()->subscription;
            if (!$plan) continue;

            // Get plan services to know initial quantities
            $planServices = \App\Models\SubscriptionPlanService::where('subscription_plan_id', $subscriptionId)
                ->with('service')
                ->get()
                ->keyBy('service_id');

            // Group quotas by client and link
            $clientLinkGroups = $quotaGroup->groupBy(function ($quota) {
                return $quota->client_id . '|' . $quota->link;
            });

            foreach ($clientLinkGroups as $key => $linkQuotas) {
                [$clientId, $link] = explode('|', $key, 2);
                $client = $linkQuotas->first()->client;

                // Count how many unique links exist for this subscription+client
                $allLinksForPlan = \App\Models\ClientServiceQuota::where('client_id', $clientId)
                    ->where('subscription_id', $subscriptionId)
                    ->where('expires_at', '>', now())
                    ->distinct()
                    ->pluck('link')
                    ->toArray();
                $linkCount = count($allLinksForPlan);

                $services = [];
                $totalInitial = 0;
                $totalUsed = 0;
                $totalRemaining = 0;

                $linkIndex = array_search($link, $allLinksForPlan);
                if ($linkIndex === false) $linkIndex = 0;

                foreach ($linkQuotas as $quota) {
                    $planService = $planServices->get($quota->service_id);
                    if (!$planService) continue;

                    $planQuantity = $planService->quantity;
                    $quantityPerLink = $linkCount > 1 
                        ? (int) floor($planQuantity / $linkCount)
                        : $planQuantity;
                    
                    $remainder = $linkCount > 1 ? $planQuantity % $linkCount : 0;
                    $initialQuantity = $quantityPerLink + ($linkIndex < $remainder ? 1 : 0);
                    
                    $remainingQuantity = $quota->quantity_left ?? 0;
                    $usedQuantity = max(0, $initialQuantity - $remainingQuantity);
                    
                    $percentage = $initialQuantity > 0 
                        ? round(($usedQuantity / $initialQuantity) * 100, 2)
                        : 0;

                    $services[] = [
                        'service_id' => $quota->service_id,
                        'service_name' => $quota->service->name ?? 'Unknown',
                        'initial_quantity' => $initialQuantity,
                        'used_quantity' => $usedQuantity,
                        'remaining_quantity' => $remainingQuantity,
                        'percentage' => $percentage,
                    ];

                    $totalInitial += $initialQuantity;
                    $totalUsed += $usedQuantity;
                    $totalRemaining += $remainingQuantity;
                }

                $overallPercentage = $totalInitial > 0 
                    ? round(($totalUsed / $totalInitial) * 100, 2)
                    : 0;

                $subscriptions[] = [
                    'subscription_id' => $subscriptionId,
                    'plan_name' => $plan->name,
                    'client_id' => $clientId,
                    'client_name' => $client->name ?? "Client #{$clientId}",
                    'link' => $link,
                    'expires_at' => $linkQuotas->first()->expires_at,
                    'auto_renew' => $linkQuotas->first()->auto_renew ?? false,
                    'services' => $services,
                    'total_initial' => $totalInitial,
                    'total_used' => $totalUsed,
                    'total_remaining' => $totalRemaining,
                    'overall_percentage' => $overallPercentage,
                ];
            }
        }

        // Get clients for filter dropdown
        $clientsQuery = \App\Models\Client::query();
        if (!$isSuperAdmin) {
            $clientsQuery->where('staff_id', $user->id);
        }
        $clients = $clientsQuery->orderBy('name')->get();

        return view('staff.subscriptions.client-subscriptions', [
            'subscriptions' => $subscriptions,
            'clients' => $clients,
            'selectedClientId' => $clientId,
            'isSuperAdmin' => $isSuperAdmin,
        ]);
    }
}
