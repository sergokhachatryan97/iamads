<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\UiText;
use App\Services\SubscriptionPlanServiceInterface;
use Illuminate\View\View;

class SubscriptionPlanController extends Controller
{
    public function __construct(
        private SubscriptionPlanServiceInterface $planService
    ) {
    }

    /**
     * Display a listing of active subscription plans (preview page).
     */
    public function index(): View
    {
        $plans = $this->planService->getAllActivePlans()->take(3);
        
        // Get the client subscriptions header text if it exists and is active
        $headerText = UiText::where('key', 'client.subscriptions.header')
            ->where('is_active', true)
            ->first();

        return view('client.subscriptions.index', [
            'plans' => $plans,
            'headerText' => $headerText?->value,
        ]);
    }
}
