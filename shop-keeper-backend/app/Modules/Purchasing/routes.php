<?php

use App\Modules\Purchasing\Controllers\DeliveryController;
use App\Modules\Purchasing\Controllers\SupplierController;
use App\Modules\Purchasing\Controllers\SupplierPaymentController;
use App\Modules\Shops\Controllers\Middleware\RequireShop;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', RequireShop::ALIAS])->group(function () {
    Route::get('suppliers', [SupplierController::class, 'index']);
    Route::post('suppliers', [SupplierController::class, 'store']);
    Route::get('suppliers/{supplier}', [SupplierController::class, 'show']);
    Route::patch('suppliers/{supplier}', [SupplierController::class, 'update']);

    Route::get('deliveries', [DeliveryController::class, 'index']);
    Route::post('deliveries', [DeliveryController::class, 'store']);
    Route::get('deliveries/{delivery}', [DeliveryController::class, 'show']);
    Route::post('deliveries/{delivery}/cancellation', [DeliveryController::class, 'cancel']);

    Route::get('supplier-payments', [SupplierPaymentController::class, 'index']);
    Route::post('supplier-payments', [SupplierPaymentController::class, 'store']);
    Route::get('supplier-payments/{payment}', [SupplierPaymentController::class, 'show']);
    Route::post('supplier-payments/{payment}/cancellation', [SupplierPaymentController::class, 'cancel']);
});
