<?php

namespace App\Modules\Payments\Models;

enum PaymentDirection: string
{
    case Paid = 'paid';
    case Received = 'received';
}
