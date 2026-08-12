<?php

namespace App\Services\Payment;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service d'intégration Semoa CashPay API V2.0
 *
 * Documentation : https://documenter.getpostman.com/view/4377470/UUxukWRR
 * Endpoint sandbox : https://sandbox.semoa-payments.com/api
 *
 * Flux :
 *   1. authenticate()   → POST /auth            → access_token (mis en cache)
 *   2. getGateways()    → GET  /gateways         → liste des passerelles dispo
 *   3. createOrder(...) → POST /orders           → créer la facture de paiement
 *   4. getOrder($ref)   → GET  /orders/{ref}     → vérifier le statut
 *
 * Gateways sandbox disponibles (depuis la doc) :
 *   - FloozTG-Ecom-Semoa  → ref: 016eb63c-f29d-4384-92e4-b1bd37ef69f8  (PUSH_USSD)
 *   - TmoneyGateway1      → ref: a2c87957-1033-46e9-8706-056e45737de1  (USSD)
 *   - SandboxSemoa        → ref: 14f4597d-ef96-4263-8107-1e1970959133  (DIRECT_URL - test)
 */
class SemoaService
{
    private string $baseUrl;
    private string $username;
    private string $password;
    private string $clientId;
    private string $clientSecret;
    private bool $simulate;

    // Références UUID des passerelles Semoa (récupérées via getGateways())
    public const GATEWAY_TMONEY = 'a2c87957-1033-46e9-8706-056e45737de1';
    public const GATEWAY_FLOOZ  = '016eb63c-f29d-4384-92e4-b1bd37ef69f8';
    public const GATEWAY_CARD   = '52bfd484-13ef-44f3-b128-adf7187779b0'; // Ecobank / carte
    public const GATEWAY_SANDBOX = '14f4597d-ef96-4263-8107-1e1970959133'; // Test

    public function __construct()
    {
        $this->baseUrl       = rtrim(config('services.semoa.base_url', 'https://sandbox.semoa-payments.com/api'), '/');
        $this->username      = config('services.semoa.username', '');
        $this->password      = config('services.semoa.password', '');
        $this->clientId      = config('services.semoa.client_id', '');
        $this->clientSecret  = config('services.semoa.client_secret', '');
        $this->ledger        = config('services.semoa.ledger', 'de7a9b8e-74be-4ced-a263-7323e242cf19');
    }

    public function isSimulation(): bool
    {
        return (bool) config('services.semoa.simulate', false);
    }

    public function authenticate(): string
    {
        // Mode simulation : retourner un faux token
        if ($this->isSimulation()) {
            Log::info('[Semoa SIMULATION] Token simulé retourné.');
            return 'SIMULATED_TOKEN_' . now()->timestamp;
        }

        $cacheKey = 'semoa_access_token_' . md5($this->username);

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        Log::info('[Semoa] Demande d\'un nouveau access_token → POST /auth');

        $response = Http::acceptJson()
            ->asJson()
            ->post("{$this->baseUrl}/auth", [
                'username'      => $this->username,
                'password'      => $this->password,
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]);

        $this->checkResponse($response, 'Authentification Semoa');

        $data  = $response->json();
        $token = $data['access_token'] ?? null;

        if (! $token) {
            throw new \RuntimeException('[Semoa] access_token absent : ' . $response->body());
        }

        $ttl = min(($data['expires_in'] ?? 3600) - 10, 3000);
        Cache::put($cacheKey, $token, $ttl);

        Log::info('[Semoa] Token obtenu, mis en cache ' . $ttl . 's.');

        return $token;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ÉTAPE 2 — Récupérer les passerelles disponibles
    // GET {baseUrl}/gateways
    // Header : Authorization Bearer {token}
    // ─────────────────────────────────────────────────────────────────────────

    public function getGateways(): array
    {
        if ($this->isSimulation()) {
            return [
                ['id' => self::GATEWAY_TMONEY, 'name' => 'T-Money (Simulé)', 'status' => 'Active'],
                ['id' => self::GATEWAY_FLOOZ,  'name' => 'Flooz (Simulé)',   'status' => 'Active'],
                ['id' => self::GATEWAY_CARD,   'name' => 'Carte Bancaire (Simulé)', 'status' => 'Active'],
            ];
        }

        $token = $this->authenticate();

        $response = Http::withToken($token)
            ->acceptJson()
            ->get("{$this->baseUrl}/gateways");

        $this->checkResponse($response, 'Récupération gateways Semoa');

        return $response->json() ?? [];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ÉTAPE 3 — Créer une facture de paiement
    // POST {baseUrl}/orders
    // Header : Authorization Bearer {token}
    // Body JSON :
    // {
    //   "amount"              : 5000,
    //   "merchant_reference"  : "LOC-XXXXXXXX",   ← votre référence interne
    //   "callback_url"        : "https://...",
    //   "client" : {
    //     "phone"   : "+22890xxxxxx",
    //     "country" : "TG"
    //   },
    //   "gateway"  : "a2c87957-..."  ← UUID de la passerelle choisie
    // }
    // Réponse : { order_reference, bill_url, state, ... }
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param array{
     *   montant: float,
     *   telephone: string,
     *   operateur: string,        // 'TMONEY' | 'FLOOZ' | 'CARD'
     *   reference: string,        // Référence ImmoPro (ex: LOC-uuid)
     *   description: string,
     *   callback_url: string,
     * } $params
     *
     * @return array{
     *   order_reference: string,
     *   bill_url: string,
     *   state: string,
     *   raw: array,
     * }
     */
    public function createOrder(array $params): array
    {
        // Mode simulation : retourner une fausse facture
        if ($this->isSimulation()) {
            $fakeRef  = 'SANDBOX-' . now()->timestamp . '-' . strtoupper(substr(md5(rand()), 0, 6));
            $billUrl  = 'https://sandbox.cashpay.tg/facture/' . $fakeRef;
            Log::info('[Semoa SIMULATION] Facture simulée créée', [
                'order_reference' => $fakeRef,
                'bill_url'        => $billUrl,
                'montant'         => $params['montant'],
            ]);
            return [
                'order_reference' => $fakeRef,
                'bill_url'        => $billUrl,
                'state'           => 'Pending',
                'raw'             => ['simulated' => true],
            ];
        }

        $token = $this->authenticate();

        // Mapper l'opérateur vers la référence UUID Semoa
        $gatewayRef = $this->resolveGateway($params['operateur']);

        Log::info('[Semoa] Création facture → POST /orders', [
            'reference' => $params['reference'],
            'montant'   => $params['montant'],
            'gateway'   => $gatewayRef,
        ]);

        $amount = (int) round($params['montant']);
        if ($amount <= 0) {
            throw new \InvalidArgumentException("[Semoa] Le montant de la commande doit être supérieur à 0 FCFA (fourni : {$params['montant']}).");
        }

        // Forcer l'utilisation de l'URL Ngrok pour le webhook Semoa si disponible dans config/env
        $webhookBase = config('services.semoa.webhook_base_url');
        if (!empty($webhookBase)) {
            $callbackUrl = str_replace(url('/'), rtrim($webhookBase, '/'), $params['callback_url']);
        } else {
            $callbackUrl = $params['callback_url'];
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->post("{$this->baseUrl}/tpos/orders", [
                'amount'             => $amount,
                'merchant_reference' => $params['reference'],
                'callback_url'       => $callbackUrl,
                'redirect_url'       => $params['redirect_url'] ?? null,
                'client'             => [
                    'phone'   => $params['telephone'],
                ],
                'gateway'            => [
                    'reference' => $gatewayRef,
                ],
                'ledger'             => [
                    'reference' => $this->ledger,
                ],
            ]);

        $this->checkResponse($response, 'Création facture Semoa');

        $data = $response->json();
        $orderRef = $data['order_reference'] ?? $params['reference'];

        $isSandbox = config('services.semoa.env', 'sandbox') === 'sandbox';
        $domain = $isSandbox ? 'sandbox.cashpay.tg' : 'cashpay.tg';
        $checkoutUrl = "https://{$domain}/facture/{$orderRef}";

        Log::info('[Semoa] Facture créée', [
            'order_reference' => $orderRef,
            'checkout_url'    => $checkoutUrl,
            'api_bill_url'    => $data['bill_url'] ?? null
        ]);

        return [
            'order_reference' => $orderRef,
            'bill_url'        => $checkoutUrl,
            'raw_bill_url'    => $data['bill_url'] ?? null,
            'state'           => $data['state'] ?? 'Pending',
            'raw'             => $data,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ÉTAPE 4 — Déclencher le PUSH USSD directement (sans ouvrir bill_url)
    // POST {baseUrl}/tpos/orders/{order_reference}/pay
    // Body : { "gateway": { "reference": "<uuid>" }, "client": { "phone": "+228..." } }
    // Semoa envoie directement la notification PUSH sur le téléphone
    // ─────────────────────────────────────────────────────────────────────────

    public function triggerDirectPay(string $orderReference, string $operateur, string $telephone): array
    {
        if ($this->isSimulation()) {
            Log::info('[Semoa SIMULATION] Direct pay simulé', [
                'order_reference' => $orderReference,
                'operateur'       => $operateur,
                'telephone'       => $telephone,
            ]);
            return ['success' => true, 'simulated' => true];
        }

        $token      = $this->authenticate();
        $gatewayRef = $this->resolveGateway($operateur);

        Log::info('[Semoa] Déclenchement PUSH direct → POST /tpos/orders/{ref}/pay', [
            'order_reference' => $orderReference,
            'gateway'         => $gatewayRef,
            'telephone'       => $telephone,
        ]);

        $response = Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->post("{$this->baseUrl}/tpos/orders/{$orderReference}/pay", [
                'gateway' => [
                    'reference' => $gatewayRef,
                ],
                'client' => [
                    'phone' => $telephone,
                ],
            ]);

        $this->checkResponse($response, "Déclenchement PUSH #{$orderReference}");

        $data = $response->json();

        Log::info('[Semoa] PUSH déclenché', [
            'order_reference' => $orderReference,
            'response'        => $data,
        ]);

        return $data;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ÉTAPE 5 — Vérifier le statut d'une facture
    // GET {baseUrl}/orders/{order_reference}
    // États : Pending | Paid | Error | Partial | Excess
    // ─────────────────────────────────────────────────────────────────────────

    public function getOrder(string $orderReference): array
    {
        $token = $this->authenticate();

        $response = Http::withToken($token)
            ->acceptJson()
            ->get("{$this->baseUrl}/orders/{$orderReference}");

        $this->checkResponse($response, "Récupération facture #{$orderReference}");

        return $response->json();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Test de connexion (ping)
    // ─────────────────────────────────────────────────────────────────────────

    public function testConnexion(): array
    {
        try {
            Cache::forget('semoa_access_token_' . md5($this->username));
            $token = $this->authenticate();

            return [
                'success'       => true,
                'message'       => 'Connexion Semoa réussie.',
                'token_preview' => substr($token, 0, 30) . '...',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Connexion Semoa échouée : ' . $e->getMessage(),
            ];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers privés
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Convertit le code opérateur ImmoPro → référence UUID Semoa gateway.
     */
    private function resolveGateway(string $operateur): string
    {
        $op = strtoupper($operateur);

        // En mode sandbox, forcer la passerelle de test (SandboxSemoa) pour FLOOZ et TMONEY
        // afin de pouvoir simuler le succès/échec sans erreur de passerelle réelle.
        if (config('services.semoa.env', 'sandbox') === 'sandbox') {
            if (in_array($op, ['TMONEY', 'FLOOZ'])) {
                return self::GATEWAY_SANDBOX;
            }
        }

        return match($op) {
            'TMONEY' => self::GATEWAY_TMONEY,
            'FLOOZ'  => self::GATEWAY_FLOOZ,
            'CARD'   => self::GATEWAY_CARD,
            'TEST', 'SANDBOX' => self::GATEWAY_SANDBOX,
            default  => self::GATEWAY_SANDBOX, // Fallback sandbox
        };
    }

    private function checkResponse(Response $response, string $context): void
    {
        if ($response->successful()) {
            return;
        }

        $body = $response->body();
        $code = $response->status();

        Log::error("[Semoa] Erreur {$context}", ['status' => $code, 'body' => $body]);

        $message = $response->json('message')
            ?? $response->json('error_description')
            ?? $body;

        throw new \RuntimeException("[Semoa] {$context} → HTTP {$code} : {$message}");
    }
}
