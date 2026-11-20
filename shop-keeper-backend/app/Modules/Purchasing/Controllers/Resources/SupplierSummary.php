<?php

namespace App\Modules\Purchasing\Controllers\Resources;

final class SupplierSummary
{
    /**
     * @return array{id: string, name: string}|null
     */
    public static function of(?string $supplierId, ?string $supplierName): ?array
    {
        if ($supplierId === null) {
            return null;
        }

        return ['id' => $supplierId, 'name' => $supplierName];
    }
}
