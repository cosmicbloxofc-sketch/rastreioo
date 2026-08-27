<?php
declare(strict_types=1);

return [
    // Mesmas credenciais GestãoPay do projeto ativo26
    // Use environment variables for credentials
    'public_key' => getenv('GESTAOPAY_PUBLIC_KEY') ?: '',
    'secret_key' => getenv('GESTAOPAY_SECRET_KEY') ?: '',
    'api_url' => 'https://api.gestaopayments.com/v1/payment-transaction/create',
];
