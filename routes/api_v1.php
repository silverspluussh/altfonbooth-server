<?php

use App\Http\Controllers\ApiV1\AuthController;
use App\Http\Controllers\ApiV1\SubscriberController;
use Illuminate\Support\Facades\Route;

// Legacy CodeIgniter API surface, served by the Laravel engine.
// Same Tymon auth guard as /api/*; response envelope + field names follow
// the legacy `api/v1` contract.
Route::prefix('api/v1')->middleware(['v1.normalize', 'v1.envelope'])->group(function () {

    Route::middleware('throttle:5,1')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('verify-otp', [AuthController::class, 'verifyOtp']);
        Route::get('regenerate-otp/{email}', [AuthController::class, 'regenerateOtp']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);
    });

    Route::middleware(['auth:api', 'throttle:60,1'])->group(function () {
        Route::get('subscriber', [SubscriberController::class, 'subscriber']);
        Route::get('user/subscribers', [SubscriberController::class, 'subscribers']);
        Route::get('all-contacts', [SubscriberController::class, 'allContacts']);
        Route::get('subscribers', [SubscriberController::class, 'subscriberList']);
        Route::get('voice-relays', [SubscriberController::class, 'voiceRelays']);
    });

    Route::middleware(['auth:api', 'throttle:20,1'])->group(function () {
        Route::post('check-balance', [SubscriberController::class, 'checkBalance']);
        Route::post('verifypayment', [SubscriberController::class, 'verifyPayment']);
        Route::post('payment-link', [SubscriberController::class, 'paymentLink']);
    });

    // Legacy parity: unknown api/v1 endpoints return the CI-style 404 envelope.
    Route::any('{path}', function () {
        return response()->json([
            'status' => false,
            'error' => [
                'code' => 404,
                'message' => 'End Point Not Found',
            ],
            'reason' => 'Invalid URI',
        ], 404);
    })->where('path', '.*');
});