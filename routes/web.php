<?php

use Illuminate\Support\Facades\Route;

// Import page
Route::get('/', function () {
    return view('import.index');
});

// Channel list page
Route::get('/channels', [\App\Http\Controllers\ChannelListController::class, 'index'])->name('channels.index');

// Comments list page
Route::get('/comments', [\App\Http\Controllers\CommentController::class, 'index'])->name('comments.index');
