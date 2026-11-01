<?php

namespace App\Shared\Errors;

use RuntimeException;

final class ApiException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        public readonly int $status,
        public readonly array $fields = [],
        public readonly ?int $retryAfterSeconds = null,
    ) {
        parent::__construct($errorCode);
    }

    public function toErrorBody(): array
    {
        return array_filter([
            'code' => $this->errorCode,
            'fields' => $this->fields,
            'retry_after_seconds' => $this->retryAfterSeconds,
        ]);
    }
}
