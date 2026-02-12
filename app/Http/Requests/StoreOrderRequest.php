<?php

namespace App\Http\Requests;

use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware/guard
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'exists:categories,id'],
            'service_id' => ['required', 'exists:services,id'],
            'targets' => ['required', 'array', 'min:1'],
            'targets.*.link' => [
                'required',
                'string',
                'max:2048',
                'regex:/^(https?:\/\/)?(t\.me|telegram\.me|telegram\.dog)\/([A-Za-z0-9_+\/\-]+(\?[A-Za-z0-9=&_%\-]+)?)$|^@[A-Za-z0-9_]{5,32}$/i',
            ],
            'targets.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'targets.*.link.regex' => 'The link must be a valid Telegram link (t.me/... or @username).',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $categoryId = (int) $this->input('category_id');
            $serviceId = (int) $this->input('service_id');

            if (!$categoryId || !$serviceId) {
                return;
            }

            // Single query: check service belongs to category AND is active
            $exists = Service::query()
                ->whereKey($serviceId)
                ->where('category_id', $categoryId)
                ->where('is_active', true)
                ->exists();

            if (!$exists) {
                $v->errors()->add(
                    'service_id',
                    'The selected service is invalid or inactive.'
                );
            }
        });
    }

    /**
     * Get normalized payload for OrderService.
     *
     * @return array{category_id: int, service_id: int, targets: array<int, array{link: string, quantity: int}>}
     */
    public function payload(): array
    {
        $validated = $this->validated();

        // Normalize targets: trim links and cast quantities to int
        $normalizedTargets = collect($validated['targets'])
            ->map(function ($target) {
                return [
                    'link' => trim($target['link']),
                    'quantity' => (int) $target['quantity'],
                ];
            })
            ->values()
            ->all();

        return [
            'category_id' => (int) $validated['category_id'],
            'service_id' => (int) $validated['service_id'],
            'targets' => $normalizedTargets,
        ];
    }
}

