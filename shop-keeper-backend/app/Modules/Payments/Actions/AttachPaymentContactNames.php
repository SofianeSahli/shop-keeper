<?php

namespace App\Modules\Payments\Actions;

use App\Modules\Contacts\Actions\FindContactNamesOfShop;
use App\Modules\Payments\Models\Payment;
use Illuminate\Support\Collection;

final class AttachPaymentContactNames
{
    public function __construct(private FindContactNamesOfShop $findContactNamesOfShop) {}

    /**
     * @param  Collection<int, Payment>  $payments
     */
    public function handle(int $shopId, Collection $payments): void
    {
        $contactIds = $payments->pluck('contact_id')->all();
        $contactNameById = $this->findContactNamesOfShop->handle($shopId, $contactIds);

        foreach ($payments as $payment) {
            $payment->contact_name = $contactNameById[$payment->contact_id] ?? null;
        }
    }
}
