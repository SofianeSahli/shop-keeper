<?php

use App\Modules\Catalog\CatalogServiceProvider;
use App\Modules\Catalog\Controllers\BarcodeController;
use App\Modules\Catalog\Controllers\ShopProductController;
use App\Modules\Catalog\Controllers\SuggestionController;
use App\Modules\Shops\Controllers\Middleware\RequireShop;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', RequireShop::ALIAS])->group(function () {
    Route::middleware('throttle:'.CatalogServiceProvider::LOOKUP_LIMITER)->group(function () {
        Route::get('catalog/barcodes/{barcode}', BarcodeController::class);
        Route::get('products/suggestions', SuggestionController::class);
    });

    Route::get('products', [ShopProductController::class, 'index']);
    Route::post('products', [ShopProductController::class, 'store']);
    Route::get('products/{product}', [ShopProductController::class, 'show']);
    Route::patch('products/{product}', [ShopProductController::class, 'update']);
});
