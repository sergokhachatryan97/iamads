<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\PromoCode;
use App\Services\PromoCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PromoCodeController extends Controller
{
    private function authorizeSuperAdmin(): void
    {
        $user = Auth::guard('staff')->user();
        if (!$user || !$user->hasRole('super_admin')) {
            abort(403, 'Only Super Admin can manage promo codes.');
        }
    }

    public function index(): View
    {
        $this->authorizeSuperAdmin();

        $promoCodes = PromoCode::with('creator')
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('staff.promo-codes.index', compact('promoCodes'));
    }

    public function create(): View
    {
        $this->authorizeSuperAdmin();

        return view('staff.promo-codes.create');
    }

    public function store(Request $request, PromoCodeService $service): RedirectResponse
    {
        $this->authorizeSuperAdmin();

        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:32', 'unique:promo_codes,code', 'alpha_num'],
            'reward_value' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'max_uses_per_client' => ['required', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'is_active' => ['boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        if (!empty($validated['code'])) {
            $validated['code'] = strtoupper($validated['code']);
        }

        $promo = $service->create($validated, Auth::guard('staff')->id());

        return redirect()
            ->route('staff.promo-codes.index')
            ->with('success', "Promo code {$promo->code} created successfully.");
    }

    public function toggleActive(PromoCode $promoCode): RedirectResponse
    {
        $this->authorizeSuperAdmin();

        $promoCode->update(['is_active' => !$promoCode->is_active]);

        $status = $promoCode->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Promo code {$promoCode->code} {$status}.");
    }

    public function destroy(PromoCode $promoCode): RedirectResponse
    {
        $this->authorizeSuperAdmin();

        $code = $promoCode->code;
        $promoCode->delete();

        return back()->with('success', "Promo code {$code} deleted.");
    }
}
