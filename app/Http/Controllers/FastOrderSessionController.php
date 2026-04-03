<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\FastOrder;
use App\Services\ClientLoginLogServiceInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class FastOrderSessionController extends Controller
{
    public function __construct(
        private ClientLoginLogServiceInterface $clientLoginLogService
    ) {}

    /**
     * Signed URL: one-time web session + flash credentials (after fast order payment).
     */
    public function establish(Request $request, string $uuid): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Invalid or expired link.');
        }

        $fastOrder = FastOrder::query()->where('uuid', $uuid)->first();
        if (! $fastOrder || $fastOrder->status !== FastOrder::STATUS_CONVERTED || ! $fastOrder->client_id) {
            return redirect()->route('login')
                ->withErrors(['email' => __('common.fast_order_login_expired')]);
        }

        $cacheKey = 'fast_order_auto_login:'.$uuid;
        $payload = Cache::pull($cacheKey);
        if (! is_array($payload) || empty($payload['client_id'])) {
            return redirect()->route('login')
                ->withErrors(['email' => __('common.fast_order_login_expired')]);
        }

        if ((int) $payload['client_id'] !== (int) $fastOrder->client_id) {
            return redirect()->route('login')
                ->withErrors(['email' => __('common.fast_order_login_expired')]);
        }

        $client = Client::query()->find($payload['client_id']);
        if (! $client) {
            return redirect()->route('login')
                ->withErrors(['email' => __('common.fast_order_login_expired')]);
        }

        Auth::guard('client')->login($client);
        $request->session()->regenerate();

        Cache::forget('fast_order_return_gate:'.$uuid);

        $client->update(['last_auth' => now()]);
        $this->clientLoginLogService->createLoginLogFromRequest($client, $request);

        $plainPassword = (string) ($payload['plain_password'] ?? '');
        session()->flash('fast_order_credentials', [
            'email' => $client->email,
            'password' => $plainPassword,
            'name' => $client->name,
        ]);

        return redirect()->route('client.orders.index');
    }
}
