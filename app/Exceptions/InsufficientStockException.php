<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when an order can't be fulfilled due to insufficient utensil stock
 * for one of its packages. Its message is safe to show directly to the
 * customer (see Customer\OrderController::store()) — unlike other Throwables
 * raised inside that same transaction (e.g. QR generation failures), which
 * are deliberately reduced to a generic error message instead of leaking
 * internal detail.
 */
class InsufficientStockException extends RuntimeException
{
}
