<?php

namespace App\Modules\Shops\Models;

use App\Modules\Shops\database\factories\ShopFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['owner_id', 'name', 'business_type', 'currency', 'city', 'address'])]
class Shop extends Model
{
    use HasFactory;

    public const DEFAULT_CURRENCY = 'TND';

    protected static function newFactory(): ShopFactory
    {
        return ShopFactory::new();
    }
}
