<?php

namespace App\Modules\Purchasing\Actions;

use App\Modules\Catalog\Support\NameComparison;
use App\Modules\Purchasing\Models\Supplier;
use App\Shared\Errors\ApiException;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\Response;

final class SupplierNameRules
{
    public function refuseIfNameTaken(int $shopId, string $name, ?string $exceptSupplierId = null, bool $readLatest = false): void
    {
        $identityKey = NameComparison::identityKey($name);
        $existing = Supplier::query()
            ->ofShop($shopId)
            ->where('identity_key', $identityKey)
            ->when($exceptSupplierId !== null, fn (Builder $query) => $query->whereNot(fn (Builder $excluded) => $excluded->whereUuid($exceptSupplierId)))
            ->when($readLatest, fn (Builder $query) => $query->lockForUpdate())
            ->first();

        if ($existing !== null) {
            $existingSupplierId = $existing->uuid();

            throw new ApiException('supplier.name_taken', Response::HTTP_CONFLICT, details: ['existing_supplier_id' => $existingSupplierId]);
        }
    }
}
