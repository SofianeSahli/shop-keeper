<?php

namespace App\Modules\Catalog\Support;

use RuntimeException;

final class CatalogFileError extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        public readonly ?int $position = null,
        public readonly ?string $key = null,
    ) {
        parent::__construct($reason);
    }

    public function at(int $position, ?string $key): self
    {
        return new self($this->reason, $position, $key);
    }

    public function describe(): string
    {
        return $this->position === null
            ? $this->reason
            : sprintf('entry %d (%s): %s', $this->position, $this->key ?? '?', $this->reason);
    }
}
