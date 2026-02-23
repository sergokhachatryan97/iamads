<?php

namespace App\Http\Requests\External;

use Illuminate\Foundation\Http\FormRequest;

class ExternalOrderStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'external_order_id' => ['required', 'string', 'max:255'],
            'service_key' => ['required_without:service_id', 'nullable', 'string', 'max:100'],
            'service_id' => ['required_without:service_key', 'nullable', 'integer', 'min:1'],
            'link' => ['required', 'string', 'max:2048'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000000'],
            'speed_tier' => ['nullable', 'string', 'max:50'],
            'meta' => ['nullable', 'array'],
        ];
    }
}
