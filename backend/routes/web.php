<?php

use Illuminate\Support\Facades\Route;

// Store pages: plain views; their scripts call the JSON API under /api/v1
Route::redirect('/', '/login');
Route::view('/login', 'auth.login')->name('login');
Route::view('/register', 'auth.register')->name('register');
Route::view('/account', 'account')->name('account');

// Interactive Swagger UI on top of the OpenAPI document Scramble generates at /docs/api.json
Route::view('/api/documentation', 'swagger')
    ->middleware(\Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess::class);
