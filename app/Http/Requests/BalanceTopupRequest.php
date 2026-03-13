<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BalanceTopupRequest extends FormRequest
{
    public function authorize(): bool
    {
        $client = $this->route('client');
        return $client && (int) $client->id === (int) auth()->guard('client')->id();
    }

    public function rules(): array
    {
        $enabled = config('payments.enabled_providers', ['heleket']);

        return [
            'amount' => ['required', 'numeric', 'min:1', 'max:999999.99'],
            'currency' => ['required', 'string', 'size:3'],
            'provider' => ['required', 'string', Rule::in($enabled)],
        ];
    }

    public function messages(): array
    {
        return [
            'provider.in' => 'The selected payment provider is not available.',
        ];
    }
}
