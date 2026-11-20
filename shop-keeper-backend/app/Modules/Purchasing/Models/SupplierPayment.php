<?php

namespace App\Modules\Purchasing\Models;

use App\Shared\Database\BinaryUuid;
use App\Shared\Database\HasBinaryUuidKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

#[Fillable(['id', 'shop_id', 'supplier_id', 'delivery_id', 'amount', 'method', 'payment_date', 'reference'])]
class SupplierPayment extends Model
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
                ->from('supplier_payment_cancellations')
                ->whereColumn('supplier_payment_cancellations.supplier_payment_id', 'supplier_payments.id');
        });
    }

    public function scopeWithDetails(Builder $query): void
    {
        $supplierName = Supplier::query()->select('name')->whereColumn('suppliers.id', 'supplier_payments.supplier_id');
        $cancelledAt = SupplierPaymentCancellation::query()
            ->select('created_at')
            ->whereColumn('supplier_payment_cancellations.supplier_payment_id', 'supplier_payments.id');

        $query->select('supplier_payments.*')->addSelect(['supplier_name' => $supplierName, 'cancelled_at' => $cancelledAt]);
    }

    protected function casts(): array
    {
        return [
            'supplier_id' => BinaryUuid::class,
            'delivery_id' => BinaryUuid::class,
            'amount' => 'integer',
            'method' => PaymentMethod::class,
            'cancelled_at' => 'immutable_datetime',
        ];
    }
}
