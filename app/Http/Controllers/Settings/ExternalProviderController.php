<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ExternalProvider;
use App\Services\ExternalPanel\ExternalPanelClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExternalProviderController extends Controller
{
    public function index(): View
    {
        $providers = ExternalProvider::orderBy('name')->get();

        return view('settings.external-providers.index', compact('providers'));
    }

    public function create(): View
    {
        return view('settings.external-providers.form');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/', 'unique:external_providers,code'],
            'name' => ['required', 'string', 'max:255'],
            'base_url' => ['required', 'url', 'max:500'],
            'api_key' => ['required', 'string', 'max:500'],
            'timeout' => ['nullable', 'integer', 'min:5', 'max:120'],
        ]);

        $data['timeout'] = $data['timeout'] ?? 30;
        $data['is_active'] = true;

        ExternalProvider::create($data);

        return redirect()->route('staff.settings.external-providers.index')
            ->with('success', __('Provider added.'));
    }

    public function edit(ExternalProvider $external_provider): View
    {
        return view('settings.external-providers.form', ['provider' => $external_provider]);
    }

    public function update(Request $request, ExternalProvider $external_provider): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/', "unique:external_providers,code,{$external_provider->id}"],
            'name' => ['required', 'string', 'max:255'],
            'base_url' => ['required', 'url', 'max:500'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'timeout' => ['nullable', 'integer', 'min:5', 'max:120'],
        ]);

        // Keep existing key if not provided
        if (empty($data['api_key'])) {
            unset($data['api_key']);
        }

        $data['timeout'] = $data['timeout'] ?? 30;

        $external_provider->update($data);

        return redirect()->route('staff.settings.external-providers.index')
            ->with('success', __('Provider updated.'));
    }

    public function toggle(ExternalProvider $external_provider): RedirectResponse
    {
        $external_provider->update(['is_active' => ! $external_provider->is_active]);

        $state = $external_provider->is_active ? __('enabled') : __('disabled');

        return redirect()->route('staff.settings.external-providers.index')
            ->with('success', __('Provider :state.', ['state' => $state]));
    }

    public function testConnection(ExternalProvider $external_provider): JsonResponse
    {
        try {
            $client = new ExternalPanelClient(
                $external_provider->base_url,
                $external_provider->api_key,
                $external_provider->timeout,
            );

            $balance = $client->balance();

            return response()->json([
                'ok' => true,
                'balance' => $balance['balance'] ?? null,
                'currency' => $balance['currency'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function destroy(ExternalProvider $external_provider): RedirectResponse
    {
        $external_provider->delete();

        return redirect()->route('staff.settings.external-providers.index')
            ->with('success', __('Provider deleted.'));
    }
}
