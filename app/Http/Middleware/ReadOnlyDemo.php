<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Demo sessions (tokens issued by /auth/demo-login) may look at everything
 * their role allows but cannot change anything, so the public demo stays intact.
 */
class ReadOnlyDemo
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken
            && in_array('demo', $token->abilities ?? [], true)
            && ! $request->isMethodSafe()
            && ! $request->is('api/auth/logout')) {
            return response()->json(['success' => false, 'message' => 'This is a read-only demo account.'], 403);
        }

        return $next($request);
    }
}
