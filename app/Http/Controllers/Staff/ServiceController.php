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
    ) {}

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
     * Reorder categories via drag-and-drop.
     */
    public function reorderCategories(Request $request): JsonResponse
    {
        $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|exists:categories,id',
        ]);

        foreach ($request->input('order') as $index => $categoryId) {
            Category::where('id', $categoryId)->update(['sort_order' => $index]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Reorder services within a category via drag-and-drop.
     */
    public function reorderServices(Request $request): JsonResponse
    {
        $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|exists:services,id',
        ]);

        foreach ($request->input('order') as $index => $serviceId) {
            Service::where('id', $serviceId)->update(['sort_order' => $index]);
        }

        return response()->json(['success' => true]);
    }

    public function create(): View
    {
        return view('staff.services.create', $this->getServiceFormData());
    }

    /**
     * Show the form for editing the specified service.
     */
    public function edit(Service $service): View
    {
        $service->load('category');

        return view('staff.services.create', $this->getServiceFormData($service));
    }

    /**
     * Store a newly created service.
     */
    public function store(StoreServiceRequest $request): RedirectResponse
    {
        $service = $this->serviceService->createService($request->validated());
        \App\Models\StaffActivityLog::log('create', "Created service #{$service->id} ({$service->name})", $service);

        return redirect()->route('staff.services.index')
            ->with('status', 'service-created');
    }

    /**
     * Update the specified service.
     */
    public function update(UpdateServiceRequest $request, Service $service): RedirectResponse
    {
        $this->serviceService->updateService($service, $request->validated());
        \App\Models\StaffActivityLog::log('update', "Updated service #{$service->id} ({$service->name})", $service);

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
        $fresh = $category->fresh();
        $state = $fresh->status ? 'enabled' : 'disabled';
        \App\Models\StaffActivityLog::log('toggle', "Category #{$category->id} ({$category->name}) {$state}", $category);

        return redirect()->route('staff.services.index')
            ->with('status', $fresh->status ? 'category-enabled' : 'category-disabled');
    }

    /**
     * Duplicate the specified service.
     */
    public function duplicate(Service $service): RedirectResponse
    {
        $this->serviceService->duplicateService($service);
        \App\Models\StaffActivityLog::log('create', "Duplicated service #{$service->id} ({$service->name})", $service);

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
            \App\Models\StaffActivityLog::log('delete', "Deleted service #{$service->id} ({$service->name})", $service);

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

        $fresh = $service->fresh();
        $state = $fresh->is_active ? 'enabled' : 'disabled';
        \App\Models\StaffActivityLog::log('toggle', "Service #{$service->id} ({$service->name}) {$state}", $service);
        $statusMessage = $fresh->is_active ? 'service-enabled' : 'service-disabled';

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
            'count' => $categories->sum(fn ($cat) => $cat->services->count()),
        ]);
    }

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

    private function getServiceFormData(?Service $service = null): array
    {
        $categories = $this->categoryService->getAllCategories();
        $telegramTemplates = array_diff_key(
            config('telegram_service_templates', []),
            ['premium_templates' => true]
        );
        $youtubeTemplates = config('youtube_service_templates', []);
        $appTemplates = config('app_service_templates', []);
        $maxTemplates = config('max_service_templates', []);
        $serviceTemplates = array_merge($telegramTemplates, $youtubeTemplates, $appTemplates, $maxTemplates);

        $templatesByTargetType = $this->buildTemplatesByTargetType($telegramTemplates, $youtubeTemplates, $appTemplates, $maxTemplates);
        $categoryIdsWithTemplates = $this->getCategoryIdsWithTemplates($categories);
        $categoryLinkDrivers = collect($categories)->pluck('link_driver', 'id')->toArray();

        return [
            'categories' => $categories,
            'service' => $service,
            'defaultMode' => Service::MODE_DEFAULT,

            'modeOptions' => [
                'manual' => 'Manual',
                //                'provider' => 'Provider',
            ],

            'serviceTypeOptions' => [
                'default' => 'Default',
                'custom_comments' => 'Custom comments',
            ],

            'allowOptions' => [
                '1' => 'Allow',
                '0' => 'Disallow',
            ],

            'yesNoOptions' => [
                '1' => 'Yes',
                '0' => 'No',
            ],

            'countTypeOptions' => [
                'telegram_members' => 'Telegram members',
                'instagram_likes' => 'Instagram likes',
                'instagram_followers' => 'Instagram followers',
                'youtube_views' => 'YouTube views',
            ],

            'targetTypeOptions' => [
                'bot' => 'Bot',
                'channel' => 'Channel/Group',
                'app' => 'App',
            ],

            'serviceTemplates' => $serviceTemplates,
            'templatesByTargetType' => $templatesByTargetType,
            'youtubeTemplates' => collect($youtubeTemplates)->mapWithKeys(fn ($t, $k) => [$k => $t['label'] ?? $k])->all(),
            'categoryIdsWithTemplates' => $categoryIdsWithTemplates,
            'categoryLinkDrivers' => $categoryLinkDrivers,
        ];
    }

    private function buildTemplatesByTargetType(array $telegramTemplates, array $youtubeTemplates, array $appTemplates = [], array $maxTemplates = []): array
    {
        $templatesByTargetType = [
            'bot' => [],
            'channel' => [],
            'youtube' => [],
            'app' => [],
            'max' => [],
        ];

        foreach ($telegramTemplates as $key => $template) {
            $peerTypes = $template['allowed_peer_types'] ?? [];

            if (in_array('bot', $peerTypes, true)) {
                $templatesByTargetType['bot'][$key] = $template['label'] ?? $key;
            }

            if (
                in_array('channel', $peerTypes, true) ||
                in_array('group', $peerTypes, true) ||
                in_array('supergroup', $peerTypes, true)
            ) {
                $templatesByTargetType['channel'][$key] = $template['label'] ?? $key;
            }
        }

        foreach ($youtubeTemplates as $key => $template) {
            $templatesByTargetType['youtube'][$key] = $template['label'] ?? $key;
        }

        foreach ($appTemplates as $key => $template) {
            $templatesByTargetType['app'][$key] = $template['label'] ?? $key;
        }

        foreach ($maxTemplates as $key => $template) {
            $templatesByTargetType['max'][$key] = $template['label'] ?? $key;
        }

        return $templatesByTargetType;
    }

    private function getCategoryIdsWithTemplates($categories): array
    {
        return collect($categories)
            ->filter(function ($category) {
                $driver = $category->link_driver ?? '';

                return stripos($driver, 'telegram') !== false || in_array($driver, ['youtube', 'app', 'max'], true);
            })
            ->pluck('id')
            ->values()
            ->all();
    }
}
