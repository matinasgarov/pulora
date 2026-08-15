<?php // app/Domain/Cart/InvalidPersonalizationException.php

namespace App\Domain\Cart;

use RuntimeException;

class InvalidPersonalizationException extends RuntimeException
{
    /**
     * @param  string|null  $type  the personalization option this is about
     *
     * The UI needs to put the error on the field the customer got wrong. It
     * used to recover that by matching the message text back to an option's
     * label, which mis-attributes whenever one label is a prefix of another —
     * "Name" and "Name Tag" on the same product put the error on the wrong
     * input and left the wrong one looking fine. The validator knows the type
     * at the moment it throws, so it says so.
     */
    public function __construct(string $message, public readonly ?string $type = null)
    {
        parent::__construct($message);
    }
}
