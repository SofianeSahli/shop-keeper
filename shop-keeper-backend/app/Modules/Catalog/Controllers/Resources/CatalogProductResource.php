<?php

namespace App\Modules\Catalog\Controllers\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class CatalogProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->translation_key,
            'status' => $this->status->value,
            'barcode' => $this->barcode,
            'names' => $this->namesByLanguage(),
        ];
    }
}
