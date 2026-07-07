<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ClientAuthController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Admin\AgentController;
use App\Http\Controllers\Admin\BienAdminController;
use App\Http\Controllers\Agent\AgentBienController;
use App\Http\Controllers\Bien\BienController;
use App\Http\Controllers\Bien\BienPublicController;

// ─────────────────────────────────────────────────────────────────────────────
// Health check (public)
// ─────────────────────────────────────────────────────────────────────────────

Route::get('/health', fn () => response()->json([
    'status'  => 'ok',
    'message' => 'ImmoPro API is running',
]));

// ─────────────────────────────────────────────────────────────────────────────
// Auth public — Client (inscription + OTP)
// ─────────────────────────────────────────────────────────────────────────────

Route::post('/register',     [RegisterController::class,   'register']);
Route::post('/client/login', [ClientAuthController::class, 'login']);
Route::post('/verify-otp',   [ClientAuthController::class, 'verifyOtp']);
Route::post('/resend-otp',   [ClientAuthController::class, 'resendOtp']);

// ─────────────────────────────────────────────────────────────────────────────
// Auth public — Admin + Agent
// ─────────────────────────────────────────────────────────────────────────────

Route::post('/login', [AuthController::class, 'login']);

// ─────────────────────────────────────────────────────────────────────────────
// Biens — lecture publique (biens publiés uniquement, sans authentification)
// ─────────────────────────────────────────────────────────────────────────────

Route::prefix('biens')->group(function () {
    Route::get('/',     [BienPublicController::class, 'index']);
    Route::get('/{id}', [BienPublicController::class, 'show']);
});

// ─────────────────────────────────────────────────────────────────────────────
// Routes protégées — token Sanctum requis pour tous les groupes ci-dessous
// ─────────────────────────────────────────────────────────────────────────────

Route::middleware('auth:sanctum')->group(function () {

    // ── Profil & déconnexion — Admin + Agent ──────────────────────────────────
    Route::middleware('role:admin,agent')->group(function () {
        Route::get ('/me',     [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });

    // ── Profil & déconnexion — Client ─────────────────────────────────────────
    Route::middleware('role:client')->group(function () {
        Route::get ('/client/me',     [ClientAuthController::class, 'me']);
        Route::post('/client/logout', [ClientAuthController::class, 'logout']);
    });


    Route::middleware('role:client')->group(function () {
        Route::post('/biens', [BienController::class, 'store']);

        Route::prefix('mes-biens')->group(function () {
            Route::get   ('/',       [BienController::class, 'index']);
            Route::get   ('/{id}',   [BienController::class, 'show']);
            Route::put   ('/{bien}', [BienController::class, 'update']);
            Route::delete('/{id}',   [BienController::class, 'destroy']);
        });
    });

    

    Route::middleware('role:admin')->prefix('admin/agents')->group(function () {
        Route::get   ('/',            [AgentController::class, 'index']);
        Route::post  ('/',            [AgentController::class, 'store']);
        Route::get   ('/{id}',        [AgentController::class, 'show']);
        Route::put   ('/{id}',        [AgentController::class, 'update']);
        Route::patch ('/{id}/status', [AgentController::class, 'updateStatus']);
        Route::delete('/{id}',        [AgentController::class, 'destroy']);
    });

   

    Route::middleware('role:admin')->prefix('admin/biens')->group(function () {
        Route::get  ('/',              [BienAdminController::class, 'index']);
        Route::get  ('/{id}',          [BienAdminController::class, 'show']);
        Route::patch('/{id}/statut',   [BienAdminController::class, 'updateStatut']);
        Route::patch('/{id}/assigner', [BienAdminController::class, 'assigner']);
    });

   
    Route::middleware('role:agent')->prefix('agent/biens')->group(function () {
        Route::get  ('/counts',        [AgentBienController::class, 'counts']);
        Route::get  ('/',              [AgentBienController::class, 'index']);
        Route::get  ('/{id}',          [AgentBienController::class, 'show']);
        Route::post ('/{id}/claim',    [AgentBienController::class, 'claim']);
        Route::post ('/{id}/release',  [AgentBienController::class, 'release']);
        Route::patch('/{id}/statut',   [AgentBienController::class, 'updateStatut']);
    });
});
