<?php

namespace App\Modules\Inventory\Contracts;

use App\Modules\Inventory\Support\PurchaseLineSource;

interface PurchaseLineSources
{
    /**
     * @param  list<string>  $lineIds
     * @return array<string, PurchaseLineSource>
     */
    public function describe(int $shopId, array $lineIds): array;
}
