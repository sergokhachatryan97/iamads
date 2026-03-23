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
            'service' => ['required', 'integer', 'min:1'],
            'link' => ['required', 'string', 'max:2048'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000000'],
            'speed_tier' => ['nullable', 'string', 'max:50'],
            'meta' => ['nullable', 'array'],
        ];
    }

    /**
     * Prepare the data for validation (service => service_id for OrderService).
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('service') && !$this->has('service_id')) {
            $this->merge(['service_id' => $this->input('service')]);
        }
        $link = $this->input('link');
        if (!empty($link)) {
            $this->merge(['link' => $this->ensureLinkHasScheme(trim((string) $link))]);
        }
    }

    private function ensureLinkHasScheme(string $link): string
    {
        if ($link === '' || preg_match('#^https?://#i', $link)) {
            return $link;
        }
        return 'https://' . $link;
    }
}
