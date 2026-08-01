<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminNotificationController;
use App\Http\Controllers\Admin\CategorieController;
use App\Http\Controllers\Admin\ConfigPublicationController;
use App\Http\Controllers\Admin\ConfigPublicationFormController;
use App\Http\Controllers\Admin\PlanAbonnementController;
use App\Http\Controllers\Annonce\CategoriePublicController;
use App\Http\Controllers\Annonce\ConfigFormPublicController;
use App\Http\Controllers\Admin\AdminActivityController;
use App\Http\Controllers\Admin\AdminRapportController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Agent\AgentStatsController;
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
use App\Http\Controllers\Client\AbonnementController;
use App\Http\Controllers\Client\ClientNotificationController;
use App\Http\Controllers\Client\ClientVisiteController;
use App\Http\Controllers\Client\ClientProfileController;
use App\Http\Controllers\Client\LocationController;
use App\Http\Controllers\Client\ProprietaireBienController;
use App\Http\Controllers\Client\BrouillonBienController;
use App\Http\Controllers\Admin\DocumentLegalController;
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
// Documents légaux — lecture publique (mobile + web sans auth)
// ─────────────────────────────────────────────────────────────────────────────

Route::prefix('legal')->group(function () {
    Route::get('/',       [DocumentLegalController::class, 'indexPublic']);
    Route::get('/{slug}', [DocumentLegalController::class, 'showPublic']);
});

// ─────────────────────────────────────────────────────────────────────────────
// Config formulaire publication — lecture publique (mobile + web sans auth)
// ─────────────────────────────────────────────────────────────────────────────

Route::prefix('config')->group(function () {
    Route::get('/formulaire',            [ConfigFormPublicController::class, 'full']);
    Route::get('/transactions',          [ConfigFormPublicController::class, 'transactions']);
    Route::get('/unites-prix',           [ConfigFormPublicController::class, 'unitesPrix']);
    Route::get('/types-document',        [ConfigFormPublicController::class, 'typesDocument']);
    Route::get('/roles-deposant',        [ConfigFormPublicController::class, 'roles']);
    Route::get('/roles-deposant/{slug}', [ConfigFormPublicController::class, 'roleBySlug']);
});

// ─────────────────────────────────────────────────────────────────────────────
// Catégories — lecture publique (schéma de formulaire)
// ─────────────────────────────────────────────────────────────────────────────

Route::prefix('categories')->group(function () {
    Route::get('/',                        [CategoriePublicController::class, 'index']);
    Route::get('/{slug}/schema',           [CategoriePublicController::class, 'schema']);
    Route::get('/{slug}/types-logement',   [CategoriePublicController::class, 'typesLogement']);
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
        Route::get ('/me',                  [AuthController::class, 'me']);
        Route::post('/logout',             [AuthController::class, 'logout']);
        Route::put ('/profile',            [AuthController::class, 'updateProfile']);
        Route::post('/profile/photo',      [AuthController::class, 'updateProfilePhoto']);
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

        // ── Brouillons de publication (bien en brouillon) ─────────────────────
        Route::prefix('client/biens')->group(function () {
            Route::post  ('/',               [BrouillonBienController::class, 'store']);
            Route::put   ('/{id}/brouillon', [BrouillonBienController::class, 'update']);
            Route::delete('/{id}/brouillon', [BrouillonBienController::class, 'destroy']);
            Route::delete('/{id}',           [BrouillonBienController::class, 'destroy']);
            Route::post  ('/{id}/soumettre', [BrouillonBienController::class, 'soumettre']);
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

        // ── Abonnements ───────────────────────────────────────────────────────
        Route::prefix('client/abonnements')->group(function () {
            Route::get ('/plans',      [AbonnementController::class, 'plans']);
            Route::get ('/quota',      [AbonnementController::class, 'quota']);
            Route::get ('/historique', [AbonnementController::class, 'historique']);
            Route::post('/acheter',    [AbonnementController::class, 'acheter']);
            Route::post('/confirmer',  [AbonnementController::class, 'confirmer']);
        });

        // ── Frais d'étude (paiement avant soumission d'un bien) ───────────────
        Route::prefix('client/frais-etude')->group(function () {
            Route::get ('/quota',      [\App\Http\Controllers\Client\FraisEtudeController::class, 'quotaEtFrais']);
            Route::get ('/historique', [\App\Http\Controllers\Client\FraisEtudeController::class, 'historique']);
            Route::post('/initier',    [\App\Http\Controllers\Client\FraisEtudeController::class, 'initier']);
            Route::post('/confirmer',  [\App\Http\Controllers\Client\FraisEtudeController::class, 'confirmer']);
        });

        // ── Visites client (paiement pour débloquer la localisation + planifier) ─
        Route::prefix('client/visites')->group(function () {
            Route::get ('/',                                    [ClientVisiteController::class, 'mesVisites']);
            Route::post('/biens/{bienId}/initier',              [ClientVisiteController::class, 'initierPaiement']);
            Route::post('/biens/{bienId}/confirmer',            [ClientVisiteController::class, 'confirmerPaiement']);
            // Planification client acheteur → choisit un créneau proposé par l'agent
            Route::post('/{visiteId}/choisir-creneau',          [ClientVisiteController::class, 'choisirCreneauVisite']);
            // Client signale son indisponibilité → l'agent devra re-proposer
            Route::post('/{visiteId}/indisponible',             [ClientVisiteController::class, 'signalerIndisponibilite']);
            // Planification proprio vérification → choisit un créneau proposé par l'agent
            Route::post('/{visiteId}/choisir-creneau-verification',   [ClientVisiteController::class, 'choisirCreneauVerification']);
            // Proprio signale son indisponibilité pour la vérification → l'agent devra re-proposer
            Route::post('/{visiteId}/indisponible-verification', [ClientVisiteController::class, 'signalerIndisponibiliteVerification']);
        });

        // ── Historique paiements client (abonnements + frais d'étude) ─────────
        Route::prefix('client/paiements')->group(function () {
            Route::get('/',               [\App\Http\Controllers\Client\ClientPaiementController::class, 'index']);
            Route::get('/{id}/recu',      [\App\Http\Controllers\Client\ClientPaiementController::class, 'recu']);
            Route::get('/{id}/recu/pdf',  [\App\Http\Controllers\Client\ClientPaiementController::class, 'recuPdf']);
        });

        // ── Historique paiements & Statistiques client ─────────────────────
        Route::get('/mobile/historique-paiements', [LocationController::class, 'historiquePaiements']);
        Route::get('/mobile/statistiques', [LocationController::class, 'statistiques']);

        // ── Statistiques utilisateur (propriétaire + client) ───────────────
        Route::prefix('statistics')->group(function () {
            Route::get('/',              [\App\Http\Controllers\Client\StatisticsController::class, 'index']);
            Route::get('/proprietaire',  [\App\Http\Controllers\Client\StatisticsController::class, 'proprietaire']);
            Route::get('/client',        [\App\Http\Controllers\Client\StatisticsController::class, 'client']);
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

    Route::middleware('role:admin')->prefix('admin/dossiers')->group(function () {
        Route::get  ('/',                            [\App\Http\Controllers\Admin\AdminDossierController::class, 'index']);
        Route::get  ('/{id}',                        [\App\Http\Controllers\Admin\AdminDossierController::class, 'show']);
        Route::post ('/{id}/assign',                 [\App\Http\Controllers\Admin\AdminDossierController::class, 'assign']);
        Route::post ('/{id}/withdraw',               [\App\Http\Controllers\Admin\AdminDossierController::class, 'withdraw']);
    });

    // ── Gestion des catégories (admin) ────────────────────────────────────────
    Route::middleware('role:admin')->prefix('admin/categories')->group(function () {
        Route::get   ('/',                                [CategorieController::class, 'index']);
        Route::post  ('/',                                [CategorieController::class, 'store']);
        Route::get   ('/{id}',                            [CategorieController::class, 'show']);
        Route::put   ('/{id}',                            [CategorieController::class, 'update']);
        Route::post  ('/{id}/attributs',                  [CategorieController::class, 'addAttribut']);
        Route::put   ('/{id}/attributs/{aid}',            [CategorieController::class, 'updateAttribut']);
        Route::patch ('/{id}/attributs/{aid}/toggle',     [CategorieController::class, 'toggleAttribut']);
        Route::delete('/{id}/attributs/{aid}',            [CategorieController::class, 'deleteAttribut']);
        // Types de logement configurables (Studio/F1, F2, F3…)
        Route::get   ('/{id}/types-logement',             [CategorieController::class, 'typesLogement']);
        Route::post  ('/{id}/types-logement',             [CategorieController::class, 'addTypeLogement']);
        Route::put   ('/{id}/types-logement/{tid}',       [CategorieController::class, 'updateTypeLogement']);
        Route::delete('/{id}/types-logement/{tid}',       [CategorieController::class, 'deleteTypeLogement']);
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
        Route::get  ('/counts',              [AgentBienController::class,   'counts']);
        Route::get  ('/',                    [AgentBienController::class,   'index']);
        Route::get  ('/{id}',                [AgentBienController::class,   'show']);
        Route::post ('/{id}/claim',          [AgentBienController::class,   'claim']);
        Route::post ('/{id}/release',        [AgentBienController::class,   'release']);
        Route::patch('/{id}/statut',         [AgentBienController::class,   'updateStatut']);
        // Édition du bien par l'agent
        Route::patch('/{id}',                [AgentBienController::class,   'updateBien']);
        Route::post ('/{id}/medias',         [AgentBienController::class,   'addMedia']);
        Route::patch('/{id}/medias/{mediaId}',    [AgentBienController::class, 'updateMedia']);
        Route::delete('/{id}/medias/{mediaId}',   [AgentBienController::class, 'deleteMedia']);
        Route::post ('/{id}/documents',      [AgentBienController::class,   'addDocument']);
        Route::delete('/{id}/documents/{docId}',  [AgentBienController::class, 'deleteDocument']);
        // Visites par bien
        Route::get  ('/{id}/visites',        [AgentVisiteController::class, 'index']);
        Route::post ('/{id}/visites',        [AgentVisiteController::class, 'store']);
        // Créneaux de visite
        Route::get  ('/{bienId}/creneaux',   [AgentVisiteController::class, 'getCreneaux']);
        Route::post ('/{bienId}/creneaux',   [AgentVisiteController::class, 'proposeCreneaux']);
        Route::delete('/{bienId}/creneaux/{creneauId}', [AgentVisiteController::class, 'deleteCreneaux']);
        // Rapport auto-save + décision agent
        Route::get  ('/{bienId}/rapport',    [AgentRapportController::class, 'byBien']);
        Route::put  ('/{bienId}/rapport/autosave', [AgentRapportController::class, 'autosave']);
        Route::post ('/{bienId}/rapport/decision', [AgentRapportController::class, 'decision']);
    });

    // ── Stats dashboard agent ──────────────────────────────────────────────────────────────
    Route::middleware('role:agent')->get('/agent/stats', [AgentBienController::class, 'stats']);
    Route::middleware('role:agent')->get('/agent/stats/charts', [AgentStatsController::class, 'charts']);


    // ── Rapports agent ───────────────────────────────────────────────────────────────────────
    Route::middleware('role:agent')->prefix('agent/rapports')->group(function () {
        Route::get ('/',     [AgentRapportController::class, 'index']);
        Route::post('/',     [AgentRapportController::class, 'store']);
        Route::get ('/{id}', [AgentRapportController::class, 'show']);
        Route::put ('/{id}', [AgentRapportController::class, 'update']);
    });

    // ── Rapport par bien (agent) ──────────────────────────────────────────────
    // (déplacé dans le groupe agent/biens ci-dessus)

    // ── Rapports admin ───────────────────────────────────────────────────────
    Route::middleware('role:admin')->prefix('admin/rapports')->group(function () {
        Route::get('/',        [AdminRapportController::class, 'index']);
        Route::get('/counts',  [AdminRapportController::class, 'counts']);
        Route::get('/{id}',    [AdminRapportController::class, 'show']);
        // decision() est désactivé côté admin — c'est l'agent qui décide
    });
    Route::middleware('role:admin')->get('/admin/biens/{id}/rapport', [AdminRapportController::class, 'showByBien']);

    // ── Calendrier : toutes les visites + créneaux libres de l'agent ────────────────────
    Route::middleware('role:agent')->prefix('agent')->group(function () {
        Route::get   ('/visites',              [AgentVisiteController::class, 'allVisites']);
        // Créneaux libres (sans bien) — DOIT être avant le groupe biens pour éviter les conflits
        Route::get   ('/creneaux/disponibles', [AgentVisiteController::class, 'creneauxDisponibles']);
        Route::post  ('/creneaux',             [AgentVisiteController::class, 'storeCreneauLibre']);
        Route::delete('/creneaux/{id}',        [AgentVisiteController::class, 'deleteCreneauLibre']);
        Route::get   ('/creneaux',             [AgentVisiteController::class, 'allCreneaux']);
    });

    // ── Téléchargement sécurisé de documents privés (agent ou admin) ────────────────
    Route::middleware('role:agent,admin')->prefix('agent')->group(function () {
        Route::get('/documents/{docId}', [AgentBienController::class, 'downloadDocument']);
    });

    // ── Stats admin dynamiques ─────────────────────────────────────────────────────────
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/stats',        [AdminStatsController::class, 'index']);
        Route::get('/stats/charts', [AdminStatsController::class, 'charts']);
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
        // Surcharge du quota gratuit d'un utilisateur spécifique
        Route::patch('/{id}/essais-gratuits', [ConfigPublicationController::class, 'setEssaisGratuits']);
    });

    // ── Plans d'abonnement (admin) ─────────────────────────────────────────────
    Route::middleware('role:admin')->prefix('admin/plans-abonnement')->group(function () {
        Route::get   ('/',          [PlanAbonnementController::class, 'index']);
        Route::post  ('/',          [PlanAbonnementController::class, 'store']);
        Route::get   ('/{id}',      [PlanAbonnementController::class, 'show']);
        Route::put   ('/{id}',      [PlanAbonnementController::class, 'update']);
        Route::patch ('/{id}/toggle',[PlanAbonnementController::class, 'toggle']);
        Route::delete('/{id}',      [PlanAbonnementController::class, 'destroy']);
    });

    // ── Frais d'étude (admin) ──────────────────────────────────────────────────
    Route::middleware('role:admin')->prefix('admin/frais-etude')->group(function () {
        Route::get('/stats', [\App\Http\Controllers\Admin\AdminFraisEtudeController::class, 'stats']);
        Route::get('/',      [\App\Http\Controllers\Admin\AdminFraisEtudeController::class, 'index']);
    });

    // ── Transactions & Paiements (admin) ───────────────────────────────────────
    Route::middleware('role:admin')->prefix('admin/transactions')->group(function () {
        Route::get('/stats',           [\App\Http\Controllers\Admin\AdminTransactionController::class, 'stats']);
        Route::get('/',                [\App\Http\Controllers\Admin\AdminTransactionController::class, 'index']);
        Route::get('/{id}/recu',       [\App\Http\Controllers\Admin\AdminTransactionController::class, 'downloadRecu']);
    });

    // ── Configuration publication (admin) ──────────────────────────────────────
    Route::middleware('role:admin')->prefix('admin/config-publication')->group(function () {
        Route::get('/',  [ConfigPublicationController::class, 'show']);
        Route::put('/',  [ConfigPublicationController::class, 'update']);
    });

    // ── Configuration formulaire de publication (admin) ────────────────────────
    Route::middleware('role:admin')->prefix('admin/config-formulaire')->group(function () {
        // Types de transaction
        Route::get   ('/transactions',           [ConfigPublicationFormController::class, 'indexTransactions']);
        Route::post  ('/transactions',           [ConfigPublicationFormController::class, 'storeTransaction']);
        Route::put   ('/transactions/{id}',      [ConfigPublicationFormController::class, 'updateTransaction']);
        Route::patch ('/transactions/{id}/toggle', [ConfigPublicationFormController::class, 'toggleTransaction']);
        Route::delete('/transactions/{id}',      [ConfigPublicationFormController::class, 'destroyTransaction']);

        // Unités de prix
        Route::get   ('/unites-prix',            [ConfigPublicationFormController::class, 'indexUnitesPrix']);
        Route::post  ('/unites-prix',            [ConfigPublicationFormController::class, 'storeUnitePrix']);
        Route::put   ('/unites-prix/{id}',       [ConfigPublicationFormController::class, 'updateUnitePrix']);
        Route::patch ('/unites-prix/{id}/toggle', [ConfigPublicationFormController::class, 'toggleUnitePrix']);
        Route::delete('/unites-prix/{id}',       [ConfigPublicationFormController::class, 'destroyUnitePrix']);

        // Types de documents
        Route::get   ('/types-document',         [ConfigPublicationFormController::class, 'indexTypesDocument']);
        Route::post  ('/types-document',         [ConfigPublicationFormController::class, 'storeTypeDocument']);
        Route::put   ('/types-document/{id}',    [ConfigPublicationFormController::class, 'updateTypeDocument']);
        Route::patch ('/types-document/{id}/toggle', [ConfigPublicationFormController::class, 'toggleTypeDocument']);
        Route::delete('/types-document/{id}',    [ConfigPublicationFormController::class, 'destroyTypeDocument']);

        // Rôles déposant
        Route::get   ('/roles',                  [ConfigPublicationFormController::class, 'indexRoles']);
        Route::post  ('/roles',                  [ConfigPublicationFormController::class, 'storeRole']);
        Route::get   ('/roles/{id}',             [ConfigPublicationFormController::class, 'showRole']);
        Route::put   ('/roles/{id}',             [ConfigPublicationFormController::class, 'updateRole']);
        Route::patch ('/roles/{id}/toggle',      [ConfigPublicationFormController::class, 'toggleRole']);
        Route::delete('/roles/{id}',             [ConfigPublicationFormController::class, 'destroyRole']);

        // Champs déposant (sous-ressource du rôle)
        Route::post  ('/roles/{id}/champs',               [ConfigPublicationFormController::class, 'storeChamp']);
        Route::put   ('/roles/{id}/champs/{cid}',         [ConfigPublicationFormController::class, 'updateChamp']);
        Route::patch ('/roles/{id}/champs/{cid}/toggle',  [ConfigPublicationFormController::class, 'toggleChamp']);
        Route::delete('/roles/{id}/champs/{cid}',         [ConfigPublicationFormController::class, 'destroyChamp']);

        // Documents par rôle
        Route::post  ('/roles/{id}/documents',            [ConfigPublicationFormController::class, 'storeDocRole']);
        Route::patch ('/roles/{id}/documents/{docId}',    [ConfigPublicationFormController::class, 'updateDocRole']);
        Route::delete('/roles/{id}/documents/{docId}',    [ConfigPublicationFormController::class, 'destroyDocRole']);
    });

    Route::middleware('role:agent')->prefix('agent/visites')->group(function () {
        Route::patch('/{id}',                          [AgentVisiteController::class, 'update']);
        // Visites clients
        Route::get  ('/clients',                       [AgentVisiteController::class, 'visitesClients']);
        Route::post ('/{id}/proposer-creneaux',        [AgentVisiteController::class, 'proposerCreneauxClient']);
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

    // ── Visites client (proprio — créneaux de vérification) ─────────────────
    // Note: GET /client/visites est géré plus haut via mesVisites() (filtre client_id)
    Route::middleware('role:client')->group(function () {
        Route::get  ('/client/biens/{bienId}/creneaux',                           [\App\Http\Controllers\Client\ClientVisiteController::class, 'getCreneaux']);
        Route::post ('/client/biens/{bienId}/creneaux/{creneauId}/choisir',       [\App\Http\Controllers\Client\ClientVisiteController::class, 'choisirCreneau']);
        Route::post ('/client/visites/{id}/annuler',                              [\App\Http\Controllers\Client\ClientVisiteController::class, 'annulerVisite']);
        // Propriétaire confirme un créneau de visite de vérification (agent → proprio)
        Route::post ('/client/visites/{visiteId}/choisir-creneau-verification',   [\App\Http\Controllers\Client\ClientVisiteController::class, 'choisirCreneauVerification']);
        // Propriétaire : liste des visites de vérification de ses biens
        Route::get  ('/client/visites/verification',                              [\App\Http\Controllers\Client\ClientVisiteController::class, 'index']);
    });

    // ── Rappels de visite (admin) ─────────────────────────────────────────────
    Route::middleware('role:admin')->prefix('admin/rappels-visite')->group(function () {
        Route::get   ('/',      [\App\Http\Controllers\Admin\RappelVisiteConfigController::class, 'index']);
        Route::post  ('/',      [\App\Http\Controllers\Admin\RappelVisiteConfigController::class, 'store']);
        Route::patch ('/{id}',  [\App\Http\Controllers\Admin\RappelVisiteConfigController::class, 'update']);
        Route::delete('/{id}',  [\App\Http\Controllers\Admin\RappelVisiteConfigController::class, 'destroy']);
    });

    // ── Documents légaux (admin) ──────────────────────────────────────────────
    Route::middleware('role:admin')->prefix('admin/legal')->group(function () {
        Route::get  ('/',           [DocumentLegalController::class, 'index']);
        Route::get  ('/{slug}',     [DocumentLegalController::class, 'show']);
        Route::put  ('/{slug}',     [DocumentLegalController::class, 'update']);
        Route::patch('/{slug}/toggle', [DocumentLegalController::class, 'toggle']);
    });
    Route::middleware('role:admin')->get('/admin/biens/{id}/workflow', [BienAdminController::class, 'workflow']);
    Route::middleware('role:agent')->get('/agent/biens/{id}/workflow', [AgentBienController::class, 'workflow']);
});
