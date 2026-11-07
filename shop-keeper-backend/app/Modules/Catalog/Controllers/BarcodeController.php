<?php

namespace App\Modules\Catalog\Controllers;

use App\Modules\Catalog\Actions\LookUpBarcode;
use App\Modules\Catalog\Controllers\Resources\BarcodeLookupResource;
use App\Modules\Catalog\Support\Barcode;
use App\Modules\Catalog\Support\InvalidBarcode;
use App\Shared\CurrentShop;
use Illuminate\Validation\ValidationException;

final class BarcodeController
{
    public function __invoke(string $barcode, CurrentShop $currentShop, LookUpBarcode $lookUpBarcode): BarcodeLookupResource
    {
        try {
            $parsedBarcode = Barcode::parse($barcode);
        } catch (InvalidBarcode $failure) {
            throw ValidationException::withMessages(['barcode' => [$failure->fieldCode]]);
        }

        return new BarcodeLookupResource($lookUpBarcode->handle($currentShop->shopId, $parsedBarcode));
    }
}
