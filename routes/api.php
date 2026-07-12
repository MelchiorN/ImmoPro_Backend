<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminRapportController;
use App\Http\Controllers\Agent\AgentVisiteController;
use App\Http\Controllers\Agent\AgentRapportController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ClientAuthController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Admin\AdminStatsController;
use App\Http\Controllers\Admin\AgentController;
use App\Http\Controllers\Admin\BienAdminController;
use App\Http\Controllers\Agent\AgentBienController;
use App\Http\Controllers\Bien\BienController;
use App\Http\Controllers\Bien\BienPublicController;
use App\Http\Controllers\Client\ClientProfileController;

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
        Route::get ('/client/me',           [ClientAuthController::class,    'me']);
        Route::post('/client/logout',        [ClientAuthController::class,    'logout']);
        // Profil client
        Route::put ('/client/profile',       [ClientProfileController::class, 'update']);
        Route::put ('/client/password',      [ClientProfileController::class, 'changePassword']);
        Route::post('/client/profile/photo', [ClientProfileController::class, 'updatePhoto']);
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
        Route::get  ('/counts',           [AgentBienController::class,   'counts']);
        Route::get  ('/',                 [AgentBienController::class,   'index']);
        Route::get  ('/{id}',             [AgentBienController::class,   'show']);
        Route::post ('/{id}/claim',       [AgentBienController::class,   'claim']);
        Route::post ('/{id}/release',     [AgentBienController::class,   'release']);
        // Visites par bien
        Route::get  ('/{id}/visites',     [AgentVisiteController::class, 'index']);
        Route::post ('/{id}/visites',     [AgentVisiteController::class, 'store']);
        Route::patch('/{id}/statut',      [AgentBienController::class,   'updateStatut']);
    });

    // ── Stats dashboard agent ──────────────────────────────────────────────────────────────
    Route::middleware('role:agent')->get('/agent/stats', [AgentBienController::class, 'stats']);


    // ── Rapports agent ───────────────────────────────────────────────────────────────────────
    Route::middleware('role:agent')->prefix('agent/rapports')->group(function () {
        Route::get ('/',           [AgentRapportController::class, 'index']);
        Route::post('/',           [AgentRapportController::class, 'store']);
        Route::get ('/{id}',       [AgentRapportController::class, 'show']);
        Route::put ('/{id}',       [AgentRapportController::class, 'update']);
        Route::post('/{id}/soumettre', [AgentRapportController::class, 'soumettre']);
    });

    // ── Rapport par bien (agent) ──────────────────────────────────────────────
    Route::middleware('role:agent')->get(
        '/agent/biens/{bienId}/rapport',
        [AgentRapportController::class, 'byBien']
    );

    // ── Rapports admin ───────────────────────────────────────────────────────
    Route::middleware('role:admin')->prefix('admin/rapports')->group(function () {
        Route::get  ('/',                 [AdminRapportController::class, 'index']);
        Route::get  ('/counts',           [AdminRapportController::class, 'counts']);
        Route::get  ('/{id}',             [AdminRapportController::class, 'show']);
        Route::post ('/{id}/decision',    [AdminRapportController::class, 'decision']);
    });

    // ── Calendrier : toutes les visites de l'agent ───────────────────────────────────────
    Route::middleware('role:agent')->group(function () {
        Route::get('/agent/visites', [AgentVisiteController::class, 'allVisites']);
    });

    // ── Téléchargement sécurisé de documents privés (agent ou admin) ────────────────
    Route::middleware('role:agent,admin')->prefix('agent')->group(function () {
        Route::get('/documents/{docId}', [AgentBienController::class, 'downloadDocument']);
    });

    // ── Stats admin dynamiques ─────────────────────────────────────────────────────────
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/stats', [AdminStatsController::class, 'index']);
    });

    Route::middleware('role:agent')->prefix('agent/visites')->group(function () {
        Route::patch('/{id}', [AgentVisiteController::class, 'update']);
    });
});
