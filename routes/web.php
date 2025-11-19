<?php

use Illuminate\Support\Facades\Route;

// Import page
Route::get('/', function () {
    return view('import.index');
})->name('import.index');

// Channel list page
Route::get('/channels', [\App\Http\Controllers\ChannelListController::class, 'index'])->name('channels.index');

// Comments list page
Route::get('/comments', [\App\Http\Controllers\CommentController::class, 'index'])->name('comments.index');

// Videos list page
Route::get('/videos', [\App\Http\Controllers\VideoController::class, 'index'])->name('videos.index');

// Video Analysis page (008-video-comment-density)
Route::get('/videos/{video}/analysis', [\App\Http\Controllers\VideoAnalysisController::class, 'showAnalysisPage'])->name('videos.analysis');

// YouTube API Import endpoints
Route::prefix('api')->group(function () {
    Route::prefix('youtube-import')->group(function () {
        Route::get('show-form', [\App\Http\Controllers\YouTubeApiImportController::class, 'showForm'])->name('youtube-import.form');
        Route::post('metadata', [\App\Http\Controllers\YouTubeApiImportController::class, 'getMetadata'])->name('youtube-import.metadata');
        Route::post('preview', [\App\Http\Controllers\YouTubeApiImportController::class, 'getPreview'])->name('youtube-import.preview');
        Route::post('confirm-import', [\App\Http\Controllers\YouTubeApiImportController::class, 'confirmImport'])->name('youtube-import.confirm');
    });
});
