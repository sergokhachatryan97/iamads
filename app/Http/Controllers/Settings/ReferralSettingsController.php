<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\UiText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReferralSettingsController extends Controller
{
    public function index(): View
    {
        $bonusPercent = UiText::where('key', 'referral_bonus_percent')->first();

        return view('settings.referral', [
            'bonusPercent' => $bonusPercent?->value ?? '1',
            'isActive' => $bonusPercent?->is_active ?? true,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'referral_bonus_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        UiText::updateOrCreate(
            ['key' => 'referral_bonus_percent'],
            [
                'value' => (string) $validated['referral_bonus_percent'],
                'is_active' => $request->boolean('is_active'),
            ]
        );

        return redirect()->route('staff.settings.referral.index')
            ->with('success', __('Referral settings updated.'));
    }
}
