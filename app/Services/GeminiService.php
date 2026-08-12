<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service d'intégration Google Gemini AI.
 *
 * Gère deux usages :
 *  1. Chatbot assistant immobilier (conversations multi-tours)
 *  2. Recommandations intelligentes de biens selon les préférences utilisateur
 */
class GeminiService
{
    private string $apiKey;
    private string $model;
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
        $this->model  = config('services.gemini.model', 'gemini-flash-lite-latest');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CHATBOT : conversation multi-tours avec contexte immobilier
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Envoie un message au chatbot avec l'historique de la conversation.
     *
     * @param  string  $userMessage   Dernier message de l'utilisateur
     * @param  array   $history       Historique [{role: 'user'|'model', text: '...'}, ...]
     * @param  array   $userContext   Contexte optionnel : préférences, rôle utilisateur, etc.
     * @return string  Réponse de Gemini
     */
    public function chat(string $userMessage, array $history = [], array $userContext = []): string
    {
        $systemInstruction = $this->buildChatSystemPrompt($userContext);

        // Construire les turns de la conversation
        $contents = [];

        foreach ($history as $turn) {
            $contents[] = [
                'role'  => $turn['role'], // 'user' ou 'model'
                'parts' => [['text' => $turn['text']]],
            ];
        }

        // Ajouter le message courant
        $contents[] = [
            'role'  => 'user',
            'parts' => [['text' => $userMessage]],
        ];

        return $this->callGemini($contents, $systemInstruction);
    }

    /**
     * Génère des recommandations de biens personnalisées pour un utilisateur.
     *
     * @param  array  $biens      Liste des biens disponibles (simplifiée)
     * @param  array  $preferences  Préférences utilisateur (budget, type, ville...)
     * @param  array  $historique   Biens consultés récemment (optionnel)
     * @return string JSON de recommandations ou message formaté
     */
    public function recommander(array $biens, array $preferences = [], array $historique = []): string
    {
        $systemInstruction = $this->buildRecommandationSystemPrompt();
        $prompt = $this->buildRecommandationPrompt($biens, $preferences, $historique);

        $contents = [
            [
                'role'  => 'user',
                'parts' => [['text' => $prompt]],
            ],
        ];

        return $this->callGemini($contents, $systemInstruction);
    }

    /**
     * Génère une description enrichie d'un bien avec Gemini.
     *
     * @param  string  $descriptionBrute  Description brute ou générée par règles
     * @param  array   $bien              Données brutes du bien
     * @return string  Description enrichie, professionnelle et élégante
     */
    public function enrichirDescription(string $descriptionBrute, array $bien): string
    {
        // 🔒 Sécurité : Filtrage strict pour ne conserver QUE les informations publiques anonymisées
        $donneesPubliques = [
            'type_bien'        => $bien['type_bien'] ?? null,
            'type_transaction' => $bien['type_transaction'] ?? null,
            'ville'            => $bien['ville'] ?? 'Lomé',
            'quartier'         => $bien['quartier'] ?? ($bien['adresse'] ?? null),
            'prix'             => isset($bien['prix']) ? (float) $bien['prix'] : null,
            'unite_prix'       => $bien['unite_prix'] ?? 'mois',
            'surface'          => isset($bien['surface']) ? (float) $bien['surface'] : null,
            'superficie'       => isset($bien['superficie']) ? (float) $bien['superficie'] : null,
            'nb_pieces'        => $bien['nb_pieces'] ?? null,
            'caracteristiques' => $bien['caracteristiques'] ?? [],
        ];

        // Retirer les clés nuls pour garder un prompt compact
        $donneesPubliques = array_filter($donneesPubliques, fn($v) => !is_null($v));

        // Styles rédactionnels variés pour que chaque annonce soit unique
        $styles = [
            "Style 1 (Moderne & Chaleureux) : Commence par mettre en avant le cadre de vie et la luminosité.",
            "Style 2 (Factualité & Élégance) : Structure en mettant d'abord l'accent sur la localisation et la superficie.",
            "Style 3 (Coup de cœur & Pratique) : Accentue la praticité, la sécurité et la qualité des équipements.",
            "Style 4 (Direct & Convaincant) : Présente d'emblée la configuration principale (nombre de chambres/salon) et le quartier.",
            "Style 5 (Descriptif Immersif) : Invite l'utilisateur à se projeter dans les espaces extérieurs et intérieurs.",
        ];
        $styleChoisi = $styles[array_rand($styles)];

        $systemInstruction = <<<PROMPT
Tu es un rédacteur immobilier professionnel expert du marché togolais (Lomé, Kara, Sokodé, Atakpamé, Kpalimé, Tsévié...).
Ton rôle est de rédiger des annonces immobilières captivantes, élégantes, uniques et variées en français.

Style requis pour cette annonce : {$styleChoisi}

Règles strictes de terminologie et de variabilité :
1. DIVERSITÉ RÉDACTIONNELLE : Ne réutilise pas la même structure de phrase qu'une annonce précédente. Varie le vocabulaire, les tournures de phrases et les accroches.
2. Nomenclatures d'appartements : NE JAMAIS utiliser "F1", "F2", "F3", "F4", "F5" ou "T1", "T2". Exprime toujours la configuration sous la forme "X chambre(s) salon" (ex: "Appartement 2 chambres salon", "1 chambre salon", "Studio").
3. Sanitaires : Utilise la terminologie locale : "WC douche interne", "WC interne", "douche interne", ou "WC externe / partagé". NE PAS utiliser le mot "privatif".
4. Devise : Utilise uniquement le Franc CFA (FCFA).
5. Longueur : 3 à 5 phrases bien rédigées et fluides.
6. Ne mets aucun texte d'introduction ni de politesse. Donne UNIQUEMENT le texte final de l'annonce.
PROMPT;

        $prompt = "Informations techniques du bien immobilier :\n"
            . json_encode($donneesPubliques, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            . ($descriptionBrute ? "\n\nNotes ou éléments spécifiques fournis :\n{$descriptionBrute}" : "")
            . "\n\nRédige une annonce unique selon le style demandé.";

        $contents = [
            ['role' => 'user', 'parts' => [['text' => $prompt]]],
        ];

        // Température élevée (0.95) pour garantir une forte variabilité créative
        return $this->callGemini($contents, $systemInstruction, 0.95);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers privés
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Effectue l'appel HTTP vers l'API Gemini.
     * La clé est passée via query param ?key= (méthode standard Google AI Studio).
     */
    private function callGemini(array $contents, string $systemInstruction = '', float $temperature = 0.7): string
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('Clé API Gemini non configurée. Ajoutez GEMINI_API_KEY dans .env');
        }

        // La clé Google AI Studio s'envoie toujours via ?key= dans l'URL
        $url = "{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}";

        $payload = [
            'contents'         => $contents,
            'generationConfig' => [
                'temperature'     => $temperature,
                'topK'            => 50,
                'topP'            => 0.95,
                'maxOutputTokens' => 1024,
            ],
            'safetySettings' => [
                ['category' => 'HARM_CATEGORY_HARASSMENT',        'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
                ['category' => 'HARM_CATEGORY_HATE_SPEECH',       'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
                ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
                ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
            ],
        ];

        if (!empty($systemInstruction)) {
            $payload['system_instruction'] = [
                'parts' => [['text' => $systemInstruction]],
            ];
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, $payload);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('[Gemini] Timeout/connexion impossible', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Timeout : impossible de joindre l\'API Gemini. Vérifiez la connexion du serveur.');
        }

        if ($response->failed()) {
            $status   = $response->status();
            $body     = $response->body();
            $decoded  = $response->json();
            $apiMsg   = $decoded['error']['message'] ?? $body;

            Log::error('[Gemini] Erreur API', [
                'status'    => $status,
                'api_error' => $apiMsg,
                'model'     => $this->model,
                'key_prefix' => substr($this->apiKey, 0, 10) . '...',
            ]);

            // Messages d'erreur clairs selon le code HTTP
            $hint = match ($status) {
                400 => "Requête invalide (400) : {$apiMsg}",
                401, 403 => "Clé API invalide ou non autorisée (HTTP {$status}). Vérifiez GEMINI_API_KEY dans .env.",
                429 => "Quota Gemini dépassé (429). Votre free tier est épuisé. Solutions : 1) Activez la facturation sur https://console.cloud.google.com/billing 2) Créez une nouvelle clé dans un autre projet Google Cloud sur https://aistudio.google.com/app/apikey",
                404 => "Modèle '{$this->model}' introuvable (404). Vérifiez GEMINI_MODEL dans .env.",
                500, 503 => "Erreur serveur Gemini ({$status}). Réessayez dans quelques instants.",
                default   => "Erreur HTTP {$status} : {$apiMsg}",
            };

            throw new \RuntimeException($hint);
        }

        $data = $response->json();

        // Extraire le texte de la réponse
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if ($text === null) {
            Log::warning('[Gemini] Réponse vide ou bloquée', ['data' => $data]);
            return 'Je suis désolé, je ne peux pas répondre à cette question pour le moment.';
        }

        return trim($text);
    }

    /**
     * Système prompt pour le chatbot assistant immobilier.
     */
    private function buildChatSystemPrompt(array $userContext = []): string
    {
        $role      = $userContext['role'] ?? 'utilisateur';
        $prenom    = $userContext['prenom'] ?? '';
        $ville     = $userContext['ville'] ?? 'Lomé';

        $salutation = $prenom ? "L'utilisateur s'appelle {$prenom}." : '';

        return <<<PROMPT
Tu es l'assistant IA d'ImmoPro, une plateforme immobilière basée au Togo.
Tu aides les utilisateurs avec tout ce qui concerne l'immobilier : recherche de biens, processus de location/vente, conseils juridiques de base, estimation de prix, visites, contrats.

{$salutation}
Rôle de l'utilisateur : {$role}.
Marché immobilier principal : {$ville} et Togo.

Règles :
- Réponds toujours en français, de façon claire et bienveillante.
- Sois concis (3-5 phrases max sauf si l'utilisateur demande plus).
- Si tu ne sais pas quelque chose, dis-le honnêtement.
- Ne donne jamais d'avis légaux définitifs — recommande toujours un notaire ou juriste.
- Contexte local : FCFA comme devise, quartiers de Lomé (Agoè, Bè, Adidogomé, Tokoin, Kodjoviakopé, Hédzranawoé, Légbassito, etc.) et villes du Togo (Kara, Sokodé, Atakpamé, Dapaong, Tsévié, Kpalimé, Notsè, Bassar, etc.).
- Tu peux suggérer des actions concrètes dans l'app (rechercher, filtrer, contacter un agent).
- Ne réponds pas aux questions hors du domaine immobilier.
PROMPT;
    }

    /**
     * Système prompt pour les recommandations.
     */
    private function buildRecommandationSystemPrompt(): string
    {
        return <<<PROMPT
Tu es un moteur de recommandation immobilière intelligent pour ImmoPro (Togo).
Analyse les biens disponibles et les préférences de l'utilisateur pour suggérer les 3 meilleurs biens.
Retourne UNIQUEMENT un JSON valide avec ce format :
{
  "recommandations": [
    {
      "bien_id": "uuid",
      "score": 0.95,
      "raison": "Correspond parfaitement à votre budget et situé à Lomé."
    }
  ],
  "message": "Voici les 3 biens qui correspondent le mieux à vos critères."
}
Critères de scoring : budget (40%), localisation (30%), type de bien (20%), surface (10%).
PROMPT;
    }

    /**
     * Construit le prompt de recommandation avec les données réelles.
     */
    private function buildRecommandationPrompt(array $biens, array $preferences, array $historique): string
    {
        $biensJson       = json_encode($biens, JSON_UNESCAPED_UNICODE);
        $preferencesJson = json_encode($preferences, JSON_UNESCAPED_UNICODE);
        $historiqueJson  = json_encode($historique, JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
Préférences de l'utilisateur :
{$preferencesJson}

Biens récemment consultés (contexte) :
{$historiqueJson}

Biens disponibles à analyser :
{$biensJson}

Recommande les 3 meilleurs biens selon les préférences. Respecte strictement le format JSON demandé.
PROMPT;
    }
}
