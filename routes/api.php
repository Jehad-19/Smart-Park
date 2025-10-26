<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\api\AuthController;
use App\Http\Controllers\api\UserController;

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
});

// Authenticated Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // مسارات المستخدم المحمية
    Route::prefix('user')->group(function () {
        Route::get('/profile', [UserController::class, 'profile']);
        Route::put('/profile', [UserController::class, 'updateProfile']);
        Route::post('/change-password', [UserController::class, 'changePassword']);
        Route::post('/update-email/request', [UserController::class, 'requestEmailUpdate']);
        Route::post('/update-email/verify', [UserController::class, 'verifyEmailUpdate']);
        Route::delete('/account', [UserController::class, 'deleteAccount']);
    });
});


// Admin Authentication (يمكن فصلها في ملف admin.php)
Route::prefix('admin/auth')->group(function () {
    Route::post('/login', [AuthController::class, 'adminLogin']);
});
