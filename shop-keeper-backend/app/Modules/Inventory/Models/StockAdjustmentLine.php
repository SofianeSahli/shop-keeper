<?php

namespace App\Modules\Inventory\Models;

use App\Shared\Database\BinaryUuid;
use App\Shared\Database\HasBinaryUuidKey;
use Illuminate\Database\Eloquent\Model;

class StockAdjustmentLine extends Model
{
    use HasBinaryUuidKey;

    public const UPDATED_AT = null;

    public const NOTE_MAX_LENGTH = 255;

    protected function casts(): array
    {
        return [
            'shop_product_id' => BinaryUuid::class,
            'position' => 'integer',
            'quantity' => 'integer',
            'counted_quantity' => 'integer',
            'shown_quantity' => 'integer',
            'loss_reason' => LossReason::class,
        ];
    }
}
