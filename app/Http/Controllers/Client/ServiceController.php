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
        $filters = [
            'search' => request()->get('search', ''),
            'search_by' => request()->get('search_by', 'all'),
            'min' => request()->get('min'),
            'max' => request()->get('max'),
            'status' => request()->get('status', 'all'),
            'category_id' => request()->get('category_id'),
            'sort' => request()->get('sort', 'id'),
            'dir' => request()->get('dir', 'asc'),
        ];

        // For client views, only show enabled categories with enabled services
        $filters['for_client'] = true;
        $categories = $this->categoryService->getAllCategoriesWithServices($filters);
        $categoriesList = $this->categoryService->getAllCategories(true); // Only enabled categories for filter dropdown
        
        // Add pricing information to services
        $client = Auth::guard('client')->user();
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
        ]);
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

        // For client views, only show enabled categories with enabled services
        $filters['for_client'] = true;
        $categories = $this->categoryService->getAllCategoriesWithServices($filters);
        
        // Add pricing information to services
        $client = Auth::guard('client')->user();
        if ($client) {
            foreach ($categories as $category) {
                foreach ($category->services as $service) {
                    $service->client_price = $this->pricingService->priceForClient($service, $client);
                    $service->default_rate = $service->rate_per_1000 ?? 0;
                    $service->has_custom_rate = isset($client->rates[$service->id]);
                }
            }
        }

        // Return HTML for the filtered categories/services
        $html = view('client.services.partials.services-list', [
            'categories' => $categories,
            'showActions' => false,
        ])->render();

        return response()->json([
            'html' => $html,
            'count' => $categories->sum(fn($cat) => $cat->services->count()),
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

