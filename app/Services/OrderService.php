<?php

namespace App\Services;

use App\Jobs\InspectTelegramLinkJob;
use App\Models\Category;
use App\Models\Client;
use App\Models\Order;
use App\Models\Service;
use App\Models\ClientTransaction;
use App\Models\ClientServiceLimit;
use App\Models\TelegramAccount;
use App\Support\TelegramLinkParser;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

            // Handle custom_comments service type: ONE ORDER PER COMMENT
            if ($service->service_type === 'custom_comments') {
                return $this->createCustomCommentsOrder($client, $data, $service, $createdBy);
            }

            // 3) Normalize targets
            $targets = $this->normalizeTargets($data['targets'] ?? []);

            $this->validateUniqueLinksInRequest($targets);

            // 4) Dripfeed toggle
            $clientDripfeedEnabled = (bool) ($data['dripfeed_enabled'] ?? false);
            if ($service->dripfeed_enabled && $clientDripfeedEnabled) {
                $totalQuantity = 0;
                foreach ($targets as $target) {
                    $totalQuantity += (int) ($target['quantity'] ?? 0);
                }
                $this->validateDripfeedFields($data, $totalQuantity);
            }

            // 5) Effective limits once (client override)
            $serviceLimit = ClientServiceLimit::query()
                ->where('client_id', $client->id)
                ->where('service_id', $service->id)
                ->first();

            $effectiveMinQty = $serviceLimit?->min_quantity ?? $service->min_quantity;
            $effectiveMaxQty = $serviceLimit?->max_quantity ?? ($service->max_quantity ?? null);
            $effectiveIncrement = $serviceLimit?->increment ?? ($service->increment ?? 0);

            // 6) Duplicate links check in ONE query (if enabled)
            if ($service->deny_link_duplicates) {
                $this->validateNoDuplicateLinksBatch($service, $targets, $now);
            }

            // 7) Base rate (per 1000)
            $baseRate = (float) $this->pricingService->priceForClient($service, $client);

            // 7b) Speed tier and rate multiplier (for pricing)
            $speedTier = $service->speed_limit_enabled ? ($data['speed_tier'] ?? 'normal') : 'normal';
            $rateMultiplier = $service->rateMultiplierForTier($speedTier);
            $finalRate = $rateMultiplier;

            // 8) Validate each row + calculate charges
            $rowCharges = [];
            $totalCharge = 0.0;

            foreach ($targets as $index => $target) {
                $qty = (int) $target['quantity'];

                $this->validateServiceQuantityRules(
                    $qty,
                    $index,
                    (int) $effectiveMinQty,
                    $effectiveMaxQty !== null ? (int) $effectiveMaxQty : null,
                    (int) $effectiveIncrement
                );

                // Calculate charge using final rate (base * rate multiplier)
                $charge = round(($qty / 100) * $finalRate, 2);

                // Cost also uses rate multiplier if service_cost_per_1000 exists
                $cost = $service->service_cost_per_1000 !== null
                    ? round(($qty / 100) * (float) $service->service_cost_per_1000 * $finalRate, 2)
                    : null;

                $rowCharges[] = [
                    'link' => $target['link'],
                    'quantity' => $qty,
                    'charge' => $charge,
                    'cost' => $cost,
                ];

                $totalCharge += $charge;
            }

            // 9) Payment source is ALWAYS balance
            if ($client->balance < $totalCharge) {
                throw ValidationException::withMessages([
                    'balance' => 'Insufficient balance. Please top up.',
                ]);
            }

            $client->balance -= $totalCharge;
            $client->save();

            // 10) Create orders
            $batchId = (string) \Illuminate\Support\Str::uuid();

            // Dripfeed fields
            $dripfeedEnabled = ($service->dripfeed_enabled && $clientDripfeedEnabled);
            $dripfeedQuantity = $dripfeedEnabled ? (int) ($data['dripfeed_quantity'] ?? null) : null;
            $dripfeedInterval = $dripfeedEnabled ? (int) ($data['dripfeed_interval'] ?? null) : null;
            $dripfeedIntervalUnit = $dripfeedEnabled ? (string) ($data['dripfeed_interval_unit'] ?? null) : null;

            // Calculate dripfeed runs and interval_minutes
            $dripfeedRunsTotal = null;
            $dripfeedIntervalMinutes = null;
            if ($dripfeedEnabled && $dripfeedQuantity && $dripfeedInterval) {
                // Convert interval to minutes
                $dripfeedIntervalMinutes = match($dripfeedIntervalUnit) {
                    'minutes' => $dripfeedInterval,
                    'hours' => $dripfeedInterval * 60,
                    'days' => $dripfeedInterval * 1440,
                    default => $dripfeedInterval, // assume minutes
                };

                // Calculate total runs needed
                $totalQty = array_sum(array_column($rowCharges, 'quantity'));
                $dripfeedRunsTotal = (int) ceil($totalQty / max(1, $dripfeedQuantity));
            }

            $orders = [];
            foreach ($rowCharges as $row) {
                // Build pricing snapshot for this order
                $pricingSnapshot = [
                    'speed_tier' => $speedTier,
                    'base_rate_per_100' => $baseRate,
                    'rate_multiplier' => $rateMultiplier,
                    'final_rate_per_100' => $finalRate,
                    'quantity' => $row['quantity'],
                    'total_price' => $row['charge'],
                    'computed_at' => $now->toDateTimeString(),
                ];

                $orderData = [
                    'batch_id' => $batchId,
                    'client_id' => $client->id,
                    'created_by' => $createdBy,
                    'category_id' => (int) $data['category_id'],
                    'service_id' => $service->id,
                    'link' => $row['link'],
                    'payment_source' => Order::PAYMENT_SOURCE_BALANCE,
                    'subscription_id' => null,
                    'charge' => $row['charge'],
                    'cost' => $row['cost'],
                    'quantity' => $row['quantity'],
                    'speed_tier' => $speedTier,
                    'speed_multiplier' => $service->getSpeedMultiplier($speedTier), // For execution interval
                    'dripfeed_enabled' => $dripfeedEnabled,
                    'dripfeed_quantity' => $dripfeedQuantity,
                    'dripfeed_interval' => $dripfeedInterval,
                    'dripfeed_interval_unit' => $dripfeedIntervalUnit,
                    'dripfeed_runs_total' => $dripfeedRunsTotal,
                    'dripfeed_interval_minutes' => $dripfeedIntervalMinutes,
                    'dripfeed_run_index' => 0,
                    'dripfeed_delivered_in_run' => 0,
                    'dripfeed_next_run_at' => $dripfeedEnabled ? $now : null,
                    'start_count' => null,
                    'delivered' => 0,
                    'remains' => $row['quantity'],
                    'status' => Order::STATUS_VALIDATING,
                    'mode' => 'manual',
                ];

                // Merge provider_payload
                $orderData['provider_payload'] = array_merge(
                    $orderData['provider_payload'] ?? [],
                    ['pricing_snapshot' => $pricingSnapshot]
                );

                $orders[] = Order::create($orderData);
            }

            // 11) Transaction history (only if charge > 0)
            if ($totalCharge > 0) {
                ClientTransaction::create([
                    'client_id' => $client->id,
                    'order_id' => null,
                    'amount' => -$totalCharge,
                    'type' => ClientTransaction::TYPE_ORDER_CHARGE,
                ]);
            }


            foreach ($orders as $order) {
                InspectTelegramLinkJob::dispatch($order->id)
                    ->onQueue('tg-inspect')
                    ->afterCommit();
            }

            return Collection::make($orders)->load(['service', 'category']);
        });
    }

    /**
     * Create orders for custom_comments service type: ONE ORDER PER COMMENT.
     */
    private function createCustomCommentsOrder(Client $client, array $data, Service $service, ?int $createdBy): Collection
    {
        return DB::transaction(function () use ($client, $data, $service, $createdBy) {
            $client = Client::lockForUpdate()->findOrFail($client->id);

            $clientDripfeedEnabled = (bool) ($data['dripfeed_enabled'] ?? false);
            if ($service->dripfeed_enabled && $clientDripfeedEnabled) {
                $this->validateDripfeedFields($data, 1);
            }

            $commentsInput = $data['comments'] ?? null;
            if (empty($commentsInput)) {
                throw ValidationException::withMessages([
                    'comments' => 'Comments are required for custom comments service.',
                ]);
            }

            $comments = array_filter(
                array_map('trim', explode("\n", (string) $commentsInput)),
                fn ($line) => $line !== ''
            );

            if (empty($comments)) {
                throw ValidationException::withMessages([
                    'comments' => 'At least one non-empty comment is required.',
                ]);
            }

            $link = $data['link'] ?? null;
            if (empty($link)) {
                throw ValidationException::withMessages([
                    'link' => 'Link is required for this service.',
                ]);
            }

            $effectiveRate = (float) $this->pricingService->priceForClient($service, $client);

            $speedTier = $service->speed_limit_enabled ? ($data['speed_tier'] ?? 'normal') : 'normal';
            $speedMultiplier = $service->getSpeedMultiplier($speedTier);

            $chargePerComment = round(($effectiveRate / 100) * $speedMultiplier, 2);
            $costPerComment = $service->service_cost_per_1000 !== null
                ? round(((float) $service->service_cost_per_1000 / 100) * $speedMultiplier, 2)
                : null;

            $commentCount = count($comments);
            $totalCharge = round($chargePerComment * $commentCount, 2);

            if ($client->balance < $totalCharge) {
                throw ValidationException::withMessages([
                    'balance' => 'Insufficient balance. Please top up.',
                ]);
            }

            $client->balance -= $totalCharge;
            $client->save();
            $batchId = (string) \Illuminate\Support\Str::uuid();

            $dripfeedQuantity = ($service->dripfeed_enabled && $clientDripfeedEnabled) ? (int) ($data['dripfeed_quantity'] ?? null) : null;
            $dripfeedInterval = ($service->dripfeed_enabled && $clientDripfeedEnabled) ? (int) ($data['dripfeed_interval'] ?? null) : null;
            $dripfeedIntervalUnit = ($service->dripfeed_enabled && $clientDripfeedEnabled) ? (string) ($data['dripfeed_interval_unit'] ?? null) : null;

            $totalCost = $costPerComment !== null ? round($costPerComment * $commentCount, 2) : null;

            $order = Order::create([
                'batch_id' => $batchId,
                'client_id' => $client->id,
                'created_by' => $createdBy,
                'category_id' => (int) $data['category_id'],
                'service_id' => $service->id,
                'link' => (string) $link,
                'comment_text' => implode("\n", $comments),
                'payment_source' => Order::PAYMENT_SOURCE_BALANCE,
                'subscription_id' => null,
                'charge' => $totalCharge,
                'cost' => $totalCost,
                'quantity' => $commentCount,
                'speed_tier' => $service->speed_limit_enabled ? $speedTier : null,
                'speed_multiplier' => $service->speed_limit_enabled ? $speedMultiplier : 1.00,
                'dripfeed_quantity' => $dripfeedQuantity,
                'dripfeed_interval' => $dripfeedInterval,
                'dripfeed_interval_unit' => $dripfeedIntervalUnit,
                'start_count' => null,
                'delivered' => 0,
                'remains' => $commentCount,
                'status' => Order::STATUS_VALIDATING,
                'mode' => 'manual',
            ]);

            // 11) Transaction history
            if ($totalCharge > 0) {
                ClientTransaction::create([
                    'client_id' => $client->id,
                    'order_id' => null,
                    'amount' => -$totalCharge,
                    'type' => ClientTransaction::TYPE_ORDER_CHARGE,
                ]);
            }

            InspectTelegramLinkJob::dispatch($order->id)
                ->onQueue('tg-inspect')
                ->afterCommit();

            return Collection::make([$order])->load(['service', 'category']);
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

                $totalBalanceCharge += $charge;

                $orderData[] = [
                    'service_id' => $serviceId,
                    'quantity' => $qty,
                    'charge' => $charge,
                    'cost' => $cost,
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
                    'payment_source' => Order::PAYMENT_SOURCE_BALANCE,
                    'subscription_id' => null,
                    'charge' => $row['charge'],
                    'cost' => $row['cost'],
                    'quantity' => $row['quantity'],
                    'start_count' => null,
                    'delivered' => 0,
                    'remains' => $row['quantity'],
                    'status' => Order::STATUS_VALIDATING,
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
                InspectTelegramLinkJob::dispatch($order->id)
                    ->onQueue('tg-inspect')
                    ->afterCommit();
            }

            return new Collection($orders);
        });
    }

    /**
     * ✅ Refund for INVALID_LINK / RESTRICTED (idempotent).
     */
    public function refundInvalid(Order $order, string $reason = 'Invalid link'): void
    {
        DB::transaction(function () use ($order, $reason) {
            $order = Order::lockForUpdate()->findOrFail($order->id);
            $client = Client::lockForUpdate()->findOrFail($order->client_id);

            $payload = $order->provider_payload ?? [];
            if (!is_array($payload)) $payload = [];

            if (!empty($payload['refund']['done'])) {
                return;
            }

            $charge = (float) ($order->charge ?? 0);

            if ($charge > 0 && $order->payment_source === Order::PAYMENT_SOURCE_BALANCE) {
                $client->balance += $charge;
                $client->save();

                ClientTransaction::create([
                    'client_id' => $client->id,
                    'order_id'  => $order->id,
                    'amount'    => $charge,
                    'type'      => ClientTransaction::TYPE_REFUND,
                ]);
            }

            $payload['refund'] = [
                'done' => true,
                'amount' => $charge,
                'reason' => $reason,
                'at' => now()->toDateTimeString(),
            ];

            $order->update([
                'provider_payload' => $payload,
            ]);
        });
    }

    // ----------------- EXISTING REFUND/CANCEL METHODS (unchanged) -----------------

    public function refund(Order $order, int $newDelivered, string $newStatus): Order
    {
        if (!in_array($newStatus, [Order::STATUS_PARTIAL, Order::STATUS_CANCELED], true)) {
            throw ValidationException::withMessages([
                'status' => 'Status must be either "partial" or "canceled" for refunds.',
            ]);
        }

        return DB::transaction(function () use ($order, $newDelivered, $newStatus) {
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
            }

            $order->update([
                'delivered' => $delivered,
                'remains' => $remains,
                'status' => $newStatus,
            ]);

            return $order->fresh();
        });
    }

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
            $client = Client::lockForUpdate()->findOrFail($client->id);
            $order = Order::lockForUpdate()->findOrFail($order->id);

            $refund = $order->charge;

            $order->update([
                'status' => Order::STATUS_CANCELED,
                'delivered' => 0,
                'remains' => $order->quantity,
            ]);

            $this->decrementTelegramAccountCounts($order);

            if ($order->payment_source === Order::PAYMENT_SOURCE_BALANCE && $refund > 0) {
                $client->balance += $refund;
                $client->save();

                ClientTransaction::create([
                    'client_id' => $client->id,
                    'order_id' => $order->id,
                    'amount' => $refund,
                    'type' => ClientTransaction::TYPE_REFUND,
                ]);
            }

            return $order->fresh();
        });
    }

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
            }

            return $order->fresh();
        });
    }

    private function decrementTelegramAccountCounts(Order $order, ?int $maxStepsToDecrement = null): void
    {
        $providerPayload = $order->provider_payload ?? [];
        $steps = $providerPayload['steps'] ?? [];
        $executionMeta = $providerPayload['execution_meta'] ?? [];
        $perCall = $executionMeta['per_call'] ?? 1;

        if (empty($steps)) {
            return;
        }

        $successfulSteps = [];
        foreach ($steps as $step) {
            if (($step['ok'] ?? false) === true && !($step['decremented'] ?? false)) {
                $successfulSteps[] = $step;
            }
        }

        if ($maxStepsToDecrement !== null && $maxStepsToDecrement > 0) {
            $successfulSteps = array_slice($successfulSteps, 0, min(count($successfulSteps), $maxStepsToDecrement));
        }

        $accountCounts = [];
        foreach ($successfulSteps as $step) {
            $accountId = $step['account_id'] ?? null;
            if ($accountId) {
                $accountCounts[$accountId] = ($accountCounts[$accountId] ?? 0) + $perCall;
            }
        }

        foreach ($accountCounts as $accountId => $decrementAmount) {
            try {
                $account = TelegramAccount::find($accountId);
                if ($account) {
                    $newCount = max(0, $account->subscription_count - $decrementAmount);
                    $account->update(['subscription_count' => $newCount]);
                }
            } catch (\Throwable $e) {
                Log::error('Failed to decrement TelegramAccount subscription_count', [
                    'order_id' => $order->id,
                    'account_id' => $accountId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (!empty($successfulSteps)) {
            $updatedSteps = [];
            $decrementedCount = 0;
            foreach ($steps as $step) {
                if (($step['ok'] ?? false) === true && !($step['decremented'] ?? false) && $decrementedCount < count($successfulSteps)) {
                    $step['decremented'] = true;
                    $decrementedCount++;
                }
                $updatedSteps[] = $step;
            }

            $providerPayload['steps'] = $updatedSteps;
            $order->update(['provider_payload' => $providerPayload]);
        }
    }

    private function validateDripfeedFields(array $data, int $maxQuantity = PHP_INT_MAX): void
    {
        $errors = [];

        $dripfeedQuantity = isset($data['dripfeed_quantity']) && is_numeric($data['dripfeed_quantity'])
            ? (int) $data['dripfeed_quantity']
            : 0;

        if ($dripfeedQuantity <= 0) {
            $errors['dripfeed_quantity'] = 'Dripfeed quantity is required and must be greater than 0.';
        } elseif ($dripfeedQuantity > $maxQuantity) {
            $errors['dripfeed_quantity'] = "Quantity per step cannot be greater than total order quantity ({$maxQuantity}).";
        }

        if (!isset($data['dripfeed_interval']) || !is_numeric($data['dripfeed_interval']) || (int) $data['dripfeed_interval'] <= 0) {
            $errors['dripfeed_interval'] = 'Dripfeed interval is required and must be greater than 0.';
        }

        $allowedUnits = ['minutes', 'hours', 'days'];
        if (!isset($data['dripfeed_interval_unit']) || !in_array($data['dripfeed_interval_unit'], $allowedUnits, true)) {
            $errors['dripfeed_interval_unit'] = 'Dripfeed interval unit is required and must be one of: ' . implode(', ', $allowedUnits) . '.';
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

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

    private function validateUniqueLinksInRequest(array $targets): void
    {
        $seen = [];

        foreach ($targets as $i => $t) {
            $raw = trim((string)($t['link'] ?? ''));

            $parsed = TelegramLinkParser::parse($raw);

            // եթե format-ը սխալ է՝ թող validation-ը բռնի, բայց ապահով key տանք
            $kind = $parsed['kind'] ?? 'unknown';

            // canonical key by kind
            $key = match ($kind) {
                'public_username', 'public_post', 'bot_start' =>
                    $kind . ':' . strtolower((string)($parsed['username'] ?? '')),

                'invite' =>
                    $kind . ':' . (string)($parsed['hash'] ?? ''),

                // fallback to raw
                default =>
                    'raw:' . strtolower($raw),
            };

            if (isset($seen[$key])) {
                $firstIndex = $seen[$key];

                throw \Illuminate\Validation\ValidationException::withMessages([
                    "targets.{$i}.link" => "Duplicate link in this order (same as row " . ($firstIndex + 1) . ").",
                ]);
            }

            $seen[$key] = $i;
        }
    }

}
