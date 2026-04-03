<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClientBulkOrderActionRequest extends FormRequest
{
    private const MAX_BATCH_SIZE = 2000;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['cancel_full', 'cancel_partial'])],
            'select_all' => ['required', 'boolean'],
            'selected_ids' => [
                'required_if:select_all,false',
                'array',
                'max:'.self::MAX_BATCH_SIZE,
            ],
            'selected_ids.*' => ['integer', 'exists:orders,id'],
            'excluded_ids' => ['array'],
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
            'filters.source' => ['nullable', 'string', Rule::in(['web', 'api'])],
        ];
    }

    public function messages(): array
    {
        return [
            'action.required' => __('Please select an action to perform.'),
            'action.in' => __('Invalid action selected.'),
            'select_all.required' => __('Selection mode is required.'),
            'selected_ids.required_if' => __('Please select at least one order.'),
            'selected_ids.max' => __('Cannot process more than :max orders at once.', ['max' => self::MAX_BATCH_SIZE]),
            'selected_ids.*.exists' => __('One or more selected orders do not exist.'),
            'filters.required_if' => __('Filters are required when selecting all matching orders.'),
        ];
    }

    protected function prepareForValidation(): void
    {
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
            }
        } elseif (! $this->has('excluded_ids')) {
            $this->merge(['excluded_ids' => []]);
        }

        if ($this->has('filters') && is_string($this->input('filters'))) {
            $this->merge([
                'filters' => json_decode($this->input('filters'), true) ?? [],
            ]);
        }

        if (! $this->has('selected_ids')) {
            $this->merge(['selected_ids' => []]);
        }

        if (! $this->has('filters')) {
            $this->merge(['filters' => []]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->boolean('select_all')) {
                if (empty($this->input('filters'))) {
                    $validator->errors()->add('filters', __('Filters are required when selecting all matching orders.'));
                }
            } elseif (empty($this->input('selected_ids'))) {
                $validator->errors()->add('selected_ids', __('Please select at least one order.'));
            }
        });
    }

    public static function getMaxBatchSize(): int
    {
        return self::MAX_BATCH_SIZE;
    }
}
