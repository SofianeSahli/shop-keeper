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
        public readonly array $details = [],
    ) {
        parent::__construct($errorCode);
    }

    public function toErrorBody(): array
    {
        return array_filter([
            'code' => $this->errorCode,
            ...$this->details,
            'fields' => $this->fields,
            'retry_after_seconds' => $this->retryAfterSeconds,
        ], fn (mixed $value): bool => $value !== [] && $value !== null);
    }
}
