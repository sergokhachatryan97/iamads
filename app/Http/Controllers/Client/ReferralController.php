<?php

namespace App\Http\Controllers\Client;

use App\Application\Payments\ReferralBonusService;
use App\Http\Controllers\Controller;
use App\Models\ClientTransaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ReferralController extends Controller
{
    public function index(ReferralBonusService $referralBonusService): View
    {
        $client = Auth::guard('client')->user();
        $client->ensureReferralCode();

        $referralLink = url('/register?ref=' . $client->referral_code);
        $bonusPercent = $referralBonusService->getBonusPercent();

        $referralsCount = $client->referrals()->count();

        $totalEarned = (float) ClientTransaction::query()
            ->where('client_id', $client->id)
            ->where('type', ClientTransaction::TYPE_REFERRAL_BONUS)
            ->sum('amount');

        $recentEarnings = ClientTransaction::query()
            ->where('client_id', $client->id)
            ->where('type', ClientTransaction::TYPE_REFERRAL_BONUS)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return view('client.referral.index', [
            'referralLink' => $referralLink,
            'bonusPercent' => $bonusPercent,
            'referralsCount' => $referralsCount,
            'totalEarned' => $totalEarned,
            'recentEarnings' => $recentEarnings,
        ]);
    }
}
