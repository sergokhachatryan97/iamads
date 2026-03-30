<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Services\CategoryServiceInterface;
use App\Services\OrderServiceInterface;
use App\Services\PricingService;
use App\Support\Links\LinkInspectorManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Public main page (SMMTool landing) and order-from-home flow.
 */
class HomeController extends Controller
{
    public function __construct(
        private CategoryServiceInterface $categoryService,
        private OrderServiceInterface $orderService,
        private PricingService $pricingService,
    ) {}

    /**
     * Main landing page: categories, services, order form.
     */
    public function index(): View
    {
        $filters = ['for_client' => true];
        $categories = $this->categoryService->getAllCategoriesWithServices($filters);

        $client = Auth::guard('client')->user();
        foreach ($categories as $category) {
            foreach ($category->services as $service) {
                if ($client) {
                    $service->client_price = $this->pricingService->priceForClient($service, $client);
                } else {
                    $service->client_price = $service->rate_per_1000 ?? 0;
                }
                $service->default_rate = $service->rate_per_1000 ?? 0;
            }
        }

        $categoriesForJs = $categories
            ->filter(fn ($c) => $c->services->isNotEmpty())
            ->values()
            ->map(function ($cat) {
                $linkDriver = $cat->link_driver ?? 'generic';
                $linkPlaceholder = match ($linkDriver) {
                    'youtube' => __('home.link_placeholder_youtube'),
                    'max' => __('home.link_placeholder_max'),
                    'whatsapp' => __('home.link_placeholder_tg'),
                    'tiktok' => __('home.link_placeholder_tg'),
                    'instagram' => __('home.link_placeholder_tg'),
                    'facebook' => __('home.link_placeholder_tg'),
                    'url', 'generic' => __('home.link_placeholder_tg'),
                    default => __('home.link_placeholder_tg'),
                };

                return [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'icon' => $cat->icon ?? '',
                    'linkType' => $linkDriver,
                    'services' => $cat->services->map(function ($s) use ($linkPlaceholder) {
                        return [
                            'id' => $s->id,
                            'name' => $s->name,
                            'icon' => $s->icon ?? '',
                            'description' => $s->description ?? '',
                            'placeholder' => $linkPlaceholder,
                            'min' => (int) ($s->min_quantity ?? 100),
                            'max' => (int) ($s->max_quantity ?? 100000),
                            'step' => (int) max(1, (int) ($s->increment ?? 100)),
                            'pricePer1000' => (float) ($s->client_price ?? $s->rate_per_1000 ?? 0),
                        ];
                    })->values()->all(),
                ];
            })
            ->values()
            ->all();

        $firstCategoryName = $categoriesForJs[0]['name'] ?? null;
        $translations = [
            'hero_title' => __('home.hero_title', ['category' => ':category']),
            'hero_select_category' => __('home.hero_select_category'),
            'hero_subtitle' => __('home.hero_subtitle'),
            'hero_subtitle_empty' => __('home.hero_subtitle_empty'),
            'services_of' => __('home.services_of', ['category' => ':category']),
            'link_hint' => __('home.link_hint'),
            'link_error' => __('home.link_error'),
            'link_error_youtube' => __('home.link_error_youtube'),
            'link_error_vk' => __('home.link_error_vk'),
            'link_error_max' => __('home.link_error_max'),
            'link_error_other' => __('home.link_error_other'),
            'qty_error' => __('home.qty_error'),
            'total_links' => __('home.total_links'),
            'estimated_charge' => __('home.estimated_charge'),
            'cancel' => __('home.cancel'),
            'create_order' => __('home.create_order'),
            'place_order' => __('home.place_order'),
            'order_success' => __('home.order_success'),
        ];

        return view('home.index', [
            'categories' => $categories,
            'categoriesForJs' => $categoriesForJs,
            'translations' => $translations,
            'firstCategoryName' => $firstCategoryName,
            'contactTelegram' => config('contact.telegram', 'Just_tg'),
            'contactEmail' => config('contact.email', ''),
            'service' => null,
        ]);
    }

    /**
     * Contacts page with Help Desk, Telegram, and Cooperation cards.
     */
    public function contacts(): View
    {
        return view('contacts', [
            'contactTelegram' => config('contact.telegram', ''),
            'contactEmail' => config('contact.email', ''),
            'contactCooperationEmail' => config('contact.cooperation_email', ''),
        ]);
    }

    /**
     * Submit order from main page. If guest: store in session and redirect to login.
     * If authenticated: create order and redirect to orders.
     */
    public function createOrder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'link' => ['required', 'string', 'max:2048'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $service = Service::with('category')->find($validated['service_id']);
        if (! $service || ! $service->is_active || (int) $service->category_id !== (int) $validated['category_id']) {
            return redirect()->route('home')->withErrors(['service_id' => __('Invalid service.')]);
        }

        $driver = $service->category?->link_driver ?? 'generic';
        $manager = app(LinkInspectorManager::class);
        $linkResult = $manager->inspect($driver, trim($validated['link']));
        if (! $linkResult['valid']) {
            return redirect()->route('home')->withErrors(['link' => $linkResult['error']]);
        }

        // Custom_comments and invite_subscribers need full order form - redirect there
        if ($service->service_type === 'custom_comments'
            || ($service->template_key ?? '') === 'invite_subscribers_from_other_channel'
            || ($service->template_key ?? '') === 'telegram_premium_folder') {
            return redirect()
                ->route('client.orders.create', ['service_id' => $service->id, 'category_id' => $service->category_id])
                ->with('info', __('This service requires the full order form.'));
        }

        if (Auth::guard('client')->check()) {
            return $this->createOrderNow($request, $validated, $service);
        }

        session(['pending_order' => [
            'service_id' => (int) $validated['service_id'],
            'category_id' => (int) $validated['category_id'],
            'link' => trim($validated['link']),
            'quantity' => (int) $validated['quantity'],
        ]]);
        session()->put('url.intended', route('home.complete-order'));

        return redirect()->route('login')->with('info', __('Please log in or register to complete your order.'));
    }

    /**
     * After login: complete pending order from session and redirect to orders.
     */
    public function completeOrder(): RedirectResponse
    {
        if (! Auth::guard('client')->check()) {
            return redirect()->route('login');
        }

        $pending = session('pending_order');
        if (! $pending) {
            return redirect()->route('client.orders.index');
        }

        $service = Service::find($pending['service_id']);
        if (! $service || ! $service->is_active) {
            return redirect()->route('client.orders.index')->withErrors(['service' => __('Service is no longer available.')]);
        }

        $client = Auth::guard('client')->user();
        $payload = [
            'category_id' => $pending['category_id'],
            'service_id' => $pending['service_id'],
            'targets' => [
                ['link' => $pending['link'], 'quantity' => $pending['quantity']],
            ],
        ];

        try {
            $this->orderService->create($client, $payload);
            session()->forget('pending_order');

            return redirect()->route('client.orders.index')->with('status', 'order-created');
        } catch (ValidationException $e) {
            if ($e->validator->errors()->has('balance')) {
                return redirect()->route('client.balance.add', ['return_to' => 'order'])
                    ->with('info', __('Insufficient balance. Please add funds to complete your order.'));
            }

            return redirect()->route('home')->withErrors($e->errors());
        } catch (\Throwable $e) {
            return redirect()->route('home')->withErrors(['order' => $e->getMessage()]);
        }
    }

    private function createOrderNow(Request $request, array $validated, Service $service): RedirectResponse
    {
        $client = Auth::guard('client')->user();
        $payload = [
            'category_id' => (int) $validated['category_id'],
            'service_id' => (int) $validated['service_id'],
            'targets' => [
                ['link' => trim($validated['link']), 'quantity' => (int) $validated['quantity']],
            ],
        ];

        if ($service->speed_limit_enabled) {
            $payload['speed_tier'] = 'normal';
        }

        try {
            $this->orderService->create($client, $payload);

            return redirect()->route('client.orders.index')->with('status', 'order-created');
        } catch (ValidationException $e) {
            if ($e->validator->errors()->has('balance')) {
                session(['pending_order' => [
                    'service_id' => (int) $validated['service_id'],
                    'category_id' => (int) $validated['category_id'],
                    'link' => trim($validated['link']),
                    'quantity' => (int) $validated['quantity'],
                ]]);

                return redirect()->route('client.balance.add', ['return_to' => 'order'])
                    ->with('info', __('Insufficient balance. Please add funds to complete your order.'));
            }

            return redirect()->route('home')->withErrors($e->errors());
        } catch (\Throwable $e) {
            return redirect()->route('home')->withErrors(['order' => $e->getMessage()]);
        }
    }
}
