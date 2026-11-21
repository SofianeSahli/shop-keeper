<?php

namespace App\Shared\Records;

use Illuminate\Database\Eloquent\Model;

final readonly class Created
{
    public function __construct(
        public Model $record,
        public bool $wasCreated,
    ) {}
}
