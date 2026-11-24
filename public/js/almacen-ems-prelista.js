function almacenEmsPrelista(wire, userId, city) {
    const fields = {
        EMS: 'selectedPaquetes',
        INT: 'selectedPaquetesInt',
        CONTRATO: 'selectedContratos',
        SOLICITUD: 'selectedSolicitudes',
    };
    const key = JSON.stringify(['almacen-ems-prelista-v1', location.pathname, userId, city]);
    const catalog = new Map();
    let signature = '';
    let summaryTimer;
    let pendingRestore = false;

    function selection() {
        return Object.fromEntries(Object.values(fields).map(field => [
            field, Array.from(wire.$get(field), String),
        ]));
    }

    function replace(selection) {
        for (const field of Object.values(fields)) {
            wire.$set(field, selection[field] || [], false);
        }
    }

    function readIds(raw) {
        const ids = JSON.parse(raw)?.ids;
        return ids && Object.values(fields).every(field => Array.isArray(ids[field])
            && ids[field].every(id => typeof id === 'string' && /^[1-9]\d*$/.test(id))) ? ids : null;
    }

    return {
        items: [],
        counts: {},
        total: 0,
        search: '',
        type: 'TODOS',
        expanded: true,
        storageError: false,

        init() {
            try {
                const saved = localStorage.getItem(key);
                const ids = readIds(saved ?? sessionStorage.getItem(key));
                if (ids) {
                    if (saved === null) localStorage.setItem(key, JSON.stringify({ids}));
                    replace(ids);
                    pendingRestore = true;
                }
                sessionStorage.removeItem(key);
            } catch {
                this.storageError = true;
            }

            signature = JSON.stringify(selection());
            this.readCatalog();
            // The same four properties also change after scans, weighing and dispatches.
            this.$watch(() => selection(), () => this.sync());
            wire.$hook('morphed', () => this.readCatalog());
            this.reconnect();
        },

        readCatalog() {
            const source = this.$root.querySelector('[data-ems-preview]');
            // A checkbox can change while an earlier page request is in flight.
            catalog.clear();
            for (const row of this.items) catalog.set(row.tipo + '-' + row.record_id, row);
            for (const row of JSON.parse(source?.textContent || '[]')) {
                catalog.set(row.tipo + '-' + row.record_id, row);
            }
            this.sync();
        },

        sync() {
            const ids = selection();
            const next = JSON.stringify(ids);
            const items = [];
            for (const [type, field] of Object.entries(fields)) {
                this.counts[type] = ids[field].length;
                for (const id of ids[field]) {
                    items.push(catalog.get(type + '-' + id) || {tipo: type, record_id: id});
                }
            }
            this.items = items.sort((a, b) => a.tipo.localeCompare(b.tipo)
                || String(a.codigo || '').localeCompare(String(b.codigo || '')));
            this.total = items.length;
            if (next === signature) return;
            signature = next;
            try {
                if (items.length) localStorage.setItem(key, JSON.stringify({ids}));
                else localStorage.removeItem(key);
                this.storageError = false;
            } catch {
                this.storageError = true;
            }
            // Only the open CN-38 panel needs a server-calculated weight summary.
            if (wire.$get('showCn38Generate') && navigator.onLine) {
                clearTimeout(summaryTimer);
                summaryTimer = setTimeout(() => wire.$commit(), 200);
            }
        },

        storageChanged(event) {
            if (event.storageArea !== localStorage || (event.key !== key && event.key !== null)) return;
            try {
                const ids = readIds(localStorage.getItem(key));
                replace(ids || {});
                signature = JSON.stringify(selection());
                pendingRestore = false;
                this.sync();
                if (navigator.onLine && (wire.$get('showCn38Generate') || this.items.some(row => !row.codigo))) {
                    wire.$commit();
                }
            } catch {
                this.storageError = true;
            }
        },

        reconnect() {
            if (!navigator.onLine) return;
            if (pendingRestore && this.total) {
                wire.restoreAlmacenSelection().then(() => { pendingRestore = false; });
            } else if (wire.$get('showCn38Generate')) {
                wire.$commit();
            }
        },

        get filteredItems() {
            const query = this.search.trim().toLocaleLowerCase();
            return this.items.filter(row => (this.type === 'TODOS' || this.type === row.tipo)
                && (!query || [row.codigo, row.destinatario, row.origen, row.destino, row.direccion_r, row.direccion_d]
                    .join(' ').toLocaleLowerCase().includes(query)));
        },

        remove(row) {
            const field = fields[row.tipo];
            wire.$set(field, wire.$get(field).filter(id => String(id) !== String(row.record_id)), false);
        },

        clear() {
            pendingRestore = false;
            replace({});
            this.sync();
        },

        destroy() {
            clearTimeout(summaryTimer);
        },
    };
}
