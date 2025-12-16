<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexClientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // All authenticated staff can access clients
        // Permission filtering (super_admin sees all, others see only their own) is handled in the repository/service layer
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
            'q'                    => ['nullable', 'string', 'max:255'],
            'staff_id'             => ['nullable', 'string'], // Can be integer (user id) or 'null' string for filtering
            'no_staff'             => ['nullable', 'boolean'], // Filter for clients without staff
            'balance_min'          => ['nullable', 'numeric', 'min:0'],
            'balance_max'          => ['nullable', 'numeric', 'min:0'],
            'spent_min'            => ['nullable', 'numeric', 'min:0'],
            'spent_max'            => ['nullable', 'numeric', 'min:0'],
            'date_filter'          => ['nullable', Rule::in(['today', 'yesterday', '7days', '30days', '90days', 'custom'])],
            'date_from'            => ['nullable', 'date', 'required_if:date_filter,custom'],
            'date_to'              => ['nullable', 'date', 'required_if:date_filter,custom', 'after_or_equal:date_from'],
            'created_at_filter'    => ['nullable', Rule::in(['today', 'yesterday', '7days', '30days', '90days', 'custom'])],
            'created_at_from'      => ['nullable', 'date', 'required_if:created_at_filter,custom'],
            'created_at_to'        => ['nullable', 'date', 'required_if:created_at_filter,custom', 'after_or_equal:created_at_from'],
            'sort'           => ['nullable', Rule::in(['id', 'name', 'email', 'balance', 'spent', 'last_auth', 'created_at'])],
            'dir'            => ['nullable', Rule::in(['asc', 'desc'])],
            'perPage'        => ['nullable', 'integer', 'min:5', 'max:100'],
        ];
    }

    public function filters(): array
    {
        $data = $this->validated();

        return [
            'q'                 => $data['q'] ?? null,
            'staff_id'         => $data['staff_id'] ?? null,
            'no_staff'         => $data['no_staff'] ?? null,
            'balance_min'      => $data['balance_min'] ?? null,
            'balance_max'      => $data['balance_max'] ?? null,
            'spent_min'        => $data['spent_min'] ?? null,
            'spent_max'        => $data['spent_max'] ?? null,
            'date_filter'      => $data['date_filter'] ?? null,
            'date_from'        => $data['date_from'] ?? null,
            'date_to'          => $data['date_to'] ?? null,
            'created_at_filter' => $data['created_at_filter'] ?? null,
            'created_at_from'  => $data['created_at_from'] ?? null,
            'created_at_to'    => $data['created_at_to'] ?? null,
            'sort'             => $data['sort'] ?? 'created_at',
            'dir'              => $data['dir'] ?? 'desc',
            'perPage'          => $data['perPage'] ?? 15,
        ];
    }
}

