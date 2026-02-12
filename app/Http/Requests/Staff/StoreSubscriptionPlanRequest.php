<?php

namespace App\Http\Requests\Staff;

use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubscriptionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->guard('staff')->check();
    }

    public function rules(): array
    {
        return [
            // Basic
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'currency' => ['required', 'string', 'size:3'],
            'is_active' => ['sometimes', 'boolean'],

            // Preview
            'preview_variant' => ['required', 'integer', Rule::in([1, 2, 3])],
            'preview_badge' => ['nullable', 'string', 'max:255'],
            'preview_features' => ['nullable', 'array'],
            'preview_features.*' => ['nullable', 'string', 'max:500'],

            // Pricing (monthly only)
            'prices' => ['required', 'array'],
            'prices.monthly.enabled' => ['nullable', 'boolean'],
            'prices.monthly.price' => [
                'nullable',
                'numeric',
                'min:0',
                // enabled=1 => required
                Rule::requiredIf(fn () => (bool) data_get($this->input('prices', []), 'monthly.enabled')),
            ],

            // Services
            'services' => ['nullable', 'array'],
            'services.*.service_id' => ['required_with:services', 'integer', 'exists:services,id'],
            'services.*.quantity' => [
                'required_with:services.*.service_id',
                'integer',
                'min:1',
                function ($attribute, $value, $fail) {
                    // attribute: services.0.quantity
                    $parts = explode('.', (string) $attribute);
                    $index = $parts[1] ?? null;

                    if ($index === null) {
                        return;
                    }

                    $serviceId = $this->input("services.$index.service_id");
                    if (!$serviceId) {
                        return;
                    }

                    $service = Service::query()
                        ->select('id', 'min_quantity', 'max_quantity', 'category_id', 'is_active')
                        ->find($serviceId);

                    if (!$service) {
                        return;
                    }

                    // service must be active
                    if (!$service->is_active) {
                        $fail('Selected service is not active.');
                        return;
                    }

                    // quantity must respect min/max
                    $min = (int) ($service->min_quantity ?? 1);
                    $max = $service->max_quantity !== null ? (int) $service->max_quantity : null;

                    $qty = (int) $value;

                    if ($qty < $min) {
                        $fail("Quantity must be at least {$min}.");
                        return;
                    }

                    if ($max !== null && $qty > $max) {
                        $fail("Quantity must not exceed {$max}.");
                        return;
                    }
                },
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // ✅ Monthly price must be enabled and > 0
            $prices = (array) $this->input('prices', []);
            $enabled = (bool) data_get($prices, 'monthly.enabled');
            $price = data_get($prices, 'monthly.price');

            $ok = $enabled && is_numeric($price) && (float) $price > 0;
            if (!$ok) {
                $validator->errors()->add('prices', 'Monthly price must be enabled and have a value greater than 0.');
            }

            // ✅ Services: belong to selected category + active + no duplicates
            $categoryId = $this->input('category_id');
            $services = $this->input('services', []);

            if ($categoryId && is_array($services) && !empty($services)) {
                $serviceIdsRaw = collect($services)->pluck('service_id')->filter()->values()->all();

                // duplicates
                if (count($serviceIdsRaw) !== count(array_unique($serviceIdsRaw))) {
                    $validator->errors()->add('services', 'Duplicate services are not allowed.');
                    return;
                }

                $serviceIds = array_map('intval', $serviceIdsRaw);

                if (!empty($serviceIds)) {
                    $validServiceIds = Service::query()
                        ->where('category_id', $categoryId)
                        ->where('is_active', true)
                        ->whereIn('id', $serviceIds)
                        ->pluck('id')
                        ->map(fn ($id) => (int) $id)
                        ->all();

                    $invalid = array_diff($serviceIds, $validServiceIds);

                    if (!empty($invalid)) {
                        $validator->errors()->add('services', 'Some selected services do not belong to the selected category or are not active.');
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Plan name is required.',
            'category_id.required' => 'Please select a category.',
            'category_id.exists' => 'The selected category does not exist.',
            'currency.required' => 'Currency is required.',
            'currency.size' => 'Currency must be 3 characters.',

            'preview_variant.required' => 'Preview variant is required.',
            'preview_variant.in' => 'Preview variant must be 1, 2, or 3.',

            'prices.required' => 'Monthly price must be provided.',

            'prices.monthly.price.required' => 'Monthly price is required when monthly billing is enabled.',
            'prices.monthly.price.numeric' => 'Monthly price must be a number.',
            'prices.monthly.price.min' => 'Monthly price must be 0 or greater.',

            'services.*.service_id.required_with' => 'Service is required.',
            'services.*.service_id.exists' => 'The selected service does not exist.',
            'services.*.quantity.required_with' => 'Quantity is required for each service.',
            'services.*.quantity.integer' => 'Quantity must be an integer.',
            'services.*.quantity.min' => 'Quantity must be at least 1.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // preview_features ensure array
        if ($this->has('preview_features') && !is_array($this->preview_features)) {
            $this->merge([
                'preview_features' => array_values(array_filter(
                    array_map('trim', explode("\n", (string) $this->preview_features)),
                    fn ($v) => $v !== ''
                )),
            ]);
        }

        // Normalize monthly enabled to boolean
        $prices = (array) $this->input('prices', []);
        if (isset($prices['monthly']['enabled'])) {
            $prices['monthly']['enabled'] = (bool) $prices['monthly']['enabled'];
        }
        $this->merge(['prices' => $prices]);
    }
}
