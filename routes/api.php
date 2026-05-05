<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\SubscribersController;
use App\Http\Controllers\AdminController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::post('/signup', [AuthController::class, 'signup'])->name('register');
Route::post('/verify', [AuthController::class, 'verify']);
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/password/request', [AuthController::class, 'requestPasswordReset']);
Route::post('/password/reset', [AuthController::class, 'resetPassword']);

// Protected Routes
Route::middleware('auth:sanctum')->group(function () {
    // Current Authenticated User (Subscriber)
    Route::get('/user', function (Request $request) {
        return $request->user();
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

    // Destination Management
    Route::post('/destinations', [SubscribersController::class, 'addDest']);
    Route::delete('/destinations', [SubscribersController::class, 'deleteDest']);
});

// Admin Routes
Route::prefix('admin')->group(function () {
    Route::post('/login', [AdminController::class, 'login']);

    Route::middleware(['auth:sanctum', 'admin.role:manager'])->group(function () {
        Route::get('/subscribers', [AdminController::class, 'listSubscribers']);
        Route::get('/auth-users', [AdminController::class, 'listAuthUsers']);

        // Super Admin only routes
        Route::middleware('admin.role:super_admin')->group(function () {
            Route::get('/admins', [AdminController::class, 'index']);
            Route::post('/admins', [AdminController::class, 'store']);
            Route::delete('/admins/{id}', [AdminController::class, 'destroy']);
        });
    });
});
