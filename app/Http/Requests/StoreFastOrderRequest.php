<?php

namespace App\Http\Requests;

use App\Models\Service;
use App\Support\Links\LinkInspectorManager;
use App\Support\TelegramLinkParser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validation for creating a fast order draft. Mirrors StoreOrderRequest rules
 * so the resulting payload is compatible with OrderService::create().
 */
class StoreFastOrderRequest extends FormRequest
{
    private ?Service $cachedService = null;

    public function authorize(): bool
    {
        return true;
    }

    private function service(): ?Service
    {
        if ($this->cachedService !== null) {
            return $this->cachedService;
        }
        $id = $this->input('service_id');
        $this->cachedService = $id ? Service::with('category')->find($id) : null;
        return $this->cachedService;
    }

    protected function prepareForValidation(): void
    {
        $service = $this->service();
        if ($service && $service->service_type === 'custom_comments') {
            $input = $this->all();
            unset($input['targets']);
            $this->replace($input);
        }
    }

    public function rules(): array
    {
        $rules = [
            'category_id' => ['required', 'exists:categories,id'],
            'service_id' => ['required', 'exists:services,id'],
        ];

        $service = $this->service();

        if (!$service) {
            return $rules;
        }

        if ($service->service_type === 'custom_comments') {
            $minQuantity = (int) ($service->min_quantity ?? 1);
            $rules['comments'] = [
                'required',
                'string',
                'min:1',
                function ($attribute, $value, $fail) use ($minQuantity) {
                    if (empty($value)) return;
                    $lines = array_filter(
                        array_map('trim', explode("\n", (string) $value)),
                        fn ($line) => $line !== ''
                    );
                    if (count($lines) < $minQuantity) {
                        $fail("Minimum {$minQuantity} comments required. You have entered " . count($lines) . " comment(s).");
                    }
                },
            ];
            $rules['link'] = [
                'nullable',
                'string',
                'max:2048',
                function ($attribute, $value, $fail) {
                    $value = trim((string) $value);
                    if ($value === '') return;
                    $parsed = TelegramLinkParser::parse($value);
                    $kind = $parsed['kind'] ?? 'unknown';
                    if ($kind === 'unknown') $fail('Invalid Telegram link format.');
                    elseif ($kind === 'special') $fail('Link is not a joinable chat.');
                    elseif ($kind === 'private_post') $fail('Private post links are not supported.');
                },
            ];
        } elseif ($service->template_key === 'invite_subscribers_from_other_channel') {
            $rules['targets'] = ['required', 'array', 'size:1'];
            $rules['targets.0.link'] = ['required', 'string', 'max:2048', $this->telegramLinkRule()];
            $rules['targets.0.quantity'] = ['required', 'integer', 'min:1'];
            $rules['link_2'] = ['required', 'string', 'max:2048', $this->telegramLinkRule()];
        } else {
            $rules['targets'] = ['required', 'array', 'min:1'];
            $driver = $service->category?->link_driver ?? 'generic';
            $manager = app(LinkInspectorManager::class);
            $rules['targets.*.link'] = [
                'required',
                'string',
                'max:2048',
                function ($attribute, $value, $fail) use ($driver, $manager) {
                    $result = $manager->inspect($driver, trim((string) $value));
                    if (!$result['valid'] && $result['error'] !== null) {
                        $fail($result['error']);
                    }
                },
            ];
            $rules['targets.*.quantity'] = ['required', 'integer', 'min:1'];
        }

        if ($service->dripfeed_enabled) {
            if ($this->boolean('dripfeed_enabled', false)) {
                $totalQuantity = 0;
                foreach ($this->input('targets', []) as $t) {
                    if (isset($t['quantity']) && is_numeric($t['quantity'])) {
                        $totalQuantity += (int) $t['quantity'];
                    }
                }
                $rules['dripfeed_quantity'] = ['required', 'integer', 'min:1', 'max:' . max(1, $totalQuantity)];
                $rules['dripfeed_interval'] = ['required', 'integer', 'min:1'];
                $rules['dripfeed_interval_unit'] = ['required', 'string', 'in:minutes,hours,days'];
            }
        }

        if ($service->speed_limit_enabled) {
            $rules['speed_tier'] = ['required', 'string', 'in:normal,fast,super_fast'];
        }

        return $rules;
    }

    private function telegramLinkRule(): \Closure
    {
        return function ($attribute, $value, $fail) {
            $value = trim((string) $value);
            if ($value === '') return;
            $parsed = TelegramLinkParser::parse($value);
            $kind = $parsed['kind'] ?? 'unknown';
            if ($kind === 'unknown') $fail('Invalid Telegram link format.');
            elseif ($kind === 'special') $fail('Link is not a joinable chat.');
            elseif ($kind === 'private_post') $fail('Private post links are not supported.');
        };
    }

    public function messages(): array
    {
        return [
            'targets.required' => 'Please add at least one target.',
            'targets.min' => 'Please add at least one target.',
            'dripfeed_quantity.max' => 'Quantity per step cannot be greater than total order quantity.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $categoryId = (int) $this->input('category_id');
            $serviceId = (int) $this->input('service_id');
            if (!$categoryId || !$serviceId) return;
            $service = $this->service();
            if (!$service || (int) $service->category_id !== $categoryId || !(bool) $service->is_active) {
                $v->errors()->add('service_id', 'The selected service is invalid or inactive.');
            }
        });
    }

    /**
     * Build payload compatible with OrderService::create()
     */
    public function payload(): array
    {
        $validated = $this->validated();
        $service = $this->service();

        $payload = [
            'category_id' => (int) $validated['category_id'],
            'service_id' => (int) $validated['service_id'],
        ];

        if (isset($validated['link'])) {
            $link = trim((string) $validated['link']);
            if ($link !== '') $payload['link'] = $link;
        }
        if (isset($validated['link_2'])) {
            $payload['link_2'] = trim((string) $validated['link_2']);
        }
        if (isset($validated['comments'])) {
            $payload['comments'] = $validated['comments'];
        }

        if ($service && $service->service_type !== 'custom_comments') {
            $targets = $validated['targets'] ?? [];
            $payload['targets'] = collect(is_array($targets) ? $targets : [])
                ->filter(fn ($t) => !empty($t['link']) && isset($t['quantity']))
                ->map(fn ($t) => [
                    'link' => trim((string) $t['link']),
                    'quantity' => (int) $t['quantity'],
                ])
                ->values()
                ->all();
        } else {
            $payload['targets'] = [];
        }

        $dripfeedEnabled = $this->boolean('dripfeed_enabled', false);
        if ($dripfeedEnabled) {
            $payload['dripfeed_enabled'] = true;
            if (isset($validated['dripfeed_quantity'])) $payload['dripfeed_quantity'] = (int) $validated['dripfeed_quantity'];
            if (isset($validated['dripfeed_interval'])) $payload['dripfeed_interval'] = (int) $validated['dripfeed_interval'];
            if (isset($validated['dripfeed_interval_unit'])) $payload['dripfeed_interval_unit'] = (string) $validated['dripfeed_interval_unit'];
        }
        if (isset($validated['speed_tier'])) {
            $payload['speed_tier'] = (string) $validated['speed_tier'];
        }

        return $payload;
    }
}
