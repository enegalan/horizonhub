const FORM_DRAWER_FRAME_ID = 'form-drawer';
const FORM_DRAWER_SHELL_ID = 'form-drawer-shell';
const FORM_DRAWER_OPEN = 'form-drawer-shell--open';
const FORM_DRAWER_CLOSING = 'form-drawer-shell--closing';
const FORM_DRAWER_CLOSE_MS = 300; // Same as the CSS transition duration. See .form-drawer-panel and .form-drawer-backdrop transitions.

var formDrawerCloseTimer = null;

/**
 * Check if the form drawer is open.
 *
 * @returns {boolean} True if the form drawer is open, false otherwise.
 */
function formDrawerIsOpen() {
    const shell = document.getElementById(FORM_DRAWER_SHELL_ID);
    return Boolean(shell && shell.classList.contains(FORM_DRAWER_OPEN));
}

/**
 * Clear the form drawer.
 */
function clearFormDrawer() {
    if (formDrawerCloseTimer) {
        window.clearTimeout(formDrawerCloseTimer);
        formDrawerCloseTimer = null;
    }

    const frame = document.getElementById(FORM_DRAWER_FRAME_ID);
    if (frame) {
        frame.innerHTML = '';
        frame.removeAttribute('src');
    }

    const shell = document.getElementById(FORM_DRAWER_SHELL_ID);
    if (shell) {
        shell.classList.remove(FORM_DRAWER_OPEN, FORM_DRAWER_CLOSING);
    }
}

/**
 * Close the form drawer.
 *
 * @param {boolean} immediate Whether to close the form drawer immediately.
 */
function closeFormDrawer(immediate) {
    if (!formDrawerIsOpen()) {
        clearFormDrawer();
        return;
    }

    if (
        immediate
        || (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)
    ) {
        clearFormDrawer();
        return;
    }

    const shell = document.getElementById(FORM_DRAWER_SHELL_ID);
    if (!shell || shell.classList.contains(FORM_DRAWER_CLOSING)) {
        return;
    }

    shell.classList.add(FORM_DRAWER_CLOSING);
    formDrawerCloseTimer = window.setTimeout(clearFormDrawer, FORM_DRAWER_CLOSE_MS);
}

/**
 * Handle the turbo:load event.
 * 
 * Clears the form drawer if it is not open.
 *
 * @returns {void}
 */
document.addEventListener('turbo:load', function () {
    const frame = document.getElementById(FORM_DRAWER_FRAME_ID);
    if (frame && frame.getAttribute('src')) {
        return;
    }

    if (!formDrawerIsOpen()) {
        clearFormDrawer();
    }
});

/**
 * Handle the turbo:before-visit event.
 *
 * Clears the form drawer before visiting a new page.
 *
 * @returns {void}
 */
document.addEventListener('turbo:before-visit', function () {
    clearFormDrawer();
});

/**
 * Handle the turbo:submit-end event.
 *
 * Perform a turbo.visit with the response HTML if the drawer form is submitted successfully and the response is redirected.
 *
 * @returns {void}
 */
document.addEventListener('turbo:submit-end', function (event) {
    if (!event.target || event.target.tagName !== 'FORM' || event.target.getAttribute('data-turbo-frame') !== FORM_DRAWER_FRAME_ID) {
        return;
    }

    const fetchResponse = event.detail?.fetchResponse;

    if (!event.detail?.success || !fetchResponse?.redirected) {
        return;
    }

    fetchResponse.responseHTML.then(function (html) {
        if (!html || !window.Turbo?.visit) {
            return;
        }

        window.Turbo.visit(fetchResponse.location, {
            action: 'replace',
            response: {
                statusCode: fetchResponse.statusCode,
                responseHTML: html,
                redirected: fetchResponse.redirected,
            },
        });
    });
});

/**
 * Handle the turbo:frame-load event.
 * 
 * Opens the form drawer if the frame is loaded and the response is not empty.
 *
 * @returns {void}
 */
document.addEventListener('turbo:frame-load', function (event) {
    if (!event.target || event.target.id !== FORM_DRAWER_FRAME_ID) {
        return;
    }

    if (!event.target.innerHTML.trim()) {
        closeFormDrawer(true);
        return;
    }

    const shell = document.getElementById(FORM_DRAWER_SHELL_ID);
    if (shell) {
        shell.classList.remove(FORM_DRAWER_CLOSING);
        shell.classList.add(FORM_DRAWER_OPEN);
    }

    if (window.Alpine && typeof window.Alpine.initTree === 'function') {
        window.Alpine.initTree(event.target);
    }
});

/**
 * Handle the click event.
 *
 * Closes the form drawer if the target is a close button element.
 *
 * @returns {void}
 */
document.addEventListener('click', function (event) {
    if (event.target.closest('[data-form-drawer-close]')) {
        event.preventDefault();
        closeFormDrawer();
    }
});

/**
 * Handle the keydown event.
 *
 * Closes the form drawer if the key is Escape.
 *
 * @returns {void}
 */
document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && formDrawerIsOpen()) {
        closeFormDrawer();
    }
});
