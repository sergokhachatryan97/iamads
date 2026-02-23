<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
class ProcessValidatingOrdersWithProviderSendingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 1;

    public function handle(): void
    {
        $orders = Order::query()
            ->where('status', Order::STATUS_VALIDATING)
            ->whereNotNull('provider_sending_at')
            ->orderBy('id')
            ->get();


        foreach ($orders as $order) {
            InspectTelegramLinkJob::dispatch($order->id);
        }
    }

}
