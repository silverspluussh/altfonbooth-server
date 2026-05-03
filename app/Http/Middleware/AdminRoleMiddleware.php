<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminRoleMiddleware
{

    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if (!$user || !($user instanceof \App\Models\AdminModel)) {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        if ($role === 'super_admin' && !$user->isSuperAdmin()) {
            return response()->json(['message' => 'Forbidden. Super Admin access required.'], 403);
        }

        return $next($request);
    }
}
