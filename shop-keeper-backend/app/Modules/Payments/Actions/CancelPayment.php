<?php

namespace App\Modules\Payments\Actions;

use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\PaymentCancellation;
use App\Shared\Records\CancelOnce;
use App\Shared\Records\Created;
use App\Shared\Records\NewCancellation;

final class CancelPayment
{
    private const RECORD_COLUMN = 'payment_id';

    public function __construct(
        private CancelOnce $cancelOnce,
        private FindPaymentOfShop $findPaymentOfShop,
    ) {}

    public function handle(Payment $payment, NewCancellation $new): Created
    {
        $paymentId = $payment->uuid();
        $cancellation = new PaymentCancellation([
            'id' => $new->id,
            'shop_id' => $payment->shop_id,
            self::RECORD_COLUMN => $paymentId,
            'reason' => $new->reason,
            'cancelled_by' => $new->cancelledBy,
        ]);

        $cancelled = $this->cancelOnce->handle($payment, $cancellation, self::RECORD_COLUMN, function (Payment $locked): void {});

        $cancelledPayment = $this->findPaymentOfShop->handle($payment->shop_id, $paymentId);

        return new Created($cancelledPayment, $cancelled->wasCreated);
    }
}
