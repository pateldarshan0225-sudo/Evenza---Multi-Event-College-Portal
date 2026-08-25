/**
 * Evenza - Tab-Isolated Authentication JavaScript Helper
 * Manages sessionStorage.evenza_tab_id and synchronizes with PHP via Cookie & X-Tab-ID Header
 */
(function () {
    'use strict';

    // 1. Initialize or retrieve unique tab_id for this browser tab
    function getOrInitTabId() {
        let tabId = sessionStorage.getItem('evenza_tab_id');
        if (!tabId || typeof tabId !== 'string' || tabId.length < 16) {
            if (window.crypto && typeof window.crypto.randomUUID === 'function') {
                tabId = window.crypto.randomUUID();
            } else {
                tabId = 'tab_' + Math.random().toString(36).substring(2, 15) + Date.now().toString(36);
            }
            sessionStorage.setItem('evenza_tab_id', tabId);
        }
        return tabId;
    }

    const currentTabId = getOrInitTabId();

    // 2. Keep Cookie in sync for standard PHP GET/POST requests
    function syncTabCookie() {
        try {
            document.cookie = "evenza_tab_id=" + encodeURIComponent(currentTabId) + "; path=/; SameSite=Lax";
        } catch (e) {}
    }

    syncTabCookie();

    // Sync on focus, click, and visibility change
    window.addEventListener('focus', syncTabCookie);
    window.addEventListener('click', syncTabCookie);
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'visible') {
            syncTabCookie();
        }
    });

    // 3. Expose global helper function
    window.getEvenzaTabId = function() {
        return currentTabId;
    };

    // 4. Auto-inject hidden input into all forms on submit
    document.addEventListener('submit', function (e) {
        syncTabCookie();
        const form = e.target;
        if (form && form.tagName === 'FORM') {
            let hiddenInput = form.querySelector('input[name="evenza_tab_id"]');
            if (!hiddenInput) {
                hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'evenza_tab_id';
                form.appendChild(hiddenInput);
            }
            hiddenInput.value = currentTabId;
        }
    }, true);

    // 5. Intercept Fetch API requests to attach X-Tab-ID header
    if (window.fetch) {
        const originalFetch = window.fetch;
        window.fetch = function (resource, init) {
            syncTabCookie();
            init = init || {};
            init.headers = init.headers || {};

            if (init.headers instanceof Headers) {
                init.headers.set('X-Tab-ID', currentTabId);
            } else if (Array.isArray(init.headers)) {
                init.headers.push(['X-Tab-ID', currentTabId]);
            } else {
                init.headers['X-Tab-ID'] = currentTabId;
            }

            return originalFetch.call(this, resource, init);
        };
    }

    // 6. Intercept XMLHttpRequest (for jQuery.ajax, $.post, $.get, etc.)
    if (window.XMLHttpRequest) {
        const originalOpen = XMLHttpRequest.prototype.open;
        XMLHttpRequest.prototype.open = function () {
            syncTabCookie();
            const result = originalOpen.apply(this, arguments);
            try {
                this.setRequestHeader('X-Tab-ID', currentTabId);
            } catch (err) {}
            return result;
        };
    }
})();
