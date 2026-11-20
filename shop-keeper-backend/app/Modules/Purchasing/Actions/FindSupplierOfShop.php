<?php

namespace App\Modules\Purchasing\Actions;

use App\Modules\Purchasing\Models\Supplier;
use App\Shared\Errors\ApiException;
use Symfony\Component\HttpFoundation\Response;

final class FindSupplierOfShop
{
    public function handle(int $shopId, string $supplierId): Supplier
    {
        return Supplier::query()
            ->ofShop($shopId)
            ->whereUuid($supplierId)
            ->withBalance()
            ->first() ?? throw new ApiException('supplier.not_found', Response::HTTP_NOT_FOUND);
    }
}
