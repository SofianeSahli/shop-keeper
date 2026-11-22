<?php

namespace App\Modules\Inventory\Models;

use App\Shared\Database\BinaryUuid;
use App\Shared\Database\HasBinaryUuidKey;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use HasBinaryUuidKey;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'shop_product_id' => BinaryUuid::class,
            'source_id' => BinaryUuid::class,
            'kind' => StockMovementKind::class,
            'quantity' => 'integer',
            'sequence' => 'integer',
        ];
    }
}
