<?php

namespace App\Http\Controllers\Client;

use App\Application\Payments\InitiatePaymentService;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BalanceController extends Controller
{
    public function __construct(
        private InitiatePaymentService $initiateService,
    ) {}
    /**
     * Show the form for adding balance.
     * Displays payment status from query params (status=success|return, provider, order_id).
     * NEVER applies balance change - balance is credited only via webhook.
     */
    public function create(Request $request): View
    {
        $client = auth()->guard('client')->user();
        $payment = null;
        $redirectStatus = $request->query('status');
        $provider = $request->query('provider');
        $orderId = $request->query('order_id');

        if ($orderId && $provider) {
            $payment = Payment::query()
                ->where('order_id', $orderId)
                ->where('provider', $provider)
                ->first();
        }

        $enabled = config('payments.enabled_providers', ['heleket']);
        $methodsConfig = config('payments.methods', []);
        $paymentTypes = [];

        foreach ($enabled as $code) {
            $config = $methodsConfig[$code] ?? [];
            $paymentTypes[$code] = $config['title'] ?? ucfirst($code);
        }

        return view('client.balance.add', [
            'client' => $client,
            'paymentTypes' => $paymentTypes,
            'payment' => $payment,
            'redirectStatus' => $redirectStatus,
        ]);
    }

    /**
     * Store the balance addition request. For Heleket, initiates payment and redirects to pay_url.
     */
    public function store(Request $request): RedirectResponse
    {
        $enabled = config('payments.enabled_providers', ['heleket']);
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:999999.99'],
            'payment_type' => ['required', 'string', 'in:' . implode(',', $enabled)],
        ]);

        $client = auth()->guard('client')->user();
        $provider = $validated['payment_type'];
        $orderId = 'balance_' . $client->id . '_' . Str::ulid();

        try {
            $result = $this->initiateService->run(
                orderId: $orderId,
                amount: (string) $validated['amount'],
                currency: 'USD',
                provider: $provider,
                clientId: $client->id,
            );

            return redirect()->away($result['pay_url']);
        } catch (\Throwable $e) {
            return redirect()->route('client.balance.add')
                ->withInput()
                ->withErrors(['payment_type' => $e->getMessage()]);
        }
    }
}


