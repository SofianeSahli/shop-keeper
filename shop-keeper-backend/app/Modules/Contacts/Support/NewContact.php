<?php

namespace App\Modules\Contacts\Support;

final readonly class NewContact
{
    public function __construct(
        public string $id,
        public int $shopId,
        public string $name,
        public ?string $phone,
        public ?string $address,
        public ?string $note,
        public bool $isSupplier,
        public bool $isCustomer,
    ) {}
}
