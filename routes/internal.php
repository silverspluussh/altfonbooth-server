<?php

use App\Http\Controllers\Internal\UssdController;
use App\Http\Controllers\Internal\UssdRechargeController;
use Illuminate\Support\Facades\Route;

Route::post('/lookup', [UssdController::class, 'lookup']);
Route::post('/accounts', [UssdController::class, 'accounts']);
Route::post('/balance', [UssdController::class, 'balance']);
Route::post('/recharge/initiate', [UssdRechargeController::class, 'initiate']);
Route::get('/recharge/status/{reference}', [UssdRechargeController::class, 'status']);
Route::post('/recharge/confirm', [UssdRechargeController::class, 'confirm']);
