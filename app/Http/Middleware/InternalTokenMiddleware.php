<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InternalTokenMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.ussd.internal_token', '');

        if ($expected === '') {
            return $this->error('INTERNAL_API_NOT_CONFIGURED', 'Internal API is not configured.', 503);
        }

        $provided = (string) $request->header('X-Internal-Token', '');

        if (!hash_equals($expected, $provided)) {
            return $this->error('UNAUTHORIZED', 'Invalid internal token.', 401);
        }

        $allowlist = config('services.ussd.internal_ip_allowlist', []);

        if (is_array($allowlist) && $allowlist !== [] && !in_array($request->ip(), $allowlist, true)) {
            return $this->error('FORBIDDEN', 'This IP is not allowed.', 403);
        }

        return $next($request);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'status' => false,
            'code' => $code,
            'message' => $message,
        ], $status);
    }
}
