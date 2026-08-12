<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Models\Bien;
use App\Services\GeminiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Contrôleur pour l'assistant IA Gemini.
 *
 * Routes :
 *   POST /api/ai/chat              → chatbot multi-tours
 *   POST /api/ai/recommandations   → recommandations de biens
 */
class AiController extends Controller
{
    public function __construct(private readonly GeminiService $gemini) {}

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/ai/chat
    // Corps : { message: string, history: [{role, text}, ...] }
    // ─────────────────────────────────────────────────────────────────────────

    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message'           => 'required|string|max:2000',
            'history'           => 'nullable|array|max:20',
            'history.*.role'    => 'required|in:user,model',
            'history.*.text'    => 'required|string|max:5000',
        ]);

        $user    = $request->user();
        $message = $request->input('message');
        $history = $request->input('history', []);

        // Contexte utilisateur injecté dans le system prompt
        $userContext = [
            'role'   => $user->role ?? 'utilisateur',
            'prenom' => $user->first_name ?? '',
        ];

        try {
            $reponse = $this->gemini->chat($message, $history, $userContext);

            return response()->json([
                'success'  => true,
                'reponse'  => $reponse,
                'role'     => 'model',
            ]);

        } catch (\Throwable $e) {
            Log::error('[AI Chat] Erreur Gemini', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);

            // En dev, on retourne le détail de l'erreur pour faciliter le debug
            $detail = app()->isLocal() ? $e->getMessage() : null;

            return response()->json([
                'success' => false,
                'message' => 'L\'assistant IA est temporairement indisponible. Veuillez réessayer.',
                'debug'   => $detail,
            ], 503);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/ai/recommandations
    // Corps : { bien_ids?: [], limit?: 10 }  (optionnel — filtre biens à analyser)
    // ─────────────────────────────────────────────────────────────────────────

    public function recommandations(Request $request): JsonResponse
    {
        $request->validate([
            'limit'    => 'nullable|integer|min:1|max:50',
            'bien_ids' => 'nullable|array|max:50',
        ]);

        $user  = $request->user();
        $limit = $request->integer('limit', 20);

        // Récupérer les préférences de l'utilisateur
        $preferences = [];
        if ($user->preference) {
            $preferences = [
                'budget_min'           => $user->preference->budget_min,
                'budget_max'           => $user->preference->budget_max,
                'types_biens_preferes' => $user->preference->types_biens_preferes ?? [],
                'villes_preferees'     => $user->preference->villes_preferees ?? [],
            ];
        }

        // Récupérer les favoris de l'utilisateur pour le comportement implicite
        $favoris = $user->favoris()
            ->select(['biens.id', 'biens.titre', 'biens.type_bien', 'biens.prix', 'biens.adresse'])
            ->orderBy('favoris.created_at', 'desc')
            ->take(5)
            ->get()
            ->toArray();

        // Récupérer les biens publiés
        $query = Bien::where('statut', 'publie')
            ->select([
                'id', 'titre', 'type_bien', 'type_transaction',
                'prix', 'unite_prix', 'surface', 'adresse',
                'nb_pieces', 'caracteristiques',
            ])
            ->latest('publie_le')
            ->limit($limit);

        if ($request->filled('bien_ids')) {
            $query->whereIn('id', $request->input('bien_ids'));
        }

        // Filtre léger sur budget si les préférences existent
        if (!empty($preferences['budget_max'])) {
            $query->where('prix', '<=', $preferences['budget_max'] * 1.25); // +25% marge
        }

        $biens = $query->get()->map(fn ($b) => [
            'id'               => $b->id,
            'titre'            => $b->titre,
            'type_bien'        => $b->type_bien,
            'type_transaction' => $b->type_transaction,
            'prix'             => (float) $b->prix,
            'unite_prix'       => $b->unite_prix,
            'surface'          => (float) $b->surface,
            'adresse'          => $b->adresse,
            'nb_pieces'        => $b->nb_pieces,
        ])->toArray();

        if (empty($biens)) {
            return response()->json([
                'success'         => true,
                'recommandations' => [],
                'message'         => 'Aucun bien disponible pour le moment.',
            ]);
        }

        try {
            $reponseRaw = $this->gemini->recommander($biens, $preferences, $favoris);

            // Tenter de parser le JSON retourné par Gemini
            $parsed = $this->parseJsonResponse($reponseRaw);
            $recs = $parsed['recommandations'] ?? [];

            if (empty($recs)) {
                $recs = $this->genererRecommandationsLocales($biens, $preferences);
            }

            // Récupérer tous les détails des biens recommandés en 1 seule requête SQL
            $bienIds = array_column($recs, 'bien_id');
            $biensDetails = Bien::whereIn('id', $bienIds)->get()->keyBy('id');

            $recommandationsEnrichies = array_filter(array_map(function ($rec) use ($biensDetails) {
                $b = $biensDetails->get($rec['bien_id']);
                if (!$b) return null;
                $rec['bien'] = $b->toArray();
                return $rec;
            }, $recs));

            return response()->json([
                'success'         => true,
                'source'          => !empty($parsed['recommandations']) ? 'gemini_ia' : 'local_fallback',
                'recommandations' => array_values($recommandationsEnrichies),
                'message'         => $parsed['message'] ?? 'Voici nos recommandations personnalisées pour vous.',
            ]);

        } catch (\Throwable $e) {
            Log::warning('[AI Recommandations] Gemini indisponible ou limite atteinte, fallback local activé', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);

            // Moteur de secours local (calcul de score pondéré)
            return response()->json([
                'success'         => true,
                'source'          => 'local_fallback',
                'recommandations' => $this->genererRecommandationsLocales($biens, $preferences),
                'message'         => 'Recommandations calculées selon vos préférences.',
            ]);
        }
    }

    /**
     * Moteur de scoring local sécurisé (Weighted Recommendation Engine).
     * Utilisé comme fallback si Gemini est indisponible ou s'il n'y a pas de quota.
     */
    private function genererRecommandationsLocales(array $biens, array $preferences): array
    {
        $scored = [];

        $budgetMax = $preferences['budget_max'] ?? null;
        $budgetMin = $preferences['budget_min'] ?? null;
        $typesPref = $preferences['types_biens_preferes'] ?? [];
        $villesPref = $preferences['villes_preferees'] ?? [];

        foreach ($biens as $bien) {
            $score = 0.50; // Score de base 50%
            $raisons = [];

            // Critère 1 : Budget (pondération 40%)
            if ($budgetMax && $bien['prix'] <= $budgetMax) {
                $score += 0.35;
                $raisons[] = 'Prix en dessous de votre budget max';
            } elseif ($budgetMax && $bien['prix'] <= $budgetMax * 1.15) {
                $score += 0.15;
                $raisons[] = 'Prix proche de votre budget';
            }

            // Critère 2 : Type de bien (pondération 30%)
            if (!empty($typesPref) && in_array(strtolower($bien['type_bien']), array_map('strtolower', $typesPref))) {
                $score += 0.25;
                $raisons[] = 'Type de bien correspondant';
            }

            // Critère 3 : Localisation (pondération 20%)
            if (!empty($villesPref) && !empty($bien['adresse'])) {
                foreach ($villesPref as $v) {
                    if (str_ireplace($v, '', $bien['adresse']) !== $bien['adresse']) {
                        $score += 0.20;
                        $raisons[] = 'Localisation souhaitée';
                        break;
                    }
                }
            }

            // Normaliser le score max à 0.98
            $score = min(0.98, round($score, 2));

            $scored[] = [
                'bien_id' => $bien['id'],
                'score'   => $score,
                'raison'  => !empty($raisons) ? implode(', ', $raisons) . '.' : 'Bien récent susceptible de vous intéresser.',
            ];
        }

        // Tri par score décroissant
        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, 5);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/ai/generer-description
    // Corps : { bien_id?: string, description_brute?: string, bien_data?: array }
    // ─────────────────────────────────────────────────────────────────────────

    public function genererDescription(Request $request, \App\Services\BienDescriptionService $ruleService): JsonResponse
    {
        $request->validate([
            'bien_id'           => 'nullable|string|exists:biens,id',
            'description_brute' => 'nullable|string|max:2000',
            'bien_data'         => 'nullable|array',
        ]);

        $bienId           = $request->input('bien_id');
        $descriptionBrute = $request->input('description_brute', '');
        $bienData         = $request->input('bien_data', []);

        $bienModel = null;
        if ($bienId) {
            $bienModel = Bien::find($bienId);
            if ($bienModel) {
                // ── Retourner la description mise en cache si disponible ──────
                // Évite un appel Gemini inutile si la desc a déjà été générée à l'approbation.
                if (!empty($bienModel->desc_personnalisee)) {
                    return response()->json([
                        'success'     => true,
                        'source'      => 'cached',
                        'description' => $bienModel->desc_personnalisee,
                    ]);
                }
                $bienData = array_merge($bienModel->toArray(), $bienData);
            }
        }

        // Étape 1 : Si aucune description brute n'est passée, générer une base par règles
        if (empty($descriptionBrute) && $bienModel) {
            $descriptionBrute = $ruleService->construire($bienModel);
        }

        try {
            // Étape 2 : Tenter la génération enrichie avec Gemini IA
            $descriptionEnrichie = $this->gemini->enrichirDescription($descriptionBrute, $bienData);

            // Mettre en cache sur le bien si on en a un (évite les appels futurs)
            if ($bienModel && empty($bienModel->desc_personnalisee)) {
                $bienModel->update(['desc_personnalisee' => $descriptionEnrichie]);
            }

            return response()->json([
                'success'     => true,
                'source'      => 'gemini_ia',
                'description' => $descriptionEnrichie,
            ]);

        } catch (\Throwable $e) {
            Log::warning('[AI Description] Gemini indisponible, fallback vers règles', [
                'error' => $e->getMessage(),
            ]);

            // Fallback Sécurisé : Si l'IA échoue, on retourne la description basée sur les règles
            $fallbackDesc = !empty($descriptionBrute) 
                ? $descriptionBrute 
                : ($bienModel ? $ruleService->construire($bienModel) : 'Bien immobilier de qualité.');

            return response()->json([
                'success'     => true,
                'source'      => 'rule_fallback',
                'description' => $fallbackDesc,
                'message'     => 'Description générée en mode secours.',
            ]);
        }
    }


    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/ai/ping  (local/debug uniquement)
    // Test rapide : vérifie que la clé API Gemini est valide
    // ─────────────────────────────────────────────────────────────────────────

    public function ping(): JsonResponse
    {
        if (!app()->isLocal()) {
            return response()->json(['message' => 'Non disponible en production.'], 403);
        }

        $apiKey = config('services.gemini.api_key');
        $model  = config('services.gemini.model');

        // Vérifications préliminaires
        $checks = [
            'key_configured'  => !empty($apiKey),
            'key_format_ok'   => str_starts_with($apiKey ?? '', 'AIzaSy'),
            'model_configured'=> !empty($model),
            'key_prefix'      => $apiKey ? substr($apiKey, 0, 10) . '...' : 'NON DÉFINIE',
            'model'           => $model,
        ];

        if (!$checks['key_configured']) {
            return response()->json([
                'success' => false,
                'error'   => 'GEMINI_API_KEY non définie dans .env',
                'checks'  => $checks,
            ], 500);
        }

        // Test avec les deux formats de clés (standard AIzaSy... et auth AQ.xxx)
        $isAuthKey = !str_starts_with($apiKey, 'AIzaSy');

        // Tester un appel réel minimal
        try {
            $reponse = $this->gemini->chat('Réponds juste "OK" pour tester la connexion.', [], []);
            return response()->json([
                'success'  => true,
                'message'  => 'Connexion Gemini OK',
                'response' => substr($reponse, 0, 100),
                'checks'   => $checks,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
                'checks'  => $checks,
            ], 500);
        }
    }

    /**
     * Extrait le JSON d'une réponse Gemini qui peut contenir du texte autour.
     */
    private function parseJsonResponse(string $raw): array
    {
        // Gemini peut entourer le JSON de backticks (```json ... ```)
        $clean = preg_replace('/```json\s*/i', '', $raw);
        $clean = preg_replace('/```\s*/i', '', $clean ?? $raw);
        $clean = trim($clean ?? $raw);

        $decoded = json_decode($clean, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            // Si Gemini n'a pas retourné du JSON valide, retourner une structure vide
            Log::warning('[AI] Réponse non-JSON de Gemini', ['raw' => $raw]);
            return ['recommandations' => [], 'message' => $raw];
        }

        return $decoded;
    }
}
