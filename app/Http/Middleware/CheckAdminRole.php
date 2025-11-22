<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAdminRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (!auth()->check()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => '請先登入'
                ], 401);
            }
            return redirect()->route('login')->with('error', '請先登入');
        }

        $user = auth()->user();

        // Check if user has administrator role
        if (!$user->roles()->where('name', 'administrator')->exists()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => '無權限訪問此功能'
                ], 403);
            }
            abort(403, '無權限訪問此功能');
        }

        return $next($request);
    }
}
