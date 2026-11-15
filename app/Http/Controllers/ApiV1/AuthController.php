<?php

namespace App\Http\Controllers\ApiV1;

use App\Http\Controllers\AuthController as ApiAuthController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController
{
    public function register(Request $request): JsonResponse
    {
        $request->merge(['channel' => 'mobile']);
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
        $request->merge(['channel' => 'mobile']);
        $response = app(ApiAuthController::class)->login($request);
        $payload = $response->getData(true);

        if (($payload['status'] ?? false) !== true) {
            return $response;
        }

        // Registration still pending: login re-issued an OTP instead of a token.
        // Forward that branch so clients can prompt for verification.
        if (!isset($payload['token']) || !isset($payload['user'])) {
            return response()->json([
                'status' => true,
                'message' => $payload['message'] ?? 'A new OTP has been sent to your sms and email successfully',
                'data' => $payload['data'] ?? [],
            ]);
        }

        return $this->sessionResponse($payload, 'Logged in successfully');
    }

    public function createChannel(Request $request): JsonResponse
    {
        $request->merge(['channel' => 'mobile']);
        $response = app(ApiAuthController::class)->createChannel($request);
        $payload = $response->getData(true);

        if (($payload['status'] ?? false) !== true) {
            return $response;
        }

        return $this->sessionResponse($payload, $payload['message'] ?? 'Mobile account created successfully');
    }

    private function sessionResponse(array $payload, string $message): JsonResponse
    {
        $user = $payload['user'] ?? [];
        [$firstname, $lastname] = $this->splitFullname($user['fullname'] ?? '');

        $data = [
            'access_token' => $payload['token'] ?? '',
            'subscriberid' => $user['subscriberid'] ?? null,
            'firstname' => $user['firstname'] ?? $firstname,
            'lastname' => $user['lastname'] ?? $lastname,
            'username' => $user['username'] ?? null,
            'emailaddress' => $user['emailaddress'] ?? null,
            'phonenumber' => $user['phonenumber'] ?? null,
            'country' => $user['country'] ?? null,
            'country_id' => $user['country_id'] ?? null,
            'domain' => $user['domain'] ?? null,
            'channel' => $user['channel'] ?? null,
            'has_mobile_account' => $user['has_mobile_account'] ?? false,
            'has_booth_account' => $user['has_booth_account'] ?? false,
            'authusername' => $user['authusername'] ?? null,
            'authpassword' => $user['authpassword'] ?? null,
        ];

        return response()->json([
            'status' => true,
            'message' => $message,
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