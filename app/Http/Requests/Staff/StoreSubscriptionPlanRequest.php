<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubscriptionPlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->guard('staff')->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'currency' => ['required', 'string', 'size:3'],
            'is_active' => ['nullable', 'boolean'],
            'preview_variant' => ['required', 'integer', 'in:1,2,3'],
            'preview_badge' => ['nullable', 'string', 'max:255'],
            'preview_features' => ['nullable', 'array'],
            'preview_features.*' => ['string', 'max:500'],
            'prices' => ['required', 'array'],
            'prices.daily.enabled' => ['nullable', 'boolean'],
            'prices.daily.price' => ['required_if:prices.daily.enabled,1', 'nullable', 'numeric', 'min:0'],
            'prices.monthly.enabled' => ['nullable', 'boolean'],
            'prices.monthly.price' => ['required_if:prices.monthly.enabled,1', 'nullable', 'numeric', 'min:0'],
            'services' => ['nullable', 'array'],
            'services.*.service_id' => ['required', 'integer', 'exists:services,id'],
            'services.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $prices = $this->input('prices', []);
            
            // At least one price must be enabled and have a value
            $dailyEnabled = !empty($prices['daily']['enabled']) && !empty($prices['daily']['price']);
            $monthlyEnabled = !empty($prices['monthly']['enabled']) && !empty($prices['monthly']['price']);
            
            if (!$dailyEnabled && !$monthlyEnabled) {
                $validator->errors()->add('prices', 'At least one price (daily or monthly) must be enabled and have a value.');
            }

            // Validate services belong to the selected category
            $categoryId = $this->input('category_id');
            if ($categoryId) {
                $services = $this->input('services', []);
                $serviceIds = collect($services)->pluck('service_id')->unique()->toArray();
                
                if (!empty($serviceIds)) {
                    $validServiceIds = \App\Models\Service::where('category_id', $categoryId)
                        ->where('is_active', true)
                        ->pluck('id')
                        ->toArray();
                    
                    $invalidServiceIds = array_diff($serviceIds, $validServiceIds);
                    if (!empty($invalidServiceIds)) {
                        $validator->errors()->add('services', 'Some selected services do not belong to the selected category or are not active.');
                    }

                    // Check for duplicates
                    if (count($serviceIds) !== count(array_unique($serviceIds))) {
                        $validator->errors()->add('services', 'Duplicate services are not allowed.');
                    }
                }
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
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
            'prices.required' => 'At least one price must be provided.',
            'services.*.service_id.required' => 'Service ID is required.',
            'services.*.service_id.exists' => 'The selected service does not exist.',
            'services.*.quantity.required' => 'Quantity is required for each service.',
            'services.*.quantity.min' => 'Quantity must be at least 1.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure preview_features is an array even if empty
        if ($this->has('preview_features') && !is_array($this->preview_features)) {
            $this->merge([
                'preview_features' => array_filter(array_map('trim', explode("\n", $this->preview_features ?? ''))),
            ]);
        }
    }
}
