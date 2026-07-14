<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\SubscribersController;
use App\Http\Controllers\AdminController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


// Public Auth Routes — Strict rate limit: 5 attempts per minute
Route::middleware('throttle:5,1')->group(function () {
    Route::post('/signup', [AuthController::class, 'signup'])->name('register');
    Route::post('/verify', [AuthController::class, 'verify']);
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/password/request', [AuthController::class, 'requestPasswordReset']);
    Route::post('/password/reset', [AuthController::class, 'resetPassword']);
});

// Protected Routes — Standard rate limit: 60 requests per minute
Route::middleware(['auth:api', 'throttle:60,1'])->group(function () {
    // Current Authenticated User (Subscriber)
    Route::get('/user', function (Request $request) {
        return new \App\Http\Resources\SubscriberResource($request->user());
    });

    // Subscriber specific actions
    Route::get('/my-destinations', [SubscribersController::class, 'myDestinations']);

    // Management Routes (CAUTION: These are currently accessible to any authenticated subscriber)
    // TODO: Implement Admin middleware/role check
    Route::get('/subscribers', [SubscribersController::class, 'index']);
    Route::get('/subscribers/{id}', [SubscribersController::class, 'show']);

    // Auth User Management
    Route::post('/auth-username', [SubscribersController::class, 'updateAuthUsername']);
    Route::get('/auth-users', [SubscribersController::class, 'listAuthUsers']);
    Route::post('/auth-users', [SubscribersController::class, 'addAuthUser']);
    Route::delete('/auth-users', [SubscribersController::class, 'deleteAuthUser']);

    // Destination Management
    Route::post('/destinations', [SubscribersController::class, 'addDest']);
    Route::delete('/destinations', [SubscribersController::class, 'deleteDest']);

    // Credits & Payments (auth required)
    Route::middleware('throttle:20,1')->group(function () {
        Route::post('/purchase-credits', [SubscribersController::class, 'purchaseCredits']);
        Route::post('/payments/initialize', [SubscribersController::class, 'initializePayment']);
    });
    Route::get('/purchase-history', [SubscribersController::class, 'getPurchaseHistory']);

    // Settings & Updates
    Route::post('/update-profile', [SubscribersController::class, 'updateProfile']);
    Route::post('/update-auth-account', [SubscribersController::class, 'updateAuthAccount']);

    // Balance
    Route::post('/get-balance', [SubscribersController::class, 'getBalance']);
});

// Payment verification & config — no auth required (Paystack API is the source of truth)
Route::post('/payments/verify', [SubscribersController::class, 'verifyPayment'])->middleware('throttle:20,1');
Route::get('/payments/config', [SubscribersController::class, 'getPaymentConfig']);

// Admin Routes — Strict login limit: 5 attempts per minute
Route::prefix('admin')->group(function () {
    Route::middleware('throttle:5,1')->group(function () {
        Route::post('/login', [AdminController::class, 'login']);
    });

    Route::middleware(['auth:admin-api', 'admin.role:manager', 'throttle:60,1'])->group(function () {
        // Dashboard
        Route::get('/dashboard', [AdminController::class, 'dashboard']);

        // Admin Profile
        Route::put('/profile', [AdminController::class, 'updateProfile']);

        // Subscriber Management
        Route::get('/subscribers', [AdminController::class, 'listSubscribers']);
        Route::get('/subscribers/{id}', [AdminController::class, 'showSubscriber']);
        Route::post('/subscribers', [AdminController::class, 'storeSubscriber']);
        Route::put('/subscribers/{id}', [AdminController::class, 'updateSubscriber']);
        Route::delete('/subscribers/{id}', [AdminController::class, 'deleteSubscriber']);

        // SIP Account Management
        Route::get('/auth-users', [AdminController::class, 'listAuthUsers']);
        Route::get('/auth-users/{id}', [AdminController::class, 'showAuthUser']);
        Route::post('/auth-users', [AdminController::class, 'storeAuthUser']);
        Route::put('/auth-users/{id}', [AdminController::class, 'updateAuthUser']);
        Route::delete('/auth-users/{id}', [AdminController::class, 'deleteAuthUser']);

        // Destination Management
        Route::get('/destinations', [AdminController::class, 'listDestinations']);
        Route::get('/destinations/{id}', [AdminController::class, 'showDestination']);
        Route::post('/destinations', [AdminController::class, 'storeDestination']);
        Route::put('/destinations/{id}', [AdminController::class, 'updateDestination']);
        Route::delete('/destinations/{id}', [AdminController::class, 'deleteDestination']);

        // Credits / Purchase History
        Route::get('/purchase-history', [AdminController::class, 'listPurchaseHistory']);
        Route::post('/credits', [AdminController::class, 'addCredits']);

        // Super Admin only routes
        Route::middleware('admin.role:super_admin')->group(function () {
            Route::get('/admins', [AdminController::class, 'index']);
            Route::post('/admins', [AdminController::class, 'store']);
            Route::delete('/admins/{id}', [AdminController::class, 'destroy']);
        });
    });
});
