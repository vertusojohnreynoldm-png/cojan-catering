<?php

namespace App\Exceptions;

/**
 * The customer already has an active scheduled menu order in this exact
 * delivery time frame. Unlike a full or blocked slot this is allowed — but
 * only once they tick the "yes, place a second order" confirmation box.
 */
class DuplicateSlotOrderException extends BookingUnavailableException
{
}
