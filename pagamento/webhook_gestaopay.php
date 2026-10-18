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

$orderId = (string)($event['Id'] ?? ($event['id'] ?? ''));
$statusRaw = strtoupper((string)($event['Status'] ?? ($event['status'] ?? '')));
$isPaid = in_array($statusRaw, ['PAID', 'APPROVED', 'COMPLETED'], true);

if ($orderId !== '' && $isPaid) {
    $pedido = utmifyCarregarPedido($orderId) ?? [
        'orderId' => $orderId,
        'platform' => (string)(utmifyConfig()['platform'] ?? 'Rastreioo'),
        'createdAt' => gmdate('Y-m-d H:i:s'),
        'valorCentavos' => (int)round(((float)($event['Amount'] ?? 0)) * 100),
        'customer' => [
            'name' => 'Cliente',
            'email' => 'cliente@email.com',
            'phone' => null,
            'document' => null,
        ],
        'trackingParameters' => utmifyNormalizarTracking(null),
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
    }
}

echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
