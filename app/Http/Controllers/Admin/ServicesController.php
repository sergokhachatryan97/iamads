<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreServiceRequest;
use App\Http\Requests\Admin\UpdateServiceRequest;
use App\Models\Category;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ServicesController extends Controller
{
    /**
     * Display a listing of services.
     */
    public function index(): View
    {
        $services = Service::with('category')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($service) {
                return [
                    'id' => $service->id,
                    'name' => $service->name,
                    'description' => $service->description,
                    'category_id' => $service->category_id,
                    'category' => $service->category ? [
                        'id' => $service->category->id,
                        'name' => $service->category->name,
                    ] : null,
                    'mode' => $service->mode,
                    'service_type' => $service->service_type,
                    'dripfeed_enabled' => $service->dripfeed_enabled,
                    'user_can_cancel' => $service->user_can_cancel,
                    'rate_per_1000' => $service->rate_per_1000,
                    'service_cost_per_1000' => $service->service_cost_per_1000,
                    'min_quantity' => $service->min_quantity,
                    'max_quantity' => $service->max_quantity,
                    'deny_link_duplicates' => $service->deny_link_duplicates,
                    'deny_duplicates_days' => $service->deny_duplicates_days,
                    'increment' => $service->increment,
                    'start_count_parsing_enabled' => $service->start_count_parsing_enabled,
                    'count_type' => $service->count_type,
                    'auto_complete_enabled' => $service->auto_complete_enabled,
                    'refill_enabled' => $service->refill_enabled,
                    'is_active' => $service->is_active,
                ];
            })
            ->values()
            ->toArray();

        $categories = Category::orderBy('name')->get();

        return view('admin.services.index', [
            'services' => $services,
            'categories' => $categories,
        ]);
    }

    /**
     * Store a newly created service.
     */
    public function store(StoreServiceRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // Set defaults
        $data['mode'] = $data['mode'] ?? Service::MODE_DEFAULT;
        $data['is_active'] = $data['is_active'] ?? true;
        $data['dripfeed_enabled'] = $data['dripfeed_enabled'] ?? false;
        $data['user_can_cancel'] = $data['user_can_cancel'] ?? false;
        $data['deny_link_duplicates'] = $data['deny_link_duplicates'] ?? false;
        $data['increment'] = $data['increment'] ?? 0;
        $data['start_count_parsing_enabled'] = $data['start_count_parsing_enabled'] ?? false;
        $data['auto_complete_enabled'] = $data['auto_complete_enabled'] ?? false;
        $data['refill_enabled'] = $data['refill_enabled'] ?? false;

        Service::create($data);

        return redirect()->route('admin.services.index')
            ->with('success', 'Service created successfully.');
    }

    /**
     * Update the specified service.
     */
    public function update(UpdateServiceRequest $request, Service $service): RedirectResponse
    {
        $data = $request->validated();

        $service->update($data);

        return redirect()->route('admin.services.index')
            ->with('success', 'Service updated successfully.');
    }
}

