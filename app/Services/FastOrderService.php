<?php

namespace App\Services;

use App\Models\Client;
use App\Models\FastOrder;
use App\Models\Service;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FastOrderService
{
    public function __construct(
        private OrderServiceInterface $orderService,
        private ServiceServiceInterface $serviceService,
        private PricingService $pricingService
    ) {}

    /**
     * Create a fast order draft. Payload must already be validated (e.g. via StoreFastOrderRequest).
     */
    public function createDraft(array $payload): FastOrder
    {
        $service = $this->serviceService->getServicesByIdAndCategoryId(
            (int) $payload['service_id'],
            (int) $payload['category_id']
        );

        $totalAmount = $this->computeTotalForGuest($service, $payload);

        return FastOrder::create([
            'uuid' => (string) Str::uuid(),
            'category_id' => (int) $payload['category_id'],
            'service_id' => (int) $payload['service_id'],
            'payload' => $payload,
            'status' => FastOrder::STATUS_DRAFT,
            'payment_status' => FastOrder::PAYMENT_STATUS_UNPAID,
            'total_amount' => $totalAmount,
            'currency' => 'USD',
        ]);
    }

    /**
     * Set payment method and move to pending_payment.
     */
    public function setPaymentMethod(FastOrder $fastOrder, string $method): FastOrder
    {
        if (! in_array($fastOrder->status, [FastOrder::STATUS_DRAFT, FastOrder::STATUS_PENDING_PAYMENT], true)) {
            throw ValidationException::withMessages([
                'fast_order' => 'Fast order is not in a state that allows setting payment method.',
            ]);
        }

        $fastOrder->update([
            'payment_method' => $method,
            'status' => FastOrder::STATUS_PENDING_PAYMENT,
        ]);

        return $fastOrder->fresh();
    }

    /**
     * Simulate payment success: create client, create real order(s), link fast order.
     * Real payment webhook should call this same method with the gateway reference.
     */
    public function markAsPaidAndConvert(FastOrder $fastOrder, ?string $paymentReference = null): array
    {
        if ($fastOrder->status === FastOrder::STATUS_CONVERTED && $fastOrder->order_id !== null) {
            return $this->buildConversionResponse($fastOrder);
        }

        if (! in_array($fastOrder->status, [FastOrder::STATUS_DRAFT, FastOrder::STATUS_PENDING_PAYMENT], true)) {
            throw ValidationException::withMessages([
                'fast_order' => 'Fast order cannot be converted (invalid status).',
            ]);
        }

        return DB::transaction(function () use ($fastOrder, $paymentReference) {
            $plainPassword = Str::random(16);
            $email = $this->generateUniqueGuestEmail();
            $guestName = $this->generateUniqueGuestUsername();

            $client = Client::create([
                'name' => $guestName,
                'email' => $email,
                'password' => $plainPassword,
                'balance' => (float) $fastOrder->total_amount,
                'spent' => 0,
                'discount' => 0,
                'rates' => [],
                'status' => 'active',
            ]);

            $payload = $fastOrder->getOrderPayload();
            $orders = $this->orderService->create($client, $payload, null);

            $firstOrder = $orders->first();
            $fastOrder->update([
                'status' => FastOrder::STATUS_CONVERTED,
                'payment_status' => FastOrder::PAYMENT_STATUS_PAID,
                'payment_reference' => $paymentReference ?? ('simulated_'.Str::random(8)),
                'generated_email' => $email,
                'client_id' => $client->id,
                'order_id' => $firstOrder?->id,
            ]);

            $uuid = $fastOrder->fresh()->uuid;
            $clientId = $client->id;

            DB::afterCommit(function () use ($uuid, $clientId, $plainPassword) {
                $ttl = now()->addMinutes(30);
                Cache::put('fast_order_auto_login:'.$uuid, [
                    'client_id' => $clientId,
                    'plain_password' => $plainPassword,
                ], $ttl);
                Cache::put('fast_order_return_gate:'.$uuid, true, $ttl);
            });

            return $this->buildConversionResponse($fastOrder->fresh(), $plainPassword);
        });
    }

    /**
     * Compute total charge for guest (no client) using same logic as OrderService.
     */
    private function computeTotalForGuest(Service $service, array $payload): float
    {
        if ($service->service_type === 'custom_comments') {
            $commentsInput = $payload['comments'] ?? '';
            $comments = array_filter(
                array_map('trim', explode("\n", (string) $commentsInput)),
                fn ($line) => $line !== ''
            );
            $commentCount = count($comments);
            if ($commentCount < 1) {
                return 0.0;
            }
            $effectiveRate = (float) $this->pricingService->priceForGuest($service);
            $chargePerComment = round($effectiveRate / 1000, 2);

            return round($chargePerComment * $commentCount, 2);
        }

        $targets = $payload['targets'] ?? [];
        if (empty($targets)) {
            return 0.0;
        }

        $finalRate = (float) $this->pricingService->priceForGuest($service);
        $total = 0.0;
        foreach ($targets as $target) {
            $qty = (int) ($target['quantity'] ?? 0);
            $total += round(($qty / 1000) * $finalRate, 2);
        }

        return round($total, 2);
    }

    private function generateUniqueGuestEmail(): string
    {
        $domain = config('fast_order.guest_email_domain', 'fastorder.local');
        $maxAttempts = 50;
        for ($i = 0; $i < $maxAttempts; $i++) {
            $email = 'fastuser_'.Str::lower(Str::random(12)).'@'.$domain;
            if (! Client::where('email', $email)->exists()) {
                return $email;
            }
        }
        throw new \RuntimeException('Unable to generate unique guest email.');
    }

    /**
     * Guest display name; must not collide with existing clients.
     */
    private function generateUniqueGuestUsername(): string
    {
        $maxAttempts = 50;
        for ($i = 0; $i < $maxAttempts; $i++) {
            $name = 'User_'.Str::lower(Str::random(8));
            if (! Client::query()->where('name', $name)->exists()) {
                return $name;
            }
        }

        return 'User_'.Str::lower(Str::random(16));
    }

    private function buildConversionResponse(FastOrder $fastOrder, ?string $plainPassword = null): array
    {
        $fastOrder->load(['client', 'order', 'order.service', 'order.category']);
        $client = $fastOrder->client;
        $order = $fastOrder->order;

        $credentials = [
            'email' => $fastOrder->generated_email,
        ];
        if ($plainPassword !== null) {
            $credentials['password'] = $plainPassword;
        }

        $response = [
            'fast_order' => [
                'uuid' => $fastOrder->uuid,
                'status' => $fastOrder->status,
                'payment_status' => $fastOrder->payment_status,
                'order_id' => $fastOrder->order_id,
                'client_id' => $fastOrder->client_id,
            ],
            'client' => $client ? [
                'id' => $client->id,
                'email' => $client->email,
                'name' => $client->name,
            ] : null,
            'order' => $order ? [
                'id' => $order->id,
                'batch_id' => $order->batch_id,
                'status' => $order->status,
                'quantity' => $order->quantity,
                'charge' => $order->charge,
            ] : null,
            'credentials' => $credentials,
        ];

        if ($plainPassword !== null) {
            $response['auto_login_url'] = URL::temporarySignedRoute(
                'fast-order.session',
                now()->addMinutes(30),
                ['uuid' => $fastOrder->uuid]
            );
        }

        return $response;
    }
}
