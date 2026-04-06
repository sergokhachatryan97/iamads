<?php

namespace App\Http\Requests;

use App\Models\Service;
use App\Services\YouTube\YouTubeExecutionPlanResolver;
use App\Support\Links\LinkInspectorManager;
use App\Support\Links\OrderLinkNormalizer;
use App\Support\TelegramLinkParser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
        $input = $this->all();

        $service = $this->service();
        $driver = $service?->category?->link_driver ?? 'generic';

        if (! empty($input['link'])) {
            $input['link'] = OrderLinkNormalizer::normalize((string) $input['link'], $driver);
        }
        if (! empty($input['link_2'])) {
            $input['link_2'] = OrderLinkNormalizer::normalize((string) $input['link_2'], $driver);
        }
        if (isset($input['targets']) && is_array($input['targets'])) {
            foreach ($input['targets'] as $i => $t) {
                if (! empty($t['link'])) {
                    $input['targets'][$i]['link'] = OrderLinkNormalizer::normalize((string) $t['link'], $driver);
                }
            }
        }

        $this->replace($input);

        $service = $this->service();

        if ($service && $service->service_type === 'custom_comments') {
            $input = $this->all();
            unset($input['targets']); // ✅ completely remove targets
            $this->replace($input);
        }

        // Speed limit: tier comes from service (fast or super_fast only; default fast)
        if ($service && $service->speed_limit_enabled) {
            $input = $this->all();
            $tierMode = $service->speed_limit_tier_mode ?? 'fast';
            $input['speed_tier'] = $tierMode === 'super_fast' ? 'super_fast' : 'fast';
            $this->replace($input);
        }

        if ($service && $service->template_key === 'telegram_premium_folder') {
            $input = $this->all();
            if (! isset($input['targets']) || ! is_array($input['targets'])) {
                $input['targets'] = [['link' => '', 'quantity' => 1]];
            }
            $input['targets'][0]['quantity'] = 1;
            $input['duration_days'] = 30;
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
        if (! $service) {
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
                    if (empty($value)) {
                        return;
                    }

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
                    if (! $result['valid'] && $result['error'] !== null) {
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
        } elseif ($service->template_key === 'telegram_premium_folder') {
            $durationOpts = $service->template()['duration_options'] ?? [3, 14, 30];
            $rules['targets'] = ['required', 'array', 'size:1'];
            $driver = $service->category?->link_driver ?? 'generic';
            $manager = app(LinkInspectorManager::class);
            $rules['targets.0.link'] = [
                'required',
                'string',
                'max:2048',
                function ($attribute, $value, $fail) use ($driver, $manager) {
                    $result = $manager->inspect($driver, trim((string) $value));
                    if (! $result['valid'] && $result['error'] !== null) {
                        $fail($result['error']);
                    }
                },
            ];
            $rules['targets.0.quantity'] = ['nullable', 'integer'];
            $rules['duration_days'] = ['required', 'integer', Rule::in($durationOpts)];
            $rules['comment_text'] = ['nullable', 'string', 'max:500'];
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
                    if (! $result['valid'] && $result['error'] !== null) {
                        $fail($result['error']);
                    }
                },
            ];

            $rules['targets.*.quantity'] = ['required', 'integer', 'min:1'];

            $template = $service->template();
            if ($driver === 'youtube' && $template && YouTubeExecutionPlanResolver::stepsContainCommentCustom($template['steps'] ?? [])) {
                // Combo custom: multiple comments (one per line), same as default comment; allow larger total
                $rules['comment_text'] = ['required', 'string', 'min:1', 'max:10000'];
            } else {
                $rules['comment_text'] = ['nullable', 'string', 'max:500'];
            }

            // App: custom review + star (Service 2)
            $template = $service->template();
            if ($driver === 'app' && ($template['accepts_star_rating'] ?? false)) {
                $rules['comment_text'] = ['required', 'string', 'min:1', 'max:5000'];
                $rules['star_rating'] = ['required', 'integer', 'min:1', 'max:5'];
            } elseif ($driver === 'app' && ($template['accepts_review_comments'] ?? false)) {
                $rules['review_comments'] = [
                    'nullable',
                    'array',
                    'max:50',
                ];
                $rules['review_comments.*'] = [
                    'required',
                    'string',
                    'min:1',
                    'max:500',
                    function ($attribute, $value, $fail) {
                        $trimmed = trim((string) $value);
                        if ($trimmed === '') {
                            $fail('Review comments cannot be empty.');
                        }
                    },
                ];
            }
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
                    'max:'.max(1, $totalQuantity),
                ];
                $rules['dripfeed_interval'] = ['required', 'integer', 'min:1'];
                $rules['dripfeed_interval_unit'] = ['required', 'string', 'in:minutes,hours,days'];
            }
        }

        if ($service->speed_limit_enabled) {
            $rules['speed_tier'] = ['nullable', 'string', 'in:normal,fast,super_fast'];
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
            $serviceId = (int) $this->input('service_id');

            if (! $categoryId || ! $serviceId) {
                return;
            }

            $service = $this->service();

            // Service must exist, belong to category, and be active
            if (
                ! $service ||
                (int) $service->category_id !== $categoryId ||
                ! (bool) $service->is_active
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
            'service_id' => (int) $validated['service_id'],
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

        if (isset($validated['comment_text'])) {
            $payload['comment_text'] = trim((string) $validated['comment_text']);
        }

        if (isset($validated['star_rating'])) {
            $payload['star_rating'] = (int) $validated['star_rating'];
        }

        if (isset($validated['comments'])) {
            $payload['comments'] = $validated['comments'];
        }

        if ($service && $service->service_type !== 'custom_comments') {
            $targets = $validated['targets'] ?? [];
            $payload['targets'] = collect(is_array($targets) ? $targets : [])
                ->filter(fn ($t) => ! empty($t['link']) && isset($t['quantity']))
                ->map(fn ($t) => [
                    'link' => trim((string) $t['link']),
                    'quantity' => (int) $t['quantity'],
                ])
                ->values()
                ->all();
        } elseif (! $service || $service->service_type === 'custom_comments') {
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

        if (($service->template_key ?? '') === 'telegram_premium_folder') {
            $payload['duration_days'] = (int) $validated['duration_days'];
        }

        return $payload;
    }
}
