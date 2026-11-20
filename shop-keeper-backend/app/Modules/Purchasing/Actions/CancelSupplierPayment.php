<?php

namespace App\Modules\Purchasing\Actions;

use App\Modules\Purchasing\Models\SupplierPayment;
use App\Modules\Purchasing\Models\SupplierPaymentCancellation;
use App\Modules\Purchasing\Support\Created;
use App\Modules\Purchasing\Support\RecordWithSameId;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CancelSupplierPayment
{
    public function __construct(private FindSupplierPaymentOfShop $findSupplierPaymentOfShop) {}

    public function handle(SupplierPayment $payment, string $cancellationId): Created
    {
        $paymentId = $payment->uuid();

        $wasCancelled = DB::transaction(function () use ($payment, $paymentId, $cancellationId): bool {
            $locked = SupplierPayment::query()->whereUuid($paymentId)->lockForUpdate()->firstOrFail();
            $paymentBytes = $locked->getKey();
            $isAlreadyCancelled = SupplierPaymentCancellation::query()->where('supplier_payment_id', $paymentBytes)->exists();

            if ($isAlreadyCancelled) {
                return false;
            }

            $cancellationQuery = SupplierPaymentCancellation::query();
            RecordWithSameId::refuseIfUsed($cancellationQuery, $cancellationId, 'id');
            $this->insertCancellation($payment, $paymentId, $cancellationId);

            return true;
        });

        $cancelledPayment = $this->findSupplierPaymentOfShop->handle($payment->shop_id, $paymentId);

        return new Created($cancelledPayment, $wasCancelled);
    }

    private function insertCancellation(SupplierPayment $payment, string $paymentId, string $cancellationId): void
    {
        try {
            SupplierPaymentCancellation::query()->create([
                'id' => $cancellationId,
                'shop_id' => $payment->shop_id,
                'supplier_payment_id' => $paymentId,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['id' => [RecordWithSameId::TAKEN]]);
        }
    }
}
