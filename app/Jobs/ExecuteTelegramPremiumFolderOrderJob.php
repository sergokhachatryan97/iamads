<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\TelegramFolderMembership;
use App\Services\OrderService;
use App\Services\Telegram\Folder\PremiumFolderAccountUsageLimiter;
use App\Services\Telegram\Folder\PremiumFolderMtprotoAccountSelector;
use App\Services\Telegram\Folder\TelegramFolderService;
use App\Services\Telegram\MtprotoClientFactory;
use App\Services\Telegram\TelegramActionDedupeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExecuteTelegramPremiumFolderOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public int $orderId) {}

    public function handle(
        OrderService $orderService,
        PremiumFolderMtprotoAccountSelector $accountSelector,
        PremiumFolderAccountUsageLimiter $usageLimiter,
        TelegramFolderService $folderService,
        TelegramActionDedupeService $dedupeService,
        MtprotoClientFactory $mtprotoClientFactory,
    ): void {
        $order = Order::query()->with('service')->find($this->orderId);
        if (! $order || ! $order->service) {
            return;
        }

        if ($order->service->template_key !== 'telegram_premium_folder') {
            return;
        }

        if ($order->status !== Order::STATUS_PROCESSING) {
            return;
        }

        // Idempotency: if THIS order already has an active membership, skip
        $existingForOrder = TelegramFolderMembership::query()
            ->where('order_id', $order->id)
            ->where('status', TelegramFolderMembership::STATUS_ACTIVE)
            ->first();

        if ($existingForOrder) {
            Log::info('PremiumFolder: membership already exists for this order, skipping', [
                'order_id' => $order->id,
                'membership_id' => $existingForOrder->id,
            ]);

            return;
        }

        $providerPayload = $order->provider_payload ?? [];
        $telegram = is_array($providerPayload['telegram'] ?? null) ? $providerPayload['telegram'] : [];
        $parsed = is_array($telegram['parsed'] ?? null) ? $telegram['parsed'] : [];
        $folderCfg = is_array($providerPayload['telegram_premium_folder'] ?? null)
            ? $providerPayload['telegram_premium_folder']
            : [];
        $durationDays = (int) ($folderCfg['duration_days'] ?? 0);

        $account = $accountSelector->select();
        if (! $account) {
            $this->failOrder($order, $orderService, 'No MTProto account available');

            return;
        }

        $folderId = (int) $account->premium_folder_id;
        if ($folderId <= 0) {
            $this->failOrder($order, $orderService, 'Invalid premium_folder_id on MTProto account');

            return;
        }

        $link = (string) $order->link;
        $linkHash = $dedupeService->normalizeAndHashLink(
            $parsed !== [] ? $parsed : ['kind' => 'unknown', 'raw' => $link]
        );

        $lockKey = 'mtp:folder_lock:'.$account->id;
        $lock = Cache::lock($lockKey, $this->timeout + 30);

        if (! $lock->get()) {
            $this->release(30);

            return;
        }

        try {
            $result = $folderService->addPeerToFolder($account, $link, $folderId, $parsed);

            if (! ($result['ok'] ?? false)) {
                $error = (string) ($result['error'] ?? 'Folder add failed');

                // Retryable errors — release back to queue instead of failing permanently
                $retryable = ($result['retryable'] ?? false)
                    || str_contains(strtoupper($error), 'FLOOD_WAIT')
                    || str_contains(strtoupper($error), 'TIMEOUT')
                    || str_contains(strtoupper($error), 'STREAM')
                    || str_contains(strtoupper($error), 'CANCELLED');

                if ($retryable && $this->attempts() < $this->tries) {
                    Log::warning('PremiumFolder: retryable error, releasing', [
                        'order_id' => $order->id,
                        'error' => $error,
                        'attempt' => $this->attempts(),
                    ]);
                    $this->release(60);

                    return;
                }

                $this->failOrder($order, $orderService, $error);

                return;
            }

            $inputPeer = $result['input_peer'] ?? [];
            $peerSummary = $this->summarizeInputPeer(is_array($inputPeer) ? $inputPeer : []);
            $folderShareLink = isset($result['folder_share_link']) ? (string) $result['folder_share_link'] : null;
            $folderShareSlug = isset($result['folder_share_slug']) ? (string) $result['folder_share_slug'] : null;

            $newRemoveAt = now()->addDays(max(1, $durationDays));

            // If this channel already has an ACTIVE membership in this folder — just extend it.
            // No duplicate row. One channel = one membership row.
            $existingForChannel = TelegramFolderMembership::query()
                ->where('folder_id', $folderId)
                ->where('target_link_hash', $linkHash)
                ->where('status', TelegramFolderMembership::STATUS_ACTIVE)
                ->first();

            if ($existingForChannel) {
                $extendedRemoveAt = $existingForChannel->remove_at->gt($newRemoveAt)
                    ? $existingForChannel->remove_at
                    : $newRemoveAt;

                $existingForChannel->update([
                    'order_id' => $order->id,
                    'remove_at' => $extendedRemoveAt,
                    'folder_share_link' => $folderShareLink ?? $existingForChannel->folder_share_link,
                    'folder_share_slug' => $folderShareSlug ?? $existingForChannel->folder_share_slug,
                    'last_error' => null,
                ]);

                Log::info('PremiumFolder: extended existing membership', [
                    'order_id' => $order->id,
                    'membership_id' => $existingForChannel->id,
                    'remove_at' => $extendedRemoveAt->toDateTimeString(),
                ]);
            } else {
                TelegramFolderMembership::query()->create([
                    'order_id' => $order->id,
                    'mtproto_telegram_account_id' => $account->id,
                    'target_link' => $link,
                    'target_link_hash' => $linkHash,
                    'peer_type' => $telegram['chat_type'] ?? null,
                    'target_username' => isset($parsed['username']) ? strtolower((string) $parsed['username']) : null,
                    'target_peer_id' => $peerSummary,
                    'folder_id' => $folderId,
                    'folder_title' => $account->premium_folder_title,
                    'folder_share_link' => $folderShareLink,
                    'folder_share_slug' => $folderShareSlug,
                    'added_at' => now(),
                    'remove_at' => $newRemoveAt,
                    'status' => TelegramFolderMembership::STATUS_ACTIVE,
                    'last_error' => null,
                ]);
            }

            $providerPayload = is_array($order->provider_payload) ? $order->provider_payload : [];
            $providerPayload['telegram_premium_folder'] = array_merge(
                is_array($providerPayload['telegram_premium_folder'] ?? null) ? $providerPayload['telegram_premium_folder'] : [],
                [
                    'duration_days' => max(1, $durationDays),
                    'folder_id' => $folderId,
                    'folder_share_link' => $folderShareLink,
                    'folder_share_slug' => $folderShareSlug,
                    'account_id' => $account->id,
                ]
            );
            $order->update(['provider_payload' => $providerPayload]);

            if (($result['was_added'] ?? false) === true) {
                $account->increment('subscription_count', 1);
            }
            $account->recordSuccess();

            // Make order claimable by performers via GET /premium/getOrder.
            // The performer needs to add the shared folder to their Telegram.
            $order->update([
                'status' => Order::STATUS_AWAITING,
                'execution_phase' => Order::EXECUTION_PHASE_RUNNING,
            ]);

        } finally {
            try {
                $lock->release();
            } catch (Throwable) {
            }
            $mtprotoClientFactory->forgetRuntimeInstance($account);
        }
    }

    public function failed(Throwable $exception): void
    {
        $order = Order::query()->find($this->orderId);
        if ($order) {
            try {
                app(OrderService::class)->refundInvalid($order, $exception->getMessage());
            } catch (Throwable) {
            }
            $order->update([
                'status' => Order::STATUS_FAIL,
                'provider_last_error' => $exception->getMessage(),
                'provider_last_error_at' => now(),
            ]);
        }

        Log::error('ExecuteTelegramPremiumFolderOrderJob failed', [
            'order_id' => $this->orderId,
            'error' => $exception->getMessage(),
        ]);
    }

    private function failOrder(Order $order, OrderService $orderService, string $message): void
    {
        try {
            $orderService->refundInvalid($order, $message);
        } catch (Throwable $e) {
            Log::warning('Premium folder order refund failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }

        $order->update([
            'status' => Order::STATUS_FAIL,
            'provider_last_error' => $message,
            'provider_last_error_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $inputPeer
     */
    private function summarizeInputPeer(array $inputPeer): ?string
    {
        if ($inputPeer === []) {
            return null;
        }

        $type = (string) ($inputPeer['_'] ?? '');

        return match ($type) {
            'inputPeerChannel' => 'channel:'.(int) ($inputPeer['channel_id'] ?? 0),
            'inputPeerChat' => 'chat:'.(int) ($inputPeer['chat_id'] ?? 0),
            'inputPeerUser' => 'user:'.(int) ($inputPeer['user_id'] ?? 0),
            default => json_encode($inputPeer, JSON_UNESCAPED_SLASHES) ?: null,
        };
    }
}
