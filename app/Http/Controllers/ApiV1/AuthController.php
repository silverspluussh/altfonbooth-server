<?php

namespace App\Http\Controllers\ApiV1;

use App\Http\Controllers\AuthController as ApiAuthController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController
{
    public function register(Request $request): JsonResponse
    {
        $response = app(ApiAuthController::class)->signup($request);
        $payload = $response->getData(true);

        // The legacy API returned HTTP 200 on successful registration.
        $statusCode = ($payload['status'] ?? false) === true && $response->getStatusCode() < 400
            ? 200
            : $response->getStatusCode();

        unset($payload['debug_otp']);

        return response()->json($payload, $statusCode);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        return app(ApiAuthController::class)->verify($request);
    }

    public function regenerateOtp(Request $request, string $email): JsonResponse
    {
        return app(ApiAuthController::class)->regenerateOtp($request, $email);
    }

    public function login(Request $request): JsonResponse
    {
        $response = app(ApiAuthController::class)->login($request);
        $payload = $response->getData(true);

        if (($payload['status'] ?? false) !== true) {
            return $response;
        }

        $user = $payload['user'] ?? [];
        [$firstname, $lastname] = $this->splitFullname($user['fullname'] ?? '');

        $data = [
            'access_token' => $payload['token'] ?? '',
            'subscriberid' => $user['subscriberid'] ?? null,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'username' => $user['username'] ?? null,
            'emailaddress' => $user['emailaddress'] ?? null,
            'phonenumber' => $user['phonenumber'] ?? null,
            'country' => $user['country'] ?? null,
            'authusername' => $user['authusername'] ?? null,
            'authpassword' => $user['authpassword'] ?? null,
        ];

        return response()->json([
            'status' => true,
            'message' => 'Logged in successfully',
            'data' => $data,
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        return app(ApiAuthController::class)->requestPasswordReset($request);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        return app(ApiAuthController::class)->resetPassword($request);
    }

    private function splitFullname(string $fullname): array
    {
        $parts = preg_split('/\s+/', trim($fullname), 2);

        return [$parts[0] ?? '', $parts[1] ?? ''];
    }
}