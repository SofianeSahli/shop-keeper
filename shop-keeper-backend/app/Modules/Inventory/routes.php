<?php

use App\Modules\Inventory\Controllers\StockAdjustmentController;
use App\Modules\Inventory\Controllers\StockMovementController;
use App\Modules\Shops\Controllers\Middleware\RequireShop;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', RequireShop::ALIAS])->group(function () {
    Route::post('stock/openings', [StockAdjustmentController::class, 'openings']);
    Route::post('stock/counts', [StockAdjustmentController::class, 'counts']);
    Route::post('stock/losses', [StockAdjustmentController::class, 'losses']);
    Route::get('products/{product}/stock-movements', StockMovementController::class);
});
