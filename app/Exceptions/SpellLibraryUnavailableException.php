<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when the spell library service cannot be reached at the transport level.
 * Distinct from HTTP-level errors (4xx/5xx), which the client handles differently.
 */
class SpellLibraryUnavailableException extends RuntimeException
{
}
