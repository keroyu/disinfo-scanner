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
            'check.admin' => \App\Http\Middleware\CheckAdminRole::class,
            'check.admin.session' => \App\Http\Middleware\CheckAdminSessionTimeout::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'check.api.quota' => \App\Http\Middleware\CheckApiQuota::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Clean up expired OTP tokens (daily at 2:00 AM)
        $schedule->call(function () {
            $service = app(\App\Services\OtpService::class);
            $service->cleanupExpired();
        })->daily()->at('02:00')->name('cleanup-expired-otp-tokens');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
