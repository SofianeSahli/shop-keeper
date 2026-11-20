<?php

namespace App\Modules\Purchasing\Actions;

use App\Modules\Purchasing\Models\Supplier;
use App\Modules\Purchasing\Models\SupplierPayment;
use App\Modules\Purchasing\Support\Created;
use App\Modules\Purchasing\Support\NewSupplierPayment;
use App\Modules\Purchasing\Support\RecordWithSameId;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RecordSupplierPayment
{
    public function __construct(private FindSupplierPaymentOfShop $findSupplierPaymentOfShop) {}

    public function handle(NewSupplierPayment $new): Created
    {
        $wasCreated = DB::transaction(function () use ($new): bool {
            $paymentQuery = SupplierPayment::query();
            $paymentWithSameId = RecordWithSameId::find($paymentQuery, $new->id, $new->shopId);

            if ($paymentWithSameId !== null) {
                return false;
            }

            $this->requireSupplierOfShop($new);

            return $this->insert($new);
        });

        $payment = $this->findSupplierPaymentOfShop->handle($new->shopId, $new->id);

        return new Created($payment, $wasCreated);
    }

    private function requireSupplierOfShop(NewSupplierPayment $new): void
    {
        $isSupplierOfShop = Supplier::query()->ofShop($new->shopId)->whereUuid($new->supplierId)->exists();

        if (! $isSupplierOfShop) {
            throw ValidationException::withMessages(['supplier_id' => ['supplier_id.unknown']]);
        }
    }

    private function insert(NewSupplierPayment $new): bool
    {
        try {
            SupplierPayment::query()->create([
                'id' => $new->id,
                'shop_id' => $new->shopId,
                'supplier_id' => $new->supplierId,
                'delivery_id' => $new->deliveryId,
                'amount' => $new->amount,
                'method' => $new->method,
                'payment_date' => $new->paymentDate,
                'reference' => $new->reference,
            ]);
        } catch (UniqueConstraintViolationException $lostRace) {
            $paymentQuery = SupplierPayment::query();
            $winner = RecordWithSameId::find($paymentQuery, $new->id, $new->shopId, readLatest: true);

            if ($winner !== null) {
                return false;
            }

            throw $lostRace;
        }

        return true;
    }
}
