<?php

namespace App\Modules\Purchasing\Models;

use App\Shared\Database\BinaryUuid;
use App\Shared\Database\HasBinaryUuidKey;
use Illuminate\Database\Eloquent\Model;

class DeliveryLine extends Model
{
    use HasBinaryUuidKey;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'delivery_id' => BinaryUuid::class,
            'shop_product_id' => BinaryUuid::class,
            'position' => 'integer',
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'amount' => 'integer',
        ];
    }
}
