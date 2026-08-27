(function () {
    'use strict';

    var TRACKING_KEYS = ['src', 'sck', 'utm_source', 'utm_campaign', 'utm_medium', 'utm_content', 'utm_term'];
    var IGNORED_VALUES = {
        organic: true,
        '(none)': true,
        none: true,
        direct: true,
        undefined: true,
        null: true
    };

    function readParam(name) {
        try {
            return new URLSearchParams(window.location.search).get(name) || '';
        } catch (e) {
            return '';
        }
    }

    function readCookie(name) {
        try {
            var escaped = name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1');
            var match = document.cookie.match(new RegExp('(?:^|; )' + escaped + '=([^;]*)'));
            return match ? decodeURIComponent(match[1]) : '';
        } catch (e) {
            return '';
        }
    }

    function readStorage(key) {
        try {
            return localStorage.getItem(key) || '';
        } catch (e) {
            return '';
        }
    }

    function normalizeTrackingValue(value) {
        var normalized = String(value || '').trim();
        if (!normalized) {
            return '';
        }
        if (IGNORED_VALUES[normalized.toLowerCase()]) {
            return '';
        }
        return normalized;
    }

    function captureUtmsFromUrl() {
        var stored = {};
        try {
            stored = JSON.parse(sessionStorage.getItem('rastreioo_utms') || '{}');
        } catch (e) {
            stored = {};
        }

        TRACKING_KEYS.forEach(function (key) {
            var value = normalizeTrackingValue(
                readParam(key)
                || readCookie(key)
                || readCookie('utmify_' + key)
                || stored[key]
                || readStorage('utmify_' + key)
                || readStorage(key)
            );
            if (value) {
                stored[key] = value;
            }
        });

        try {
            if (Object.keys(stored).length) {
                sessionStorage.setItem('rastreioo_utms', JSON.stringify(stored));
            }
        } catch (e) {}
    }

    function getTrackingParameters() {
        var out = {};
        var saved = {};
        try {
            saved = JSON.parse(sessionStorage.getItem('rastreioo_utms') || '{}');
        } catch (e) {
            saved = {};
        }

        TRACKING_KEYS.forEach(function (key) {
            var value = normalizeTrackingValue(
                readParam(key)
                || saved[key]
                || readCookie(key)
                || readCookie('utmify_' + key)
                || readStorage('utmify_' + key)
                || readStorage(key)
            );
            out[key] = value || null;
        });
        return out;
    }

    function appendTrackingToForm(form) {
        if (!form) {
            return;
        }
        var params = getTrackingParameters();
        TRACKING_KEYS.forEach(function (key) {
            if (!params[key]) {
                return;
            }
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

    captureUtmsFromUrl();

    function trackMeta(eventName, params) {
        if (typeof window.fbq !== 'function') {
            return;
        }
        window.fbq('track', eventName, params || {});
    }

    window.RastreiooTracking = {
        getTrackingParameters: getTrackingParameters,
        appendTrackingToForm: appendTrackingToForm,

        trackInitiateCheckout: function (valorCentavos) {
            var value = Number(valorCentavos || 0) / 100;
            if (!value || value <= 0) {
                return;
            }
            trackMeta('InitiateCheckout', {
                value: value,
                currency: 'BRL',
                content_name: 'Taxa de liberacao de encomenda',
                num_items: 1
            });
        },

        trackPurchase: function (orderId, valorCentavos) {
            if (!orderId) {
                return;
            }
            var key = 'rastreioo_purchase_' + orderId;
            try {
                if (sessionStorage.getItem(key) === '1') {
                    return;
                }
                sessionStorage.setItem(key, '1');
            } catch (e) {}

            var value = Number(valorCentavos || 0) / 100;
            trackMeta('Purchase', {
                value: value,
                currency: 'BRL',
                content_name: 'Taxa de liberacao de encomenda',
                content_type: 'product',
                num_items: 1,
                order_id: String(orderId)
            });
        }
    };
})();
