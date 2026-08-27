<?php

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when an agent tries to claim a ticket that someone else got to first.
 *
 * Extends InvalidArgumentException so existing callers, which already catch that
 * type to turn a guard failure into a flash message, keep working unchanged.
 */
class TicketAlreadyAssignedException extends InvalidArgumentException {}
