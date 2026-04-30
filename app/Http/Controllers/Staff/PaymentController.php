<?php

namespace App\Http\Controllers\Staff;

use App\Application\Payments\CreditPaymentToBalanceService;
use App\Domain\Payments\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;

/**
 * Staff management of payments: list and change status.
 * Marking as PAID credits the client's balance.
 */
class PaymentController extends Controller
{
    public function __construct(
        private CreditPaymentToBalanceService $creditService,
    ) {}

    /**
     * List payments with optional filters.
     */
    public function index(Request $request): View
    {
        $query = Payment::query()
            ->with('client:id,name,email');

        $currentUser = Auth::guard('staff')->user();
        if ($currentUser && !$currentUser->canAccessAllClients()) {
            $query->whereHas('client', fn ($q) => $q->where('staff_id', $currentUser->id));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }
        if ($request->filled('provider')) {
            $query->where('provider', $request->provider);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');

        if (!in_array($sortDir, ['asc', 'desc'])) {
            $sortDir = 'desc';
        }

        $allowedSortColumns = ['id', 'created_at', 'amount', 'status', 'paid_at', 'provider'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'created_at';
        }

        $query->orderBy($sortBy, $sortDir);

        $payments = $query->paginate(25)->withQueryString();
        $statuses = array_map(fn (PaymentStatus $s) => $s->value, PaymentStatus::cases());

        return view('staff.payments.index', [
            'payments' => $payments,
            'statuses' => $statuses,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
        ]);
    }

    /**
     * Update payment status. If changing to PAID, credits client balance.
     */
    public function updateStatus(Request $request, Payment $payment): RedirectResponse
    {
        $currentUser = Auth::guard('staff')->user();
        if ($currentUser && !$currentUser->canAccessAllClients()) {
            $client = $payment->client;
            if (!$client || $client->staff_id !== $currentUser->id) {
                return redirect()->back()
                    ->withErrors(['error' => 'You do not have permission to update this payment.']);
            }
        }

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:' . implode(',', array_map(fn (PaymentStatus $s) => $s->value, PaymentStatus::cases()))],
        ]);

        $newStatus = $validated['status'];
        $wasPaid = $payment->status === PaymentStatus::PAID->value;
        $willBePaid = $newStatus === PaymentStatus::PAID->value;

        $payment->status = $newStatus;
        if ($willBePaid && $payment->paid_at === null) {
            $payment->paid_at = now();
        }
        $payment->save();

        if ($willBePaid && !$wasPaid && $payment->client_id) {
            $credited = $this->creditService->credit($payment);
            if ($credited) {
                return redirect()->back()
                    ->with('success', __('Payment marked as paid and balance credited.'));
            }
        }

        return redirect()->back()
            ->with('success', __('Payment status updated.'));
    }
}
