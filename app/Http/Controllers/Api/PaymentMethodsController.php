<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PaymentMethodsController extends Controller
{
    /**
     * GET /api/payment-methods
     * Returns enabled balance top-up methods.
     */
    public function index(): JsonResponse
    {
        $enabled = config('payments.enabled_providers', ['heleket']);
        $methodsConfig = config('payments.methods', []);

        $methods = [];
        foreach ($enabled as $code) {
            $config = $methodsConfig[$code] ?? [];
            $methods[] = [
                'code' => $code,
                'title' => $config['title'] ?? ucfirst($code),
                'notes' => $config['notes'] ?? null,
            ];
        }

        return response()->json(['methods' => $methods]);
    }
}
