function pad(n) {
    return n < 10 ? '0' + n : '' + n;
}

export function formatDateTimeElements(root) {
    var context = root || document;
    var els = context.querySelectorAll('[data-datetime]');
    if (!els || !els.length) return;

    els.forEach(el => {
        var iso = el.getAttribute('data-datetime');
        if (!iso) return;

        try {
            var d = new Date(iso);
            if (isNaN(d.getTime())) return;

            var year = d.getFullYear();
            var month = pad(d.getMonth() + 1);
            var day = pad(d.getDate());
            var hour = pad(d.getHours());
            var minute = pad(d.getMinutes());
            var second = pad(d.getSeconds());
            var formatted = year + '-' + month + '-' + day + ' ' + hour + ':' + minute + ':' + second;
            el.textContent = formatted;
        } catch (e) {}
    });
}
