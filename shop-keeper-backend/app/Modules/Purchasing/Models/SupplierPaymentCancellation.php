<?php

namespace App\Modules\Purchasing\Models;

use App\Shared\Database\BinaryUuid;
use App\Shared\Database\HasBinaryUuidKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['id', 'shop_id', 'supplier_payment_id'])]
class SupplierPaymentCancellation extends Model
{
    use HasBinaryUuidKey;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'supplier_payment_id' => BinaryUuid::class,
        ];
    }
}
