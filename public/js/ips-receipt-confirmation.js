(() => {
    'use strict';

    function officeOptions(row) {
        try {
            const options = JSON.parse(row.dataset.receiptOffices || '[]');
            return Array.isArray(options) ? options : [];
        } catch (_) {
            return [];
        }
    }

    function upuData(row) {
        let data = {};
        try {
            const parsed = JSON.parse(row.dataset.upuReceipt || '{}');
            if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) data = parsed;
        } catch (_) {}
        const zone = row.querySelector?.('.ips-upu-zone');
        if (zone && !['processing', 'uncertain'].includes(row.dataset.status)) data.zona = zone.value;
        return data;
    }

    function prepareUpu(container, rows, destination) {
        container.replaceChildren();
        rows.forEach(row => {
            const data = upuData(row);
            const pending = ['processing', 'uncertain'].includes(row.dataset.status);
            const section = document.createElement('fieldset');
            section.dataset.code = row.dataset.code;
            section.dataset.pending = pending ? '1' : '0';
            section.disabled = pending;
            section.upuSourceRow = row;
            const legend = document.createElement('legend');
            legend.textContent = row.dataset.code;
            section.appendChild(legend);
            const grid = document.createElement('div');
            grid.className = 'row';
            const definitions = [
                {key: 'codigo', label: 'Código', value: row.dataset.code, readOnly: true},
                {key: 'destino', label: 'Departamento de destino', value: data.destino ?? destination, readOnly: true},
                {key: 'destinatario', label: 'Destinatario', required: true, maxLength: 255},
                {key: 'telefono', label: 'Teléfono', type: 'tel', maxLength: 64},
                {key: 'zona', label: 'Zona / dirección', type: 'textarea', maxLength: 1000},
                {key: 'peso', label: 'Peso (kg)', type: 'number', required: true},
            ];
            definitions.forEach(definition => {
                const column = document.createElement('div');
                column.className = definition.key === 'zona' ? 'col-12' : 'col-md-6';
                const label = document.createElement('label');
                label.className = 'd-block mb-3';
                label.textContent = definition.label;
                const input = document.createElement(definition.type === 'textarea' ? 'textarea' : 'input');
                input.className = 'form-control mt-1';
                input.value = String(definition.value ?? data[definition.key] ?? '');
                input.required = Boolean(definition.required);
                input.readOnly = Boolean(definition.readOnly);
                if (definition.type === 'textarea') input.rows = 2;
                else input.type = definition.type || 'text';
                if (definition.maxLength) input.maxLength = definition.maxLength;
                if (definition.key === 'peso') { input.min = '0.001'; input.max = '999.999'; input.step = '0.001'; }
                if (definition.readOnly) input.dataset.upuReadOnly = definition.key;
                else input.dataset.upuField = definition.key;
                label.appendChild(input);
                column.appendChild(label);
                grid.appendChild(column);
            });
            section.appendChild(grid);
            if (pending) {
                const note = document.createElement('p');
                note.className = 'small text-muted';
                note.textContent = 'El reintento conservará los datos del registro original.';
                section.appendChild(note);
            }
            container.appendChild(section);
        });
    }

    function suggestedOffice(row, options, preferredOffice) {
        for (const candidate of [row.dataset.receiptLockedOffice, row.dataset.workbenchOfficeCd, preferredOffice]) {
            const match = options.find(office => candidate !== undefined && candidate !== null && String(office.office_cd) === String(candidate));
            if (match) return String(match.office_cd);
        }
        return options.length ? String(options[0].office_cd) : '';
    }

    function prepare(dialog, rows, destination = '', preferredOffice = '') {
        const fields = dialog.querySelector('#ips-receipt-confirm-fields');
        const offices = dialog.querySelector('#ips-confirm-receipt-offices');
        const physical = dialog.querySelector('#ips-confirm-physical-receipt');
        const mismatchFields = dialog.querySelector('#ips-confirm-route-fields');
        const mismatch = dialog.querySelector('#ips-confirm-receipt-route');
        fields.hidden = false;
        fields.disabled = false;
        physical.checked = false;
        physical.required = true;
        offices.replaceChildren();
        prepareUpu(dialog.querySelector('#ips-confirm-upu-fields'), rows, destination);
        let needsRouteConfirmation = false;
        rows.forEach(row => {
            const options = officeOptions(row);
            const pending = ['processing', 'uncertain'].includes(row.dataset.status);
            needsRouteConfirmation ||= row.dataset.routeMismatch === '1' || options.length > 0;
            if (!options.length || pending) return;
            const label = document.createElement('label');
            label.className = 'd-block mb-3';
            label.textContent = row.dataset.code + ' · Oficina donde llegó físicamente';
            const select = document.createElement('select');
            select.className = 'form-control mt-2';
            select.dataset.code = row.dataset.code;
            select.receiptSourceRow = row;
            select.required = true;
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Selecciona la oficina de recepción';
            select.appendChild(placeholder);
            options.forEach(office => {
                const option = document.createElement('option');
                option.value = String(office.office_cd);
                option.textContent = office.office_name + (office.office_fcd ? ' · ' + office.office_fcd : '');
                select.appendChild(option);
            });
            select.value = suggestedOffice(row, options, preferredOffice);
            select.receiptLockedOffice = select.value;
            select.disabled = true;
            label.appendChild(select);
            offices.appendChild(label);
        });
        mismatchFields.hidden = !needsRouteConfirmation;
        mismatch.disabled = !needsRouteConfirmation;
        mismatch.required = needsRouteConfirmation;
        mismatch.checked = false;
    }

    function read(dialog) {
        const offices = {};
        dialog.querySelectorAll('#ips-confirm-receipt-offices select[data-code]').forEach(select => {
            offices[select.dataset.code] = select.receiptLockedOffice;
        });
        const upu = {};
        dialog.querySelectorAll('#ips-confirm-upu-fields fieldset[data-code]').forEach(section => {
            if (section.dataset.pending === '1') return;
            const values = {};
            section.querySelectorAll('[data-upu-field]').forEach(input => {
                const value = input.value.trim();
                values[input.dataset.upuField] = value === '' && ['telefono', 'zona'].includes(input.dataset.upuField) ? null : value;
            });
            upu[section.dataset.code] = values;
            if (section.upuSourceRow) {
                section.upuSourceRow.dataset.upuReceipt = JSON.stringify({...upuData(section.upuSourceRow), ...values});
                const zone = section.upuSourceRow.querySelector?.('.ips-upu-zone');
                if (zone) zone.value = values.zona ?? '';
            }
        });
        return {
            physical: dialog.querySelector('#ips-confirm-physical-receipt').checked,
            routeMismatch: dialog.querySelector('#ips-confirm-receipt-route').checked,
            offices,
            upu,
        };
    }

    function reset(dialog) {
        const fields = dialog.querySelector('#ips-receipt-confirm-fields');
        fields.hidden = true;
        fields.disabled = true;
    }

    // Each search owns its confirmation, even if more scans arrive while it is open.
    function createSearchQueue({find, isBlocked, open, onIdle = () => {}}) {
        const pending = [];
        let activeCode = null;
        function resume() {
            if (activeCode || isBlocked()) return;
            while (pending.length) {
                const code = pending.shift();
                const row = find(code);
                if (!row) continue;
                activeCode = code;
                open(row);
                return;
            }
            onIdle();
        }
        return {
            request(code) {
                if (!code || activeCode === code || pending.includes(code)) return;
                pending.push(code);
                resume();
            },
            complete() { activeCode = null; resume(); },
            resume,
        };
    }

    window.IpsReceiptConfirmation = {prepare, read, reset, officeOptions, createSearchQueue};
})();
