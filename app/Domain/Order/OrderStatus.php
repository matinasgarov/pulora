<?php // app/Domain/Order/OrderStatus.php

namespace App\Domain\Order;

enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case InProduction = 'in_production';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }
}
