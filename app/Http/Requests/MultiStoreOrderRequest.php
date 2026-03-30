<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Models\Service;
use App\Support\Links\LinkInspectorManager;
use App\Support\TelegramSystemManagedTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class MultiStoreOrderRequest extends FormRequest
{
    private ?Category $cachedCategory = null;

    protected function prepareForValidation(): void
    {
        $link = $this->input('link');
        if (! empty($link)) {
            $this->merge(['link' => $this->ensureLinkHasScheme(trim((string) $link))]);
        }
    }

    private function ensureLinkHasScheme(string $link): string
    {
        if ($link === '' || preg_match('#^https?://#i', $link)) {
            return $link;
        }

        return 'https://'.$link;
    }

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
    private function category(): ?Category
    {
        if ($this->cachedCategory !== null) {
            return $this->cachedCategory;
        }
        $id = $this->input('category_id');
        $this->cachedCategory = $id ? Category::find($id) : null;

        return $this->cachedCategory;
    }

    public function rules(): array
    {
        $rules = [
            'category_id' => ['required', 'exists:categories,id'],
            'link' => ['required', 'string', 'max:2048'],
            'services' => ['required', 'array', 'min:1'],
            'services.*.service_id' => ['required', 'exists:services,id'],
            'services.*.quantity' => ['required', 'integer'],
        ];

        $category = $this->category();
        if ($category) {
            $driver = $category->link_driver ?? 'generic';
            $manager = app(LinkInspectorManager::class);
            $rules['link'][] = function ($attribute, $value, $fail) use ($driver, $manager) {
                $result = $manager->inspect($driver, trim((string) $value));
                if (! $result['valid'] && $result['error'] !== null) {
                    $fail($result['error']);
                }
            };
        }

        $serviceIds = collect($this->input('services', []))->pluck('service_id')->filter()->unique()->values();
        if ($serviceIds->isNotEmpty()) {
            $rules['services.*.comments'] = ['nullable', 'string', 'max:50000'];
            $services = Service::with('category')->whereIn('id', $serviceIds)->get();
            $rules['services.*.star_rating'] = ['nullable', 'integer', 'min:1', 'max:5'];
        }

        return $rules;
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $categoryId = (int) $this->input('category_id');
            $services = $this->input('services', []);

            if (! $categoryId || ! is_array($services) || empty($services)) {
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

            // custom_comments: validate min comment lines per row; quantity for non-custom_comments
            $services = Service::whereIn('id', $serviceIds->all())->get()->keyBy('id');
            foreach ($this->input('services', []) as $i => $row) {
                $svc = $services->get((int) ($row['service_id'] ?? 0));
                if ($svc && $svc->service_type === 'custom_comments') {
                    $commentsInput = $row['comments'] ?? null;
                    $lines = $commentsInput ? array_values(array_filter(array_map('trim', explode("\n", (string) $commentsInput)), fn ($l) => $l !== '')) : [];
                    $minLines = (int) ($svc->min_quantity ?? 1);
                    if (count($lines) < $minLines) {
                        $v->errors()->add("services.{$i}.comments", "Minimum {$minLines} comment(s) required for this service. You have ".count($lines).'.');
                    }
                }
            }
            $blockedSystemManaged = false;
            foreach ($this->input('services', []) as $row) {
                $svc = $services->get((int) ($row['service_id'] ?? 0));
                if ($svc && TelegramSystemManagedTemplate::isSystemManagedTemplateKey($svc->template_key)) {
                    $blockedSystemManaged = true;
                    break;
                }
            }
            if ($blockedSystemManaged) {
                $v->errors()->add(
                    'services',
                    'One or more selected services must be ordered from the single-service form.'
                );
            }
            foreach ($this->input('services', []) as $i => $row) {
                $svc = $services->get((int) ($row['service_id'] ?? 0));
                if ($svc && $svc->service_type !== 'custom_comments') {
                    $qty = (int) ($row['quantity'] ?? 0);
                    if ($qty < 1) {
                        $v->errors()->add("services.{$i}.quantity", 'Quantity must be at least 1.');
                    }
                }
                if ($svc && (bool) (($svc->template() ?? [])['accepts_star_rating'] ?? false)) {
                    $starRating = $row['star_rating'] ?? null;
                    if ($starRating === null || $starRating === '' || (int) $starRating < 1 || (int) $starRating > 5) {
                        $v->errors()->add("services.{$i}.star_rating", 'Star rating (1-5) is required for this service.');
                    }
                }
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

        // Normalize services: merge duplicate service_id, preserve comments from first row
        $normalizedServices = collect($validated['services'])
            ->groupBy(fn ($row) => (string) $row['service_id'])
            ->map(function ($rows) {
                $first = $rows->first();
                $res = [
                    'service_id' => (int) $first['service_id'],
                    'quantity' => (int) $rows->sum(fn ($r) => (int) ($r['quantity'] ?? 0)),
                ];
                if (! empty(trim((string) ($first['comments'] ?? '')))) {
                    $res['comments'] = trim((string) $first['comments']);
                }
                if (isset($first['star_rating']) && $first['star_rating'] >= 1 && $first['star_rating'] <= 5) {
                    $res['star_rating'] = (int) $first['star_rating'];
                }

                return $res;
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
