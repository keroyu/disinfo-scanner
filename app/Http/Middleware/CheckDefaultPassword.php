<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Check Default Password Middleware
 *
 * Enforces mandatory password change for users with must_change_password flag
 * Redirects to password change page if flag is true
 *
 * T053: User Story 2 - Prevent bypass of mandatory password change
 */
class CheckDefaultPassword
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip check if user is not authenticated
        if (!Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();

        // Skip check for password change routes to avoid redirect loop
        $passwordChangeRoutes = [
            'auth/mandatory-password-change',
            'api/auth/password/change',
            'api/auth/logout',
        ];

        foreach ($passwordChangeRoutes as $route) {
            if ($request->is($route)) {
                return $next($request);
            }
        }

        // If user must change password, redirect to mandatory change page
        if ($user->must_change_password) {
            // For API requests, return JSON response
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => '您必須先更改密碼才能訪問此功能',
                    'redirect' => '/auth/mandatory-password-change',
                ], 403);
            }

            // For web requests, redirect to password change page
            return redirect('/auth/mandatory-password-change')
                ->with('info', '為了您的帳戶安全，請更改預設密碼');
        }

        return $next($request);
    }
}
