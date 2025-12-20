<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\CategoryServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function __construct(
        private CategoryServiceInterface $categoryService
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

        $categories = $this->categoryService->getAllCategoriesWithServices($filters);
        $categoriesList = $this->categoryService->getAllCategories();

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

        $categories = $this->categoryService->getAllCategoriesWithServices($filters);

        // Return HTML for the filtered categories/services
        $html = view('staff.services.partials.services-list', [
            'categories' => $categories,
            'showActions' => false,
        ])->render();

        return response()->json([
            'html' => $html,
            'count' => $categories->sum(fn($cat) => $cat->services->count()),
        ]);
    }
}

