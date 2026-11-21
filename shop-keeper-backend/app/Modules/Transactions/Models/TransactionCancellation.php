<?php

namespace App\Modules\Transactions\Models;

use App\Shared\Database\BinaryUuid;
use App\Shared\Database\HasBinaryUuidKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['id', 'shop_id', 'transaction_id', 'reason', 'cancelled_by'])]
class TransactionCancellation extends Model
{
    use HasBinaryUuidKey;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'transaction_id' => BinaryUuid::class,
            'cancelled_by' => 'integer',
        ];
    }
}
