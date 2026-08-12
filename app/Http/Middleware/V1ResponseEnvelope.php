<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class V1ResponseEnvelope
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!($response instanceof JsonResponse)) {
            return $response;
        }

        $payload = $response->getData(true);
        $statusCode = $response->getStatusCode();

        $success = array_key_exists('status', $payload) && $payload['status'] === true;

        if ($success) {
            $data = array_key_exists('data', $payload) ? $payload['data'] : $this->extractData($payload);

            return response()->json([
                'status' => true,
                'code' => $statusCode,
                'data' => $data,
                'message' => $payload['message'] ?? '',
            ], $statusCode);
        }

        $message = $payload['message']
            ?? ($payload['error']['message'] ?? 'Request failed');

        $error = $payload['error'] ?? [];
        $error['code'] = $error['code'] ?? ($statusCode >= 400 ? $statusCode : 400);
        $error['message'] = $error['message'] ?? $message;

        return response()->json([
            'status' => false,
            'error' => $error,
            'reason' => $message,
        ], $statusCode >= 400 ? $statusCode : 400);
    }

    private function extractData(array $payload): array
    {
        unset($payload['status'], $payload['message'], $payload['code']);

        return $payload;
    }
}