<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminNotificationController;
use App\Http\Controllers\Admin\CategorieController;
use App\Http\Controllers\Annonce\CategoriePublicController;
use App\Http\Controllers\Admin\AdminActivityController;
use App\Http\Controllers\Admin\AdminRapportController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Agent\AgentNotificationController;
use App\Http\Controllers\Agent\AgentVisiteController;
use App\Http\Controllers\Agent\AgentRapportController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ClientAuthController;
use App\Http\Controllers\Auth\DeviceTokenController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Admin\AdminStatsController;
use App\Http\Controllers\Admin\AgentController;
use App\Http\Controllers\Admin\BienAdminController;
use App\Http\Controllers\Agent\AgentBienController;
use App\Http\Controllers\Bien\BienController;
use App\Http\Controllers\Bien\BienPublicController;
use App\Http\Controllers\Client\ClientNotificationController;
use App\Http\Controllers\Client\ClientProfileController;
use App\Http\Controllers\Client\LocationController;
use App\Http\Controllers\Client\ProprietaireBienController;
use App\Http\Controllers\SemoaWebhookController;

// ─────────────────────────────────────────────────────────────────────────────
// Health check (public)
// ─────────────────────────────────────────────────────────────────────────────

Route::get('/health', fn () => response()->json([
    'status'  => 'ok',
    'message' => 'ImmoPro API is running',
]));

Route::get('/semoa/health', function (\App\Services\Payment\SemoaService $semoa) {
    return response()->json($semoa->testConnexion());
});

// ─────────────────────────────────────────────────────────────────────────────
// Webhook Semoa CashPay (public — appelé par les serveurs Semoa)
// ─────────────────────────────────────────────────────────────────────────────

Route::post('/webhooks/semoa', [SemoaWebhookController::class, 'handle']);

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
// Catégories — lecture publique (schéma de formulaire)
// ─────────────────────────────────────────────────────────────────────────────

Route::prefix('categories')->group(function () {
    Route::get('/',            [CategoriePublicController::class, 'index']);
    Route::get('/{slug}/schema', [CategoriePublicController::class, 'schema']);
});

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

    // ── Device token (push notifications) — tous rôles ────────────────────────
    Route::post  ('/device-token', [DeviceTokenController::class, 'update']);
    Route::delete('/device-token', [DeviceTokenController::class, 'destroy']);

    // ── Profil & déconnexion — Client ─────────────────────────────────────────
    Route::middleware('role:client')->group(function () {
        Route::get ('/client/me',           [ClientAuthController::class,    'me']);
        Route::post('/client/logout',        [ClientAuthController::class,    'logout']);
        // Profil client
        Route::put ('/client/profile',       [ClientProfileController::class, 'update']);
        Route::put ('/client/password',      [ClientProfileController::class, 'changePassword']);
        Route::post('/client/profile/photo', [ClientProfileController::class, 'updatePhoto']);
        // Notifications
        Route::get ('/client/notifications',            [ClientNotificationController::class, 'index']);
        Route::patch('/client/notifications/{id}/read', [ClientNotificationController::class, 'markAsRead']);
        Route::post ('/client/notifications/read-all',  [ClientNotificationController::class, 'markAllAsRead']);
    });


    Route::middleware('role:client')->group(function () {
        Route::post('/biens', [BienController::class, 'store']);

        Route::prefix('mes-biens')->group(function () {
            Route::get   ('/',       [BienController::class, 'index']);
            Route::get   ('/{id}',   [BienController::class, 'show']);
            Route::put   ('/{bien}', [BienController::class, 'update']);
            Route::post  ('/{id}/media', [BienController::class, 'updateMedia']);
            Route::delete('/{id}',   [BienController::class, 'destroy']);
        });

        // ── Propriétaire : suivi de ses annonces (tous statuts) ───────────────
        // Compatible avec le mobile GET /api/proprietaire/biens
        Route::prefix('proprietaire/biens')->group(function () {
            Route::get('/stats', [ProprietaireBienController::class, 'stats']);
            Route::get('/',      [ProprietaireBienController::class, 'index']);
            Route::get('/{id}',  [ProprietaireBienController::class, 'show']);
            Route::post('/{id}/publier', [ProprietaireBienController::class, 'publier']);
        });

        // ── Module Location (tunnel de location) ──────────────────────────────
        Route::prefix('mobile/locations')->group(function () {
            Route::post('/',                               [LocationController::class, 'initier']);
            Route::post('/initier',                        [LocationController::class, 'initier']);
            Route::post('/{id}/accepter-contrat',          [LocationController::class, 'accepterContrat']);
            Route::post('/{id}/refuser-contrat',           [LocationController::class, 'refuserContrat']);
            Route::post('/{id}/payer',                     [LocationController::class, 'payer']);
            Route::post('/{id}/confirmer-paiement',        [LocationController::class, 'confirmerPaiement']);
            Route::get ('/{id}/contrat/telecharger',       [LocationController::class, 'telechargerContrat']);
            Route::get ('/{id}/recu/telecharger',          [LocationController::class, 'telechargerRecu']);
        });

        // ── Favoris ───────────────────────────────────────────────────────────
        Route::prefix('favoris')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\FavoriController::class, 'index']);
            Route::post('/{bien}/toggle', [\App\Http\Controllers\Api\FavoriController::class, 'toggle']);
        });

        // ── Historique paiements & Statistiques client ─────────────────────
        Route::get('/mobile/historique-paiements', [LocationController::class, 'historiquePaiements']);
        Route::get('/mobile/statistiques', [LocationController::class, 'statistiques']);
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

    // ── Gestion des catégories (admin) ────────────────────────────────────────
    Route::middleware('role:admin')->prefix('admin/categories')->group(function () {
        Route::get   ('/',                            [CategorieController::class, 'index']);
        Route::post  ('/',                            [CategorieController::class, 'store']);
        Route::get   ('/{id}',                        [CategorieController::class, 'show']);
        Route::put   ('/{id}',                        [CategorieController::class, 'update']);
        Route::post  ('/{id}/attributs',              [CategorieController::class, 'addAttribut']);
        Route::put   ('/{id}/attributs/{aid}',        [CategorieController::class, 'updateAttribut']);
        Route::patch ('/{id}/attributs/{aid}/toggle', [CategorieController::class, 'toggleAttribut']);
        Route::delete('/{id}/attributs/{aid}',        [CategorieController::class, 'deleteAttribut']);
    });

    // ── Commissions & Reversements (admin) ────────────────────────────────────
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get  ('/commissions/stats',             [\App\Http\Controllers\Admin\CommissionController::class, 'stats']);
        Route::get  ('/commissions',                   [\App\Http\Controllers\Admin\CommissionController::class, 'index']);
        Route::get  ('/reversements',                  [\App\Http\Controllers\Admin\CommissionController::class, 'reversements']);
        Route::patch('/reversements/{id}/traiter',     [\App\Http\Controllers\Admin\CommissionController::class, 'traiterReversement']);
    });

    // ── Gestion des modèles de contrat (admin) ───────────────────────────────
    Route::middleware('role:admin')->prefix('admin/contrat-templates')->group(function () {
        Route::get   ('/',                   [\App\Http\Controllers\Admin\ContratTemplateController::class, 'index']);
        Route::post  ('/',                   [\App\Http\Controllers\Admin\ContratTemplateController::class, 'store']);
        Route::get   ('/placeholders',       [\App\Http\Controllers\Admin\ContratTemplateController::class, 'placeholders']);
        Route::post  ('/preview',            [\App\Http\Controllers\Admin\ContratTemplateController::class, 'preview']);
        Route::get   ('/{id}',               [\App\Http\Controllers\Admin\ContratTemplateController::class, 'show']);
        Route::put   ('/{id}',               [\App\Http\Controllers\Admin\ContratTemplateController::class, 'update']);
        Route::delete('/{id}',               [\App\Http\Controllers\Admin\ContratTemplateController::class, 'destroy']);
        Route::patch ('/{id}/defaut',        [\App\Http\Controllers\Admin\ContratTemplateController::class, 'setDefault']);
        Route::patch ('/{id}/toggle-status', [\App\Http\Controllers\Admin\ContratTemplateController::class, 'toggleStatus']);
    });

    // Rétro-compatibilité route au singulier
    Route::middleware('role:admin')->prefix('admin/contrat-template')->group(function () {
        Route::get ('/',        [\App\Http\Controllers\Admin\ContratTemplateController::class, 'show']);
        Route::put ('/',        [\App\Http\Controllers\Admin\ContratTemplateController::class, 'update']);
        Route::post('/preview', [\App\Http\Controllers\Admin\ContratTemplateController::class, 'preview']);
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

    // ── Journal d'activités (admin) ────────────────────────────────────────────────────
    Route::middleware('role:admin')->prefix('admin/activities')->group(function () {
        Route::get('/',           [AdminActivityController::class, 'index']);
        Route::get('/user/{id}',  [AdminActivityController::class, 'byUser']);
    });

    // ── Gestion des utilisateurs clients (admin) ───────────────────────────────────────
    Route::middleware('role:admin')->prefix('admin/users')->group(function () {
        Route::get  ('/stats',          [AdminUserController::class, 'stats']);
        Route::get  ('/',               [AdminUserController::class, 'index']);
        Route::get  ('/{id}',           [AdminUserController::class, 'show']);
        Route::patch('/{id}/status',    [AdminUserController::class, 'updateStatus']);
        Route::get  ('/{id}/historique',[AdminUserController::class, 'historique']);
    });

    Route::middleware('role:agent')->prefix('agent/visites')->group(function () {
        Route::patch('/{id}', [AgentVisiteController::class, 'update']);
    });

    // ── Notifications agent ────────────────────────────────────────────────────
    Route::middleware('role:agent')->prefix('agent/notifications')->group(function () {
        Route::get  ('/',            [AgentNotificationController::class, 'index']);
        Route::patch('/{id}/read',   [AgentNotificationController::class, 'markAsRead']);
        Route::post ('/read-all',    [AgentNotificationController::class, 'markAllAsRead']);
    });

    // ── Notifications admin ────────────────────────────────────────────────────
    Route::middleware('role:admin')->prefix('admin/notifications')->group(function () {
        Route::get  ('/',            [AdminNotificationController::class, 'index']);
        Route::patch('/{id}/read',   [AdminNotificationController::class, 'markAsRead']);
        Route::post ('/read-all',    [AdminNotificationController::class, 'markAllAsRead']);
    });
});
