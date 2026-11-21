<?php

namespace App\Modules\Payments\Models;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Cheque = 'cheque';
    case Card = 'card';
    case Transfer = 'transfer';
}
