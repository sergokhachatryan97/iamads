<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;

/**
 * Performer endpoint: list all awaiting orders whose category is YouTube.
 * GET /api/provider/youtube/awaiting-orders
 */
class YouTubeAwaitingOrdersController extends Controller
{
    /**
     * Return all orders with status awaiting and category link_driver = youtube.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $orders = Order::query()
            ->whereIn('status', [Order::STATUS_AWAITING, Order::STATUS_IN_PROGRESS])
            ->whereHas('service', function ($q) {
                $q->whereHas('category', function ($q2) {
                    $q2->where('link_driver', 'youtube');
                });
            })
            ->with(['service:id,name,description_for_performer,category_id,template_key', 'category:id,name,link_driver'])
            ->orderBy('id')
            ->get(['id', 'link', 'quantity', 'delivered', 'remains', 'status', 'service_id', 'category_id', 'created_at']);

        $items = $orders->map(function (Order $order) {
            $service = $order->service;
            $category = $order->category;
            return [
                'id' => $order->id,
                'link' => $order->link,
                'quantity' => $order->quantity,
                'delivered' => $order->delivered,
                'remains' => $order->remains,
                'status' => $order->status,
                'service_id' => $order->service_id,
                'service_name' => $service?->name,
                'service_description' => $service?->description_for_performer,
                'category_id' => $order->category_id,
                'category_name' => $category?->name,
                'created_at' => $order->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'ok' => true,
            'count' => $items->count(),
            'orders' => $items->values()->all(),
        ]);
    }
}
