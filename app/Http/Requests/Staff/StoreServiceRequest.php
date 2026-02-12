<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceRequest extends FormRequest
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
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'mode' => ['required', 'string', 'in:manual,auto'],
            'service_type' => ['nullable', 'string'],
            'target_type' => ['nullable', 'string', Rule::in(['bot', 'channel', 'group'])],
            'template_key' => ['nullable', 'string', Rule::in(array_keys(config('telegram_service_templates', [])))],
            'duration_days' => ['nullable', 'integer', 'min:1'],
            'dripfeed_enabled' => ['nullable', 'boolean'],
            'speed_limit_enabled' => ['nullable', 'boolean'],
            'speed_multiplier_fast' => ['nullable', 'numeric', 'min:1', 'max:10'],
            'speed_multiplier_super_fast' => ['nullable', 'numeric', 'min:1', 'max:10'],
            'rate_multiplier_fast' => ['nullable', 'numeric', 'min:1', 'max:10'],
            'rate_multiplier_super_fast' => ['nullable', 'numeric', 'min:1', 'max:10'],
            'requires_subscription' => ['nullable', 'boolean'],
            'required_subscription_template_key' => ['nullable', 'string', Rule::in(array_keys(config('telegram_service_templates', [])))],
            'user_can_cancel' => ['nullable', 'boolean'],
            'rate_per_1000' => ['required', 'numeric', 'min:0'],
            'service_cost_per_1000' => ['nullable', 'numeric', 'min:0'],
            'min_quantity' => ['required', 'integer', 'min:1'],
            'max_quantity' => ['required', 'integer', 'min:1', 'gte:min_quantity'],
            'deny_link_duplicates' => ['nullable', 'boolean'],
            'deny_duplicates_days' => ['required_if:deny_link_duplicates,1', 'nullable', 'integer', 'min:0', 'max:65535'],
            'increment' => ['nullable', 'integer', 'min:0'],
            'start_count_parsing_enabled' => ['nullable', 'boolean'],
            'count_type' => ['required_if:start_count_parsing_enabled,1', 'nullable', 'string'],
            'auto_complete_enabled' => ['nullable', 'boolean'],
            'refill_enabled' => ['nullable', 'boolean'],
        ];

        // Validate duration_days if template requires it
        $templateKey = $this->input('template_key');
        if ($templateKey) {
            $template = config("telegram_service_templates.{$templateKey}");
            if ($template && ($template['requires_duration_days'] ?? false)) {
                $rules['duration_days'] = ['required', 'integer', 'min:1'];
            }
        }

        // Validate speed multipliers if speed limit is enabled
        if ($this->boolean('speed_limit_enabled')) {
            $rules['speed_multiplier_fast'] = ['required', 'numeric', 'min:1', 'max:10'];
            $rules['speed_multiplier_super_fast'] = ['required', 'numeric', 'min:1', 'max:10'];
            // Rate multipliers are always required (default to 1.000)
            $rules['rate_multiplier_fast'] = ['required', 'numeric', 'min:1', 'max:10'];
            $rules['rate_multiplier_super_fast'] = ['required', 'numeric', 'min:1', 'max:10'];
        } else {
            // If speed limit disabled, force rate multipliers to 1.000
            $rules['rate_multiplier_fast'] = ['nullable', 'numeric', 'min:1', 'max:10'];
            $rules['rate_multiplier_super_fast'] = ['nullable', 'numeric', 'min:1', 'max:10'];
        }

        // Validate required_subscription_template_key if requires_subscription is enabled
        if ($this->boolean('requires_subscription')) {
            $rules['required_subscription_template_key'] = ['required', 'string', Rule::in(array_keys(config('telegram_service_templates', [])))];
        }

        return $rules;
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Auto complete requires parsing to be enabled
            if ($this->input('auto_complete_enabled') && !$this->input('start_count_parsing_enabled')) {
                $validator->errors()->add('auto_complete_enabled', 'Auto Complete can only be enabled when Start count parsing is enabled.');
            }

            // Speed limit and dripfeed are mutually exclusive
            if ($this->boolean('speed_limit_enabled') && $this->boolean('dripfeed_enabled')) {
                $validator->errors()->add('speed_limit_enabled', 'Speed limit and Dripfeed cannot be enabled at the same time.');
                $validator->errors()->add('dripfeed_enabled', 'Speed limit and Dripfeed cannot be enabled at the same time.');
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
            'name.required' => 'Service name is required.',
            'category_id.required' => 'Please select a category.',
            'category_id.exists' => 'The selected category does not exist.',
            'rate_per_1000.required' => 'Rate per 1000 is required.',
            'rate_per_1000.numeric' => 'Rate per 1000 must be a number.',
            'rate_per_1000.min' => 'Rate per 1000 must be at least 0.',
            'min_quantity.required' => 'Min quantity is required.',
            'min_quantity.min' => 'Min quantity must be at least 1.',
            'max_quantity.required' => 'Max quantity is required.',
            'max_quantity.min' => 'Max quantity must be at least 1.',
            'max_quantity.gte' => 'Max quantity must be greater than or equal to min quantity.',
            'deny_duplicates_days.required_if' => 'Deny duplicates days is required when Deny link duplicates is enabled.',
            'count_type.required_if' => 'Count type is required when Start count parsing is enabled.',
            'increment.min' => 'Increment must be at least 0.',
        ];
    }
}
