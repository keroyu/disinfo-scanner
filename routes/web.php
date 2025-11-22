<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\Auth\PasswordResetController;

// Authentication Routes (011-member-system)
Route::prefix('auth')->group(function () {
    // Public routes - GET (show forms)
    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::get('/verify-email', [EmailVerificationController::class, 'showVerificationPage'])->name('verification.notice');

    // Public routes - POST (submit forms)
    Route::post('/register', [RegisterController::class, 'register'])->name('register.submit');
    Route::post('/login', [LoginController::class, 'login'])->name('login.submit');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Email verification
    Route::get('/verify-email/verify', [EmailVerificationController::class, 'verify'])->name('verification.verify');
    Route::post('/verify-email/resend', [EmailVerificationController::class, 'resend'])->name('verification.resend');
    Route::post('/verify-email/check-status', [EmailVerificationController::class, 'checkStatus'])->name('verification.check-status');

    // Password reset routes (T054: User Story 2)
    Route::get('/password/reset', [PasswordResetController::class, 'showRequestForm'])->name('password.request');
    Route::post('/password/reset/request', [PasswordResetController::class, 'sendResetLink'])->name('password.email');
    Route::get('/password/reset/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('/password/reset', [PasswordResetController::class, 'reset'])->name('password.update');

    // Mandatory password change routes (T054: User Story 2)
    Route::middleware('auth')->group(function () {
        Route::get('/mandatory-password-change', [PasswordChangeController::class, 'showMandatoryChangeForm'])->name('password.mandatory');
        Route::post('/mandatory-password-change', [PasswordChangeController::class, 'change'])->name('password.mandatory-change');
    });
});

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

// User settings page
Route::middleware('auth')->group(function () {
    Route::get('/settings', [\App\Http\Controllers\UserSettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings/password', [\App\Http\Controllers\UserSettingsController::class, 'updatePassword'])->name('settings.password');
    Route::post('/settings/api-key', [\App\Http\Controllers\UserSettingsController::class, 'updateApiKey'])->name('settings.api-key');
    Route::post('/settings/api-key/remove', [\App\Http\Controllers\UserSettingsController::class, 'removeApiKey'])->name('settings.api-key.remove');
});

// Video Analysis page (008-video-comment-density)
Route::get('/videos/{video}/analysis', [\App\Http\Controllers\VideoAnalysisController::class, 'showAnalysisPage'])->name('videos.analysis');

// Admin Panel Routes (011-member-system - Admin Module)
// T221: Add admin user management routes
// T284: Admin routes with rate limiting (120 requests per minute for web)
// T286: Admin session timeout middleware
Route::prefix('admin')->middleware(['auth', 'check.admin', 'check.admin.session', 'throttle:120,1'])->group(function () {
    // Admin dashboard (Phase 3 - to be implemented)
    Route::get('/dashboard', function () {
        return view('admin.dashboard');
    })->name('admin.dashboard');

    // User management views (Phase 3 - to be implemented)
    Route::get('/users', function () {
        return view('admin.users.index');
    })->name('admin.users.index');

    Route::get('/users/{userId}/edit', function ($userId) {
        return view('admin.users.edit', ['userId' => $userId]);
    })->name('admin.users.edit');

    // Identity verification views (Phase 4)
    Route::get('/verifications', function () {
        return view('admin.verifications.index');
    })->name('admin.verifications.index');

    Route::get('/verifications/{verificationId}/review', function ($verificationId) {
        return view('admin.verifications.review', ['verificationId' => $verificationId]);
    })->name('admin.verifications.review');

    // Analytics & Reporting views (Phase 5)
    Route::get('/analytics', function () {
        return view('admin.analytics.index');
    })->name('admin.analytics.index');

    // Audit Logs view (Phase 6 - T287)
    Route::get('/audit-logs', function () {
        return view('admin.audit.index');
    })->name('admin.audit.index');
});
