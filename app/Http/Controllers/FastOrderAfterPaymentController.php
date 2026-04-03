<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FastOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class FastOrderAfterPaymentController extends Controller
{
    /**
     * Payment return URL: wait for conversion if needed, then redirect to signed session link.
     */
    public function __invoke(string $uuid): RedirectResponse|View
    {
        $fastOrder = FastOrder::query()->where('uuid', $uuid)->first();
        if (! $fastOrder) {
            return redirect()->route('home')
                ->withErrors(['order' => __('Order not found.')]);
        }

        if ($fastOrder->status !== FastOrder::STATUS_CONVERTED || ! $fastOrder->client_id) {
            return view('fast-order.processing', ['uuid' => $uuid]);
        }

        if (! Cache::pull('fast_order_return_gate:'.$uuid)) {
            return redirect()->route('login')
                ->with('info', __('common.fast_order_sign_in_orders'));
        }

        $signed = URL::temporarySignedRoute(
            'fast-order.session',
            now()->addMinutes(10),
            ['uuid' => $uuid]
        );

        return redirect()->to($signed);
    }
}
