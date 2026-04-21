<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AdminReferralService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminReferralController extends Controller
{
    public function __construct(
        private AdminReferralService $referralService
    ) {}

    /**
     * Handle admin referral link: /r/{code}
     * Store the code in session + cookie, then redirect to homepage.
     */
    public function __invoke(Request $request, string $code): RedirectResponse
    {
        $admin = User::where('referral_code', $code)->first();

        if ($admin) {
            $this->referralService->storeReferral($request, $code);

            return redirect()->route('register')
                ->withCookie($this->referralService->makeCookie($code));
        }

        // Invalid code — redirect silently
        return redirect()->route('register');
    }
}
