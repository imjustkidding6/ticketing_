<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Exception thrown when an OpenAI API request fails, times out, or receives invalid response data.
 */
class OpenAiException extends RuntimeException {}
