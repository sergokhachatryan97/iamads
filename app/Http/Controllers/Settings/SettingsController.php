<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class SettingsController extends Controller
{
    /**
     * Display the settings index page.
     */
    public function index(): View
    {
        return view('settings.index');
    }
}
