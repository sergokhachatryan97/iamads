<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use Illuminate\Http\JsonResponse;

/**
 * Performer endpoint: list all awaiting orders whose category is YouTube.
 * GET /api/provider/youtube/awaiting-orders
 */
class YouTubeAwaitingOrdersController extends Controller
{
    public function index(): JsonResponse
    {
        $categoryId = cache()->remember('yt:category_id', 3600, fn () =>
            Category::where('link_driver', 'youtube')->value('id')
        );

        if (! $categoryId) {
            return response()->json(['ok' => true, 'count' => 0, 'orders' => []]);
        }

        $orders = Order::query()
            ->whereIn('status', [Order::STATUS_AWAITING, Order::STATUS_IN_PROGRESS])
            ->where('category_id', $categoryId)
            ->where('remains', '>', 0)
            ->with(['service:id,name,description_for_performer', 'category:id,name'])
            ->orderBy('id')
            ->get(['id', 'link', 'quantity', 'delivered', 'remains', 'status', 'service_id', 'category_id', 'created_at']);

        $items = $orders->map(fn (Order $order) => [
            'id' => $order->id,
            'link' => $order->link,
            'quantity' => $order->quantity,
            'delivered' => $order->delivered,
            'remains' => $order->remains,
            'status' => $order->status,
            'service_id' => $order->service_id,
            'service_name' => $order->service?->name,
            'service_description' => $order->service?->description_for_performer,
            'category_id' => $order->category_id,
            'category_name' => $order->category?->name,
            'created_at' => $order->created_at?->toIso8601String(),
        ]);

        return response()->json([
            'ok' => true,
            'count' => $items->count(),
            'orders' => $items->values()->all(),
        ]);
    }
}
