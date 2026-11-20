<?php

namespace App\Modules\Purchasing\Models;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Cheque = 'cheque';
    case Card = 'card';
    case Transfer = 'transfer';
}
