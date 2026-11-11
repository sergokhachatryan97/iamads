<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\ClientTransaction;
use App\Models\Order;
use App\Services\Order\OrderInspectionDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Put orders wrongly rejected as invalid_link back into validation.
 * Reverses the refund done by OrderService::refundInvalid (re-charges the
 * client balance) and re-dispatches link inspection. Dry run unless --apply.
 */
class ReviveInvalidOrdersCommand extends Command
{
    protected $signature = 'orders:revive-invalid {ids* : Order IDs} {--apply : Actually revive (default is dry run)}';

    protected $description = 'Re-validate orders wrongly marked invalid_link: re-charge the refund and re-inspect the link';

    public function handle(OrderInspectionDispatcher $dispatcher): int
    {
        $apply = (bool) $this->option('apply');
        $this->info($apply ? 'APPLY mode' : 'DRY RUN (add --apply to revive)');

        foreach ($this->argument('ids') as $id) {
            $this->line('');
            try {
                $result = DB::transaction(function () use ($id, $apply) {
                    $order = Order::lockForUpdate()->find((int) $id);
                    if (! $order) {
                        return [false, "#{$id}: not found"];
                    }
                    if ($order->status !== Order::STATUS_INVALID_LINK) {
                        return [false, "#{$id}: status is {$order->status}, not invalid_link — skipped"];
                    }

                    $client = Client::lockForUpdate()->findOrFail($order->client_id);
                    $payload = is_array($order->provider_payload) ? $order->provider_payload : [];
                    $refund = $payload['refund'] ?? null;
                    $refunded = ! empty($refund['done']);
                    $amount = $refunded ? (float) ($refund['amount'] ?? 0) : 0.0;
                    $fromBalance = $order->payment_source === Order::PAYMENT_SOURCE_BALANCE;
                    $recharge = $refunded && $fromBalance && $amount > 0;

                    $info = sprintf(
                        '#%d client=%d link=%s refunded=%s amount=%.2f balance=%.2f',
                        $order->id, $client->id, $order->link, $refunded ? 'yes' : 'no', $amount, (float) $client->balance
                    );

                    if ($recharge && (float) $client->balance < $amount) {
                        return [false, "{$info} — client balance too low to re-charge, skipped"];
                    }

                    $duplicate = Order::where('client_id', $client->id)
                        ->where('link', $order->link)
                        ->where('id', '>', $order->id)
                        ->whereNotIn('status', [Order::STATUS_INVALID_LINK])
                        ->value('id');
                    if ($duplicate) {
                        return [false, "{$info} — client already re-created it as #{$duplicate}, skipped"];
                    }

                    if (! $apply) {
                        return [false, "{$info} — would revive".($recharge ? " and re-charge {$amount}" : '')];
                    }

                    if ($recharge) {
                        $client->balance -= $amount;
                        $client->spent = (float) $client->spent + $amount;
                        $client->save();

                        ClientTransaction::create([
                            'client_id' => $client->id,
                            'order_id' => $order->id,
                            'amount' => -$amount,
                            'type' => ClientTransaction::TYPE_ORDER_CHARGE,
                        ]);
                    }

                    if ($refunded) {
                        $payload['refund_reverted'] = array_merge($refund, ['reverted_at' => now()->toDateTimeString()]);
                        unset($payload['refund']);
                    }
                    unset($payload['telegram']);

                    $order->update([
                        'status' => Order::STATUS_VALIDATING,
                        'provider_last_error' => null,
                        'provider_last_error_at' => null,
                        'provider_sending_at' => null,
                        'provider_payload' => $payload,
                    ]);

                    return [true, "{$info} — revived".($recharge ? ", re-charged {$amount}" : '')];
                }, 5);
            } catch (\Throwable $e) {
                $this->error("#{$id}: ".$e->getMessage());
                continue;
            }

            [$revived, $message] = $result;
            $revived ? $this->info($message) : $this->line($message);

            if ($revived) {
                $dispatcher->dispatch(Order::with('service.category')->find((int) $id));
            }
        }

        return self::SUCCESS;
    }
}
