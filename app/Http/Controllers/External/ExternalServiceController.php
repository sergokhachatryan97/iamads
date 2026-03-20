<?php

namespace App\Http\Controllers\External;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * External API: list active services for the authenticated client.
 */
class ExternalServiceController extends Controller
{
    public function __construct(
        private PricingService $pricingService
    ) {}

    /**
     * GET /external/services
     * Returns active services with pricing for the authenticated API client.
     */
    public function index(Request $request): JsonResponse
    {
        $client = $request->attributes->get('api_client');

        $categories = Category::query()
            ->where('status', true)
            ->orderBy('name')
            ->with(['services' => fn ($q) => $q->where('is_active', true)->orderBy('name')])
            ->get();

        $services = [];
        foreach ($categories as $category) {
            foreach ($category->services as $service) {
                $rate = (float) $this->pricingService->priceForClient($service, $client);
                $template = $service->template();
                $type = $template['action'] ?? 'default';

                $services[] = [
                    'service' => (int) $service->id,
                    'name' => $service->name,
                    'type' => $type,
                    'category' => $category->name,
                    'rate' => number_format($rate, 2, '.', ''),
                    'min' => (int) $service->min_quantity,
                    'max' => $service->max_quantity !== null ? (int) $service->max_quantity : 0,
                    'refill' => (bool) ($service->refill_enabled ?? false),
                    'cancel' => (bool) ($service->user_can_cancel ?? false),
                ];
            }
        }

        return response()->json($services);
    }
}
