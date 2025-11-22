<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Register middleware aliases
        $middleware->alias([
            'check.default.password' => \App\Http\Middleware\CheckDefaultPassword::class,
            'check.email.verified' => \App\Http\Middleware\CheckEmailVerified::class,
            'check.admin' => \App\Http\Middleware\CheckAdminRole::class,
            'check.admin.session' => \App\Http\Middleware\CheckAdminSessionTimeout::class,
        ]);

        // Apply CheckDefaultPassword middleware to web group (excluding auth routes)
        $middleware->web(append: [
            \App\Http\Middleware\CheckDefaultPassword::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Clean up expired email verification tokens (daily at 2:00 AM)
        $schedule->call(function () {
            $service = app(\App\Services\EmailVerificationService::class);
            $service->cleanupExpiredTokens();
        })->daily()->at('02:00')->name('cleanup-expired-verification-tokens');

        // Clean up expired password reset tokens (daily at 2:30 AM)
        $schedule->call(function () {
            \Illuminate\Support\Facades\DB::table('password_reset_tokens')
                ->where('created_at', '<', now()->subDays(7))
                ->delete();
        })->daily()->at('02:30')->name('cleanup-expired-password-tokens');

        // Reset API quotas on the 1st of each month (at midnight)
        $schedule->call(function () {
            \Illuminate\Support\Facades\DB::table('api_quotas')
                ->where('is_unlimited', false)
                ->update([
                    'usage_count' => 0,
                    'current_month' => now()->format('Y-m'),
                    'updated_at' => now(),
                ]);
        })->monthly()->at('00:00')->name('reset-monthly-api-quotas');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
