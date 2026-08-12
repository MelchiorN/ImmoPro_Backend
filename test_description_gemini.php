<?php
/**
 * Test d'enrichissement de description avec l'IA Gemini pour ImmoPro.
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\GeminiService;

echo "=== TEST ENRICHISSEMENT DESCRIPTION GEMINI ===" . PHP_EOL;

$gemini = app(GeminiService::class);

$bienExemple = [
    'type_bien'        => 'villa',
    'type_transaction' => 'location',
    'ville'            => 'Lomé',
    'quartier'         => 'Agoè-Togbin',
    'prix'             => 250000,
    'unite_prix'       => 'mois',
    'surface'          => 180,
    'nb_pieces'        => 5,
    'caracteristiques' => [
        'nb_chambres'     => 3,
        'nb_salles_bain'  => 2,
        'salon'           => true,
        'cuisine'         => true,
        'climatisation'   => true,
        'terrasse'        => true,
        'parking_existe'  => true,
        'gardiennage'     => true,
    ]
];

$notesCourtes = "Belle villa moderne, proche du goudron, quartier calme et sécurisé.";

echo "Données du bien :" . PHP_EOL;
echo " - Type: Villa en location à Agoè-Togbin (Lomé)" . PHP_EOL;
echo " - Prix: 250 000 FCFA/mois" . PHP_EOL;
echo " - Caractéristiques: 3 chambres, 2 SDB, climatisée, terrasse, parking, gardien" . PHP_EOL . PHP_EOL;

try {
    echo "Génération de la description enrichie via Gemini..." . PHP_EOL;
    $resultat = $gemini->enrichirDescription($notesCourtes, $bienExemple);

    echo PHP_EOL . "✨ DESCRIPTION GÉNÉRÉE PAR L'IA :" . PHP_EOL;
    echo "--------------------------------------------------------" . PHP_EOL;
    echo $resultat . PHP_EOL;
    echo "--------------------------------------------------------" . PHP_EOL;
    echo "✅ TEST REUSSI !" . PHP_EOL;

} catch (\Throwable $e) {
    echo "❌ ERREUR : " . $e->getMessage() . PHP_EOL;
}
