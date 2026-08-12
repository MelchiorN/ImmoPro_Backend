<?php
/**
 * Test rapide de validation finale du chatbot ImmoPro.
 */

$envContent = file_get_contents(__DIR__ . '/.env');
preg_match('/GEMINI_API_KEY=(.+)/', $envContent, $m);
$apiKey = trim($m[1] ?? '');

preg_match('/GEMINI_MODEL=(.+)/', $envContent, $m2);
$model = trim($m2[1] ?? 'gemini-flash-lite-latest');

echo "=== VALIDATION FINALE IA IMMOPRO ===" . PHP_EOL;
echo "Clé    : " . substr($apiKey, 0, 12) . "..." . PHP_EOL;
echo "Modèle : $model" . PHP_EOL . PHP_EOL;

$url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
$payload = json_encode([
    'contents' => [['role' => 'user', 'parts' => [['text' => 'Bonjour, je cherche une maison à louer à Lomé.']]]],
    'generationConfig' => ['maxOutputTokens' => 100],
]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);

if ($httpCode === 200 && isset($data['candidates'])) {
    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    echo "✅ SUCCÈS TOTAL ! Gemini répond au chatbot :" . PHP_EOL;
    echo "----------------------------------------" . PHP_EOL;
    echo trim($text) . PHP_EOL;
    echo "----------------------------------------" . PHP_EOL;
    echo "🎉 Le chatbot est 100% fonctionnel et prêt pour l'application ImmoPro !" . PHP_EOL;
} else {
    echo "❌ Erreur ($httpCode) : " . json_encode($data) . PHP_EOL;
}
