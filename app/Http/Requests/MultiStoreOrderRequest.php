<?php

namespace App\Http\Requests;

use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class MultiStoreOrderRequest extends FormRequest
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
            'link' => [
                'required',
                'string',
                'max:2048',
                'regex:/^(https?:\/\/)?(t\.me|telegram\.me|telegram\.dog)\/([A-Za-z0-9_+\/\-]+(\?[A-Za-z0-9=&_%\-]+)?)$|^@[A-Za-z0-9_]{5,32}$/i',
            ],
            'services' => ['required', 'array', 'min:1'],
            'services.*.service_id' => ['required', 'exists:services,id'],
            'services.*.quantity' => ['required', 'integer', 'min:1'],
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
            'link.regex' => 'The link must be a valid Telegram link (t.me/... or @username).',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $categoryId = (int) $this->input('category_id');
            $services = $this->input('services', []);

            if (!$categoryId || !is_array($services) || empty($services)) {
                return;
            }

            // Extract unique service IDs
            $serviceIds = collect($services)
                ->pluck('service_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            if ($serviceIds->isEmpty()) {
                return;
            }

            // Single query: check all services belong to category AND are active
            $validCount = Service::query()
                ->whereIn('id', $serviceIds->all())
                ->where('category_id', $categoryId)
                ->where('is_active', true)
                ->count();

            if ($validCount !== $serviceIds->count()) {
                $v->errors()->add(
                    'services',
                    'One or more selected services are invalid, inactive, or do not belong to the selected category.'
                );
            }
        });
    }

    /**
     * Get normalized payload for OrderService.
     *
     * @return array{category_id: int, link: string, services: array<int, array{service_id: int, quantity: int}>}
     */
    public function payload(): array
    {
        $validated = $this->validated();

        // Normalize services: merge duplicate service_id by summing quantity
        $normalizedServices = collect($validated['services'])
            ->groupBy(fn ($row) => (string) $row['service_id'])
            ->map(function ($rows) {
                $first = $rows->first();
                return [
                    'service_id' => (int) $first['service_id'],
                    'quantity' => (int) $rows->sum(fn ($r) => (int) $r['quantity']),
                ];
            })
            ->values()
            ->all();

        return [
            'category_id' => (int) $validated['category_id'],
            'link' => trim($validated['link']),
            'services' => $normalizedServices,
        ];
    }
}

