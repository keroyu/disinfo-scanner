<?php

use Illuminate\Support\Facades\Route;

// Import page
Route::get('/', function () {
    return view('import.index');
});

// Channel list page
Route::get('/channels', [\App\Http\Controllers\ChannelListController::class, 'index']);
