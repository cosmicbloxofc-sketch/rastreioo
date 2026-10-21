<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/utmify_lib.php';
require_once __DIR__ . '/meta_capi_lib.php';

$payload = file_get_contents('php://input');
$data = date('d/m/Y H:i:s');
@file_put_contents(
    __DIR__ . '/log_gestaopay_webhook.txt',
    '[' . $data . '] ' . (string)$payload . PHP_EOL . PHP_EOL,
    FILE_APPEND
);

$event = json_decode((string)$payload, true);
if (!is_array($event)) {
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

$orderId = (string)($event['Id'] ?? ($event['id'] ?? ($event['identifier'] ?? ($event['external_id'] ?? ''))));
$statusRaw = strtoupper((string)($event['Status'] ?? ($event['status'] ?? ($event['status_pagamento'] ?? ''))));
$isPaid = in_array($statusRaw, ['PAID', 'APPROVED', 'COMPLETED'], true);
// Alguns payloads GestãoPay aninham em data/request
if ($orderId === '' && isset($event['data']) && is_array($event['data'])) {
    $orderId = (string)($event['data']['id'] ?? ($event['data']['Id'] ?? ''));
    $statusRaw = strtoupper((string)($event['data']['Status'] ?? ($event['data']['status'] ?? $statusRaw)));
    $isPaid = in_array($statusRaw, ['PAID', 'APPROVED', 'COMPLETED'], true);
}

$processed = false;
$detail = 'no_paid';

if ($orderId !== '' && $isPaid) {
    // Tentar extrair dados ricos do payload GestãoPay quando orders/*.json sumiu (Vercel ephemeral)
    $evData = $event['data'] ?? ($event['request'] ?? $event);
    if (!is_array($evData)) $evData = $event;
    $evCustomer = $evData['Customer'] ?? ($evData['customer'] ?? ($event['Customer'] ?? []));
    $evMeta = $evData['metadata'] ?? ($evData['Metadata'] ?? ($event['metadata'] ?? []));
    $evAmount = (float)($event['Amount'] ?? ($evData['amount'] ?? ($evData['Amount'] ?? 0)));
    if ($evAmount <= 0) $evAmount = (float)($event['amount'] ?? 0);
    $fbFromMeta = [
        'fbp' => is_array($evMeta) ? ($evMeta['fbp'] ?? null) : null,
        'fbc' => is_array($evMeta) ? ($evMeta['fbc'] ?? null) : null,
        'url' => is_array($evMeta) ? ($evMeta['url'] ?? null) : null,
    ];
    $trackingFromMeta = utmifyNormalizarTracking(is_array($evMeta) ? [
        'utm_source' => $evMeta['utm_source'] ?? null,
        'utm_campaign' => $evMeta['utm_campaign'] ?? null,
        'utm_medium' => $evMeta['utm_medium'] ?? null,
        'utm_content' => $evMeta['utm_content'] ?? null,
        'utm_term' => $evMeta['utm_term'] ?? null,
        'fbclid' => $evMeta['fbclid'] ?? null,
        'xcod' => $evMeta['xcod'] ?? null,
        'src' => $evMeta['src'] ?? null,
        'sck' => $evMeta['sck'] ?? null,
    ] : null);

    $pedido = utmifyCarregarPedido($orderId) ?? [
        'orderId' => $orderId,
        'platform' => (string)(utmifyConfig()['platform'] ?? 'Rastreioo'),
        'createdAt' => gmdate('Y-m-d H:i:s'),
        'valorCentavos' => $evAmount > 0 ? (int)round($evAmount * 100) : ((int)round(((float)($event['Amount'] ?? 0)) * 100) ?: 6161),
        'customer' => [
            'name' => is_array($evCustomer) ? (string)($evCustomer['Name'] ?? ($evCustomer['name'] ?? 'Cliente')) : 'Cliente',
            'email' => is_array($evCustomer) ? (string)($evCustomer['Email'] ?? ($evCustomer['email'] ?? 'cliente@email.com')) : 'cliente@email.com',
            'phone' => is_array($evCustomer) ? (string)($evCustomer['Phone'] ?? ($evCustomer['phone'] ?? null)) : null,
            'document' => is_array($evCustomer) ? (string)(($evCustomer['Document']['Number'] ?? ($evCustomer['document'] ?? null)) ?? null) : null,
        ],
        'trackingParameters' => $trackingFromMeta,
        'meta' => array_filter([
            'fbp' => $fbFromMeta['fbp'],
            'fbc' => $fbFromMeta['fbc'],
            'event_source_url' => $fbFromMeta['url'],
        ], fn($v) => $v !== null && $v !== ''),
    ];

    $paidAt = (string)($event['PaidAt'] ?? '');
    $approvedDate = null;
    if ($paidAt !== '' && $paidAt !== '0001-01-01T00:00:00') {
        $timestamp = strtotime($paidAt);
        if ($timestamp !== false) {
            $approvedDate = gmdate('Y-m-d H:i:s', $timestamp);
        }
    }
    if ($approvedDate === null) {
        $approvedDate = gmdate('Y-m-d H:i:s');
    }

    marcarPedidoPagoUtmify($orderId, $pedido, $approvedDate);
    $pedidoAtual = utmifyCarregarPedido($orderId) ?? $pedido;
    if (!metaCapiJaEnviado($pedidoAtual)) {
        $capiRes = metaCapiEnviarPurchase($pedidoAtual, $orderId, $approvedDate);
        if (!empty($capiRes['success'])) {
            metaCapiMarcarEnviado($orderId);
        }
        $processed = !empty($capiRes['success']);
        $detail = 'utmify+meta fired';
    } else {
        $processed = true;
        $detail = 'already_sent';
    }
}

echo json_encode([
    'ok' => true,
    'processed' => $processed,
    'detail' => $detail,
    'orderId' => $orderId,
    'status' => $statusRaw,
    'kv' => utmifyKvEnabled() ? 'enabled' : 'file',
    'time' => gmdate('Y-m-d H:i:s'),
], JSON_UNESCAPED_UNICODE);
