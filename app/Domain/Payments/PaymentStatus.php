<?php

declare(strict_types=1);

namespace App\Domain\Payments;

enum PaymentStatus: string
{
    case NEW = 'new';
    case PENDING = 'pending';
    case PAID = 'paid';
    case FAILED = 'failed';
    case EXPIRED = 'expired';
    case REFUNDED = 'refunded';
}
