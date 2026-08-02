<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deactivating a staff member should cut off access that is already open, not
 * just block the next sign-in. That means both the browser session and any API
 * token the phone in their pocket is still holding.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user || $user->is_active) {
            return $next($request);
        }

        $message = 'Your account has been deactivated.';
        $token = $user->currentAccessToken();

        // Burn the token so a deactivated device cannot keep using it. A
        // transient token means the caller is session-backed, not a real API
        // client, so there is nothing to revoke.
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('error', $message);
    }
}
