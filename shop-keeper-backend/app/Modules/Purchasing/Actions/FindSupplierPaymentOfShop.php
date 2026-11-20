<?php

namespace App\Modules\Purchasing\Actions;

use App\Modules\Purchasing\Models\SupplierPayment;
use App\Shared\Errors\ApiException;
use Symfony\Component\HttpFoundation\Response;

final class FindSupplierPaymentOfShop
{
    public function handle(int $shopId, string $paymentId): SupplierPayment
    {
        return SupplierPayment::query()
            ->ofShop($shopId)
            ->whereUuid($paymentId)
            ->withDetails()
            ->first() ?? throw new ApiException('supplier_payment.not_found', Response::HTTP_NOT_FOUND);
    }
}
