<?php

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when a clock with no open window is asked to do interval arithmetic.
 *
 * A calendar that never opens makes "add 60 business minutes" non-terminating, and
 * the place that would hang is a queue worker. Failing loudly is the only safe
 * answer. Extends InvalidArgumentException — the same base as
 * InvalidStatusTransitionException — so the catch blocks that already handle that
 * type need no further plumbing.
 */
class EmptySlaCalendarException extends InvalidArgumentException
{
    public static function neverOpens(): self
    {
        return new self('This SLA calendar has no open windows, so no deadline can be computed from it.');
    }

    public static function scanExhausted(int $days): self
    {
        return new self(sprintf(
            'Scanned %d days without finding enough open time; the SLA calendar is effectively closed.',
            $days
        ));
    }
}
