<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GeminiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests d'intégration pour l'assistant IA Gemini.
 *
 * Pour lancer ces tests :
 *   php artisan test --filter=GeminiIntegrationTest
 *
 * ⚠️ Ces tests nécessitent une clé API Gemini valide dans .env
 */
class GeminiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Créer un utilisateur de test
        $this->user = User::factory()->create([
            'role'       => 'client',
            'first_name' => 'Jean',
            'last_name'  => 'Dupont',
        ]);

        // Skip les tests si pas de clé API
        if (empty(config('services.gemini.api_key'))) {
            $this->markTestSkipped('GEMINI_API_KEY non configurée dans .env');
        }
    }

    /** @test */
    public function it_can_send_a_simple_chat_message()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/ai/chat', [
                'message' => 'Bonjour',
                'history' => [],
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'reponse',
                'role',
            ])
            ->assertJson(['success' => true, 'role' => 'model']);

        $reponse = $response->json('reponse');
        $this->assertNotEmpty($reponse);
        $this->assertIsString($reponse);
    }

    /** @test */
    public function it_can_chat_with_history()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/ai/chat', [
                'message' => 'Et pour une vente ?',
                'history' => [
                    ['role' => 'user', 'text' => 'Quels documents pour une location ?'],
                    ['role' => 'model', 'text' => 'Pour une location au Togo, il faut...'],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $reponse = $response->json('reponse');
        $this->assertNotEmpty($reponse);
    }

    /** @test */
    public function it_validates_chat_input()
    {
        // Message vide
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/ai/chat', [
                'message' => '',
                'history' => [],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('message');

        // Message trop long
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/ai/chat', [
                'message' => str_repeat('a', 2001),
                'history' => [],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('message');
    }

    /** @test */
    public function it_can_get_recommendations()
    {
        // Créer quelques biens de test
        \App\Models\Bien::factory()->count(5)->create([
            'statut' => 'publie',
            'prix'   => 100000,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/ai/recommandations', [
                'limit' => 10,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'recommandations',
                'message',
            ])
            ->assertJson(['success' => true]);

        $recommandations = $response->json('recommandations');
        $this->assertIsArray($recommandations);
    }

    /** @test */
    public function it_returns_empty_recommendations_if_no_properties()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/ai/recommandations', ['limit' => 10]);

        $response->assertStatus(200)
            ->assertJson([
                'success'         => true,
                'recommandations' => [],
            ]);
    }

    /** @test */
    public function it_requires_authentication_for_ai_endpoints()
    {
        // Chat sans auth
        $response = $this->postJson('/api/ai/chat', [
            'message' => 'Test',
            'history' => [],
        ]);

        $response->assertStatus(401);

        // Recommandations sans auth
        $response = $this->postJson('/api/ai/recommandations');
        $response->assertStatus(401);
    }

    /** @test */
    public function gemini_service_can_enrich_description()
    {
        $service = app(GeminiService::class);

        $descriptionBrute = 'Appartement 3 pièces à Lomé. Surface : 80 m². Prix : 150 000 FCFA par mois.';

        $bien = [
            'titre'            => 'Bel appartement F3',
            'type_bien'        => 'appartement',
            'type_transaction' => 'location',
            'prix'             => 150000,
            'surface'          => 80,
            'adresse'          => 'Tokoin, Lomé',
        ];

        $descriptionEnrichie = $service->enrichirDescription($descriptionBrute, $bien);

        $this->assertNotEmpty($descriptionEnrichie);
        $this->assertIsString($descriptionEnrichie);
        $this->assertNotEquals($descriptionBrute, $descriptionEnrichie);
    }
}
