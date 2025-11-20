<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckEmailVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => '請先登入',
            ], 401);
        }

        if (!$user->is_email_verified) {
            return response()->json([
                'success' => false,
                'message' => '請先驗證您的電子郵件',
            ], 403);
        }

        return $next($request);
    }
}
