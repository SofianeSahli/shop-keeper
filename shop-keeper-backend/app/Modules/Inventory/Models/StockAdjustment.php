<?php

namespace App\Modules\Inventory\Models;

use App\Shared\Database\HasBinaryUuidKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

#[Fillable(['id', 'shop_id', 'kind', 'adjustment_date', 'line_count', 'created_by'])]
class StockAdjustment extends Model
{
    use HasBinaryUuidKey;

    public const UPDATED_AT = null;

    public function scopeOfShop(Builder $query, int $shopId): void
    {
        $query->where('shop_id', $shopId);
    }

    /**
     * @return Collection<int, StockAdjustmentLine>
     */
    public function orderedLines(): Collection
    {
        $adjustmentBytes = $this->getKey();

        return StockAdjustmentLine::query()->where('stock_adjustment_id', $adjustmentBytes)->orderBy('position')->get();
    }

    protected function casts(): array
    {
        return [
            'shop_id' => 'integer',
            'kind' => StockAdjustmentKind::class,
            'line_count' => 'integer',
            'created_by' => 'integer',
        ];
    }
}
