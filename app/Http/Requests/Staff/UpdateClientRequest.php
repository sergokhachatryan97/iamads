<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClientRequest extends FormRequest
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
            'discount' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rates' => ['nullable', 'array'],
            'rates.*.type' => ['required_with:rates.*.enabled', 'in:fixed,percent'],
            'rates.*.value' => ['required_with:rates.*.enabled', 'numeric', 'min:0'],
            'rates.*.enabled' => ['sometimes', 'boolean'],
            'social_media' => ['nullable', 'array'],
            'social_media.*.platform' => ['required_with:social_media.*.username', 'string', 'in:telegram,facebook,instagram'],
            'social_media.*.username' => ['required_with:social_media.*.platform', 'string', 'max:255'],
        ];

        // Only super_admin can update staff_id
        if (auth()->guard('staff')->check() && auth()->guard('staff')->user()->hasRole('super_admin')) {
            $rules['staff_id'] = ['nullable', 'integer', 'exists:users,id'];
        }

        return $rules;
    }
}


