<?php

namespace App\Shared;

final readonly class CurrentShop
{
    public function __construct(public int $shopId) {}
}
