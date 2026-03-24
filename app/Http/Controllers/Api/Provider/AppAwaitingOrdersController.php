<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;

/**
 * Performer endpoint: list all awaiting orders whose category is App (App Store / Google Play).
 * GET /api/provider/app/orders-list
 */
class AppAwaitingOrdersController extends Controller
{
    /**
     * Return all orders with status awaiting/in_progress and category link_driver = app.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $orders = Order::query()
            ->whereIn('status', [Order::STATUS_AWAITING, Order::STATUS_IN_PROGRESS])
            ->whereHas('service', function ($q) {
                $q->whereHas('category', function ($q2) {
                    $q2->where('link_driver', 'app');
                });
            })
            ->with(['service:id,name,description_for_performer,category_id,template_key', 'category:id,name,link_driver'])
            ->orderBy('id')
            ->get(['id', 'link', 'quantity', 'delivered', 'remains', 'status', 'service_id', 'category_id', 'star_rating', 'comment_text', 'created_at']);

        $items = $orders->map(function (Order $order) {
            $service = $order->service;
            $category = $order->category;
            $item = [
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
            if ($order->star_rating !== null) {
                $item['star_rating'] = (int) $order->star_rating;
            }
            if (!empty($order->comment_text)) {
                $item['comment_text'] = $order->comment_text;
            }
            return $item;
        });

        return response()->json([
            'ok' => true,
            'count' => $items->count(),
            'orders' => $items->values()->all(),
        ]);
    }
}
