// Pixel Utmify Centralizado - Rastreioo
(function () {
    'use strict';

    var pixelConfig = {
        pixelId: '917972162691039',
        cdnUrl: 'https://cdn.utmify.com.br/scripts/pixel/pixel.js'
    };

    var UTM_KEYS = [
        'utm_source', 'utm_campaign', 'utm_medium', 'utm_content', 'utm_term',
        'xcod', 'src', 'fbclid', 'sck', 'subid'
    ];

    var API_TRACKING_KEYS = ['src', 'sck', 'utm_source', 'utm_campaign', 'utm_medium', 'utm_content', 'utm_term'];

    window.pixelId = pixelConfig.pixelId;

    window.utmify = window.utmify || function () {
        (window.utmify.q = window.utmify.q || []).push(arguments);
    };

    function readParam(name) {
        try {
            return new URLSearchParams(window.location.search).get(name) || '';
        } catch (e) {
            return '';
        }
    }

    function obterParametrosUTM() {
        var params = new URLSearchParams(window.location.search);
        var result = {};

        UTM_KEYS.forEach(function (key) {
            var value = params.get(key);
            if (value) {
                try {
                    localStorage.setItem(key, value);
                } catch (e) {}
            }
            try {
                result[key] = value || localStorage.getItem(key) || '';
            } catch (e) {
                result[key] = value || '';
            }
        });

        return result;
    }

    function identificarPagina() {
        var path = window.location.pathname.toLowerCase();
        if (path.indexOf('/pagamento') !== -1) return 'pagamento';
        if (path.indexOf('index5') !== -1) return 'checkout';
        if (path.indexOf('rastreio') !== -1) return 'encomenda';
        if (path.indexOf('consulta') !== -1) return 'consulta';
        return 'consulta';
    }

    function montarDadosRastreamento(extra) {
        var utmParams = obterParametrosUTM();
        var pagina = identificarPagina();
        var cpf = readParam('cpf');

        var dados = {
            page: pagina,
            utm_source: utmParams.utm_source,
            utm_campaign: utmParams.utm_campaign,
            utm_medium: utmParams.utm_medium,
            utm_content: utmParams.utm_content,
            utm_term: utmParams.utm_term,
            fbclid: utmParams.fbclid,
            src: utmParams.src,
            sck: utmParams.sck
        };

        if (cpf) {
            dados.cpf = cpf;
        }

        if (extra && typeof extra === 'object') {
            Object.keys(extra).forEach(function (key) {
                dados[key] = extra[key];
            });
        }

        return dados;
    }

    function trackUtmify(evento, extra) {
        window.utmify('track', evento, montarDadosRastreamento(extra));
    }

    function carregarPixel(callback) {
        if (document.querySelector('script[src*="cdn.utmify.com.br/scripts/pixel/pixel.js"]')) {
            if (typeof callback === 'function') {
                callback();
            }
            return;
        }

        if (document.querySelector('script[data-rastreioo-pixel="1"]')) {
            if (typeof callback === 'function') {
                callback();
            }
            return;
        }

        var script = document.createElement('script');
        script.type = 'text/javascript';
        script.async = true;
        script.defer = true;
        script.src = pixelConfig.cdnUrl;
        script.setAttribute('data-rastreioo-pixel', '1');

        script.onload = function () {
            if (typeof callback === 'function') {
                callback();
            }
        };

        script.onerror = function () {
            console.error('Erro ao carregar pixel Utmify');
        };

        document.head.appendChild(script);
    }

    function rastrearPagina() {
        var pagina = identificarPagina();
        var evento = 'ViewContent';

        if (pagina === 'pagamento' || pagina === 'checkout') {
            evento = 'InitiateCheckout';
        }

        trackUtmify(evento);
    }

    function getTrackingParameters() {
        var utmParams = obterParametrosUTM();
        var out = {};

        API_TRACKING_KEYS.forEach(function (key) {
            var value = (utmParams[key] || '').trim();
            out[key] = value || null;
        });

        return out;
    }

    function appendTrackingToForm(form) {
        if (!form) return;

        var params = getTrackingParameters();
        API_TRACKING_KEYS.forEach(function (key) {
            if (!params[key]) return;

            var existing = form.querySelector('input[name="' + key + '"]');
            if (existing) {
                existing.value = params[key];
                return;
            }

            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = params[key];
            form.appendChild(input);
        });
    }

    function rastrearEventos() {
        var btnConsultar = document.getElementById('btn-consultar')
            || document.getElementById('btnAbrirConsulta');
        if (btnConsultar) {
            btnConsultar.addEventListener('click', function () {
                trackUtmify('InitiateCheckout', { page: 'consulta_click' });
            });
        }

        var btnPagar = document.getElementById('btn-pagar')
            || document.getElementById('btnCheckout');
        if (btnPagar) {
            btnPagar.addEventListener('click', function () {
                trackUtmify('InitiateCheckout', { page: 'pagar_click' });
            });
        }

        document.querySelectorAll('form').forEach(function (form) {
            form.addEventListener('submit', function () {
                appendTrackingToForm(form);
            });
        });
    }

    window.RastreiooTracking = {
        getTrackingParameters: getTrackingParameters,
        appendTrackingToForm: appendTrackingToForm,

        trackInitiateCheckout: function (valorCentavos) {
            var value = Number(valorCentavos || 0) / 100;
            trackUtmify('InitiateCheckout', {
                value: value > 0 ? value : undefined,
                currency: 'BRL'
            });
        },

        trackPurchase: function (orderId, valorCentavos) {
            if (!orderId) return;

            var key = 'rastreioo_purchase_' + orderId;
            try {
                if (sessionStorage.getItem(key) === '1') return;
                sessionStorage.setItem(key, '1');
            } catch (e) {}

            var value = Number(valorCentavos || 0) / 100;
            trackUtmify('Purchase', {
                value: value > 0 ? value : undefined,
                currency: 'BRL',
                order_id: String(orderId)
            });
        }
    };

    function iniciar() {
        obterParametrosUTM();
        carregarPixel(function () {
            rastrearPagina();
        });
        rastrearEventos();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();
