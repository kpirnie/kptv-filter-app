const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

// intercept XHR
if (typeof XMLHttpRequest !== 'undefined' && csrfToken) {
    const _open = XMLHttpRequest.prototype.open;
    XMLHttpRequest.prototype.open = function (...args) {
        this._method = args[0];
        return _open.apply(this, args);
    };
    const _send = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.send = function (...args) {
        if (this._method?.toUpperCase() === 'POST') {
            this.setRequestHeader('X-CSRF-TOKEN', csrfToken);
            this.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        }
        return _send.apply(this, args);
    };
}

// intercept fetch
if (typeof window.fetch !== 'undefined' && csrfToken) {
    const _fetch = window.fetch;
    window.fetch = function (input, init = {}) {
        const method = (init.method || 'GET').toUpperCase();
        if (method === 'POST') {
            init.headers = init.headers || {};
            if (init.headers instanceof Headers) {
                if (!init.headers.has('X-CSRF-TOKEN')) {
                    init.headers.set('X-CSRF-TOKEN', csrfToken);
                }
                init.headers.set('X-Requested-With', 'XMLHttpRequest');
            } else {
                // leave an existing csrf header alone (kp-datatables sends its own)
                const hasCsrf = Object.keys(init.headers).some((k) => k.toLowerCase() === 'x-csrf-token');
                if (!hasCsrf) {
                    init.headers['X-CSRF-TOKEN'] = csrfToken;
                }
                init.headers['X-Requested-With'] = 'XMLHttpRequest';
            }

            // also inject into FormData body if present
            if (init.body instanceof FormData) {
                init.body.append('csrf', csrfToken);
            }
        }
        return _fetch.call(this, input, init);
    };
}