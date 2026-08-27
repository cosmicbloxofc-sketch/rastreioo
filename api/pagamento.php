<?php
declare(strict_types=1);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// DEBUG mode - desabilitar em produção
$debug = getenv('DEBUG') === '1' || getenv('DEBUG') === 'true';
if ($debug) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

require_once __DIR__ . '/../consulta_receita_lib.php';
require_once __DIR__ . '/../_shared/cpf_bloqueio.php';

// CARREGA CONFIGURAÇÕES DE ENV
$expressPayApiKey = getenv('EXPRESSAY_API_KEY') ?: 'sk_test_placeholder';
$fusionPayApiKey = getenv('FUSIONPAY_API_KEY') ?: 'sk_test_placeholder';
$wintechPublicKey = getenv('WINTECH_PUBLIC_KEY') ?: 'test_public_key';
$wintechSecretKey = getenv('WINTECH_SECRET_KEY') ?: 'test_secret_key';
$wintechApiUrl = 'https://app.wintechpagamentos.com/api/v1/gateway/pix/receive';

$expressPayAuthHeader = "Basic " . base64_encode($expressPayApiKey);
$fusionPayAuthHeader = "Basic " . base64_encode($fusionPayApiKey);

// Carrega config GestãoPay
$gestaoPayConfig = [
    'public_key' => getenv('GESTAOPAY_PUBLIC_KEY') ?: '',
    'secret_key' => getenv('GESTAOPAY_SECRET_KEY') ?: '',
    'api_url' => 'https://api.gestaopayments.com/v1/payment-transaction/create',
];

$valorPadraoCentavos = 6161; // R$ 61,61 em centavos

// Utmify config
function utmifyConfig(): array {
    return [
        'platform' => 'Rastreioo',
    ];
}

function normalizarTelefone(string $telefone): string
{
    $digits = preg_replace('/\D/', '', $telefone);
    if ($digits === '') {
        return '11999999999';
    }
    if (strlen($digits) === 13 && str_starts_with($digits, '55')) {
        $digits = substr($digits, 2);
    }
    if (strlen($digits) === 10) {
        $digits = substr($digits, 0, 2) . '9' . substr($digits, 2);
    }
    if (strlen($digits) < 10) {
        return '11999999999';
    }
    return $digits;
}

function extrairErroGestaoPay($res, $fallback = 'Erro ao gerar PIX') {
    if (!is_array($res)) {
        return $fallback;
    }
    if (!empty($res['error_messages']) && is_array($res['error_messages'])) {
        foreach ($res['error_messages'] as $item) {
            if (is_array($item) && !empty($item['message'])) {
                return (string)$item['message'];
            }
        }
    }
    if (!empty($res['message'])) {
        return (string)$res['message'];
    }
    if (!empty($res['error']) && is_string($res['error'])) {
        return $res['error'];
    }
    return $fallback;
}

function gerarPixFusionPay($valor, $nome, $email, $telefone, $cpf) {
    global $gestaoPayConfig, $fusionPayAuthHeader;

    $publicKey = trim((string)($gestaoPayConfig['public_key'] ?? ''));
    $secretKey = trim((string)($gestaoPayConfig['secret_key'] ?? ''));
    $apiUrl = (string)($gestaoPayConfig['api_url'] ?? 'https://api.gestaopayments.com/v1/payment-transaction/create');

    if ($publicKey === '' || $secretKey === '') {
        return ['success' => false, 'error' => 'Credenciais GestaoPay não configuradas'];
    }

    $cpfLimpo = preg_replace('/\D/', '', (string)$cpf);
    $telefoneLimpo = normalizarTelefone((string)$telefone);
    $telefoneComDdi = '55' . $telefoneLimpo;

    $valorCentavos = (int)round(((float)$valor) * 100);
    $identifier = 'pedido_' . uniqid();

    $payload = [
        'amount' => $valorCentavos,
        'payment_method' => 'pix',
        'Customer' => [
            'Name' => $nome ?: 'Cliente',
            'Email' => $email ?: 'email@naoinformado.com',
            'Phone' => '+' . $telefoneComDdi,
            'Document' => [
                'Type' => 'cpf',
                'Number' => $cpfLimpo,
            ],
        ],
        'items' => [[
            'title' => 'Produto',
            'unit_price' => $valorCentavos,
            'quantity' => 1,
            'tangible' => false,
        ]],
        'metadata' => [
            'provider_name' => 'rastreioo',
            'external_id' => $identifier,
        ],
        'pix' => [
            'expires_in_days' => 1,
        ],
    ];

    $auth = 'Basic ' . base64_encode(trim($publicKey) . ':' . $secretKey);

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'authorization: ' . $auth,
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $response = curl_exec($curl);
    $httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    if ($curlError !== '') {
        return ['success' => false, 'error' => 'Erro de conexão', 'details' => $curlError];
    }

    $res = json_decode((string)$response, true);
    if ($httpCode < 200 || $httpCode > 201) {
        return [
            'success' => false,
            'error' => extrairErroGestaoPay($res),
            'response' => $res,
        ];
    }

    $root = $res['data'] ?? ($res['request'] ?? ($res['Request'] ?? $res));
    $pix = $root['pix'] ?? ($root['Pix'] ?? []);
    $pixCode = $pix['qr_code'] ?? ($pix['code'] ?? ($pix['Code'] ?? ($pix['qrcode'] ?? ($pix['QrCode'] ?? ($root['pixCode'] ?? ($root['emv'] ?? null))))));

    if (!$pixCode) {
        return ['success' => false, 'error' => 'Erro ao gerar PIX', 'response' => $res];
    }

    $pixQrBase64 = $pix['base64'] ?? ($pix['Base64'] ?? ($pix['image'] ?? ($root['qrCodeBase64'] ?? null)));
    $pixQr = $pixQrBase64 ? 'data:image/png;base64,' . $pixQrBase64 : null;
    $orderId = $root['id'] ?? ($root['transactionId'] ?? ($root['transaction_id'] ?? $identifier));
    $qrFallback = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($pixCode);

    return [
        'success' => true,
        'pix_code' => $pixCode,
        'pix_qrcode_url' => $pixQr ?: $qrFallback,
        'pix_favorecido' => null,
        'transaction_id' => $orderId,
        'provider' => 'gestaopay',
        'data' => $res,
    ];
}

// AÇÃO: Gerar PIX (wintech_new)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['act'] ?? '') === 'wintech_new') {
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);

    if (!$data || !isset($data["valor"])) {
        echo json_encode(["error" => "Dados inválidos"]);
        exit;
    }

    $nomeReal = $data["name"] ?? "Cliente";
    $emailReal = strtolower(trim((string)($data["email"] ?? "anonimo@email.com")));

    $bloqueio = cpf_bloqueio_verificar($data, 'rastreioo');
    if ($bloqueio !== null) {
        echo json_encode($bloqueio);
        exit;
    }

    $cpf = preg_replace('/\D/', '', $data["cpf"] ?? '');
    $telefoneReal = normalizarTelefone((string)($data['telefone'] ?? ($data['phone'] ?? '')));

    if (empty($cpf)) {
        echo json_encode(["error" => "CPF inválido"]);
        exit;
    }

    $valorCentavos = $valorPadraoCentavos;
    $valor = $valorCentavos / 100;

    $resultado = gerarPixFusionPay($valor, $nomeReal, $emailReal, $telefoneReal, $cpf);

    if (!($resultado["success"] ?? false)) {
        echo json_encode([
            "error" => $resultado["error"] ?? "Erro no pagamento",
            "detalhes" => $resultado
        ]);
        exit;
    }

    echo json_encode([
        "pixQrCode" => $resultado["pix_qrcode_url"] ?? "",
        "pixCode" => $resultado["pix_code"] ?? "",
        "pixFavorecido" => $resultado["pix_favorecido"] ?? "",
        "orderId" => $resultado["transaction_id"] ?? uniqid('pedido_'),
        "provider" => $resultado["provider"] ?? "gestaopay"
    ]);

    exit;
}

// AÇÃO: Rastrear CPF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['act'] ?? '') === 'rastrear') {
    $codigo = $_POST['codigo'] ?? '';

    if (empty($codigo)) {
        echo json_encode(["error" => "Código não informado"]);
        exit;
    }

    $cpfLimpo = preg_replace('/\D/', '', $codigo);
    $receita = consultarCpfReceita26($cpfLimpo);

    $dados = [
        "cpf" => $codigo,
        "nome" => $receita['nome'] ?? '',
        "sexo" => (intval(substr($codigo, -1)) % 2 == 0) ? "F" : "M",
        "dataNascimento" => $receita['data_nascimento'] ?? 'Não encontrado',
        "valor" => (string)$valorPadraoCentavos,
        "pixCode" => "",
        "pixQrCode" => "",
        "orderId" => "",
        "imagemComDados" => "assets/images/caixa.png"
    ];

    echo json_encode($dados);
    exit;
}

echo json_encode(["error" => "Ação inválida. Use ?act=wintech_new ou ?act=rastrear"]);
