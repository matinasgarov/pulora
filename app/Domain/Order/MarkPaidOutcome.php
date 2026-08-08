<?php

namespace App\Domain\Order;

enum MarkPaidOutcome
{
    case Transitioned;   // this call marked it paid
    case AlreadyPaid;    // a duplicate callback — harmless
    case NotPayable;     // cancelled/refunded — money arrived for an order we cannot honour
    case AmountMismatch; // the amount/currency paid does not match what is owed
}
