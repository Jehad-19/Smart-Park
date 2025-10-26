<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\api\AuthController;

// --- Auth Routes ---
Route::prefix('auth')->group(function () {
    // User Authentication
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/verify-otp', [AuthController::class, 'verifyAccountOtp']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/resend-otp', [AuthController::class, 'resendOtp']);

    // Password Reset
    Route::post('/password/forget', [AuthController::class, 'forgetPasswordRequest']);
    Route::post('/password/verify-otp', [AuthController::class, 'verifyPasswordOtp']);
    Route::post('/password/reset', [AuthController::class, 'resetPassword']);

    // Authenticated Routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        // Route::get('/user', function(Request $request) { return $request->user(); });
    });
});

// Admin Authentication (يمكن فصلها في ملف admin.php)
Route::prefix('admin/auth')->group(function () {
    Route::post('/login', [AuthController::class, 'adminLogin']);
    // ... admin routes
});
