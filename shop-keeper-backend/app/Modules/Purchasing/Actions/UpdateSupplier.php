<?php

namespace App\Modules\Purchasing\Actions;

use App\Modules\Catalog\Support\NameComparison;
use App\Modules\Purchasing\Models\Supplier;
use App\Modules\Purchasing\Support\SupplierChanges;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class UpdateSupplier
{
    public function __construct(
        private SupplierNameRules $nameRules,
        private FindSupplierOfShop $findSupplierOfShop,
    ) {}

    public function handle(Supplier $supplier, SupplierChanges $changes): Supplier
    {
        $supplierId = $supplier->uuid();

        DB::transaction(function () use ($supplierId, $changes): void {
            $locked = Supplier::query()->whereUuid($supplierId)->lockForUpdate()->firstOrFail();

            if ($changes->name !== null) {
                $this->checkNewName($locked, $changes->name);
                $locked->name = $changes->name;
            }

            if ($changes->changesPhone) {
                $locked->phone = $changes->phone;
            }

            $this->save($locked);
        });

        return $this->findSupplierOfShop->handle($supplier->shop_id, $supplierId);
    }

    private function checkNewName(Supplier $supplier, string $newName): void
    {
        $comparedName = NameComparison::compared($newName);

        if ($comparedName === $supplier->compared_name) {
            return;
        }

        $supplierId = $supplier->uuid();
        $this->nameRules->refuseIfNameTaken($supplier->shop_id, $newName, exceptSupplierId: $supplierId);
    }

    private function save(Supplier $supplier): void
    {
        try {
            $supplier->save();
        } catch (UniqueConstraintViolationException $lostRace) {
            $supplierId = $supplier->uuid();
            $this->nameRules->refuseIfNameTaken($supplier->shop_id, $supplier->name, exceptSupplierId: $supplierId, readLatest: true);

            throw $lostRace;
        }
    }
}
