<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when the Bank of Vivaldi answers a request with a client error (4xx).
 * Carries the upstream status and its `{ "error": { ... } }` envelope so the
 * loot controller can pass field-level validation `details` back to the browser.
 *
 * Transport failures and upstream 5xx use BankOfVivaldiUnavailableException instead.
 */
class BankOfVivaldiRequestException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $errorBody  the decoded upstream error envelope
     */
    public function __construct(
        public readonly int $status,
        public readonly array $errorBody,
    ) {
        parent::__construct($errorBody['error']['message'] ?? 'The Bank of Vivaldi rejected the request.');
    }
}
