<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\UpdateAccountRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class AccountController extends Controller
{
    /**
     * Display the client's account information form.
     */
    public function edit(Request $request): View
    {
        return view('client.account.edit', [
            'client' => Auth::guard('client')->user(),
        ]);
    }

    /**
     * Update the client's account information.
     */
    public function update(UpdateAccountRequest $request): RedirectResponse
    {
        $client = Auth::guard('client')->user();
        $client->fill($request->validated());
        $client->save();

        return Redirect::route('client.account.edit')->with('status', 'account-updated');
    }
}


