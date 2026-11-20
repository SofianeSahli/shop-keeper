<?php

namespace App\Modules\Purchasing\Actions;

use App\Modules\Purchasing\Models\Delivery;
use App\Modules\Purchasing\Models\DeliveryLine;
use App\Modules\Purchasing\Models\SupplierPayment;
use App\Shared\Errors\ApiException;
use Symfony\Component\HttpFoundation\Response;

final class FindDeliveryOfShop
{
    public function handle(int $shopId, string $deliveryId): Delivery
    {
        $delivery = Delivery::query()
            ->ofShop($shopId)
            ->whereUuid($deliveryId)
            ->withDetails()
            ->first() ?? throw new ApiException('delivery.not_found', Response::HTTP_NOT_FOUND);

        $deliveryBytes = $delivery->getKey();
        $lines = DeliveryLine::query()->where('delivery_id', $deliveryBytes)->orderBy('position')->get();
        $payment = SupplierPayment::query()->where('delivery_id', $deliveryBytes)->withDetails()->first();

        return $delivery->setRelation('lines', $lines)->setRelation('payment', $payment);
    }
}
