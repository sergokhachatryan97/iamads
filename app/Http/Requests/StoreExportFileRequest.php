<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExportFileRequest extends FormRequest
{
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
        $module = $this->input('module');
        $moduleConfig = config("exports.modules.{$module}");

        $rules = [
            'module' => ['required', 'string', Rule::in(array_keys(config('exports.modules', [])))],
            'format' => ['required', 'string', Rule::in(config('exports.allowed_formats', ['csv', 'xlsx']))],
            'filters' => ['nullable', 'array'],
            'columns' => ['nullable', 'array'],
            'columns.*' => ['string'],
        ];

        // Validate filters if module config exists
        if ($moduleConfig) {
            $allowedFilters = $moduleConfig['allowed_filters'] ?? [];
            foreach ($allowedFilters as $filter) {
                $rules["filters.{$filter}"] = ['nullable'];
            }

            // Validate columns
            $allowedColumns = array_keys($moduleConfig['allowed_columns'] ?? []);
            if (!empty($allowedColumns)) {
                $rules['columns.*'] = [Rule::in($allowedColumns)];
            }
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'module.required' => 'Please select a module to export.',
            'module.in' => 'Invalid module selected.',
            'format.required' => 'Please select a file format.',
            'format.in' => 'Invalid file format. Allowed formats: CSV, XLSX.',
            'columns.*.in' => 'One or more selected columns are not allowed.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure filters and columns are arrays
        if ($this->has('filters') && !is_array($this->input('filters'))) {
            $this->merge([
                'filters' => [],
            ]);
        }


        if (is_string($this->input('columns'))) {
            $decoded = json_decode($this->input('columns'), true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $this->merge(['columns' => $decoded]);
            }
        }

        // Use default columns if none provided
        if (empty($this->input('columns'))) {
            $module = $this->input('module');
            $moduleConfig = config("exports.modules.{$module}");
            if ($moduleConfig && isset($moduleConfig['default_columns'])) {
                $this->merge([
                    'columns' => $moduleConfig['default_columns'],
                ]);
            }
        }
    }
}
