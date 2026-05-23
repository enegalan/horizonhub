const FORM_DRAWER_FRAME_ID = 'form-drawer';
const FORM_DRAWER_SHELL_ID = 'form-drawer-shell';
const FORM_DRAWER_OPEN = 'form-drawer-shell--open';
const FORM_DRAWER_CLOSING = 'form-drawer-shell--closing';
const FORM_DRAWER_CLOSE_MS = 300; // Same as the CSS transition duration. See .form-drawer-panel and .form-drawer-backdrop transitions.

var formDrawerCloseTimer = null;

function getFormDrawerShell() {
    return document.getElementById(FORM_DRAWER_SHELL_ID);
}

function getFormDrawerFrame() {
    return document.getElementById(FORM_DRAWER_FRAME_ID);
}

function formDrawerIsOpen() {
    const shell = getFormDrawerShell();
    return Boolean(shell && shell.classList.contains(FORM_DRAWER_OPEN));
}

function clearFormDrawer() {
    if (formDrawerCloseTimer) {
        window.clearTimeout(formDrawerCloseTimer);
        formDrawerCloseTimer = null;
    }

    const frame = getFormDrawerFrame();
    if (frame) {
        frame.innerHTML = '';
        frame.removeAttribute('src');
    }

    const shell = getFormDrawerShell();
    if (shell) {
        shell.classList.remove(FORM_DRAWER_OPEN, FORM_DRAWER_CLOSING);
    }
}

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

    const shell = getFormDrawerShell();
    if (!shell || shell.classList.contains(FORM_DRAWER_CLOSING)) {
        return;
    }

    shell.classList.add(FORM_DRAWER_CLOSING);
    formDrawerCloseTimer = window.setTimeout(clearFormDrawer, FORM_DRAWER_CLOSE_MS);
}

function openFormDrawerFromQuery() {
    const params = new URLSearchParams(window.location.search);
    var drawerPath = params.get('drawer');
    if (!drawerPath) {
        return false;
    }

    if (drawerPath.charAt(0) !== '/') {
        drawerPath = '/' + drawerPath;
    }

    params.delete('drawer');
    var search = params.toString();
    history.replaceState(
        null,
        '',
        window.location.pathname + (search ? '?' + search : '') + window.location.hash,
    );

    var frame = getFormDrawerFrame();
    if (!frame) {
        return false;
    }

    frame.src = drawerPath;

    return true;
}

document.addEventListener('turbo:load', function () {
    if (!openFormDrawerFromQuery() && !formDrawerIsOpen()) {
        clearFormDrawer();
    }
});

document.addEventListener('turbo:before-visit', function () {
    clearFormDrawer();
});

document.addEventListener('turbo:frame-load', function (event) {
    if (!event.target || event.target.id !== FORM_DRAWER_FRAME_ID || !event.target.innerHTML.trim()) {
        return;
    }

    var shell = getFormDrawerShell();
    if (shell) {
        shell.classList.remove(FORM_DRAWER_CLOSING);
        shell.classList.add(FORM_DRAWER_OPEN);
    }

    if (window.Alpine && typeof window.Alpine.initTree === 'function') {
        window.Alpine.initTree(event.target);
    }
});

document.addEventListener('click', function (event) {
    if (event.target.closest('[data-form-drawer-close]')) {
        event.preventDefault();
        closeFormDrawer();
    }
});

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && formDrawerIsOpen()) {
        closeFormDrawer();
    }
});
