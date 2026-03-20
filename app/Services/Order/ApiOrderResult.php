<?php

namespace App\Services\Order;

use App\Models\Order;

readonly class ApiOrderResult
{
    public function __construct(
        public Order $order,
        public bool $duplicate,
    ) {}
}
