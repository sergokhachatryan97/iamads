<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Handles admin (staff) referral tracking across session and cookie.
 * Used to auto-assign clients to the staff member who referred them.
 */
final class AdminReferralService
{
    private const SESSION_KEY = 'admin_referral_code';
    private const COOKIE_NAME = 'admin_ref';
    private const COOKIE_DAYS = 30;

    /**
     * Store the admin referral code in session and cookie.
     */
    public function storeReferral(Request $request, string $code): void
    {
        $request->session()->put(self::SESSION_KEY, $code);
    }

    /**
     * Create a cookie for the referral code (to be attached to the response).
     */
    public function makeCookie(string $code): \Symfony\Component\HttpFoundation\Cookie
    {
        return cookie(self::COOKIE_NAME, $code, self::COOKIE_DAYS * 24 * 60);
    }

    /**
     * Resolve the referring admin from session or cookie.
     * Returns the staff User or null.
     */
    public function resolveReferrer(Request $request): ?User
    {
        $code = $request->session()->get(self::SESSION_KEY)
            ?? $request->cookie(self::COOKIE_NAME);

        if (!$code || !is_string($code)) {
            return null;
        }

        return User::where('referral_code', $code)->first();
    }

    /**
     * Resolve the referring admin's ID. Returns null if not found.
     */
    public function resolveStaffId(Request $request): ?int
    {
        return $this->resolveReferrer($request)?->id;
    }

    /**
     * Clear the stored referral after successful attribution.
     */
    public function clearReferral(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }
}
