<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\CatalogLanguage;
use App\Modules\Catalog\Models\CatalogStatus;
use App\Modules\Catalog\Models\GlobalProduct;
use App\Modules\Catalog\Support\Barcode;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class FindOrCreatePendingProduct
{
    public function handle(Barcode $barcode, string $typedName, CatalogLanguage $typedLanguage): GlobalProduct
    {
        try {
            return DB::transaction(fn (): GlobalProduct => GlobalProduct::query()->create([
                'translation_key' => GlobalProduct::pendingKeyFor($barcode->value),
                'status' => CatalogStatus::Pending,
                'barcode' => $barcode->value,
                'pending_name' => $typedName,
                'pending_language' => $typedLanguage->value,
            ]));
        } catch (UniqueConstraintViolationException) {
            return GlobalProduct::query()
                ->where('barcode', $barcode->value)
                ->lockForUpdate()
                ->with('names')
                ->firstOrFail();
        }
    }
}
