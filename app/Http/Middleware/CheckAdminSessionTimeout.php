<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckAdminSessionTimeout
{
    /**
     * T286: Check admin session timeout (30 minutes vs 120 minutes for regular users)
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();

            // Check if user is admin
            if ($user->roles->contains('name', 'administrator')) {
                $lastActivity = session('admin_last_activity');
                $timeout = 30 * 60; // 30 minutes in seconds

                if ($lastActivity && (time() - $lastActivity > $timeout)) {
                    // Session expired - log out admin
                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    if ($request->expectsJson()) {
                        return response()->json([
                            'message' => '管理員會話已過期，請重新登入'
                        ], 401);
                    }

                    return redirect()->route('login')
                        ->with('error', '管理員會話已過期，請重新登入');
                }

                // Update last activity time
                session(['admin_last_activity' => time()]);
            }
        }

        return $next($request);
    }
}
