(function () {
    'use strict';

    var REDIRECT_URL = 'https://shopee.com.br/';

    function limparCookiesArmazenamento() {
        try {
            var host = location.hostname;
            var partes = host.split('.');
            var cookies = document.cookie ? document.cookie.split(';') : [];
            var i;
            var nome;
            var exp = 'expires=Thu, 01 Jan 1970 00:00:00 GMT';
            var preservedSession = null;
            var preservedLocal = {};
            var utmKeys = [
                'utm_source', 'utm_campaign', 'utm_medium', 'utm_content', 'utm_term',
                'xcod', 'src', 'fbclid', 'sck', 'subid'
            ];

            try {
                preservedSession = sessionStorage.getItem('rastreioo_utms');
            } catch (e) {}

            try {
                utmKeys.forEach(function (key) {
                    var value = localStorage.getItem(key);
                    if (value) {
                        preservedLocal[key] = value;
                    }
                });
            } catch (e) {}

            for (i = 0; i < cookies.length; i++) {
                nome = cookies[i].split('=')[0].replace(/^\s+|\s+$/g, '');
                if (!nome) continue;
                // NUNCA apagar cookies do Facebook (_fbp/_fbc) e de tracking FB (_rstr_fb) - essenciais para atribuicao
                if (nome.indexOf('_fbp') === 0 || nome.indexOf('_fbc') === 0 || nome.indexOf('_fb') === 0) continue;
                if (nome === '_rstr_fb') continue;
                document.cookie = nome + '=;' + exp + ';path=/';
                document.cookie = nome + '=;' + exp + ';path=/;domain=' + host;
                if (partes.length > 1) {
                    document.cookie = nome + '=;' + exp + ';path=/;domain=.' + partes.slice(-2).join('.');
                }
            }

            localStorage.clear();
            sessionStorage.clear();

            if (preservedSession) {
                sessionStorage.setItem('rastreioo_utms', preservedSession);
            }

            try {
                Object.keys(preservedLocal).forEach(function (key) {
                    localStorage.setItem(key, preservedLocal[key]);
                });
            } catch (e) {}
        } catch (e) {}
    }

    limparCookiesArmazenamento();

    function isMobileDevice() {
        var ua = navigator.userAgent || '';
        if (/Android|webOS|iPhone|iPod|BlackBerry|IEMobile|Opera Mini|Mobile/i.test(ua)) {
            return true;
        }
        if (/iPad/i.test(ua)) {
            return true;
        }
        return navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1;
    }

    if (!isMobileDevice()) {
        window.location.replace(REDIRECT_URL);
        return;
    }

    document.addEventListener('contextmenu', function (e) {
        e.preventDefault();
        return false;
    });

    document.addEventListener('dragstart', function (e) {
        if (e.target && e.target.tagName === 'IMG') {
            e.preventDefault();
            return false;
        }
    });

    document.addEventListener('keydown', function (e) {
        var key = e.key || '';
        var code = e.keyCode || e.which;
        var ctrl = e.ctrlKey || e.metaKey;
        var shift = e.shiftKey;

        if (code === 123 || key === 'F12') {
            e.preventDefault();
            return false;
        }

        if (ctrl && shift && (
            code === 73 || code === 74 || code === 67 ||
            key === 'I' || key === 'J' || key === 'C' ||
            key === 'i' || key === 'j' || key === 'c'
        )) {
            e.preventDefault();
            return false;
        }

        if (ctrl && (code === 85 || key === 'u' || key === 'U')) {
            e.preventDefault();
            return false;
        }

        if (ctrl && (code === 83 || key === 's' || key === 'S')) {
            e.preventDefault();
            return false;
        }
    });
})();
