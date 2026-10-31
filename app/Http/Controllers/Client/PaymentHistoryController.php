<?php

namespace App\Http\Controllers\Client;

use App\Domain\Payments\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PaymentHistoryController extends Controller
{
    /** Filter tab => stored statuses */
    private const FILTERS = [
        'paid' => [PaymentStatus::PAID->value],
        'pending' => [PaymentStatus::NEW->value, PaymentStatus::PENDING->value],
        'unsuccessful' => [PaymentStatus::FAILED->value, PaymentStatus::EXPIRED->value, PaymentStatus::REFUNDED->value],
    ];

    /** Invoices older than this are not offered for "Continue payment". */
    public const CONTINUE_WINDOW_MINUTES = 60;

    public function index(Request $request): View
    {
        $client = Auth::guard('client')->user();

        $filter = $request->query('status');
        if (! array_key_exists((string) $filter, self::FILTERS)) {
            $filter = 'all';
        }

        $payments = Payment::query()
            ->where('client_id', $client->id)
            ->when($filter !== 'all', fn ($q) => $q->whereIn('status', self::FILTERS[$filter]))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $paidQuery = Payment::query()
            ->where('client_id', $client->id)
            ->where('status', PaymentStatus::PAID->value);

        return view('client.payments.index', [
            'payments' => $payments,
            'filter' => $filter,
            'totalDeposited' => (float) (clone $paidQuery)->sum('amount'),
            'paidCount' => (clone $paidQuery)->count(),
            'lastPaidAt' => (clone $paidQuery)->max('paid_at'),
            'continueSince' => now()->subMinutes(self::CONTINUE_WINDOW_MINUTES),
        ]);
    }
}
