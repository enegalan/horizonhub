import { isHotReloadEnabled } from "../lib/sse";

/**
 * Read selected values from hidden inputs under a filter root.
 * @param {HTMLElement} root
 * @param {string} inputName e.g. service_tag[] or service_id[]
 * @returns {string[]}
 */
function readHiddenInputValues(root, inputName) {
    var values = [];
    root.querySelectorAll('input[name="' + inputName + '"]').forEach(function (input) {
        if (input.value) {
            values.push(String(input.value));
        }
    });
    return values;
}

/**
 * Sync service tag (and optional service) filters to the URL and refresh SSE streams.
 * @returns {void}
 */
export function initServiceTagFilters() {
    if (typeof window === "undefined" || typeof document === "undefined") {
        return;
    }

    var root = document.querySelector("[data-service-tag-filter]");
    if (!root) {
        return;
    }

    function syncUrlAndStream() {
        var url = new URL(window.location.href);
        url.searchParams.delete("service_tag");
        url.searchParams.delete("service_tag[]");
        url.searchParams.delete("service_tag_mode");

        readHiddenInputValues(root, "service_tag[]")
            .sort()
            .forEach(function (tag) {
                url.searchParams.append("service_tag[]", tag);
            });

        var serviceInputNames = ["service_id[]", "serviceFilter[]", "queue_services[]"];
        serviceInputNames.forEach(function (inputName) {
            var paramBase = inputName.replace("[]", "");
            url.searchParams.delete(paramBase);
            url.searchParams.delete(inputName);
            readHiddenInputValues(root, inputName)
                .sort()
                .forEach(function (id) {
                    url.searchParams.append(inputName, id);
                });
        });

        window.history.replaceState({}, "", url.toString());

        if (isHotReloadEnabled() && typeof window.__horizonHubRefreshStreamReconnect === "function") {
            window.__horizonHubRefreshStreamReconnect();
        }
    }

    root.addEventListener("change", function (e) {
        if (e.detail && Array.isArray(e.detail.values)) {
            syncUrlAndStream();
        }
    });
}
