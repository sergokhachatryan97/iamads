<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\PromoCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PromoCodeController extends Controller
{
    public function apply(Request $request, PromoCodeService $service): RedirectResponse
    {
        $request->validate([
            'promo_code' => ['required', 'string', 'max:32'],
        ]);

        $client = Auth::guard('client')->user();
        $result = $service->apply($request->input('promo_code'), $client);

        if ($result['success']) {
            return back()->with('promo_success', $result['message']);
        }

        return back()->withErrors(['promo_code' => $result['message']]);
    }
}
