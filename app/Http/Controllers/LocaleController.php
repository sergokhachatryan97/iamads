<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class LocaleController extends Controller
{
    /**
     * Switch application locale and redirect back.
     */
    public function switch(Request $request, string $locale): RedirectResponse
    {
        $locales = config('locales', []);

        if (! array_key_exists($locale, $locales)) {
            abort(404);
        }

        Session::put('locale', $locale);

        return redirect()->back();
    }
}
