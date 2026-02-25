<?php

namespace App\Exceptions;

use Exception;

class ThirdPartyApiException extends Exception
{
    public function __construct(
        string $message = 'Third-party API request failed',
        int $code = 0,
        ?\Throwable $previous = null,
        protected ?array $responseBody = null,
        protected ?int $httpStatus = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getResponseBody(): ?array
    {
        return $this->responseBody;
    }

    public function getHttpStatus(): ?int
    {
        return $this->httpStatus;
    }
}
