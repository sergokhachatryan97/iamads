<?php

namespace App\Http\Requests;

use App\Models\Service;
use App\Support\Links\LinkInspectorManager;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreOrderRequest extends FormRequest
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
            unset($input['targets']); // ✅ completely remove targets
            $this->replace($input);
        }

        // When service has single speed tier, set speed_tier automatically (no selection needed)
        if ($service && $service->speed_limit_enabled) {
            $tierMode = $service->speed_limit_tier_mode ?? 'both';
            if ($tierMode === 'fast' || $tierMode === 'super_fast') {
                $input = $this->all();
                $input['speed_tier'] = $tierMode;
                $this->replace($input);
            }
        }
    }

    public function rules(): array
    {
        $rules = [
            'category_id' => ['required', 'exists:categories,id'],
            'service_id'  => ['required', 'exists:services,id'],
        ];

        $service = $this->service();
        if (!$service) {
            return $rules;
        }

        // ---------- custom_comments ----------
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

                    $count = count($lines);
                    if ($count < $minQuantity) {
                        $fail("Minimum {$minQuantity} comments required. You have entered {$count} comment(s).");
                    }
                },
            ];

            $driver = $service->category?->link_driver ?? 'generic';
            $manager = app(LinkInspectorManager::class);

            $rules['link'] = [
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
        } elseif ($service->template_key === 'invite_subscribers_from_other_channel') {
            // ---------- invite_subscribers_from_other_channel (2 links: source + target) ----------
            $rules['targets'] = ['required', 'array', 'size:1'];
            $rules['targets.0.link'] = [
                'required',
                'string',
                'max:2048',
                function ($attribute, $value, $fail) {
                    $parsed = TelegramLinkParser::parse(trim((string) $value));
                    $kind = $parsed['kind'] ?? 'unknown';
                    if ($kind === 'unknown') {
                        $fail('Invalid target link format.');
                        return;
                    }
                    if ($kind === 'special') {
                        $fail('Target link is not a joinable chat.');
                        return;
                    }
                    if ($kind === 'private_post') {
                        $fail('Private post links are not supported.');
                        return;
                    }
                },
            ];
            $rules['targets.0.quantity'] = ['required', 'integer', 'min:1'];
            $rules['link_2'] = [
                'required',
                'string',
                'max:2048',
                function ($attribute, $value, $fail) {
                    $value = trim((string) $value);
                    if ($value === '') {
                        $fail('Source channel link is required.');
                        return;
                    }
                    $parsed = TelegramLinkParser::parse($value);
                    $kind = $parsed['kind'] ?? 'unknown';
                    if ($kind === 'unknown') {
                        $fail('Invalid source channel link format.');
                        return;
                    }
                    if ($kind === 'special') {
                        $fail('Source link is not a joinable chat.');
                        return;
                    }
                    if ($kind === 'private_post') {
                        $fail('Private post links are not supported.');
                        return;
                    }
                },
            ];
        } else {
            // ---------- Regular services (link validation by category link_driver) ----------
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

        // ---------- Dripfeed rules ----------
        if ($service->dripfeed_enabled) {
            $dripfeedEnabled = $this->boolean('dripfeed_enabled', false);

            if ($dripfeedEnabled) {
                $totalQuantity = 0;

                $targets = $this->input('targets', []);
                if (is_array($targets)) {
                    foreach ($targets as $t) {
                        if (isset($t['quantity']) && is_numeric($t['quantity'])) {
                            $totalQuantity += (int) $t['quantity'];
                        }
                    }
                }

                $rules['dripfeed_quantity'] = [
                    'required',
                    'integer',
                    'min:1',
                    'max:' . max(1, $totalQuantity),
                ];
                $rules['dripfeed_interval'] = ['required', 'integer', 'min:1'];
                $rules['dripfeed_interval_unit'] = ['required', 'string', 'in:minutes,hours,days'];
            }
        }

        // ---------- Speed tier ----------
        // When both tiers: client must select. When single tier: we set it in prepareForValidation
        $tierMode = $service->speed_limit_tier_mode ?? 'both';
        if ($service->speed_limit_enabled) {
            if ($tierMode === 'both') {
                $rules['speed_tier'] = ['required', 'string', 'in:normal,fast,super_fast'];
            } else {
                $rules['speed_tier'] = ['nullable', 'string', 'in:normal,fast,super_fast'];
            }
        }

        return $rules;
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
            $serviceId  = (int) $this->input('service_id');

            if (!$categoryId || !$serviceId) return;

            $service = $this->service();

            // Service must exist, belong to category, and be active
            if (
                !$service ||
                (int) $service->category_id !== $categoryId ||
                !(bool) $service->is_active
            ) {
                $v->errors()->add('service_id', 'The selected service is invalid or inactive.');
            }
        });
    }

    /**
     * Payload for OrderService
     */
    public function payload(): array
    {
        $validated = $this->validated();
        $service = $this->service();

        $payload = [
            'category_id' => (int) $validated['category_id'],
            'service_id'  => (int) $validated['service_id'],
        ];

        if (isset($validated['link'])) {
            $link = trim((string) $validated['link']);
            if ($link !== '') {
                $payload['link'] = $link;
            }
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
                    'link'     => trim((string) $t['link']),
                    'quantity' => (int) $t['quantity'],
                ])
                ->values()
                ->all();
        } elseif (!$service || $service->service_type === 'custom_comments') {
            $payload['targets'] = [];
        }

        // Dripfeed fields only if toggle is ON
        $dripfeedEnabled = $this->boolean('dripfeed_enabled', false);
        if ($dripfeedEnabled) {
            $payload['dripfeed_enabled'] = true;

            if (isset($validated['dripfeed_quantity'])) {
                $payload['dripfeed_quantity'] = (int) $validated['dripfeed_quantity'];
            }
            if (isset($validated['dripfeed_interval'])) {
                $payload['dripfeed_interval'] = (int) $validated['dripfeed_interval'];
            }
            if (isset($validated['dripfeed_interval_unit'])) {
                $payload['dripfeed_interval_unit'] = (string) $validated['dripfeed_interval_unit'];
            }
        }

        if (isset($validated['speed_tier'])) {
            $payload['speed_tier'] = (string) $validated['speed_tier'];
        }

        return $payload;
    }
}
