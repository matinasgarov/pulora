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

    /**
     * The transitions an operator is allowed to make. PendingPayment and Paid
     * are absent from every target list: only the payment callback may create
     * Paid, and only ReleaseExpiredReservations may retire an unpaid order.
     */
    public function canTransitionTo(self $to): bool
    {
        return in_array($to, match ($this) {
            self::Paid => [self::InProduction, self::Cancelled, self::Refunded],
            self::InProduction => [self::Shipped, self::Cancelled, self::Refunded],
            self::Shipped => [self::Delivered, self::Refunded],
            self::Delivered => [self::Refunded],
            self::PendingPayment, self::Cancelled, self::Refunded => [],
        }, true);
    }
}
