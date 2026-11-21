<?php

namespace App\Modules\Transactions\Models;

use App\Shared\Database\BinaryUuid;
use App\Shared\Database\HasBinaryUuidKey;
use Illuminate\Database\Eloquent\Model;

class TransactionLine extends Model
{
    use HasBinaryUuidKey;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'transaction_id' => BinaryUuid::class,
            'shop_product_id' => BinaryUuid::class,
            'position' => 'integer',
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'amount' => 'integer',
        ];
    }
}
