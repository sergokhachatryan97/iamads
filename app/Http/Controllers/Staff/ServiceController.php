<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StoreCategoryRequest;
use App\Http\Requests\Staff\StoreServiceRequest;
use App\Http\Requests\Staff\UpdateCategoryRequest;
use App\Http\Requests\Staff\UpdateServiceRequest;
use App\Models\Category;
use App\Models\Service;
use App\Services\CategoryServiceInterface;
use App\Services\ServiceServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function __construct(
        private CategoryServiceInterface $categoryService,
        private ServiceServiceInterface $serviceService
    ) {
    }

    /**
     * Display a listing of services grouped by category.
     */
    public function index(): View
    {
        $filters = [
            'search' => request()->get('search', ''),
            'search_by' => request()->get('search_by', 'all'),
            'min' => request()->get('min'),
            'max' => request()->get('max'),
            'status' => request()->get('status', 'all'),
            'category_id' => request()->get('category_id'),
            'sort' => request()->get('sort', 'id'),
            'dir' => request()->get('dir', 'asc'),
            'show_deleted' => request()->get('show_deleted', '0'),
        ];

        $categories = $this->categoryService->getAllCategoriesWithServices($filters);
        $allServices = $this->serviceService->getAllServicesWithCategory($filters);
        $categoriesList = $this->categoryService->getAllCategories();

        // Check if admin route
        if (request()->is('admin/services*') || request()->routeIs('admin.services.*')) {
            return view('admin.services.index', [
                'services' => $allServices,
                'categories' => $categoriesList,
                'filters' => $filters,
            ]);
        }

        return view('staff.services.index', [
            'categories' => $categories,
            'categoriesList' => $categoriesList,
            'filters' => $filters,
        ]);
    }

    /**
     * Show the form for creating a new service.
     */
    public function create(): View
    {
        $categories = $this->categoryService->getAllCategories();

        return view('staff.services.create', [
            'categories' => $categories,
            'service' => null,
        ]);
    }

    /**
     * Show the form for editing the specified service.
     */
    public function edit(Service $service): View
    {
        $categories = $this->categoryService->getAllCategories();
        $service->load('category');

        return view('staff.services.create', [
            'categories' => $categories,
            'service' => $service,
        ]);
    }

    /**
     * Store a newly created service.
     */
    public function store(StoreServiceRequest $request): RedirectResponse
    {
        $this->serviceService->createService($request->validated());

        return redirect()->route('staff.services.index')
            ->with('status', 'service-created');
    }

    /**
     * Update the specified service.
     */
    public function update(UpdateServiceRequest $request, Service $service): RedirectResponse
    {
        $this->serviceService->updateService($service, $request->validated());

        return redirect()->route('staff.services.index')
            ->with('status', 'service-updated');
    }

    /**
     * Store a newly created category.
     */
    public function storeCategory(StoreCategoryRequest $request): RedirectResponse
    {
        $this->categoryService->createCategory($request->validated());

        return redirect()->route('staff.services.index')
            ->with('status', 'category-created');
    }

    /**
     * Update an existing category.
     */
    public function updateCategory(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $this->categoryService->updateCategory($category, $request->validated());

        return redirect()->route('staff.services.index')
            ->with('status', 'category-updated');
    }

    /**
     * Toggle category status (enable/disable).
     */
    public function toggleCategoryStatus(Category $category): RedirectResponse
    {
        $this->categoryService->toggleCategoryStatus($category);

        $statusMessage = $category->fresh()->status ? 'category-enabled' : 'category-disabled';

        return redirect()->route('staff.services.index')
            ->with('status', $statusMessage);
    }

    /**
     * Duplicate the specified service.
     */
    public function duplicate(Service $service): RedirectResponse
    {
        $this->serviceService->duplicateService($service);

        return redirect()->route('staff.services.index')
            ->with('status', 'service-duplicated');
    }

    /**
     * Remove the specified service.
     */
    public function destroy(Request $request, Service $service): RedirectResponse|JsonResponse
    {
        try {
            $this->serviceService->deleteService($service);

            // Check if it's an AJAX request (check multiple conditions)
            $isAjax = $request->wantsJson() 
                || $request->ajax() 
                || $request->header('X-Requested-With') === 'XMLHttpRequest'
                || $request->expectsJson();

            if ($isAjax) {
                return response()->json([
                    'success' => true,
                    'message' => __('Service deleted successfully.')
                ]);
            }

            return redirect()->route('staff.services.index')
                ->with('status', 'service-deleted');
        } catch (\Exception $e) {
            // Check if it's an AJAX request
            $isAjax = $request->wantsJson() 
                || $request->ajax() 
                || $request->header('X-Requested-With') === 'XMLHttpRequest'
                || $request->expectsJson();

            if ($isAjax) {
                return response()->json([
                    'success' => false,
                    'error' => __('Failed to delete service. Please try again.')
                ], 500);
            }

            return redirect()->route('staff.services.index')
                ->with('error', __('Failed to delete service. Please try again.'));
        }
    }

    /**
     * Update service mode.
     */
    public function updateMode(Request $request, Service $service): RedirectResponse
    {
        $request->validate([
            'mode' => ['required', 'string', 'in:manual,auto'],
        ]);

        $this->serviceService->updateService($service, ['mode' => $request->input('mode')]);

        return redirect()->route('staff.services.index')
            ->with('status', 'service-mode-updated');
    }

    /**
     * Toggle service status (enable/disable).
     * If is_active is provided in request, set to that value; otherwise toggle.
     */
    public function toggleServiceStatus(Request $request, Service $service): RedirectResponse|JsonResponse
    {
        // If is_active is explicitly provided in request (for bulk operations), use it
        if ($request->has('is_active')) {
            $service->is_active = (bool) $request->input('is_active');
            $service->save();
        } else {
            // Otherwise, toggle the status
            $this->serviceService->toggleServiceStatus($service);
        }

        $statusMessage = $service->fresh()->is_active ? 'service-enabled' : 'service-disabled';

        // If AJAX request, return JSON response
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'status' => $statusMessage,
                'is_active' => $service->fresh()->is_active
            ]);
        }

        return redirect()->route('staff.services.index')
            ->with('status', $statusMessage);
    }

    /**
     * Search services via AJAX.
     */
    public function search(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->get('search', ''),
            'search_by' => $request->get('search_by', 'all'),
            'min' => $request->get('min'),
            'max' => $request->get('max'),
            'status' => $request->get('status', 'all'),
            'category_id' => $request->get('category_id'),
            'sort' => $request->get('sort', 'id'),
            'dir' => $request->get('dir', 'asc'),
        ];

        $categories = $this->categoryService->getAllCategoriesWithServices($filters);

        // Return HTML for the filtered categories/services
        $html = view('staff.services.partials.services-list', [
            'categories' => $categories,
        ])->render();

        return response()->json([
            'html' => $html,
            'count' => $categories->sum(fn($cat) => $cat->services->count()),
        ]);
    }

    /**
     * Display services list for clients (view only, no actions).
     */
    public function clientIndex(): View
    {
        $filters = [
            'search' => request()->get('search', ''),
            'search_by' => 'service_name', // Only search by service name for clients
            'status' => 'active', // Clients only see active services
            'category_id' => request()->get('category_id'),
            'sort' => request()->get('sort', 'id'),
            'dir' => request()->get('dir', 'asc'),
            'favorites_only' => request()->get('favorites_only', 0),
        ];

        // Get authenticated client for favorites
        $client = auth('client')->user();
        $favoriteServiceIds = ($client && $client instanceof \App\Models\Client) ? $client->favoriteServices()->get()->pluck('id')->toArray() : [];

        // If favorites_only filter is enabled, add it to filters
        if ($filters['favorites_only'] && !empty($favoriteServiceIds)) {
            $filters['favorite_service_ids'] = $favoriteServiceIds;
        } elseif ($filters['favorites_only'] && empty($favoriteServiceIds)) {
            // User has no favorites, return empty result
            return view('client.services.index', [
                'categories' => collect(),
                'categoriesList' => $this->categoryService->getAllCategories(),
                'filters' => $filters,
                'favoriteServiceIds' => [],
            ]);
        }

        $categories = $this->categoryService->getAllCategoriesWithServices($filters);

        // Filter out categories with no active services
        $categories = $categories->filter(function ($category) {
            return $category->services->where('is_active', true)->isNotEmpty();
        })->values();

        // Apply favorites filter if enabled
        if ($filters['favorites_only'] && !empty($favoriteServiceIds)) {
            $categories = $categories->map(function ($category) use ($favoriteServiceIds) {
                $category->services = $category->services->where('is_active', true)
                    ->filter(function ($service) use ($favoriteServiceIds) {
                        return in_array($service->id, $favoriteServiceIds);
                    })->values();
                return $category;
            })->filter(function ($category) {
                return $category->services->isNotEmpty();
            })->values();
        } else {
            // Only show active services
            $categories = $categories->map(function ($category) {
                $category->services = $category->services->where('is_active', true)->values();
                return $category;
            });
        }

        // Add is_favorited flag to each service
        if ($client) {
            $categories = $categories->map(function ($category) use ($favoriteServiceIds) {
                $category->services = $category->services->map(function ($service) use ($favoriteServiceIds) {
                    $service->is_favorited = in_array($service->id, $favoriteServiceIds);
                    return $service;
                });
                return $category;
            });
        }

        // Get all categories and filter to only show enabled ones with active services
        $allCategories = $this->categoryService->getAllCategories();
        $categoriesList = $allCategories->filter(function ($category) {
            // Only include categories that are enabled
            if (!($category->status ?? true)) {
                return false;
            }
            // Only include categories that have at least one active service
            return $category->services()->where('is_active', true)->exists();
        })->values();

        return view('client.services.index', [
            'categories' => $categories,
            'categoriesList' => $categoriesList,
            'filters' => $filters,
            'favoriteServiceIds' => $favoriteServiceIds,
        ]);
    }

    /**
     * Search services for clients (AJAX).
     */
    public function clientSearch(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->get('search', ''),
            'search_by' => 'service_name', // Only search by service name for clients
            'status' => 'active', // Clients only see active services
            'category_id' => $request->get('category_id'),
            'sort' => $request->get('sort', 'id'),
            'dir' => $request->get('dir', 'asc'),
            'favorites_only' => $request->get('favorites_only', 0),
        ];

        // Get authenticated client for favorites
        $client = auth('client')->user();
        $favoriteServiceIds = ($client && $client instanceof \App\Models\Client) ? $client->favoriteServices()->get()->pluck('id')->toArray() : [];

        // If favorites_only filter is enabled, add it to filters
        if ($filters['favorites_only'] && !empty($favoriteServiceIds)) {
            $filters['favorite_service_ids'] = $favoriteServiceIds;
        } elseif ($filters['favorites_only'] && empty($favoriteServiceIds)) {
            // User has no favorites, return empty result
            $html = view('client.services.partials.services-list', [
                'categories' => collect(),
                'favoriteServiceIds' => [],
            ])->render();

            return response()->json([
                'html' => $html,
                'count' => 0,
            ]);
        }

        $categories = $this->categoryService->getAllCategoriesWithServices($filters);

        // Filter out categories with no active services
        $categories = $categories->filter(function ($category) {
            return $category->services->where('is_active', true)->isNotEmpty();
        })->values();

        // Apply favorites filter if enabled
        if ($filters['favorites_only'] && !empty($favoriteServiceIds)) {
            $categories = $categories->map(function ($category) use ($favoriteServiceIds) {
                $category->services = $category->services->where('is_active', true)
                    ->filter(function ($service) use ($favoriteServiceIds) {
                        return in_array($service->id, $favoriteServiceIds);
                    })->values();
                return $category;
            })->filter(function ($category) {
                return $category->services->isNotEmpty();
            })->values();
        } else {
            // Only show active services
            $categories = $categories->map(function ($category) {
                $category->services = $category->services->where('is_active', true)->values();
                return $category;
            });
        }

        // Add is_favorited flag to each service
        if ($client) {
            $categories = $categories->map(function ($category) use ($favoriteServiceIds) {
                $category->services = $category->services->map(function ($service) use ($favoriteServiceIds) {
                    $service->is_favorited = in_array($service->id, $favoriteServiceIds);
                    return $service;
                });
                return $category;
            });
        }

        // Return HTML for the filtered categories/services (client view)
        $html = view('client.services.partials.services-list', [
            'categories' => $categories,
            'favoriteServiceIds' => $favoriteServiceIds,
        ])->render();

        return response()->json([
            'html' => $html,
            'count' => $categories->sum(fn($cat) => $cat->services->count()),
        ]);
    }

    /**
     * Toggle favorite status for a service (Client favorites only).
     */
    public function toggleFavorite(Request $request, Service $service): JsonResponse
    {
        // Get authenticated client
        $client = auth('client')->user();

        if (!$client || !($client instanceof \App\Models\Client)) {
            return response()->json(['ok' => false, 'error' => 'Unauthorized'], 401);
        }

        // Ensure service is active (security check)
        if (!$service->is_active) {
            return response()->json(['ok' => false, 'error' => 'Service is not available'], 403);
        }

        // Handle client favorites
        $isFavorite = $client->favoriteServices()->where('service_id', $service->id)->exists();

        if ($isFavorite) {
            $client->favoriteServices()->detach($service->id);
            $favorited = false;
        } else {
            $client->favoriteServices()->attach($service->id);
            $favorited = true;
        }

        return response()->json([
            'ok' => true,
            'favorited' => $favorited,
        ]);
    }

    /**
     * Restore a soft-deleted service.
     */
    public function restore(Request $request, int $serviceId): RedirectResponse|JsonResponse
    {
        try {
            $service = Service::onlyTrashed()->findOrFail($serviceId);
            $this->serviceService->restoreService($service);

            if ($request->wantsJson() || $request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest' || $request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => __('Service restored successfully.')
                ]);
            }

            return redirect()->route('staff.services.index')
                ->with('status', 'service-restored');
        } catch (\Exception $e) {
            if ($request->wantsJson() || $request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest' || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => __('Failed to restore service. Please try again.')
                ], 500);
            }

            return redirect()->route('staff.services.index')
                ->with('error', __('Failed to restore service. Please try again.'));
        }
    }
}
