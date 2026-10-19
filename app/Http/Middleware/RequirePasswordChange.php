<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accounts created or reset by an admin carry a temporary password. Until the
 * owner replaces it, the only things they can do are see who they are, change
 * the password, or log out.
 */
class RequirePasswordChange
{
    private const ALLOWED = ['api/auth/me', 'api/auth/logout', 'api/auth/change-password'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password && ! $request->is(self::ALLOWED)) {
            return response()->json([
                'success' => false,
                'code'    => 'password_change_required',
                'message' => 'Please set a new password before continuing.',
            ], 403);
        }

        return $next($request);
    }
}
