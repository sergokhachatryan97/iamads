<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkOrderActionRequest extends FormRequest
{
    /**
     * Maximum batch size for bulk operations.
     */
    private const MAX_BATCH_SIZE = 2000;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['cancel_full', 'cancel_partial', 'refund'])],
            'select_all' => ['required', 'boolean'],
            'selected_ids' => [
                'required_if:select_all,false',
                'array',
                'max:' . self::MAX_BATCH_SIZE,
            ],
            'selected_ids.*' => ['integer', 'exists:orders,id'],
            'excluded_ids' => [
                'array',
            ],
            'excluded_ids.*' => ['integer'],
            'filters' => [
                'required_if:select_all,true',
                'array',
            ],
            'filters.status' => ['nullable', 'string'],
            'filters.search' => ['nullable', 'string', 'max:255'],
            'filters.service_id' => ['nullable', 'integer', 'exists:services,id'],
            'filters.category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'filters.date_from' => ['nullable', 'date'],
            'filters.date_to' => ['nullable', 'date', 'after_or_equal:filters.date_from'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'action.required' => 'Please select an action to perform.',
            'action.in' => 'Invalid action selected.',
            'select_all.required' => 'Selection mode is required.',
            'selected_ids.required_if' => 'Please select at least one order.',
            'selected_ids.max' => 'Cannot process more than ' . self::MAX_BATCH_SIZE . ' orders at once.',
            'selected_ids.*.exists' => 'One or more selected orders do not exist.',
            'filters.required_if' => 'Filters are required when selecting all matching orders.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Parse JSON strings to arrays
        if ($this->has('selected_ids') && is_string($this->input('selected_ids'))) {
            $this->merge([
                'selected_ids' => json_decode($this->input('selected_ids'), true) ?? [],
            ]);
        }

        if ($this->has('excluded_ids')) {
            if (is_string($this->input('excluded_ids'))) {
                $decoded = json_decode($this->input('excluded_ids'), true);
                $this->merge([
                    'excluded_ids' => is_array($decoded) ? $decoded : [],
                ]);
            } elseif (is_array($this->input('excluded_ids'))) {
                // Already an array, keep it
            } else {
                $this->merge(['excluded_ids' => []]);
            }
        }

        if ($this->has('filters') && is_string($this->input('filters'))) {
            $this->merge([
                'filters' => json_decode($this->input('filters'), true) ?? [],
            ]);
        }

        // Ensure arrays are set even if empty
        if (!$this->has('selected_ids')) {
            $this->merge(['selected_ids' => []]);
        }

        if (!$this->has('excluded_ids')) {
            $this->merge(['excluded_ids' => []]);
        }

        if (!$this->has('filters')) {
            $this->merge(['filters' => []]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->boolean('select_all')) {
                // When select_all is true, validate that filters are provided
                if (empty($this->input('filters'))) {
                    $validator->errors()->add('filters', 'Filters are required when selecting all matching orders.');
                }
            } else {
                // When select_all is false, validate that selected_ids are provided
                if (empty($this->input('selected_ids'))) {
                    $validator->errors()->add('selected_ids', 'Please select at least one order.');
                }
            }
        });
    }

    /**
     * Get the maximum batch size.
     */
    public static function getMaxBatchSize(): int
    {
        return self::MAX_BATCH_SIZE;
    }
}

