<?php

use Illuminate\Support\Facades\Route;

// Store pages: plain views; their scripts call the JSON API under /api/v1
Route::redirect('/', '/products');
Route::view('/login', 'auth.login')->name('login');
Route::view('/register', 'auth.register')->name('register');
Route::view('/account', 'account')->name('account');

Route::view('/products', 'products.index')->name('products.index');
Route::get('/products/{productId}', fn (int $productId) => view('products.show', ['productId' => $productId]))
    ->whereNumber('productId')
    ->name('products.show');

Route::view('/cart', 'cart')->name('cart');
Route::view('/notifications', 'notifications')->name('notifications');
Route::view('/orders', 'orders.index')->name('orders.index');
Route::get('/orders/{orderId}', fn (int $orderId) => view('orders.show', ['orderId' => $orderId]))
    ->whereNumber('orderId')
    ->name('orders.show');

// The page itself is public markup; the API refuses the save unless the token belongs to an admin
Route::view('/admin/products/create', 'admin.products.create')->name('admin.products.create');

// Interactive Swagger UI on top of the OpenAPI document Scramble generates at /docs/api.json
Route::view('/api/documentation', 'swagger')
    ->middleware(\Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess::class);
