<?php

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when a merge would produce an invalid parent/child structure — a ticket
 * merged into itself, or into a ticket that has already been merged away.
 */
class InvalidMergeException extends InvalidArgumentException {}
