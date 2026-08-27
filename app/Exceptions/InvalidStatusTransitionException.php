<?php

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when a ticket is asked to move between two statuses the workflow forbids.
 *
 * Extends InvalidArgumentException so the controllers that already catch that type
 * and turn it into a flash message handle it without further plumbing.
 */
class InvalidStatusTransitionException extends InvalidArgumentException
{
    public function __construct(
        public readonly string $from,
        public readonly string $to,
    ) {
        parent::__construct(sprintf(
            'A ticket cannot go from %s to %s.',
            str_replace('_', ' ', $from),
            str_replace('_', ' ', $to),
        ));
    }
}
