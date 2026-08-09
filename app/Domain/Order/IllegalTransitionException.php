<?php // app/Domain/Order/IllegalTransitionException.php

namespace App\Domain\Order;

use DomainException;

class IllegalTransitionException extends DomainException
{
    public static function between(OrderStatus $from, OrderStatus $to): self
    {
        return new self("An order cannot move from {$from->label()} to {$to->label()}.");
    }
}
