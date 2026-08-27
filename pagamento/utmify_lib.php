<?php
declare(strict_types=1);

function utmifyConfig(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/utmify_config.php';
    }
    return $config;
}

function utmifyOrdersDir(): string
{
    $dir = __DIR__ . '/orders';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function utmifySalvarPedido(string $orderId, array $data): void
{
    $path = utmifyOrdersDir() . '/' . preg_replace('/[^a-zA-Z0-9._-]/', '', $orderId) . '.json';
    $payload = array_merge($data, [
        'orderId' => $orderId,
        'updatedAt' => gmdate('Y-m-d H:i:s'),
    ]);
    @file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

function utmifyCarregarPedido(string $orderId): ?array
{
    $path = utmifyOrdersDir() . '/' . preg_replace('/[^a-zA-Z0-9._-]/', '', $orderId) . '.json';
    if (!is_file($path)) {
        return null;
    }
    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') {
        return null;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function utmifyNormalizarTracking(?array $tracking): array
{
    $tracking = is_array($tracking) ? $tracking : [];
    $keys = ['src', 'sck', 'utm_source', 'utm_campaign', 'utm_medium', 'utm_content', 'utm_term'];
    $out = [];
    foreach ($keys as $key) {
        $value = $tracking[$key] ?? null;
        $out[$key] = ($value === '' || $value === null) ? null : (string)$value;
    }
    return $out;
}

function utmifyMontarPayload(array $order, string $status, ?string $approvedDate = null): array
{
    $valorCentavos = (int)($order['valorCentavos'] ?? 0);
    if ($valorCentavos <= 0) {
        $valorCentavos = (int)round(((float)($order['valorReais'] ?? 0)) * 100);
    }

    return [
        'orderId' => (string)($order['orderId'] ?? ''),
        'platform' => (string)($order['platform'] ?? utmifyConfig()['platform'] ?? 'Rastreioo'),
        'paymentMethod' => 'pix',
        'status' => $status,
        'createdAt' => (string)($order['createdAt'] ?? gmdate('Y-m-d H:i:s')),
        'approvedDate' => $approvedDate,
        'refundedAt' => null,
        'customer' => [
            'name' => (string)($order['customer']['name'] ?? 'Cliente'),
            'email' => (string)($order['customer']['email'] ?? 'cliente@email.com'),
            'phone' => $order['customer']['phone'] ?? null,
            'document' => $order['customer']['document'] ?? null,
            'country' => 'BR',
        ],
        'products' => [[
            'id' => 'taxa-liberacao-encomenda',
            'name' => 'Taxa de liberação de encomenda',
            'planId' => null,
            'planName' => null,
            'quantity' => 1,
            'priceInCents' => $valorCentavos,
        ]],
        'trackingParameters' => utmifyNormalizarTracking($order['trackingParameters'] ?? null),
        'commission' => [
            'totalPriceInCents' => $valorCentavos,
            'gatewayFeeInCents' => 0,
            'userCommissionInCents' => $valorCentavos,
            'currency' => 'BRL',
        ],
        'isTest' => false,
    ];
}

function utmifyEnviarPedido(array $payload): array
{
    $token = trim((string)(utmifyConfig()['api_token'] ?? ''));
    if ($token === '') {
        return ['success' => false, 'error' => 'Token Utmify não configurado'];
    }

    $curl = curl_init('https://api.utmify.com.br/api-credentials/orders');
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'x-api-token: ' . $token,
        ],
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $response = curl_exec($curl);
    $httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    if ($curlError !== '') {
        return ['success' => false, 'error' => $curlError, 'httpCode' => $httpCode];
    }

    $decoded = json_decode((string)$response, true);
    $ok = $httpCode >= 200 && $httpCode <= 299;

    return [
        'success' => $ok,
        'httpCode' => $httpCode,
        'response' => $decoded ?? $response,
    ];
}

function utmifyRegistrarPedido(array $order, string $status, ?string $approvedDate = null): array
{
    $orderId = (string)($order['orderId'] ?? '');
    if ($orderId === '') {
        return ['success' => false, 'error' => 'orderId vazio'];
    }

    $payload = utmifyMontarPayload($order, $status, $approvedDate);
    $result = utmifyEnviarPedido($payload);

    utmifySalvarPedido($orderId, array_merge($order, [
        'status' => $status,
        'approvedDate' => $approvedDate,
        'lastUtmifyResponse' => $result,
    ]));

    @file_put_contents(
        __DIR__ . '/log_utmify.txt',
        '[' . date('d/m/Y H:i:s') . '] ' . $orderId . ' status=' . $status
        . ' http=' . ($result['httpCode'] ?? 0)
        . ' ok=' . (($result['success'] ?? false) ? '1' : '0')
        . ' resp=' . json_encode($result['response'] ?? null, JSON_UNESCAPED_UNICODE)
        . PHP_EOL,
        FILE_APPEND
    );

    return $result;
}

function marcarPedidoPagoUtmify(string $orderId, ?array $pedido = null, ?string $approvedDate = null): bool
{
    $pedido = $pedido ?? utmifyCarregarPedido($orderId);
    if (!is_array($pedido)) {
        return false;
    }

    $statusAtual = strtolower((string)($pedido['status'] ?? ''));
    if (in_array($statusAtual, ['paid', 'approved', 'completed'], true)) {
        return true;
    }

    if ($approvedDate === null) {
        $approvedDate = gmdate('Y-m-d H:i:s');
        $paidAt = (string)($pedido['paidAt'] ?? '');
        if ($paidAt !== '' && $paidAt !== '0001-01-01T00:00:00') {
            $timestamp = strtotime($paidAt);
            if ($timestamp !== false) {
                $approvedDate = gmdate('Y-m-d H:i:s', $timestamp);
            }
        }
    }

    $result = utmifyRegistrarPedido($pedido, 'paid', $approvedDate);
    return (bool)($result['success'] ?? false);
}
