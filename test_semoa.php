<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $semoa = app(App\Services\Payment\SemoaService::class);
    
    echo "--- GET GATEWAYS ---\n";
    $gateways = $semoa->getGateways();
    print_r($gateways);

    echo "\n--- CREATE ORDER ---\n";
    $res = $semoa->createOrder([
        'montant' => 200,
        'telephone' => '+22897884049', // Flooz number provided by user
        'operateur' => 'FLOOZ',
        'reference' => 'TEST-SANDBOX-' . time(),
        'description' => 'Test',
        'callback_url' => 'https://example.com'
    ]);
    print_r($res);
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
