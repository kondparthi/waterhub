<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();
        if (! $user || ! in_array($user->role, $roles, true)) {
            return response()->json(['success' => false, 'message' => 'You are not allowed to access this resource.'], 403);
        }
        if ($user->status !== 'active') {
            $user->currentAccessToken()?->delete();
            return response()->json(['success' => false, 'message' => 'Your account is ' . str_replace('_', ' ', $user->status) . '.'], 403);
        }
        return $next($request);
    }
}
