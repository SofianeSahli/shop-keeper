<?php

namespace App\Modules\Transactions\Models;

enum PaymentState: string
{
    case Unpaid = 'unpaid';
    case PartlyPaid = 'partly_paid';
    case Paid = 'paid';

    public static function of(int $total, int $paid): self
    {
        return match (true) {
            $paid >= $total => self::Paid,
            $paid === 0 => self::Unpaid,
            default => self::PartlyPaid,
        };
    }
}
