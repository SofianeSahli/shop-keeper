<?php

use App\Modules\Contacts\Controllers\ContactController;
use App\Modules\Shops\Controllers\Middleware\RequireShop;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', RequireShop::ALIAS])->group(function () {
    Route::get('contacts', [ContactController::class, 'index']);
    Route::post('contacts', [ContactController::class, 'store']);
    Route::get('contacts/{contact}', [ContactController::class, 'show']);
    Route::patch('contacts/{contact}', [ContactController::class, 'update']);
});
