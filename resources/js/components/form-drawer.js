import { refreshStream } from '../lib/sse';

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

document.addEventListener('turbo:load', function () {
    const frame = getFormDrawerFrame();
    if (frame && frame.getAttribute('src')) {
        return;
    }

    if (!formDrawerIsOpen()) {
        clearFormDrawer();
    }
});

document.addEventListener('turbo:before-visit', function () {
    clearFormDrawer();
});

document.addEventListener('turbo:frame-load', function (event) {
    if (!event.target || event.target.id !== FORM_DRAWER_FRAME_ID) {
        return;
    }

    if (!event.target.innerHTML.trim()) {
        closeFormDrawer(true);
        return;
    }

    const shell = getFormDrawerShell();
    if (shell) {
        shell.classList.remove(FORM_DRAWER_CLOSING);
        shell.classList.add(FORM_DRAWER_OPEN);
    }

    if (window.Alpine && typeof window.Alpine.initTree === 'function') {
        window.Alpine.initTree(event.target);
    }
});

document.addEventListener('turbo:submit-end', function (event) {
    if (!event.target || event.target.getAttribute('data-turbo-frame') !== FORM_DRAWER_FRAME_ID || !event.detail?.success) {
        return;
    }

    closeFormDrawer(true);

    refreshStream();
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
