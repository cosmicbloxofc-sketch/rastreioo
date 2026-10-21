<?php
declare(strict_types=1);

function metaCapiConfig(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require __DIR__ . '/meta_capi_config.php';
    }
    return $cfg;
}

function metaCapiHash(?string $value): ?string
{
    if ($value === null) return null;
    $v = strtolower(trim($value));
    if ($v === '') return null;
    // email: normaliza, telefone remove nao-digitos, demais trim+lower
    return hash('sha256', $v);
}

function metaCapiHashEmail(?string $email): ?string
{
    if ($email === null) return null;
    $v = strtolower(trim($email));
    if ($v === '' || strpos($v, '@') === false) return null;
    return hash('sha256', $v);
}

function metaCapiHashPhone(?string $phone): ?string
{
    if ($phone === null) return null;
    $d = preg_replace('/\D/', '', $phone);
    if ($d === null || $d === '') return null;
    // Normaliza BR: sem 55, com DDD, 11 digitos com 9. Se vier com 55+11, vira 11. Se 10, insere 9.
    if (strlen($d) === 13 && str_starts_with($d, '55')) $d = substr($d, 2);
    if (strlen($d) === 10) $d = substr($d, 0, 2) . '9' . substr($d, 2);
    if (strlen($d) < 10) return null;
    return hash('sha256', $d);
}

function metaCapiHashCpf(?string $cpf): ?string
{
    if ($cpf === null) return null;
    $d = preg_replace('/\D/', '', $cpf);
    if ($d === null || $d === '' || strlen($d) !== 11) return null;
    return hash('sha256', $d);
}

function metaCapiGetClientIp(): string
{
    $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    foreach ($keys as $k) {
        $v = $_SERVER[$k] ?? '';
        if ($v === '') continue;
        // X-Forwarded-For pode ter lista
        $v = trim(explode(',', $v)[0] ?? '');
        if (filter_var($v, FILTER_VALIDATE_IP)) return $v;
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

function metaCapiExtractFbpFbc(array $order): array
{
    // fbp/fbc podem vir salvos no pedido (coletados no front no ato da criacao)
    $fbp = $order['meta']['fbp'] ?? ($order['fbp'] ?? null);
    $fbc = $order['meta']['fbc'] ?? ($order['fbc'] ?? null);

    // Fallback: _fbc vem da URL atual (fbclid) - ja salvo em trackingParameters se houver?
    // Tentar cookies atuais se pedido ainda esta na mesma sessao (webhook nao tem cookie, mas gestaopay_check tem)
    if ($fbp === null || $fbp === '') {
        $fbp = $_COOKIE['_fbp'] ?? null;
    }
    if ($fbc === null || $fbc === '') {
        $fbc = $_COOKIE['_fbc'] ?? null;
    }

    // Se ainda nao tem fbc mas tem fbclid na URL salva, montar clic_id
    if (($fbc === null || $fbc === '') && isset($order['trackingParameters']['fbclid'])) {
        $fbclid = $order['trackingParameters']['fbclid'] ?? '';
        if ($fbclid !== null && $fbclid !== '') {
            $ts = time();
            $fbc = 'fb.1.' . $ts . '.' . $fbclid;
        }
    }

    return ['fbp' => $fbp ?: null, 'fbc' => $fbc ?: null];
}

function metaCapiEnviarPurchase(array $order, string $eventId, ?string $eventTime = null): array
{
    $cfg = metaCapiConfig();
    $pixelId = trim((string)($cfg['pixel_id'] ?? ''));
    $token = trim((string)($cfg['access_token'] ?? ''));

    if ($pixelId === '') {
        return ['success' => false, 'skipped' => true, 'error' => 'pixel_id vazio em meta_capi_config.php'];
    }
    if ($token === '') {
        return ['success' => false, 'skipped' => true, 'error' => 'access_token vazio - configure meta_capi_config.php com o Token da Conversions API'];
    }

    $version = trim((string)($cfg['api_version'] ?? 'v20.0'));
    $testCode = $cfg['test_event_code'] ?? null;
    $testCode = is_string($testCode) && trim($testCode) !== '' ? trim($testCode) : null;

    $valorCentavos = (int)($order['valorCentavos'] ?? 0);
    if ($valorCentavos <= 0) {
        $valorCentavos = (int)round(((float)($order['valorReais'] ?? 0)) * 100);
    }
    $valorReais = $valorCentavos / 100;

    $customer = $order['customer'] ?? [];
    $nome = (string)($customer['name'] ?? '');
    $email = (string)($customer['email'] ?? '');
    $phone = (string)($customer['phone'] ?? '');
    $cpf = (string)($customer['document'] ?? ($customer['cpf'] ?? ''));
    $addr = $order['customer_address'] ?? $order['address'] ?? [];

    // Quebrar nome em fn/ln para matching avancado
    $fn = null; $ln = null;
    if (trim($nome) !== '' && strtolower(trim($nome)) !== 'cliente') {
        $parts = preg_split('/\s+/', trim($nome));
        if (is_array($parts) && count($parts) >= 1) {
            $fn = $parts[0];
            $ln = count($parts) > 1 ? end($parts) : null;
        }
    }

    // user_data hasheado
    $ud = [];
    $hEm = metaCapiHashEmail($email !== '' ? $email : null);
    $hPh = metaCapiHashPhone($phone !== '' ? $phone : null);
    $hCpf = metaCapiHashCpf($cpf !== '' ? $cpf : null);
    $hFn = ($fn !== null && strtolower($fn) !== 'cliente') ? metaCapiHash($fn) : null;
    $hLn = ($ln !== null && strtolower($ln) !== 'cliente') ? metaCapiHash($ln) : null;

    if ($hEm !== null) $ud['em'] = [$hEm];
    if ($hPh !== null) $ud['ph'] = [$hPh];
    // CPF como external_id hasheado ja ajuda matching BR
    if ($hCpf !== null) $ud['external_id'] = [$hCpf];
    if ($hFn !== null) $ud['fn'] = [$hFn];
    if ($hLn !== null) $ud['ln'] = [$hLn];

    // Endereco hasheado (opcional mas recomendado quando enviar cidade/estado)
    $city = $addr['city'] ?? null;
    $state = $addr['state'] ?? null;
    $zip = $addr['zipCode'] ?? ($addr['zip'] ?? null);
    $country = $addr['country'] ?? 'BR';
    if (is_string($city) && trim($city) !== '') {
        $h = metaCapiHash($city);
        if ($h !== null) $ud['ct'] = [$h];
    }
    if (is_string($state) && trim($state) !== '') {
        $h = metaCapiHash($state);
        if ($h !== null) $ud['st'] = [$h];
    }
    if (is_string($zip) && trim($zip) !== '') {
        $d = preg_replace('/\D/', '', $zip);
        if ($d !== '' && $d !== null) {
            $h = hash('sha256', $d);
            $ud['zp'] = [$h];
        }
    }
    $ud['country'] = [hash('sha256', strtolower(trim((string)$country)))];

    // fbp/fbc
    $fb = metaCapiExtractFbpFbc($order);
    if (!empty($fb['fbp'])) $ud['fbp'] = $fb['fbp'];
    if (!empty($fb['fbc'])) $ud['fbc'] = $fb['fbc'];

    // IP e User-Agent (nao hasheado)
    $ip = $order['meta']['client_ip_address'] ?? ($order['client_ip_address'] ?? null);
    if ($ip === null || $ip === '') $ip = metaCapiGetClientIp();
    $ua = $order['meta']['client_user_agent'] ?? ($order['client_user_agent'] ?? null);
    if ($ua === null || $ua === '') $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $ud['client_ip_address'] = $ip;
    $ud['client_user_agent'] = $ua;

    // source_url
    $sourceUrl = $order['meta']['event_source_url'] ?? ($order['event_source_url'] ?? null);
    if ($sourceUrl === null || $sourceUrl === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'https://';
        $host = $_SERVER['HTTP_HOST'] ?? 'rastreioo.local';
        $sourceUrl = $scheme . $host . '/pagamento/';
    }

    $eventTimeTs = $eventTime !== null ? (int)strtotime($eventTime) : time();
    if ($eventTimeTs === false || $eventTimeTs <= 0) $eventTimeTs = time();

    $customData = [
        'currency' => 'BRL',
        'value' => round($valorReais, 2),
        'content_name' => 'Taxa de liberacao de encomenda',
        'content_type' => 'product',
        'contents' => [['id' => 'taxa-liberacao-encomenda', 'quantity' => 1, 'item_price' => round($valorReais, 2)]],
        'num_items' => 1,
        'order_id' => (string)$order['orderId'],
    ];

    $payload = [
        'data' => [[
            'event_name' => 'Purchase',
            'event_time' => $eventTimeTs,
            'event_id' => (string)$eventId,
            'event_source_url' => (string)$sourceUrl,
            'action_source' => 'website',
            'user_data' => $ud,
            'custom_data' => $customData,
        ]],
    ];

    if ($testCode !== null) {
        $payload['test_event_code'] = $testCode;
    }

    $url = 'https://graph.facebook.com/' . rawurlencode($version) . '/' . rawurlencode($pixelId) . '/events?access_token=' . rawurlencode($token);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $raw = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    $decoded = json_decode((string)$raw, true);
    $ok = ($http >= 200 && $http <= 299) && ($err === '');

    // Deteccao de erro CAPI (ex: token invalido) mesmo com 200 pode vir error
    if (is_array($decoded) && isset($decoded['error'])) {
        $ok = false;
    }

    $result = [
        'success' => $ok,
        'httpCode' => $http,
        'response' => $decoded ?? $raw,
        'event_id' => (string)$eventId,
    ];
    if ($err !== '') $result['curl_error'] = $err;

    // Log dedicado CAPI
    $logLine = sprintf(
        "[%s] orderId=%s event_id=%s http=%d ok=%d em=%s ph=%s cpf=%s fbp=%s fbc=%s resp=%s\n",
        date('d/m/Y H:i:s'),
        $order['orderId'] ?? '-',
        $eventId,
        $http,
        $ok ? 1 : 0,
        $hEm !== null ? '1' : '0',
        $hPh !== null ? '1' : '0',
        $hCpf !== null ? '1' : '0',
        !empty($fb['fbp']) ? '1' : '0',
        !empty($fb['fbc']) ? '1' : '0',
        json_encode($decoded ?? $raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
    @file_put_contents(__DIR__ . '/log_meta_capi.txt', $logLine, FILE_APPEND);

    return $result;
}

function metaCapiMarcarEnviado(string $orderId): bool
{
    $dir = __DIR__ . '/orders';
    $path = $dir . '/' . preg_replace('/[^a-zA-Z0-9._-]/', '', $orderId) . '.json';
    $data = null;
    if (is_file($path)) {
        $raw = @file_get_contents($path);
        if ($raw !== false && $raw !== '') {
            $d = json_decode($raw, true);
            if (is_array($d)) $data = $d;
        }
    }
    if (!is_array($data)) {
        // tenta do KV (container diferente)
        $data = call_user_func('utmifyKvGet', $orderId);
    }
    if (!is_array($data)) return false;
    if (!empty($data['meta_capi_sent'])) return true;
    $data['meta_capi_sent'] = true;
    $data['meta_capi_sent_at'] = gmdate('Y-m-d H:i:s');
    @file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    @utmifyKvSet($orderId, json_encode($data, JSON_UNESCAPED_UNICODE));
    return true;
}

function metaCapiJaEnviado(array $order): bool
{
    return !empty($order['meta_capi_sent']);
}
