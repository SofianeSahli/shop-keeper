<?php

namespace App\Modules\Purchasing\Actions;

use App\Modules\Purchasing\Models\Supplier;
use App\Modules\Purchasing\Support\Created;
use App\Modules\Purchasing\Support\NewSupplier;
use App\Modules\Purchasing\Support\RecordWithSameId;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class CreateSupplier
{
    public function __construct(
        private SupplierNameRules $nameRules,
        private FindSupplierOfShop $findSupplierOfShop,
    ) {}

    public function handle(NewSupplier $new): Created
    {
        $wasCreated = DB::transaction(function () use ($new): bool {
            $supplierQuery = Supplier::query();
            $supplierWithSameId = RecordWithSameId::find($supplierQuery, $new->id, $new->shopId);

            if ($supplierWithSameId !== null) {
                return false;
            }

            $this->nameRules->refuseIfNameTaken($new->shopId, $new->name);

            return $this->insert($new);
        });

        $supplier = $this->findSupplierOfShop->handle($new->shopId, $new->id);

        return new Created($supplier, $wasCreated);
    }

    private function insert(NewSupplier $new): bool
    {
        try {
            Supplier::query()->create([
                'id' => $new->id,
                'shop_id' => $new->shopId,
                'name' => $new->name,
                'phone' => $new->phone,
            ]);
        } catch (UniqueConstraintViolationException $lostRace) {
            $supplierQuery = Supplier::query();
            $winner = RecordWithSameId::find($supplierQuery, $new->id, $new->shopId, readLatest: true);

            if ($winner !== null) {
                return false;
            }

            $this->nameRules->refuseIfNameTaken($new->shopId, $new->name, readLatest: true);

            throw $lostRace;
        }

        return true;
    }
}
