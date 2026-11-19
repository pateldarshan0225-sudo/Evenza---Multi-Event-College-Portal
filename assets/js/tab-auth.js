/** Per-tab request context. Authentication never depends on a shared tab cookie. */
(function () {
    'use strict';
    if (window.__evenzaTabAuthReady) return;
    window.__evenzaTabAuthReady = true;
    const key = 'evenza_tab_id';
    const valid = value => typeof value === 'string' && /^[a-zA-Z0-9_-]{16,64}$/.test(value);
    const currentUrl = new URL(location.href);
    let stored;
    try { stored = sessionStorage.getItem(key); } catch (_) {}
    const supplied = currentUrl.searchParams.get(key);
    const currentTabId = valid(supplied) ? supplied : valid(stored) ? stored : (
        window.crypto && typeof crypto.randomUUID === 'function' ? crypto.randomUUID() :
        'tab_' + Array.from(crypto.getRandomValues(new Uint8Array(16)), value => value.toString(16).padStart(2, '0')).join('')
    );
    try { sessionStorage.setItem(key, currentTabId); } catch (_) {}
    window.getEvenzaTabId = () => currentTabId;

    // A refresh sends this explicit context before PHP chooses an account.
    if (supplied !== currentTabId) {
        currentUrl.searchParams.set(key, currentTabId);
        location.replace(currentUrl.href);
        return;
    }

    const scriptUrl = new URL(document.currentScript.src, location.href);
    const appPath = scriptUrl.pathname.replace(/assets\/js\/tab-auth\.js$/, '');
    function internalUrl(value) {
        try {
            const url = new URL(value, location.href);
            return url.origin === location.origin && url.pathname.startsWith(appPath) ? url : null;
        } catch (_) { return null; }
    }
    function prepareLink(link) {
        const raw = link.getAttribute('href');
        if (!raw || raw.startsWith('#') || link.hasAttribute('download')) return;
        const url = internalUrl(raw);
        if (!url || (url.pathname === location.pathname && url.search === location.search && url.hash)) return;
        url.searchParams.set(key, currentTabId);
        link.href = url.href;
    }
    function prepareForm(form) {
        const action = internalUrl(form.getAttribute('action') || location.href);
        if (!action) return;
        let field = form.querySelector('input[name="evenza_tab_id"]');
        if (!field) { field = document.createElement('input'); field.type = 'hidden'; field.name = key; form.appendChild(field); }
        field.value = currentTabId;
        if ((form.method || 'get').toLowerCase() === 'post') {
            action.searchParams.set(key, currentTabId);
            form.action = action.href;
        }
    }
    function prepareTree(root) {
        if (root.matches?.('a[href]')) prepareLink(root);
        if (root.matches?.('form')) prepareForm(root);
        root.querySelectorAll?.('a[href]').forEach(prepareLink);
        root.querySelectorAll?.('form').forEach(prepareForm);
    }
    function ready() {
        prepareTree(document);
        new MutationObserver(records => records.forEach(record => record.addedNodes.forEach(node => {
            if (node.nodeType === Node.ELEMENT_NODE) prepareTree(node);
        }))).observe(document.documentElement, {childList:true, subtree:true});
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ready); else ready();
    document.addEventListener('click', event => { const link = event.target.closest?.('a[href]'); if (link) prepareLink(link); }, true);
    document.addEventListener('submit', event => { if (event.target instanceof HTMLFormElement) prepareForm(event.target); }, true);
    const nativeSubmit = HTMLFormElement.prototype.submit;
    HTMLFormElement.prototype.submit = function () { prepareForm(this); return nativeSubmit.call(this); };

    if (window.fetch) {
        const nativeFetch = window.fetch;
        window.fetch = function (resource, init) {
            const url = internalUrl(resource instanceof Request ? resource.url : resource);
            if (!url) return nativeFetch.call(this, resource, init);
            const options = {...(init || {})};
            const headers = new Headers(options.headers || (resource instanceof Request ? resource.headers : undefined));
            headers.set('X-Tab-ID', currentTabId); options.headers = headers;
            return nativeFetch.call(this, resource, options);
        };
    }
    const nativeOpen = XMLHttpRequest.prototype.open;
    XMLHttpRequest.prototype.open = function (method, url) {
        const result = nativeOpen.apply(this, arguments);
        if (internalUrl(url)) this.setRequestHeader('X-Tab-ID', currentTabId);
        return result;
    };
})();
