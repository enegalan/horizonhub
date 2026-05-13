/**
 * @param {'Service'|'Provider'|'Alert'} entityKey
 * @param {{ listMode?: boolean }} [options]
 * @returns {Record<string, unknown>}
 */
export function horizonDeleteConfirm(entityKey, options = {}) {
    const listMode = options.listMode !== false;

    return {
        [`showDelete${entityKey}Modal`]: false,
        ...(listMode
            ? {
                [`delete${entityKey}Name`]: '',
                [`delete${entityKey}Action`]: '',
            }
            : {}),
        [`openDelete${entityKey}Modal`](name, action) {
            if (listMode) {
                this[`delete${entityKey}Name`] = name;
                this[`delete${entityKey}Action`] = action;
            }

            this[`showDelete${entityKey}Modal`] = true;
        },
        [`closeDelete${entityKey}Modal`]() {
            this[`showDelete${entityKey}Modal`] = false;
        },
        [`confirmDelete${entityKey}`]() {
            this.$refs[`delete${entityKey}Form`].requestSubmit();
            this[`closeDelete${entityKey}Modal`]();
        },
    };
}
