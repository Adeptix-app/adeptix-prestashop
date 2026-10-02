<?php

declare(strict_types=1);

namespace Adeptix\Exceptions;

use Exception;

/**
 * Thrown for any non-2xx response from the Adeptix API. getErrorCode() matches the `error` field
 * documented at https://docs.adeptix.app/errors - always check that, not getMessage() (which
 * isn't guaranteed to be present or stable across API versions).
 */
class AdeptixApiException extends Exception
{
    private int $statusCode;
    private string $errorCode;
    /** @var mixed */
    private $details;

    /**
     * @param mixed $details
     */
    public function __construct(int $statusCode, string $errorCode, ?string $message, $details = null)
    {
        parent::__construct($message ?? "Adeptix API request failed with {$statusCode} ({$errorCode})");
        $this->statusCode = $statusCode;
        $this->errorCode = $errorCode;
        $this->details = $details;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /** @return mixed */
    public function getDetails()
    {
        return $this->details;
    }
}
