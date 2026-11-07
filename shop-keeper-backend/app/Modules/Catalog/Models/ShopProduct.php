<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\database\factories\ShopProductFactory;
use App\Modules\Catalog\Support\NameComparison;
use App\Shared\Database\HasBinaryUuidKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['id', 'shop_id', 'global_product_id', 'name', 'sale_price', 'purchase_price'])]
class ShopProduct extends Model
{
    use HasBinaryUuidKey, HasFactory;

    public const NAME_MAX_LENGTH = 120;

    public function globalProduct(): BelongsTo
    {
        return $this->belongsTo(GlobalProduct::class);
    }

    public function scopeOfShop(Builder $query, int $shopId): void
    {
        $query->where('shop_id', $shopId);
    }

    public function uuid(): string
    {
        return $this->getAttribute($this->getKeyName());
    }

    protected static function booted(): void
    {
        static::saving(function (ShopProduct $product): void {
            $product->compared_name = NameComparison::compared($product->name);
            $product->identity_key = NameComparison::identityKey($product->name);
        });
    }

    protected function casts(): array
    {
        return [
            'sale_price' => 'integer',
            'purchase_price' => 'integer',
        ];
    }

    protected static function newFactory(): ShopProductFactory
    {
        return ShopProductFactory::new();
    }
}
