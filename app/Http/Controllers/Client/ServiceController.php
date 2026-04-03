<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\CategoryServiceInterface;
use App\Services\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function __construct(
        private CategoryServiceInterface $categoryService,
        private PricingService $pricingService
    ) {
    }

    /**
     * Display a listing of services for clients.
     */
    public function index(): View
    {
        $client = Auth::guard('client')->user();
        $favoriteServiceIds = $client
            ? $client->favoriteServices()->where('is_active', true)->pluck('id')->values()->all()
            : [];

        $filters = [
            'search' => request()->get('search', ''),
            'search_by' => request()->get('search_by', 'service_name'),
            'min' => request()->get('min'),
            'max' => request()->get('max'),
            'status' => request()->get('status', 'all'),
            'category_id' => request()->get('category_id'),
            'favorites_only' => request()->get('favorites_only') === '1' ? '1' : '0',
            'sort' => request()->get('sort', 'id'),
            'dir' => request()->get('dir', 'asc'),
        ];

        $filters['for_client'] = true;
        $filters['favorite_service_ids'] = $favoriteServiceIds;

        $categories = $this->categoryService->getAllCategoriesWithServices($filters);
        $categoriesList = $this->categoryService->getAllCategories(true);

        $countFilters = [
            'for_client' => true,
            'search' => '',
            'search_by' => 'service_name',
            'sort' => 'id',
            'dir' => 'asc',
            'favorite_service_ids' => $favoriteServiceIds,
        ];
        $categoriesForCounts = $this->categoryService->getAllCategoriesWithServices($countFilters);
        $serviceTabCounts = [
            'all' => 0,
            'favorites' => count($favoriteServiceIds),
            'categories' => [],
        ];
        foreach ($categoriesForCounts as $cat) {
            $n = $cat->services->count();
            $serviceTabCounts['categories'][$cat->id] = $n;
            $serviceTabCounts['all'] += $n;
        }

        if ($client) {
            foreach ($categories as $category) {
                foreach ($category->services as $service) {
                    $service->client_price = $this->pricingService->priceForClient($service, $client);
                    $service->default_rate = $service->rate_per_1000 ?? 0;
                    $service->has_custom_rate = isset($client->rates[$service->id]);
                }
            }
        }

        return view('client.services.index', [
            'categories' => $categories,
            'categoriesList' => $categoriesList,
            'filters' => $filters,
            'favoriteServiceIds' => $favoriteServiceIds,
            'serviceTabCounts' => $serviceTabCounts,
        ]);
    }

    /**
     * Search services via AJAX.
     */
    public function search(Request $request): JsonResponse
    {
        $client = Auth::guard('client')->user();
        $favoriteServiceIds = $client
            ? $client->favoriteServices()->where('is_active', true)->pluck('id')->values()->all()
            : [];

        $filters = [
            'search' => $request->get('search', ''),
            'search_by' => $request->get('search_by', 'service_name'),
            'min' => $request->get('min'),
            'max' => $request->get('max'),
            'status' => $request->get('status', 'all'),
            'category_id' => $request->get('category_id'),
            'favorites_only' => $request->get('favorites_only') === '1' ? '1' : '0',
            'sort' => $request->get('sort', 'id'),
            'dir' => $request->get('dir', 'asc'),
        ];

        $filters['for_client'] = true;
        $filters['favorite_service_ids'] = $favoriteServiceIds;

        $categories = $this->categoryService->getAllCategoriesWithServices($filters);

        if ($client) {
            foreach ($categories as $category) {
                foreach ($category->services as $service) {
                    $service->client_price = $this->pricingService->priceForClient($service, $client);
                    $service->default_rate = $service->rate_per_1000 ?? 0;
                    $service->has_custom_rate = isset($client->rates[$service->id]);
                }
            }
        }

        $html = view('client.services.partials.services-list', [
            'categories' => $categories,
            'showActions' => false,
            'favoriteServiceIds' => $favoriteServiceIds,
        ])->render();

        return response()->json([
            'html' => $html,
            'count' => $categories->sum(fn ($cat) => $cat->services->count()),
        ]);
    }

    /**
     * Toggle favorite status for a service.
     */
    public function toggleFavorite(Service $service): JsonResponse
    {
        $client = Auth::guard('client')->user();

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $isFavorite = $client->favoriteServices()->where('service_id', $service->id)->exists();

        if ($isFavorite) {
            $client->favoriteServices()->detach($service->id);
            $isFavorite = false;
        } else {
            $client->favoriteServices()->attach($service->id);
            $isFavorite = true;
        }

        return response()->json([
            'ok' => true,
            'favorited' => $isFavorite,
        ]);
    }
}
