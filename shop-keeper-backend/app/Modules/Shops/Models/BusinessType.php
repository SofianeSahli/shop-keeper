<?php

namespace App\Modules\Shops\Models;

enum BusinessType: string
{
    case Grocery = 'grocery';
    case Hardware = 'hardware';
    case Bakery = 'bakery';
    case Clothing = 'clothing';
    case Other = 'other';
}
