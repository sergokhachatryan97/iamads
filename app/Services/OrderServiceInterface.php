<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;

interface OrderServiceInterface
{
    /**
     * Create new orders (one per target link).
     *
     * @param Client $client
     * @param array $data
     * @param int|null $createdBy
     * @return \Illuminate\Database\Eloquent\Collection
     * @throws \Illuminate\Validation\ValidationException
     */
    public function create(Client $client, array $data, ?int $createdBy = null): Collection;

    /**
     * Process a refund for an order.
     *
     * @param Order $order
     * @param int $newDelivered
     * @param string $newStatus
     * @return Order
     * @throws \Illuminate\Validation\ValidationException
     */
    public function refund(Order $order, int $newDelivered, string $newStatus): Order;

    /**
     * Cancel an order fully (awaiting/pending status).
     *
     * @param Order $order
     * @param \App\Models\Client $client
     * @return Order
     * @throws \Illuminate\Validation\ValidationException
     */
    public function cancelFull(Order $order, \App\Models\Client $client): Order;

    /**
     * Cancel an order partially (in_progress/processing status).
     *
     * @param Order $order
     * @param \App\Models\Client $client
     * @return Order
     * @throws \Illuminate\Validation\ValidationException
     */
    public function cancelPartial(Order $order, \App\Models\Client $client): Order;
}

