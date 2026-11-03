<?php

use App\Modules\Products\Http\Controllers\CategoryController;
use App\Modules\Products\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

// Browsing the catalogue is public
Route::get('categories', [CategoryController::class, 'index']);
Route::get('products', [ProductController::class, 'index']);
Route::get('products/{product}', [ProductController::class, 'show'])->whereNumber('product');

Route::middleware(['auth:api', 'role:admin'])->group(function () {
    Route::post('products', [ProductController::class, 'store']);
});
