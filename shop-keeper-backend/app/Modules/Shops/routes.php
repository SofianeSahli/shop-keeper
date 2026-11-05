<?php

use App\Modules\Shops\Controllers\ShopController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('shops', [ShopController::class, 'store']);
    Route::get('shops/{shop}', [ShopController::class, 'show']);
});
