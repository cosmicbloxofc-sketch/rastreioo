// Pixel Utmify + Meta Purchase - Rastreioo
(function () {
    'use strict';

    var pixelConfig = {
        pixelId: '1794883931600243',
        cdnUrl: 'https://cdn.utmify.com.br/scripts/pixel/pixel.js'
    };

    var UTM_KEYS = [
        'utm_source', 'utm_campaign', 'utm_medium', 'utm_content', 'utm_term',
        'xcod', 'src', 'fbclid', 'sck', 'subid'
    ];

    var API_TRACKING_KEYS = ['src', 'sck', 'utm_source', 'utm_campaign', 'utm_medium', 'utm_content', 'utm_term', 'fbclid', 'xcod'];

    window.pixelId = pixelConfig.pixelId;

    window.utmify = window.utmify || function () {
        (window.utmify.q = window.utmify.q || []).push(arguments);
    };

    // --- Meta Pixel (facebook) - mesmo ID da Utmify ---
    function carregarMetaPixel() {
        var PIXEL_ID = pixelConfig.pixelId;
        if (!PIXEL_ID) return;

        if (window.fbq) {
            try { window.fbq('init', PIXEL_ID); window.fbq('track', 'PageView'); } catch(e){}
            return;
        }

        !function(f,b,e,v,n,t,s){
            if(f.fbq) return;
            n=f.fbq=function(){ n.callMethod ? n.callMethod.apply(n,arguments) : n.queue.push(arguments); };
            if(!f._fbq) f._fbq=n;
            n.push=n; n.loaded=!0; n.version='2.0'; n.queue=[];
            t=b.createElement(e); t.async=!0; t.src=v;
            s=b.getElementsByTagName(e)[0]; s.parentNode.insertBefore(t,s);
        }(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');

        try { window.fbq('init', PIXEL_ID); window.fbq('track', 'PageView'); } catch(e){}
    }

    function trackMeta(eventName, params) {
        if (typeof window.fbq !== 'function') return;
        try {
            if (params && params.order_id) {
                window.fbq('track', eventName, params, { eventID: String(params.order_id) });
            } else {
                window.fbq('track', eventName, params || {});
            }
        } catch (e) {}
    }

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

        // Prioridade: cookies persistentes FB (_rstr_fb) -> URL -> localStorage
        var fbParams = {};
        try { fbParams = (typeof window.__getFbParams === 'function') ? (window.__getFbParams() || {}) : {}; } catch(e) {}

        UTM_KEYS.forEach(function (key) {
            var value = params.get(key);
            if (value) {
                try {
                    localStorage.setItem(key, value);
                } catch (e) {}
            }
            var fallbackFb = (key === 'fbclid' || key === 'fbc') ? (fbParams[key] || '') : '';
            try {
                result[key] = value || fallbackFb || localStorage.getItem(key) || '';
            } catch (e) {
                result[key] = value || fallbackFb || '';
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
        // espelha ViewContent/InitiateCheckout no Meta tambem
        if (evento === 'ViewContent') {
            trackMeta('ViewContent', { content_name: 'Consulta taxa encomenda' });
        } else {
            trackMeta('InitiateCheckout', { content_name: 'Taxa de liberacao de encomenda', num_items: 1, currency: 'BRL' });
        }
    }

    function getTrackingParameters() {
        var utmParams = obterParametrosUTM();
        var out = {};

        API_TRACKING_KEYS.forEach(function (key) {
            var value = (utmParams[key] || '').trim();
            out[key] = value || null;
        });

        // Garantir que fbclid sempre vai mesmo se UTM_KEYS falhar (fallback direto da URL)
        if (!out.fbclid) {
            try {
                var directFbclid = new URLSearchParams(window.location.search).get('fbclid');
                if (directFbclid) out.fbclid = directFbclid.trim();
            } catch(e) {}
        }

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
                trackMeta('InitiateCheckout', { content_name: 'Botao consultar', num_items: 1, currency: 'BRL' });
            });
        }

        var btnPagar = document.getElementById('btn-pagar')
            || document.getElementById('btnCheckout');
        if (btnPagar) {
            btnPagar.addEventListener('click', function () {
                trackUtmify('InitiateCheckout', { page: 'pagar_click' });
                trackMeta('InitiateCheckout', { content_name: 'Botao pagar', num_items: 1, currency: 'BRL' });
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
            var extra = {
                value: value > 0 ? value : undefined,
                currency: 'BRL'
            };
            trackUtmify('InitiateCheckout', extra);
            trackMeta('InitiateCheckout', {
                value: value > 0 ? value : undefined,
                currency: 'BRL',
                content_name: 'Taxa de liberacao de encomenda',
                num_items: 1
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
            var utmExtra = {
                value: value > 0 ? value : undefined,
                currency: 'BRL',
                order_id: String(orderId)
            };
            trackUtmify('Purchase', utmExtra);

            // Meta Pixel Purchase - com deduplicacao via eventID=orderId
            trackMeta('Purchase', {
                value: value > 0 ? value : undefined,
                currency: 'BRL',
                content_name: 'Taxa de liberacao de encomenda',
                content_type: 'product',
                num_items: 1,
                order_id: String(orderId)
            });
        }
    };

    function iniciar() {
        obterParametrosUTM();
        carregarMetaPixel();
        carregarPixel(function () {
            rastrearPagina();
        });
        // se Utmify ja carregado via pixel-loader, rastreia imediato tambem
        if (document.querySelector('script[src*="cdn.utmify.com.br/scripts/pixel/pixel.js"]')) {
            try { rastrearPagina(); } catch(e){}
        }
        rastrearEventos();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();
