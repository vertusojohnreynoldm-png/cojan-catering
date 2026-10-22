<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by App\Services\BookingCapacity from inside the order/booking
 * transaction when the chosen date or time frame can't be taken (blocked,
 * full, outside the booking window, or the customer already holds it).
 * Like InsufficientStockException, its message is written for the customer
 * and is safe to show as-is; $field is the form field to attach it to.
 */
class BookingUnavailableException extends RuntimeException
{
    public function __construct(string $message, public readonly string $field = 'event_date')
    {
        parent::__construct($message);
    }
}
