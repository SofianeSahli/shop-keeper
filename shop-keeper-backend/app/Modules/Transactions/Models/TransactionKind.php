<?php

namespace App\Modules\Transactions\Models;

enum TransactionKind: string
{
    case Purchase = 'purchase';

    private const PAID_BY_THE_SHOP = 'paid';

    public function paymentDirection(): string
    {
        return match ($this) {
            self::Purchase => self::PAID_BY_THE_SHOP,
        };
    }
}
