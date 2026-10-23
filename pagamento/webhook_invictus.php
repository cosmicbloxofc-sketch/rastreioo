<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/utmify_lib.php';
require_once __DIR__ . '/meta_capi_lib.php';

$payload = file_get_contents('php://input');
$data = date('d/m/Y H:i:s');
@file_put_contents(
    __DIR__ . '/log_invictus_webhook.txt',
    '[' . $data . '] ' . (string)$payload . PHP_EOL . PHP_EOL,
    FILE_APPEND
);

$event = json_decode((string)$payload, true);
if (!is_array($event)) {
    http_response_code(200);
    echo json_encode(['received' => true, 'ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

$eventName = strtoupper(trim((string)(
    $_SERVER['HTTP_X_WEBHOOK_EVENT']
    ?? ($event['event'] ?? '')
)));

$tx = $event['transaction'] ?? [];
if (!is_array($tx)) $tx = [];

$orderId = (string)($tx['id'] ?? '');
$statusRaw = strtoupper((string)($tx['status'] ?? ''));
$isPaid = str_contains($eventName, 'CHARGE_PAID')
    || in_array($statusRaw, ['PAID', 'APPROVED', 'COMPLETED'], true);

$processed = false;
$detail = 'no_paid';

if ($orderId !== '' && $isPaid) {
    $evCustomer = is_array($tx['customer'] ?? null) ? $tx['customer'] : [];
    $evAmount = (float)($tx['amount'] ?? 0);

    $pedido = utmifyCarregarPedido($orderId) ?? [
        'orderId' => $orderId,
        'provider' => 'invictus',
        'platform' => (string)(utmifyConfig()['platform'] ?? 'Rastreioo'),
        'createdAt' => (string)($tx['created_at'] ?? gmdate('Y-m-d H:i:s')),
        'valorCentavos' => $evAmount > 0 ? (int)round($evAmount) : 6161,
        'customer' => [
            'name' => (string)($evCustomer['name'] ?? 'Cliente'),
            'email' => (string)($evCustomer['email'] ?? 'cliente@email.com'),
            'phone' => $evCustomer['phone'] ?? null,
            'document' => $evCustomer['document'] ?? null,
        ],
        'trackingParameters' => utmifyNormalizarTracking(null),
    ];

    $paidAt = (string)($tx['paid_at'] ?? '');
    $approvedDate = null;
    if ($paidAt !== '' && $paidAt !== '0001-01-01T00:00:00') {
        $ts = strtotime($paidAt);
        if ($ts !== false) $approvedDate = gmdate('Y-m-d H:i:s', $ts);
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

http_response_code(200);
echo json_encode([
    'received' => true,
    'ok' => true,
    'processed' => $processed,
    'detail' => $detail,
    'orderId' => $orderId,
    'event' => $eventName,
    'status' => $statusRaw,
    'kv' => utmifyKvEnabled() ? 'enabled' : 'file',
    'time' => gmdate('Y-m-d H:i:s'),
], JSON_UNESCAPED_UNICODE);
