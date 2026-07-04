<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ClientAuthController;
use App\Http\Controllers\Auth\RegisterController;

// ─────────────────────────────────────────────────────────────────────────────
// Health check
// ─────────────────────────────────────────────────────────────────────────────

Route::get('/health', fn () => response()->json([
    'status'  => 'ok',
    'message' => 'ImmoPro API is running',
]));

// ─────────────────────────────────────────────────────────────────────────────
// Auth client (publiques)
// ─────────────────────────────────────────────────────────────────────────────

// Inscription d'un nouveau client + envoi OTP
Route::post('/register', [RegisterController::class, 'register']);

// Connexion client
Route::post('/client/login', [ClientAuthController::class, 'login']);

// Vérification de l'OTP (après inscription ou renvoi)
Route::post('/verify-otp', [ClientAuthController::class, 'verifyOtp']);

// Renvoi d'un OTP
Route::post('/resend-otp', [ClientAuthController::class, 'resendOtp']);

// ─────────────────────────────────────────────────────────────────────────────
// Auth admin (publiques)
// ─────────────────────────────────────────────────────────────────────────────

Route::post('/login', [AuthController::class, 'login']);

// ─────────────────────────────────────────────────────────────────────────────
// Routes protégées — Client (token Sanctum requis)
// ─────────────────────────────────────────────────────────────────────────────

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/client/me',       [ClientAuthController::class, 'me']);
    Route::post('/client/logout',  [ClientAuthController::class, 'logout']);
});

// ─────────────────────────────────────────────────────────────────────────────
// Routes protégées — Admin
// ─────────────────────────────────────────────────────────────────────────────

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/admin/me',       [AuthController::class, 'me']);
    Route::post('/admin/logout',  [AuthController::class, 'logout']);
});
