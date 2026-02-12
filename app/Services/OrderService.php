<?php

namespace App\Services;

use App\Jobs\SendOrderToProvider;
use App\Models\Category;
use App\Models\Client;
use App\Models\Order;
use App\Models\Service;
use App\Models\ClientTransaction;
use App\Models\ClientServiceQuota;
use App\Models\ClientServiceLimit;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService implements OrderServiceInterface
{
    public function __construct(
        private ServiceServiceInterface $serviceService,
        private PricingService $pricingService
    ) {}

    /**
     * Create new orders (one per target link).
     */
    public function create(Client $client, array $data, ?int $createdBy = null): Collection
    {
        return DB::transaction(function () use ($client, $data, $createdBy) {
            $now = now();

            // 1) Lock client row
            $client = Client::lockForUpdate()->findOrFail($client->id);

            // 2) Load service
            $service = $this->serviceService->getServicesByIdAndCategoryId(
                (int) $data['service_id'],
                (int) $data['category_id']
            );

            // 3) Normalize targets
            $targets = $this->normalizeTargets($data['targets'] ?? []);

            // 4) Effective limits once
            $serviceLimit = ClientServiceLimit::query()
                ->where('client_id', $client->id)
                ->where('service_id', $service->id)
                ->first();

            $effectiveMinQty = $serviceLimit?->min_quantity ?? $service->min_quantity;
            $effectiveMaxQty = $serviceLimit?->max_quantity ?? ($service->max_quantity ?? null);
            $effectiveIncrement = $serviceLimit?->increment ?? ($service->increment ?? 0);

            // 5) Duplicate links check in ONE query (if enabled)
            if ($service->deny_link_duplicates) {
                $this->validateNoDuplicateLinksBatch($service, $targets, $now);
            }

            // 6) Rate once
            $effectiveRate = (float) $this->pricingService->priceForClient($service, $client);

            // 7) Validate each row + calculate charges
            $rowCharges = [];
            $totalCharge = 0.0;
            $totalQty = 0;

            foreach ($targets as $index => $target) {
                $qty = (int) $target['quantity'];

                $this->validateServiceQuantityRules(
                    $qty,
                    $index,
                    (int) $effectiveMinQty,
                    $effectiveMaxQty !== null ? (int) $effectiveMaxQty : null,
                    (int) $effectiveIncrement
                );

                // NOTE: keeping your original formula (/100).
                $charge = round(($qty / 100) * $effectiveRate, 2);

                $cost = $service->service_cost_per_1000 !== null
                    ? round(($qty / 100) * (float) $service->service_cost_per_1000, 2)
                    : null;

                $rowCharges[] = [
                    'link' => $target['link'],
                    'quantity' => $qty,
                    'charge' => $charge,
                    'cost' => $cost,
                ];

                $totalCharge += $charge;
                $totalQty += $qty;
            }

            // 8) Decide payment source
            $paymentSource = Order::PAYMENT_SOURCE_BALANCE;
            $subscriptionId = null;

            $quota = ClientServiceQuota::query()
                ->where('client_id', $client->id)
                ->where('service_id', $service->id)
                ->where('expires_at', '>', $now)
                ->where(function ($q) use ($targets) {
                    $q->whereNull('orders_left')
                        ->orWhere('orders_left', '>=', count($targets));
                })
                ->where(function ($q) use ($totalQty) {
                    $q->whereNull('quantity_left')
                        ->orWhere('quantity_left', '>=', $totalQty);
                })
                ->lockForUpdate()
                ->first();

            if ($quota) {
                $paymentSource = Order::PAYMENT_SOURCE_SUBSCRIPTION;
                $subscriptionId = $quota->subscription_id;

                if ($quota->orders_left !== null) {
                    $quota->orders_left -= count($targets);
                }
                if ($quota->quantity_left !== null) {
                    $quota->quantity_left -= $totalQty;
                }
                $quota->save();
            } else {
                if ($client->balance < $totalCharge) {
                    throw ValidationException::withMessages([
                        'balance' => 'Insufficient balance. Please top up.',
                    ]);
                }

                $client->balance -= $totalCharge;
                $client->save();
            }

            // 9) Create orders
            $batchId = (string) \Illuminate\Support\Str::uuid();

            $orders = [];
            foreach ($rowCharges as $row) {
                $orders[] = Order::create([
                    'batch_id' => $batchId,
                    'client_id' => $client->id,
                    'created_by' => $createdBy,
                    'category_id' => (int) $data['category_id'],
                    'service_id' => $service->id,
                    'link' => $row['link'],
                    'payment_source' => $paymentSource,
                    'subscription_id' => $subscriptionId,
                    'charge' => $row['charge'],
                    'cost' => $row['cost'],
                    'quantity' => $row['quantity'],
                    'start_count' => null,
                    'delivered' => 0,
                    'remains' => $row['quantity'],
                    'status' => Order::STATUS_AWAITING,
                    'mode' => 'manual',
                ]);
            }

            if ($paymentSource === Order::PAYMENT_SOURCE_BALANCE) {
                ClientTransaction::create([
                    'client_id' => $client->id,
                    'order_id' => null,
                    'amount' => -$totalCharge,
                    'type' => ClientTransaction::TYPE_ORDER_CHARGE,
                ]);
            }


            foreach ($orders as $order) {
                SendOrderToProvider::dispatch($order->id)->afterCommit();
            }

            return Collection::make($orders)->load(['service', 'category']);
        });
    }

    /**
     * Refund an order.
     */
    public function refund(Order $order, int $newDelivered, string $newStatus): Order
    {
        if (!in_array($newStatus, [Order::STATUS_PARTIAL, Order::STATUS_CANCELED], true)) {
            throw ValidationException::withMessages([
                'status' => 'Status must be either "partial" or "canceled" for refunds.',
            ]);
        }

        return DB::transaction(function () use ($order, $newDelivered, $newStatus) {
            $now = now();

            $client = Client::lockForUpdate()->findOrFail($order->client_id);

            $delivered = max(0, $newDelivered);
            $remains = max(0, $order->quantity - $delivered);

            $undelivered = $newStatus === Order::STATUS_CANCELED
                ? $order->quantity
                : max(0, $order->quantity - $delivered);

            if ($order->payment_source === Order::PAYMENT_SOURCE_BALANCE && $order->quantity > 0) {
                $refund = round($order->charge * ($undelivered / $order->quantity), 2);

                if ($refund > 0) {
                    $client->balance += $refund;
                    $client->save();

                    ClientTransaction::create([
                        'client_id' => $client->id,
                        'order_id' => $order->id,
                        'amount' => $refund,
                        'type' => ClientTransaction::TYPE_REFUND,
                    ]);
                }
            } elseif ($order->payment_source === Order::PAYMENT_SOURCE_SUBSCRIPTION) {
                $quota = ClientServiceQuota::query()
                    ->where('client_id', $client->id)
                    ->where('subscription_id', $order->subscription_id)
                    ->where('service_id', $order->service_id)
                    ->where('expires_at', '>', $now)
                    ->lockForUpdate()
                    ->first();

                if ($quota) {
                    if ($newStatus === Order::STATUS_CANCELED && $quota->orders_left !== null) {
                        $quota->orders_left += 1;
                    }
                    if ($quota->quantity_left !== null) {
                        $quota->quantity_left += $undelivered;
                    }
                    $quota->save();
                }
            }

            $order->update([
                'delivered' => $delivered,
                'remains' => $remains,
                'status' => $newStatus,
            ]);

            return $order->fresh();
        });
    }

    /**
     * Cancel an order fully.
     */
    public function cancelFull(Order $order, Client $client): Order
    {
        if ($order->client_id !== $client->id) {
            throw ValidationException::withMessages([
                'order' => 'You can only cancel your own orders.',
            ]);
        }

        $service = $order->service;
        if (!$service || !$service->user_can_cancel) {
            throw ValidationException::withMessages([
                'order' => 'This service does not allow cancellation.',
            ]);
        }

        if (!in_array($order->status, [Order::STATUS_AWAITING, Order::STATUS_PENDING, Order::STATUS_PROCESSING], true)) {
            throw ValidationException::withMessages([
                'order' => 'This order cannot be canceled. Only awaiting or pending orders can be fully canceled.',
            ]);
        }

        return DB::transaction(function () use ($order, $client) {
            $now = now();

            $client = Client::lockForUpdate()->findOrFail($client->id);
            $order = Order::lockForUpdate()->findOrFail($order->id);

            $refund = $order->charge;

            $order->update([
                'status' => Order::STATUS_CANCELED,
                'delivered' => 0,
                'remains' => $order->quantity,
            ]);

            if ($order->payment_source === Order::PAYMENT_SOURCE_BALANCE && $refund > 0) {
                $client->balance += $refund;
                $client->save();

                ClientTransaction::create([
                    'client_id' => $client->id,
                    'order_id' => $order->id,
                    'amount' => $refund,
                    'type' => ClientTransaction::TYPE_REFUND,
                ]);
            } elseif ($order->payment_source === Order::PAYMENT_SOURCE_SUBSCRIPTION) {
                $quota = ClientServiceQuota::query()
                    ->where('client_id', $client->id)
                    ->where('subscription_id', $order->subscription_id)
                    ->where('service_id', $order->service_id)
                    ->where('expires_at', '>', $now)
                    ->lockForUpdate()
                    ->first();

                if ($quota) {
                    if ($quota->orders_left !== null) {
                        $quota->orders_left += 1;
                    }
                    if ($quota->quantity_left !== null) {
                        $quota->quantity_left += $order->quantity;
                    }
                    $quota->save();
                }
            }

            return $order->fresh();
        });
    }

    /**
     * Cancel an order partially.
     */
    public function cancelPartial(Order $order, Client $client): Order
    {
        if ($order->client_id !== $client->id) {
            throw ValidationException::withMessages([
                'order' => 'You can only cancel your own orders.',
            ]);
        }

        $service = $order->service;
        if (!$service || !$service->user_can_cancel) {
            throw ValidationException::withMessages([
                'order' => 'This service does not allow cancellation.',
            ]);
        }

        if (!in_array($order->status, [Order::STATUS_IN_PROGRESS, Order::STATUS_PROCESSING], true)) {
            throw ValidationException::withMessages([
                'order' => 'This order cannot be partially canceled. Only in_progress or processing orders can be partially canceled.',
            ]);
        }

        return DB::transaction(function () use ($order, $client) {
            $now = now();

            $client = Client::lockForUpdate()->findOrFail($client->id);
            $order = Order::lockForUpdate()->findOrFail($order->id);

            $undelivered = max(0, $order->quantity - $order->delivered);

            $refund = 0.0;
            if ($undelivered > 0 && $order->quantity > 0) {
                $refund = round($order->charge * ($undelivered / $order->quantity), 2);
            }

            $order->update([
                'status' => Order::STATUS_PARTIAL,
                'remains' => max(0, $order->quantity - $order->delivered),
            ]);

            if ($order->payment_source === Order::PAYMENT_SOURCE_BALANCE && $refund > 0) {
                $client->balance += $refund;
                $client->save();

                ClientTransaction::create([
                    'client_id' => $client->id,
                    'order_id' => $order->id,
                    'amount' => $refund,
                    'type' => ClientTransaction::TYPE_REFUND,
                ]);
            } elseif ($order->payment_source === Order::PAYMENT_SOURCE_SUBSCRIPTION && $undelivered > 0) {
                $quota = ClientServiceQuota::query()
                    ->where('client_id', $client->id)
                    ->where('subscription_id', $order->subscription_id)
                    ->where('service_id', $order->service_id)
                    ->where('expires_at', '>', $now)
                    ->lockForUpdate()
                    ->first();

                if ($quota && $quota->quantity_left !== null) {
                    $quota->quantity_left += $undelivered;
                    $quota->save();
                }
            }

            return $order->fresh();
        });
    }

    /**
     * Create multiple orders for one link with multiple services.
     */
    public function createMultiServiceOrders(Client $client, array $data, ?int $createdBy = null): Collection
    {
        return DB::transaction(function () use ($client, $data, $createdBy) {
            $now = now();

            $client = Client::lockForUpdate()->findOrFail($client->id);

            $categoryId = (int) $data['category_id'];
            $link = trim((string) $data['link']);

            Category::findOrFail($categoryId);

            $servicesInput = collect($data['services'] ?? [])
                ->groupBy(fn ($r) => (string) ($r['service_id'] ?? ''))
                ->map(function ($rows) {
                    $first = $rows->first();
                    return [
                        'service_id' => (int) $first['service_id'],
                        'quantity' => (int) $rows->sum(fn ($x) => (int) ($x['quantity'] ?? 0)),
                    ];
                })
                ->values()
                ->all();

            if (empty($servicesInput)) {
                throw ValidationException::withMessages([
                    'services' => 'At least one service is required.',
                ]);
            }

            $serviceIds = array_column($servicesInput, 'service_id');

            $services = Service::query()
                ->whereIn('id', $serviceIds)
                ->where('category_id', $categoryId)
                ->where('is_active', true)
                ->get()
                ->keyBy('id');

            foreach ($serviceIds as $serviceId) {
                if (!$services->has($serviceId)) {
                    throw ValidationException::withMessages([
                        'services' => "Service ID {$serviceId} is invalid, inactive, or does not belong to the selected category.",
                    ]);
                }
            }

            $limits = ClientServiceLimit::query()
                ->where('client_id', $client->id)
                ->whereIn('service_id', $serviceIds)
                ->get()
                ->keyBy('service_id');

            $orderData = [];
            $totalBalanceCharge = 0.0;
            $quotaMap = [];

            foreach ($servicesInput as $index => $serviceInput) {
                $serviceId = (int) $serviceInput['service_id'];
                $service = $services->get($serviceId);
                $qty = (int) $serviceInput['quantity'];

                $serviceLimit = $limits->get($serviceId);

                $effectiveMinQty = $serviceLimit?->min_quantity ?? $service->min_quantity;
                $effectiveMaxQty = $serviceLimit?->max_quantity ?? ($service->max_quantity ?? null);
                $effectiveIncrement = $serviceLimit?->increment ?? ($service->increment ?? 0);

                $this->validateServiceQuantityRules(
                    $qty,
                    $index,
                    (int) $effectiveMinQty,
                    $effectiveMaxQty !== null ? (int) $effectiveMaxQty : null,
                    (int) $effectiveIncrement
                );

                if ($service->deny_link_duplicates) {
                    $this->validateNoDuplicateLink($service, $link);
                }

                $effectiveRate = (float) $this->pricingService->priceForClient($service, $client);

                $charge = round(($qty / 100) * $effectiveRate, 2);
                $cost = $service->service_cost_per_1000 !== null
                    ? round(($qty / 100) * (float) $service->service_cost_per_1000, 2)
                    : null;

                $paymentSource = Order::PAYMENT_SOURCE_BALANCE;
                $subscriptionId = null;

                if (!isset($quotaMap[$serviceId])) {
                    $quota = ClientServiceQuota::query()
                        ->where('client_id', $client->id)
                        ->where('service_id', $serviceId)
                        ->where('expires_at', '>', $now)
                        ->where(function ($q) {
                            $q->whereNull('orders_left')->orWhere('orders_left', '>=', 1);
                        })
                        ->where(function ($q) use ($qty) {
                            $q->whereNull('quantity_left')->orWhere('quantity_left', '>=', $qty);
                        })
                        ->lockForUpdate()
                        ->first();

                    if ($quota) {
                        $quotaMap[$serviceId] = $quota;
                    }
                }

                if (isset($quotaMap[$serviceId])) {
                    $quota = $quotaMap[$serviceId];
                    $paymentSource = Order::PAYMENT_SOURCE_SUBSCRIPTION;
                    $subscriptionId = $quota->subscription_id;

                    // Decrement quota
                    if ($quota->orders_left !== null) {
                        $quota->orders_left -= 1;
                    }
                    if ($quota->quantity_left !== null) {
                        $quota->quantity_left -= $qty;
                    }
                } else {
                    $totalBalanceCharge += $charge;
                }

                $orderData[] = [
                    'service_id' => $serviceId,
                    'quantity' => $qty,
                    'charge' => $charge,
                    'cost' => $cost,
                    'payment_source' => $paymentSource,
                    'subscription_id' => $subscriptionId,
                ];
            }

            if ($totalBalanceCharge > 0) {
                if ($client->balance < $totalBalanceCharge) {
                    throw ValidationException::withMessages([
                        'balance' => 'Insufficient balance. Please top up.',
                    ]);
                }

                $client->balance -= $totalBalanceCharge;
                $client->save();
            }

            foreach ($quotaMap as $quota) {
                $quota->save();
            }

            $batchId = (string) \Illuminate\Support\Str::uuid();

            $orders = [];
            foreach ($orderData as $row) {
                $orders[] = Order::create([
                    'batch_id' => $batchId,
                    'client_id' => $client->id,
                    'created_by' => $createdBy,
                    'category_id' => $categoryId,
                    'service_id' => $row['service_id'],
                    'link' => $link,
                    'payment_source' => $row['payment_source'],
                    'subscription_id' => $row['subscription_id'],
                    'charge' => $row['charge'],
                    'cost' => $row['cost'],
                    'quantity' => $row['quantity'],
                    'start_count' => null,
                    'delivered' => 0,
                    'remains' => $row['quantity'],
                    'status' => Order::STATUS_AWAITING,
                    'mode' => 'manual',
                ]);
            }

            if ($totalBalanceCharge > 0) {
                ClientTransaction::create([
                    'client_id' => $client->id,
                    'order_id' => null,
                    'amount' => -$totalBalanceCharge,
                    'type' => ClientTransaction::TYPE_ORDER_CHARGE,
                ]);
            }

            foreach ($orders as $order) {
                \App\Jobs\SendOrderToProvider::dispatch($order->id)->afterCommit();
            }

            return new Collection($orders);
        });
    }

    /**
     * Normalize and validate targets.
     */
    private function normalizeTargets(array $targets): array
    {
        if (empty($targets)) {
            throw ValidationException::withMessages([
                'targets' => 'At least one target is required.',
            ]);
        }

        $normalized = [];
        foreach ($targets as $target) {
            $link = trim((string) ($target['link'] ?? ''));
            $quantity = (int) ($target['quantity'] ?? 0);

            if ($link === '') {
                throw ValidationException::withMessages([
                    'targets' => 'Each target must have a valid link.',
                ]);
            }

            if ($quantity < 1) {
                throw ValidationException::withMessages([
                    'targets' => 'Each target must have a quantity of at least 1.',
                ]);
            }

            $normalized[] = [
                'link' => $link,
                'quantity' => $quantity,
            ];
        }

        return $normalized;
    }

    /**
     * Validate service quantity rules per row.
     */
    private function validateServiceQuantityRules(int $rowQty, int $rowIndex, int $minQuantity, ?int $maxQuantity, int $increment): void
    {
        if ($rowQty < $minQuantity) {
            throw ValidationException::withMessages([
                "targets.{$rowIndex}.quantity" => "Minimum quantity is {$minQuantity}.",
            ]);
        }

        if ($maxQuantity !== null && $maxQuantity > 0 && $rowQty > $maxQuantity) {
            throw ValidationException::withMessages([
                "targets.{$rowIndex}.quantity" => "Maximum quantity is {$maxQuantity}.",
            ]);
        }

        if ($increment > 0 && $rowQty % $increment !== 0) {
            throw ValidationException::withMessages([
                "targets.{$rowIndex}.quantity" => "Quantity must be a multiple of {$increment}.",
            ]);
        }
    }

    /**
     * Single-link duplicate check (used in multi, per service).
     */
    private function validateNoDuplicateLink(Service $service, string $link): void
    {
        $days = $service->deny_duplicates_days ?? 90;
        $since = Carbon::now()->subDays($days);

        $statuses = [
            Order::STATUS_AWAITING,
            Order::STATUS_PENDING,
            Order::STATUS_IN_PROGRESS,
            Order::STATUS_PROCESSING,
            Order::STATUS_FAIL,
        ];

        $exists = Order::query()
            ->where('link', $link)
            ->where('service_id', $service->id)
            ->where('created_at', '>=', $since)
            ->whereIn('status', $statuses)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'targets' => "The link '{$link}' has already been used in a recent order for this service.",
            ]);
        }
    }

    /**
     * Batch duplicate check (used in create() to avoid N queries).
     */
    private function validateNoDuplicateLinksBatch(Service $service, array $targets, \Carbon\CarbonInterface $now): void
    {
        $days = $service->deny_duplicates_days ?? 90;
        $since = $now->copy()->subDays($days);

        $statuses = [
            Order::STATUS_AWAITING,
            Order::STATUS_PENDING,
            Order::STATUS_IN_PROGRESS,
            Order::STATUS_PROCESSING,
            Order::STATUS_FAIL,
        ];

        $links = array_values(array_unique(array_column($targets, 'link')));

        $exists = Order::query()
            ->where('service_id', $service->id)
            ->whereIn('link', $links)
            ->where('created_at', '>=', $since)
            ->whereIn('status', $statuses)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'targets' => 'One or more links were already used recently for this service.',
            ]);
        }
    }
}
