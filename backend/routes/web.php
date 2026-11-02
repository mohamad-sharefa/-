<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Interactive Swagger UI on top of the OpenAPI document Scramble generates at /docs/api.json
Route::view('/api/documentation', 'swagger')
    ->middleware(\Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess::class);
