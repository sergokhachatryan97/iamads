<?php

namespace App\Http\Requests\External;

use App\Models\Service;
use App\Support\Links\OrderLinkNormalizer;
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
        if ($this->has('service') && ! $this->has('service_id')) {
            $this->merge(['service_id' => $this->input('service')]);
        }
        $link = $this->input('link');
        if (! empty($link)) {
            $serviceId = (int) ($this->input('service_id') ?? $this->input('service') ?? 0);
            $service = $serviceId > 0 ? Service::with('category')->find($serviceId) : null;
            $driver = $service?->category?->link_driver ?? 'generic';
            $this->merge(['link' => OrderLinkNormalizer::normalize(trim((string) $link), $driver)]);
        }
    }
}
