<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$telephone = '+22897884049';
$operateur = 'FLOOZ';

try {
    $semoa = app(App\Services\Payment\SemoaService::class);

    echo "--- CREATE ORDER via Service ---\n";
    $res = $semoa->createOrder([
        'montant'      => 200,
        'telephone'    => $telephone,
        'operateur'    => $operateur,
        'reference'    => 'TEST-FINAL-' . time(),
        'description'  => 'Test final sandbox gateway resolution',
        'callback_url' => 'https://example.com',
    ]);
    
    print_r($res);

} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

