<?php

namespace App\Modules\Purchasing\Models;

use App\Modules\Catalog\Support\NameComparison;
use App\Modules\Purchasing\database\factories\SupplierFactory;
use App\Shared\Database\HasBinaryUuidKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['id', 'shop_id', 'name', 'phone'])]
class Supplier extends Model
{
    use HasBinaryUuidKey, HasFactory;

    public const NAME_MAX_LENGTH = 120;

    public function scopeOfShop(Builder $query, int $shopId): void
    {
        $query->where('shop_id', $shopId);
    }

    public function scopeWithBalance(Builder $query): void
    {
        $deliveredTotal = Delivery::query()
            ->notCancelled()
            ->selectRaw('coalesce(sum(total), 0)')
            ->whereColumn('deliveries.supplier_id', 'suppliers.id');
        $paidTotal = SupplierPayment::query()
            ->notCancelled()
            ->selectRaw('coalesce(sum(amount), 0)')
            ->whereColumn('supplier_payments.supplier_id', 'suppliers.id');

        $query->select('suppliers.*')->addSelect(['delivered_total' => $deliveredTotal, 'paid_total' => $paidTotal]);
    }

    public function balance(): int
    {
        return (int) $this->delivered_total - (int) $this->paid_total;
    }

    protected static function booted(): void
    {
        static::saving(function (Supplier $supplier): void {
            $supplier->compared_name = NameComparison::compared($supplier->name);
            $supplier->identity_key = NameComparison::identityKey($supplier->name);
        });
    }

    protected static function newFactory(): SupplierFactory
    {
        return SupplierFactory::new();
    }
}
