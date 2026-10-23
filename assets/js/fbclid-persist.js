(function () {
    'use strict';

    // Chave dos parametros FB no cookie
    var FB_PARAM_COOKIE = '_rstr_fb';
    // Validade: 30 dias em segundos
    var COOKIE_MAX_AGE = 30 * 24 * 60 * 60;

    function readParam(name) {
        try {
            return new URLSearchParams(window.location.search).get(name) || '';
        } catch (e) { return ''; }
    }

    function setCookie(name, value, maxAge) {
        try {
            var enc = encodeURIComponent(value);
            document.cookie = name + '=' + enc + ';max-age=' + maxAge + ';path=/;SameSite=Lax';
        } catch (e) {}
    }

    function getCookie(name) {
        try {
            var m = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '=([^;]*)'));
            return m ? decodeURIComponent(m[1]) : '';
        } catch (e) { return ''; }
    }

    // Capturar fbclid + fbc da URL agora (entrada Facebook) e guardar em cookie
    var fbclidUrl = readParam('fbclid');
    var fbcUrl    = readParam('fbc');

    if (fbclidUrl || fbcUrl) {
        // Se tem na URL, sobrescreve cookie (click mais recente)
        var existing = {};
        try { existing = JSON.parse(getCookie(FB_PARAM_COOKIE) || '{}'); } catch(e) {}
        if (!existing.fbclid || fbclidUrl) existing.fbclid = fbclidUrl || existing.fbclid;
        if (!existing.fbc    || fbcUrl)    existing.fbc    = fbcUrl    || existing.fbc;
        if (existing.fbclid || existing.fbc) {
            setCookie(FB_PARAM_COOKIE, JSON.stringify(existing), COOKIE_MAX_AGE);
        }
    }

    // Expor funcao para pixel-utmify.js / demais JS lerem o fbclid/fbc do cookie
    window.__getFbclid = function () {
        try { return JSON.parse(getCookie(FB_PARAM_COOKIE) || '{}').fbclid || ''; } catch(e) { return ''; }
    };
    window.__getFbc = function () {
        try { return JSON.parse(getCookie(FB_PARAM_COOKIE) || '{}').fbc || ''; } catch(e) { return ''; }
    };
    window.__getFbParams = function () {
        try { return JSON.parse(getCookie(FB_PARAM_COOKIE) || '{}'); } catch(e) { return {}; }
    };

    // Sync: se fbclid esta na URL mas fbc nao, montar fbc a partir de fbclid
    if (fbclidUrl && !fbcUrl) {
        var stored = {};
        try { stored = JSON.parse(getCookie(FB_PARAM_COOKIE) || '{}'); } catch(e) {}
        if (!stored.fbc && fbclidUrl) {
            stored.fbclid = fbclidUrl;
            stored.fbc = 'fb.1.' + Math.floor(Date.now() / 1000) + '.' + fbclidUrl;
            setCookie(FB_PARAM_COOKIE, JSON.stringify(stored), COOKIE_MAX_AGE);
        }
    }
})();
