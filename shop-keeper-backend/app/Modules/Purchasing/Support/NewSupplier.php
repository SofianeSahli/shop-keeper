<?php

namespace App\Modules\Purchasing\Support;

final readonly class NewSupplier
{
    public function __construct(
        public string $id,
        public int $shopId,
        public string $name,
        public ?string $phone,
    ) {}
}
