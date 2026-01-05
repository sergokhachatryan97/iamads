<?php

namespace App\Http\Requests;


use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['nullable', 'exists:clients,id'],
            'category_id' => ['required', 'exists:categories,id'],
            'service_id' => [
                'required',
                'exists:services,id',
                Rule::exists('services', 'id')->where(function ($query) {
                    $query->where('category_id', $this->category_id)
                        ->where('is_active', true);
                }),
            ],
            'targets' => ['required', 'array', 'min:1'],
            'targets.*.link' => ['required', 'string', 'max:2048'],
            'targets.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
