const FORM_DRAWER_FRAME_ID = 'form-drawer';
const FORM_DRAWER_SHELL_ID = 'form-drawer-shell';
const FORM_DRAWER_OPEN = 'form-drawer-shell--open';
const FORM_DRAWER_CLOSING = 'form-drawer-shell--closing';
const FORM_DRAWER_CLOSE_MS = 300; // Same as the CSS transition duration. See .form-drawer-panel and .form-drawer-backdrop transitions.
const FORM_DRAWER_DISCARD_EVENT = 'form-drawer-discard-request';

var formDrawerCloseTimer = null;
var formDrawerCleanSignature = null;

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
 * Serialize the values of the form inside the drawer.
 *
 * @returns {string|null} The serialized form values, or null when there is no form.
 */
function formDrawerSignature() {
    const frame = document.getElementById(FORM_DRAWER_FRAME_ID);
    const form = frame && frame.querySelector(`form[data-turbo-frame="${FORM_DRAWER_FRAME_ID}"]`);

    if (!form) {
        return null;
    }

    return JSON.stringify(
        Array.from(new FormData(form), ([name, value]) => [name, value.name ? `${value.name}:${value.size}` : value]),
    );
}

/**
 * Check if the drawer form holds changes that were never submitted.
 *
 * @returns {boolean} True if the form differs from the values it was loaded with.
 */
function formDrawerIsDirty() {
    return formDrawerCleanSignature !== null && formDrawerSignature() !== formDrawerCleanSignature;
}

/**
 * Enable or disable the drawer save button depending on whether the form has changes.
 *
 * Buttons flagged with data-form-drawer-submit-blocked stay disabled because of a
 * server side condition.
 *
 * @returns {void}
 */
function formDrawerSyncSubmit() {
    const frame = document.getElementById(FORM_DRAWER_FRAME_ID);
    const submit = frame && frame.querySelector('[data-form-drawer-submit]');

    if (!submit) {
        return;
    }

    submit.disabled = submit.dataset.formDrawerSubmitBlocked === 'true' || !formDrawerIsDirty();
}

/**
 * Run a callback once Alpine has rendered its pending updates.
 *
 * The rows added or removed by Alpine components are rendered on a later tick than
 * the event that triggered them, so the form has to be read afterwards.
 *
 * @param {Function} callback The callback to run.
 *
 * @returns {void}
 */
function formDrawerAfterAlpineTick(callback) {
    if (window.Alpine && typeof window.Alpine.nextTick === 'function') {
        window.Alpine.nextTick(callback);
        return;
    }

    callback();
}

/**
 * Keep the save button in sync whenever the form may have changed.
 *
 * The signature comparison is authoritative, so listening broadly is safe: clicking
 * a field that was not edited recomputes an unchanged signature. Clicks are needed
 * because the rows Alpine adds or removes have no input event of their own.
 *
 * @returns {void}
 */
function formDrawerSyncSubmitOnEdit(event) {
    if (event.target.closest(`form[data-turbo-frame="${FORM_DRAWER_FRAME_ID}"]`)) {
        formDrawerAfterAlpineTick(formDrawerSyncSubmit);
    }
}

document.addEventListener('input', formDrawerSyncSubmitOnEdit);
document.addEventListener('change', formDrawerSyncSubmitOnEdit);
document.addEventListener('click', formDrawerSyncSubmitOnEdit);

/**
 * Clear the form drawer.
 */
function clearFormDrawer() {
    if (formDrawerCloseTimer) {
        window.clearTimeout(formDrawerCloseTimer);
        formDrawerCloseTimer = null;
    }

    formDrawerCleanSignature = null;

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

    if (!immediate && formDrawerIsDirty()) {
        window.dispatchEvent(new CustomEvent(FORM_DRAWER_DISCARD_EVENT));
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

    // The rows Alpine renders on init only exist on a later tick, so the values the
    // form was loaded with can only be read afterwards.
    formDrawerAfterAlpineTick(function () {
        formDrawerCleanSignature = formDrawerSignature();
        formDrawerSyncSubmit();
    });
});

/**
 * Handle the click event.
 *
 * Closes the form drawer if the target is a close or discard button element.
 *
 * @returns {void}
 */
document.addEventListener('click', function (event) {
    if (event.target.closest('[data-form-drawer-discard]')) {
        event.preventDefault();
        formDrawerCleanSignature = formDrawerSignature();
        closeFormDrawer();
        return;
    }

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
