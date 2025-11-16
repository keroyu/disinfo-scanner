<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application.
|
*/

// Import endpoints
Route::post('/import', [\App\Http\Controllers\ImportController::class, 'store']);
Route::post('/import/confirm', [\App\Http\Controllers\ImportConfirmationController::class, 'confirm']);
Route::post('/import/cancel', [\App\Http\Controllers\ImportConfirmationController::class, 'cancel']);

// Tag selection endpoints
Route::post('/tags/select', [\App\Http\Controllers\TagSelectionController::class, 'store']);
Route::get('/tags', [\App\Http\Controllers\TagSelectionController::class, 'index']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
