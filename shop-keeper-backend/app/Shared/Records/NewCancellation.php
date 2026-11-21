<?php

namespace App\Shared\Records;

final readonly class NewCancellation
{
    public function __construct(
        public string $id,
        public ?string $reason,
        public int $cancelledBy,
    ) {}
}
