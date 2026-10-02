/**
 * Make a GET request.
 * @param {string} url
 * @param {object} config
 * @returns {Promise<object>}
 */
export function httpGet(url, config) {
    return httpRequest('get', url, null, config);
}

/**
 * Make a POST request.
 * @param {string} url
 * @param {object} data
 * @param {object} config
 * @returns {Promise<object>}
 */
export function httpPost(url, data, config) {
    return httpRequest('post', url, data, config);
}

/**
 * Make a DELETE request.
 * @param {string} url
 * @param {object} config
 * @returns {Promise<object>}
 */
export function httpDelete(url, config) {
    return httpRequest('delete', url, null, config);
}

/**
 * Make a request.
 * @param {string} method
 * @param {string} url
 * @param {object} data
 * @param {object} config
 * @returns {Promise<object>}
 */
export function httpRequest(method, url, data, config) {
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

/**
 * CSRF token and HTTP helpers for horizon API.
 * @returns {string}
 */
function getCsrfToken() {
    var token = document.querySelector('meta[name="csrf-token"]');
    return token ? token.getAttribute('content') : '';
}
