<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only super_admin can access this
        return $this->user() && $this->user()->hasRole('super_admin');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q'        => ['nullable', 'string', 'max:255'],
            'role'     => ['nullable', 'string', 'max:100'],
            'verified' => ['nullable', Rule::in(['0', '1'])],
            'sort'     => ['nullable', Rule::in(['name', 'email', 'created_at'])],
            'dir'      => ['nullable', Rule::in(['asc', 'desc'])],
            'perPage'  => ['nullable', 'integer', 'min:5', 'max:100'],
        ];
    }

    public function filters(): array
    {
        $data = $this->validated();

        return [
            'q'        => $data['q'] ?? null,
            'role'     => $data['role'] ?? null,
            'verified' => $data['verified'] ?? null,
            'sort'     => $data['sort'] ?? 'created_at',
            'dir'      => $data['dir'] ?? 'desc',
            'perPage'  => $data['perPage'] ?? 15,
        ];
    }
}
