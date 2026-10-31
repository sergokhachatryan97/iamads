<?php

namespace App\Http\Controllers;

use App\Domain\Payments\PaymentStatus;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * Pay page for gateways without a hosted checkout (Baosbabs): shows the wallet
 * address and exact crypto amount, and polls our own payment status. Balance is
 * still credited only by the webhook. Both routes are signed, so the order id
 * alone is not enough to open them (guest fast orders have no session).
 */
class CryptoPaymentController extends Controller
{
    public function show(string $orderId): View|RedirectResponse
    {
        $payment = $this->findPayment($orderId);
        $meta = is_array($payment->meta) ? $payment->meta : [];

        if ($payment->status === PaymentStatus::PAID->value && !empty($meta['url_success'])) {
            return redirect()->away($meta['url_success']);
        }

        $invoice = $meta['invoice'] ?? [];

        return view('payments.crypto', [
            'payment' => $payment,
            'invoice' => $invoice,
            'methodTitle' => config("payments.methods.{$payment->provider}.title", $invoice['asset'] ?? ''),
            'expiresAt' => $this->expiresAt($invoice),
            'status' => $this->displayStatus($payment, $invoice),
            'statusUrl' => URL::signedRoute('payments.crypto.status', ['orderId' => $orderId]),
            'returnUrl' => $meta['url_return'] ?? url('/'),
        ]);
    }

    public function status(string $orderId): JsonResponse
    {
        $payment = $this->findPayment($orderId);
        $meta = is_array($payment->meta) ? $payment->meta : [];
        $status = $this->displayStatus($payment, $meta['invoice'] ?? []);

        return response()->json([
            'status' => $status,
            'redirect_url' => $status === PaymentStatus::PAID->value ? ($meta['url_success'] ?? null) : null,
        ]);
    }

    private function findPayment(string $orderId): Payment
    {
        return Payment::query()
            ->where('order_id', $orderId)
            ->whereIn('provider', array_keys(config('services.baosbabs.assets', [])))
            ->firstOrFail();
    }

    /**
     * Pending invoices past expires_at are shown as expired; the stored status
     * is left to the webhook.
     */
    private function displayStatus(Payment $payment, array $invoice): string
    {
        $expiresAt = $this->expiresAt($invoice);
        if ($payment->status === PaymentStatus::PENDING->value && $expiresAt && $expiresAt->isPast()) {
            return PaymentStatus::EXPIRED->value;
        }

        return $payment->status;
    }

    private function expiresAt(array $invoice): ?Carbon
    {
        if (empty($invoice['expires_at'])) {
            return null;
        }

        try {
            return Carbon::parse($invoice['expires_at']);
        } catch (\Throwable) {
            return null;
        }
    }
}
