/**
 * CSRF token and HTTP helpers for horizon API.
 * @returns {string}
 */
function getCsrfToken() {
    var token = document.querySelector('meta[name="csrf-token"]');
    return token ? token.getAttribute('content') : '';
}

/**
 * Default API error handler.
 * @param {Error} error
 * @returns {void}
 */
function defaultApiErrorHandler(error) {
    var message = 'Request failed';
    if (error && error.response && error.response.data && error.response.data.message) {
        message = error.response.data.message;
    }
    if (window.toast && window.toast.error) {
        window.toast.error(message);
    } else {
        alert(message);
    }
}

/**
 * Fetch the current page as HTML and parse into a document.
 * Internally used by SSE refresh stream.
 * @returns {Promise<Document|null>}
 */
export function fetchCurrentPageAsDocument() {
    var url = typeof window !== 'undefined' ? window.location.href : '';
    if (!url) {
        return Promise.resolve(null);
    }
    return fetch(url, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    }).then(function (response) {
        if (!response.ok) return null;
        return response.text();
    }).then(function (html) {
        if (!html) return null;
        var parser = new DOMParser();
        return parser.parseFromString(html, 'text/html');
    }).catch(function () {
        return null;
    });
}

/**
 * Create HTTP helpers.
 * @returns {{ get: function, post: function, delete: function }}
 */
export function createHttpHelpers() {
    /**
     * Make a request.
     * @param {string} method
     * @param {string} url
     * @param {object} data
     * @param {object} config
     * @returns {Promise<object>}
     */
    function request(method, url, data, config) {
        if (!window.axios) {
            return Promise.reject(new Error('axios is not available'));
        }
        var finalConfig = Object.assign(
            {
                method: method,
                url: url,
                data: data || {},
                headers: { 'X-CSRF-TOKEN': getCsrfToken() },
            },
            config || {}
        );
        return window.axios(finalConfig)
            .then(function (response) { return response.data; })
            .catch(function (error) {
                defaultApiErrorHandler(error);
                throw error;
            });
    }
    return {
        get: function (url, config) { return request('get', url, null, config); },
        post: function (url, data, config) { return request('post', url, data, config); },
        delete: function (url, config) { return request('delete', url, null, config); },
    };
}
