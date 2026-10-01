/**
 * Run a binder once per element, marking it with the given attribute.
 *
 * Turbo stream renders swap nodes in and out of the document, so components that
 * (re)initialize an existing subtree must be able to run their binding again to
 * pick up the new nodes without stacking duplicate listeners on the ones that
 * survived.
 *
 * @param {Element} element element to bind to; must support attributes
 * @param {string} attr marker attribute, conventionally `data-<name>-bound`
 * @param {function(Element): void} bind called at most once per element
 * @returns {void}
 */
export function bindOnce(element, attr, bind) {
    if (element.getAttribute(attr) === '1') {
        return;
    }
    element.setAttribute(attr, '1');
    bind(element);
}

/**
 * Registered document delegates, keyed by namespace.
 *
 * A document-level delegate must be installed exactly once, but the Alpine
 * component instance behind it is replaced on every page load and Turbo visit.
 * Each entry therefore holds both the handler that was installed and the
 * instance it currently dispatches to, so registering again is just a swap of
 * that instance.
 *
 * @type {Map<string, { handle: function(Event, object): void, instance: object }>}
 */
const documentDelegates = new Map();

/**
 * Register a document-level delegated click handler under a namespace.
 *
 * The handler is installed on first call and never again. Every later call only
 * swaps the instance it dispatches to, which is what makes it safe for Alpine
 * components whose `init()` runs again after a Turbo visit: a naive
 * `addEventListener` per init would stack handlers and fire duplicate toasts.
 *
 * @param {string} namespace unique key for this delegate, e.g. `alerts-list`
 * @param {object} instance object the delegate should dispatch to
 * @param {function(Event, object): void} handle invoked with the event and the live instance
 * @returns {void}
 */
export function registerDocumentDelegate(namespace, instance, handle) {
    var delegate = documentDelegates.get(namespace);

    if (delegate) {
        delegate.instance = instance;
        return;
    }

    documentDelegates.set(namespace, { handle: handle, instance: instance });

    document.addEventListener('click', function (event) {
        var target = documentDelegates.get(namespace);
        if (!target || !event.target || !event.target.closest) return;

        target.handle.call(target.instance, event, target.instance);
    });
}
