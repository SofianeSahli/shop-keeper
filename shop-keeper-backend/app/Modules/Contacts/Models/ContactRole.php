<?php

namespace App\Modules\Contacts\Models;

enum ContactRole: string
{
    case Supplier = 'supplier';
    case Customer = 'customer';

    public function column(): string
    {
        return match ($this) {
            self::Supplier => 'is_supplier',
            self::Customer => 'is_customer',
        };
    }
}
