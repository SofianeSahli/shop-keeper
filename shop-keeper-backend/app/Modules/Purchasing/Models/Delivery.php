<?php

namespace App\Modules\Purchasing\Models;

use App\Shared\Database\BinaryUuid;
use App\Shared\Database\HasBinaryUuidKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

#[Fillable(['id', 'shop_id', 'supplier_id', 'delivery_date', 'reference', 'total', 'line_count'])]
class Delivery extends Model
{
    use HasBinaryUuidKey;

    public const UPDATED_AT = null;

    public function scopeOfShop(Builder $query, int $shopId): void
    {
        $query->where('shop_id', $shopId);
    }

    public function scopeOfSupplier(Builder $query, ?string $supplierId): void
    {
        if ($supplierId === null) {
            return;
        }

        $supplierBytes = BinaryUuid::toBytes($supplierId);
        $query->where('supplier_id', $supplierBytes);
    }

    public function scopeNotCancelled(Builder $query): void
    {
        $query->whereNotExists(function (QueryBuilder $cancellations): void {
            $cancellations->selectRaw('1')
                ->from('delivery_cancellations')
                ->whereColumn('delivery_cancellations.delivery_id', 'deliveries.id');
        });
    }

    public function scopeWithDetails(Builder $query): void
    {
        $supplierName = Supplier::query()->select('name')->whereColumn('suppliers.id', 'deliveries.supplier_id');
        $cancelledAt = DeliveryCancellation::query()->select('created_at')->whereColumn('delivery_cancellations.delivery_id', 'deliveries.id');

        $query->select('deliveries.*')->addSelect(['supplier_name' => $supplierName, 'cancelled_at' => $cancelledAt]);
    }

    protected function casts(): array
    {
        return [
            'supplier_id' => BinaryUuid::class,
            'total' => 'integer',
            'line_count' => 'integer',
            'cancelled_at' => 'immutable_datetime',
        ];
    }
}
