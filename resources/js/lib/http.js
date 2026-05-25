
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
                var message = 'An error occurred while making the request';
                if (error && error.response && error.response.data && error.response.data.message) {
                    message = error.response.data.message;
                }
                window.toast.error(message);
                throw error;
            });
    }
    return {
        get: function (url, config) { return request('get', url, null, config); },
        post: function (url, data, config) { return request('post', url, data, config); },
        delete: function (url, config) { return request('delete', url, null, config); },
    };
}

/**
 * CSRF token and HTTP helpers for horizon API.
 * @returns {string}
 */
function getCsrfToken() {
    var token = document.querySelector('meta[name="csrf-token"]');
    return token ? token.getAttribute('content') : '';
}
