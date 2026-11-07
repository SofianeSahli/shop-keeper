<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['global_product_id', 'language', 'kind', 'term', 'compared'])]
#[WithoutTimestamps]
class GlobalProductTerm extends Model
{
    public function globalProduct(): BelongsTo
    {
        return $this->belongsTo(GlobalProduct::class);
    }

    protected function casts(): array
    {
        return ['kind' => CatalogTermKind::class];
    }
}
