<?php

declare(strict_types=1);

namespace Adeptix\Exceptions;

use Exception;

/**
 * Thrown when the API returns a non-JSON body, or JSON missing the `error` field, on a non-2xx
 * response - a genuine surprise, not a normal error path.
 */
class AdeptixUnexpectedResponseException extends Exception
{
    private int $statusCode;
    private string $rawBody;

    public function __construct(int $statusCode, string $rawBody)
    {
        parent::__construct("Adeptix API returned an unexpected {$statusCode} response");
        $this->statusCode = $statusCode;
        $this->rawBody = $rawBody;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getRawBody(): string
    {
        return $this->rawBody;
    }
}
