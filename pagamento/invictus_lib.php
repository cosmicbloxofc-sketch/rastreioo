<?php
declare(strict_types=1);

/**
 * InvictusPay — integração PIX (API v1)
 * Docs: https://app.invictuspayv2.com.br/docs
 * Base: https://api.invictuspayv2.com.br/api/v1
 * Auth: header X-Api-Key: sk_...
 * Valores monetários SEMPRE em centavos.
 */

function invictusConfig(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $file = __DIR__ . '/invictus_config.php';
        $cfg = is_file($file) ? (require $file) : [];
    }
    return is_array($cfg) ? $cfg : [];
}

function invictusApiKey(): string
{
    $cfg = invictusConfig();
    return trim((string)($cfg['api_key'] ?? (getenv('INVICTUS_API_KEY') ?: '')));
}

function invictusApiBase(): string
{
    $cfg = invictusConfig();
    $base = trim((string)($cfg['api_base'] ?? 'https://api.invictuspayv2.com.br/api/v1'));
    return rtrim($base, '/');
}

function invictusPostbackUrl(): string
{
    $env = trim((string)(getenv('INVICTUS_POSTBACK_URL') ?: ''));
    if ($env !== '') return $env;

    $cfg = invictusConfig();
    $fixed = trim((string)($cfg['postback_url'] ?? ''));
    if ($fixed !== '') return $fixed;

    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '') {
        return 'https://rastreioo-phi.vercel.app/api/webhook_invictus.php';
    }
    if (str_contains($host, 'vercel.app')) {
        // /pagamento/*.php e arquivo estatico no Vercel -> POST 405.
        // O endpoint real (function) e /api/webhook_invictus.php.
        return 'https://' . $host . '/api/webhook_invictus.php';
    }
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'] ?? '/pagamento')), '/');
    if ($dir === '' || $dir === '/' || $dir === '/api') $dir = '/pagamento';

    return 'https://' . $host . $dir . '/webhook_invictus.php';
}

function invictusRequest(string $method, string $path, ?array $body = null): array
{
    $key = invictusApiKey();
    if ($key === '') {
        return ['success' => false, 'error' => 'INVICTUS_API_KEY nao configurada'];
    }

    $url = invictusApiBase() . '/' . ltrim($path, '/');
    $ch = curl_init();
    $headers = [
        'Accept: application/json',
        'X-Api-Key: ' . $key,
    ];
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
    }

    $opts = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    curl_setopt_array($ch, $opts);

    $raw = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['success' => false, 'error' => $err !== '' ? $err : 'Falha de conexao', 'httpCode' => $http];
    }

    $decoded = json_decode((string)$raw, true);
    $ok = $http >= 200 && $http <= 299 && (($decoded['success'] ?? true) !== false);

    return [
        'success' => $ok,
        'httpCode' => $http,
        'response' => is_array($decoded) ? $decoded : $raw,
        'error' => $ok ? null : (string)(
            $decoded['message']
            ?? ($decoded['error'] ?? null)
            ?? (is_array($decoded['errors'] ?? null) ? implode(' ', array_map(
                fn($v) => is_array($v) ? implode(' ', $v) : (string)$v,
                $decoded['errors']
            )) : 'Erro InvictusPay')
        ),
    ];
}

/**
 * Cria cobrança PIX e devolve formato compatível com o front.
 */
function invictusCriarPix(
    int $valorCentavos,
    string $nome,
    string $email,
    string $telefone,
    string $cpf,
    ?array $extraMeta = null,
    string $descricao = 'Curso Mesa Posta'
): array {
    $doc = preg_replace('/\D/', '', $cpf);
    $phone = preg_replace('/\D/', '', $telefone);

    $payload = [
        'amount' => $valorCentavos,
        'paymentMethod' => 'pix',
        'customer' => [
            'name' => $nome !== '' ? $nome : 'Cliente',
            'email' => $email !== '' ? strtolower(trim($email)) : 'cliente@email.com',
            'document' => $doc,
            'phone' => $phone,
        ],
        'items' => [[
            'description' => $descricao,
            'quantity' => 1,
            'amount' => $valorCentavos,
        ]],
        'description' => $descricao,
        'reference' => 'rastreioo_' . bin2hex(random_bytes(8)),
        'pix' => [
            'expirationInSeconds' => 900,
        ],
    ];

    $postback = invictusPostbackUrl();
    if ($postback !== '') {
        $payload['postbackUrl'] = $postback;
    }

    $res = invictusRequest('POST', '/transactions', $payload);
    if (!($res['success'] ?? false)) {
        return [
            'success' => false,
            'error' => (string)($res['error'] ?? 'Erro ao criar PIX InvictusPay'),
            'details' => $res['response'] ?? null,
            'httpCode' => $res['httpCode'] ?? 0,
        ];
    }

    $data = $res['response']['data'] ?? [];
    $qr = (string)($data['pix']['qr_code'] ?? '');
    $id = (string)($data['id'] ?? '');

    if ($id === '' || $qr === '') {
        return ['success' => false, 'error' => 'Resposta InvictusPay sem id/qr_code', 'details' => $data];
    }

    return [
        'success' => true,
        'transaction_id' => $id,
        'pix_code' => $qr,
        'pix_qrcode_url' => '',
        'pix_favorecido' => invictusExtrairFavorecido($qr),
        'status' => (string)($data['status'] ?? 'pending'),
        'provider' => 'invictus',
        'postback_url' => $postback,
        'reference' => (string)($data['reference'] ?? ($payload['reference'] ?? '')),
    ];
}

function invictusExtrairFavorecido(string $emv): string
{
    $i = 0;
    $len = strlen($emv);
    while ($i + 4 <= $len) {
        $tag = substr($emv, $i, 2);
        $size = (int)substr($emv, $i + 2, 2);
        $i += 4;
        if ($size <= 0 || $i + $size > $len) break;
        $value = substr($emv, $i, $size);
        $i += $size;
        if ($tag === '59') {
            return trim($value);
        }
    }
    return '';
}

function invictusConsultar(string $transactionId): array
{
    $id = trim($transactionId);
    if ($id === '') {
        return ['success' => false, 'paid' => false, 'error' => 'transactionId vazio'];
    }

    $res = invictusRequest('GET', '/transactions/' . rawurlencode($id));
    if (!($res['success'] ?? false)) {
        return [
            'success' => false,
            'paid' => false,
            'error' => (string)($res['error'] ?? 'Erro ao consultar'),
            'httpCode' => $res['httpCode'] ?? 0,
        ];
    }

    $data = $res['response']['data'] ?? [];
    $status = strtoupper((string)($data['status'] ?? ''));
    $paid = in_array($status, ['PAID', 'APPROVED', 'COMPLETED'], true);

    return [
        'success' => true,
        'paid' => $paid,
        'status' => $status,
        'httpCode' => $res['httpCode'] ?? 0,
        'response' => $data,
        'paid_at' => $data['paid_at'] ?? null,
        'customer' => $data['customer'] ?? null,
        'amount' => $data['amount'] ?? null,
        'reference' => $data['reference'] ?? null,
    ];
}

function invictusNormalizarStatus(string $status): string
{
    $s = strtoupper(trim($status));
    if (in_array($s, ['PAID', 'APPROVED', 'COMPLETED'], true)) return 'paid';
    if (in_array($s, ['EXPIRED', 'CANCELLED', 'FAILED', 'REFUSED'], true)) return 'failed';
    if ($s === '') return 'waiting_payment';
    return 'waiting_payment';
}
