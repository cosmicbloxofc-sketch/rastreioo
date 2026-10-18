<?php
declare(strict_types=1);

return [
    // Pixel do Eliseu Moreira - sync com front (pixel-utmify.js)
    'pixel_id' => getenv('META_PIXEL_ID') ?: '1794883931600243',

    // Token da Conversions API: Business Settings > Fontes de dados > Pixel > Configuracoes > API de Conversoes > Gerar token
    // Se deixar vazio, o envio server-side fica desabilitado e loga como skip.
    'access_token' => getenv('META_CAPI_TOKEN') ?: 'EAAXarvljVWABSZBd9tBDUEUr7rbQ9edq8TW4lpIiff4DizeYZCZAiVTKPZC2XZCIZBuZCZBksz2YMDfqvz0meAFexVuXTG2NySXfJhws5P1zZBLfnTX4QMDNTXrHkO1GUdBC3SVVfvZBZBlLRvKpcvaIZCePfzLp6sX3xuZCbkSyuQiP6TZCQ14n1ui4VV4UoZCZBAYklQZDZD',

    // Versao da Graph API
    'api_version' => 'v20.0',

    // Opcional: codigo de teste do Gerenciador de Eventos para ver eventos em "Teste de eventos"
    // Ex: 'TEST12345' - remover em producao
    'test_event_code' => null,
];
