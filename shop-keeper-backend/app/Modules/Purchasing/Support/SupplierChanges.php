<?php

namespace App\Modules\Purchasing\Support;

final readonly class SupplierChanges
{
    public function __construct(
        public ?string $name,
        public bool $changesPhone,
        public ?string $phone,
    ) {}
}
