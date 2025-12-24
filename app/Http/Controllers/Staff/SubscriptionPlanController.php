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
use Illuminate\View\View;

class SubscriptionPlanController extends Controller
{
    public function __construct(
        private SubscriptionPlanServiceInterface $planService,
        private CategoryServiceInterface $categoryService,
        private ServiceServiceInterface $serviceService
    ) {
    }

    /**
     * Display a listing of subscription plans.
     */
    public function index(): View
    {
        $plans = $this->planService->getAllPlans();

        return view('staff.subscriptions.index', [
            'plans' => $plans,
        ]);
    }

    /**
     * Show the form for creating a new subscription plan.
     */
    public function create(): View
    {
        $categories = $this->categoryService->getAllCategories();
        
        // Get all active services grouped by category
        $servicesByCategory = [];
        foreach ($categories as $category) {
            $services = $this->serviceService->getServicesByCategoryId($category->id, true)
                ->map(function ($service) {
                    return [
                        'id' => $service->id,
                        'name' => $service->name,
                    ];
                })
                ->toArray();
            
            // Use string keys to match JavaScript behavior
            $servicesByCategory[(string) $category->id] = $services;
        }
        
        return view('staff.subscriptions.create', [
            'categories' => $categories,
            'plan' => null,
            'servicesByCategory' => $servicesByCategory,
        ]);
    }

    /**
     * Store a newly created subscription plan.
     */
    public function store(StoreSubscriptionPlanRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // Transform prices data
        $prices = [];
        if (!empty($data['prices']['daily']['enabled']) && isset($data['prices']['daily']['price']) && $data['prices']['daily']['price'] > 0) {
            $prices[] = [
                'billing_cycle' => 'daily',
                'price' => (float) $data['prices']['daily']['price'],
            ];
        }
        if (!empty($data['prices']['monthly']['enabled']) && isset($data['prices']['monthly']['price']) && $data['prices']['monthly']['price'] > 0) {
            $prices[] = [
                'billing_cycle' => 'monthly',
                'price' => (float) $data['prices']['monthly']['price'],
            ];
        }
        $data['prices'] = $prices;

        // Transform services data - filter out invalid entries
        $services = [];
        if (!empty($data['services']) && is_array($data['services'])) {
            foreach ($data['services'] as $serviceData) {
                if (!empty($serviceData['service_id']) && !empty($serviceData['quantity']) && $serviceData['quantity'] > 0) {
                    $services[] = [
                        'service_id' => (int) $serviceData['service_id'],
                        'quantity' => (int) $serviceData['quantity'],
                    ];
                }
            }
        }
        $data['services'] = $services;

        // Ensure is_active is boolean (defaults to true for new plans)
        $data['is_active'] = $request->has('is_active') && $request->boolean('is_active');

        // Clean up preview_features - remove empty strings
        if (isset($data['preview_features']) && is_array($data['preview_features'])) {
            $data['preview_features'] = array_values(array_filter($data['preview_features'], fn($feature) => !empty(trim($feature))));
        }

        $this->planService->createPlan($data);

        return redirect()->route('staff.subscriptions.index')
            ->with('status', 'subscription-plan-created');
    }

    /**
     * Show the form for editing the specified subscription plan.
     */
    public function edit(SubscriptionPlan $subscriptionPlan): View
    {
        $plan = $this->planService->getPlanById($subscriptionPlan->id);

        if (!$plan) {
            abort(404);
        }

        $categories = $this->categoryService->getAllCategories();

        // Get all active services grouped by category
        $servicesByCategory = [];
        foreach ($categories as $category) {
            $services = $this->serviceService->getServicesByCategoryId($category->id, true)
                ->map(function ($service) {
                    return [
                        'id' => $service->id,
                        'name' => $service->name,
                    ];
                })
                ->toArray();
            
            // Use string keys to match JavaScript behavior
            $servicesByCategory[(string) $category->id] = $services;
        }

        // Get services for the plan's category (active only) - kept for backward compatibility
        $services = $this->serviceService->getServicesByCategoryId($plan->category_id, true);

        // Format existing prices
        $dailyPrice = $plan->prices->where('billing_cycle', 'daily')->first()?->price;
        $monthlyPrice = $plan->prices->where('billing_cycle', 'monthly')->first()?->price;

        // Format existing services
        $selectedServices = $plan->planServices->map(function ($planService) {
            return [
                'service_id' => $planService->service_id,
                'quantity' => $planService->quantity,
            ];
        })->toArray();

        return view('staff.subscriptions.edit', [
            'plan' => $plan,
            'categories' => $categories,
            'services' => $services,
            'servicesByCategory' => $servicesByCategory,
            'dailyPrice' => $dailyPrice,
            'monthlyPrice' => $monthlyPrice,
            'selectedServices' => $selectedServices,
        ]);
    }

    /**
     * Update the specified subscription plan.
     */
    public function update(UpdateSubscriptionPlanRequest $request, SubscriptionPlan $subscriptionPlan): RedirectResponse
    {
        $data = $request->validated();

        // Transform prices data
        $prices = [];
        if (!empty($data['prices']['daily']['enabled']) && isset($data['prices']['daily']['price']) && $data['prices']['daily']['price'] > 0) {
            $prices[] = [
                'billing_cycle' => 'daily',
                'price' => (float) $data['prices']['daily']['price'],
            ];
        }
        if (!empty($data['prices']['monthly']['enabled']) && isset($data['prices']['monthly']['price']) && $data['prices']['monthly']['price'] > 0) {
            $prices[] = [
                'billing_cycle' => 'monthly',
                'price' => (float) $data['prices']['monthly']['price'],
            ];
        }
        $data['prices'] = $prices;

        // Transform services data - filter out invalid entries
        // Note: empty array means remove all services, null means don't change
        // Since validation passes services array, we always set it (empty array removes all services)
        $services = [];
        if (isset($data['services']) && is_array($data['services'])) {
            foreach ($data['services'] as $serviceData) {
                if (!empty($serviceData['service_id']) && !empty($serviceData['quantity']) && $serviceData['quantity'] > 0) {
                    $services[] = [
                        'service_id' => (int) $serviceData['service_id'],
                        'quantity' => (int) $serviceData['quantity'],
                    ];
                }
            }
        }
        $data['services'] = $services;

        // Ensure is_active is boolean
        $data['is_active'] = $request->has('is_active') && $request->boolean('is_active');

        // Clean up preview_features - remove empty strings
        if (isset($data['preview_features']) && is_array($data['preview_features'])) {
            $data['preview_features'] = array_values(array_filter($data['preview_features'], fn($feature) => !empty(trim($feature))));
        }

        $this->planService->updatePlan($subscriptionPlan, $data);

        return redirect()->route('staff.subscriptions.index')
            ->with('status', 'subscription-plan-updated');
    }

    /**
     * Remove the specified subscription plan.
     */
    public function destroy(SubscriptionPlan $subscriptionPlan): RedirectResponse
    {
        $this->planService->deletePlan($subscriptionPlan);

        return redirect()->route('staff.subscriptions.index')
            ->with('status', 'subscription-plan-deleted');
    }

    /**
     * Get services by category ID (for AJAX requests).
     */
    public function getServicesByCategory(Request $request): JsonResponse
    {
        $categoryId = $request->input('category_id');

        if (!$categoryId) {
            return response()->json(['services' => []]);
        }

        $services = $this->serviceService->getServicesByCategoryId((int) $categoryId, true)
            ->map(function ($service) {
                return [
                    'id' => $service->id,
                    'name' => $service->name,
                ];
            });

        return response()->json(['services' => $services]);
    }

    /**
     * Show the form for editing the client subscriptions header text.
     */
    public function editHeader(): View
    {
        $uiText = UiText::where('key', 'client.subscriptions.header')->first();

        return view('staff.subscriptions.edit-header', [
            'uiText' => $uiText,
        ]);
    }

    /**
     * Update or create the client subscriptions header text.
     */
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

        return redirect()->route('staff.subscriptions.index')
            ->with('status', 'client-header-text-updated');
    }
}
