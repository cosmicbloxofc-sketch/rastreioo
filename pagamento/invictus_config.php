<?php
declare(strict_types=1);

return [
    // Painel InvictusPay -> Configuracoes -> Credenciais de API
    'api_key' => getenv('INVICTUS_API_KEY') ?: 'sk_gpj8hdXjTzpqs8q8EL9FhxVMwClEFDwvF5WhsBZCZHmlhsGHWftiYOkm',
    'api_base' => 'https://api.invictuspayv2.com.br/api/v1',
    // Deixe vazio para detectar automaticamente o host (Vercel/local)
    'postback_url' => getenv('INVICTUS_POSTBACK_URL') ?: '',
];
