<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\UiText;
use App\Models\SubscriptionPlan;
use App\Services\SubscriptionPlanServiceInterface;
use App\Services\SubscriptionPurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SubscriptionPlanController extends Controller
{
    public function __construct(
        private SubscriptionPlanServiceInterface $planService,
        private SubscriptionPurchaseService $purchaseService
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

        $client = Auth::guard('client')->user();
        
        // Get current client balance
        $clientBalance = $client->balance ?? 0;

        // Get active subscription plan IDs for this client
        $activeSubscriptionPlanIds = \App\Models\ClientServiceQuota::where('client_id', $client->id)
            ->where('expires_at', '>', now())
            ->distinct()
            ->pluck('subscription_id')
            ->toArray();

        return view('client.subscriptions.index', [
            'plans' => $plans,
            'headerText' => $headerText?->value,
            'clientBalance' => $clientBalance,
            'activeSubscriptionPlanIds' => $activeSubscriptionPlanIds,
        ]);
    }

    /**
     * Purchase a subscription plan.
     */
    public function purchase(Request $request, SubscriptionPlan $subscriptionPlan): RedirectResponse
    {
        $request->validate([
            'billing_cycle' => ['required', 'in:monthly'],
            'links' => ['required', 'array', 'min:1'],
            'links.*' => [
                'required',
                'string',
                'max:2048',
                function ($attribute, $value, $fail) {
                    $trimmed = trim($value);

                    // Must not contain spaces
                    if (strpos($trimmed, ' ') !== false) {
                        $fail('Please enter a valid Telegram link.');
                        return;
                    }

                    // Check if it's a @username format
                    if (preg_match('/^@[A-Za-z0-9_]{3,}$/i', $trimmed)) {
                        return; // Valid @username format
                    }

                    // Must start with https://t.me/ or t.me/
                    if (!preg_match('/^https?:\/\/t\.me\//i', $trimmed) && !preg_match('/^t\.me\//i', $trimmed)) {
                        $fail('Please enter a valid Telegram link.');
                        return;
                    }

                    // Must match regex for Telegram links
                    // Public channel/user: https?://t\.me/[A-Za-z0-9_]{3,}
                    // Private invite: https?://t\.me/\+[A-Za-z0-9_-]{10,}
                    if (!preg_match('/^https?:\/\/t\.me\/[A-Za-z0-9_]{3,}$/i', $trimmed) &&
                        !preg_match('/^https?:\/\/t\.me\/\+[A-Za-z0-9_-]{10,}$/i', $trimmed) &&
                        !preg_match('/^t\.me\/[A-Za-z0-9_]{3,}$/i', $trimmed) &&
                        !preg_match('/^t\.me\/\+[A-Za-z0-9_-]{10,}$/i', $trimmed)) {
                        $fail('Please enter a valid Telegram link.');
                        return;
                    }
                },
            ],
            // Support legacy single 'link' field for backward compatibility
            'link' => ['sometimes', 'string', 'max:2048'],
        ]);

        $client = Auth::guard('client')->user();
        
        // Support both 'links' array and legacy 'link' string
        $links = $request->has('links') && is_array($request->input('links'))
            ? array_filter(array_map('trim', $request->input('links')))
            : ($request->has('link') ? [trim($request->input('link'))] : []);
        
        if (empty($links)) {
            return redirect()->back()
                ->withErrors(['links' => 'At least one link is required.'])
                ->withInput();
        }
        
        $autoRenew = $request->has('auto_renew') && $request->input('auto_renew') === '1';

        try {
            $this->purchaseService->purchaseMonthlyFromBalance($client, $subscriptionPlan->id, $links, $autoRenew);

            return redirect()->route('client.subscriptions.index')
                ->with('status', 'subscription-purchased')
                ->with('message', 'Subscription activated successfully!');
        } catch (ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        }
    }

    /**
     * Display active subscriptions with progress tracking.
     */
    public function mySubscriptions(): View
    {
        $client = Auth::guard('client')->user();

        // Get all active subscription quotas for this client
        $quotas = \App\Models\ClientServiceQuota::where('client_id', $client->id)
            ->where('expires_at', '>', now())
            ->with(['subscription', 'service'])
            ->get()
            ->groupBy('subscription_id');

        // Build subscription progress data
        $subscriptions = [];
        foreach ($quotas as $subscriptionId => $quotaGroup) {
            $plan = $quotaGroup->first()->subscription;
            if (!$plan) continue;

            // Get plan services to know initial quantities
            $planServices = \App\Models\SubscriptionPlanService::where('subscription_plan_id', $subscriptionId)
                ->with('service')
                ->get()
                ->keyBy('service_id');

            // Group quotas by link to show progress per link
            $linkGroups = $quotaGroup->groupBy('link');
            
            // Count how many unique links exist for this subscription to reconstruct initial division
            $allLinksForPlan = \App\Models\ClientServiceQuota::where('client_id', $client->id)
                ->where('subscription_id', $subscriptionId)
                ->where('expires_at', '>', now())
                ->distinct()
                ->pluck('link')
                ->toArray();
            $linkCount = count($allLinksForPlan);
            
            foreach ($linkGroups as $link => $linkQuotas) {
                $services = [];
                $totalInitial = 0;
                $totalUsed = 0;
                $totalRemaining = 0;

                // Get the index of this link to determine if it gets remainder
                $linkIndex = array_search($link, $allLinksForPlan);
                if ($linkIndex === false) $linkIndex = 0;

                foreach ($linkQuotas as $quota) {
                    $planService = $planServices->get($quota->service_id);
                    if (!$planService) continue;

                    // Calculate initial quantity per link
                    // When multiple links are used, quantity is divided: floor(planQuantity / linkCount) + remainder
                    $planQuantity = $planService->quantity;
                    
                    // Reconstruct the initial quantity for this specific link
                    // This matches the division logic in SubscriptionPurchaseService
                    $quantityPerLink = $linkCount > 1 
                        ? (int) floor($planQuantity / $linkCount)
                        : $planQuantity;
                    
                    $remainder = $linkCount > 1 ? $planQuantity % $linkCount : 0;
                    $initialQuantity = $quantityPerLink + ($linkIndex < $remainder ? 1 : 0);
                    
                    // Current remaining
                    $remainingQuantity = $quota->quantity_left ?? 0;
                    
                    // Used quantity = initial - remaining
                    $usedQuantity = max(0, $initialQuantity - $remainingQuantity);
                    
                    // Calculate percentage
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

                // Calculate overall percentage
                $overallPercentage = $totalInitial > 0 
                    ? round(($totalUsed / $totalInitial) * 100, 2)
                    : 0;

                $subscriptions[] = [
                    'subscription_id' => $subscriptionId,
                    'plan_name' => $plan->name,
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

        return view('client.subscriptions.my-subscriptions', [
            'subscriptions' => $subscriptions,
        ]);
    }
}

