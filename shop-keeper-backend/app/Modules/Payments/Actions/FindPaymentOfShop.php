<?php

namespace App\Modules\Payments\Actions;

use App\Modules\Payments\Models\Payment;
use App\Shared\Errors\ApiException;
use Symfony\Component\HttpFoundation\Response;

final class FindPaymentOfShop
{
    public function __construct(private AttachPaymentContactNames $attachPaymentContactNames) {}

    public function handle(int $shopId, string $paymentId): Payment
    {
        $payment = Payment::query()
            ->ofShop($shopId)
            ->whereUuid($paymentId)
            ->withCancellation()
            ->first() ?? throw new ApiException('payment.not_found', Response::HTTP_NOT_FOUND);

        $payments = collect([$payment]);
        $this->attachPaymentContactNames->handle($shopId, $payments);

        return $payment;
    }
}
