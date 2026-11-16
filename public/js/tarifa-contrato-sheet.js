(() => {
    'use strict';

    const root = document.getElementById('tarifas-sheet');
    if (!root) return;
    const config = JSON.parse(document.getElementById('tarifas-sheet-config').textContent);
    const form = document.getElementById('tarifas-sheet-form');
    const body = document.getElementById('sheet-body');
    const viewport = root.querySelector('.sheet-viewport');
    const status = document.getElementById('sheet-status');
    const maxRows = Number(root.dataset.maxRows);
    const servicesByYear = JSON.parse(document.getElementById('servicios-contrato-config')?.textContent || '{}');
    const selectedTarifario = document.getElementById('tarifario_contrato_id');
    const initialServiceYear = selectedTarifario?.value ? selectedTarifario.selectedOptions[0].dataset.anio : document.getElementById('tarifario_anio')?.value;
    const initialServices = servicesByYear[initialServiceYear] || [];
    const columns = [
        { key: 'origen', label: 'Origen *', width: 180, options: config.departments, required: true },
        { key: 'destino', label: 'Destino *', width: 180, options: config.departments, required: true },
        { key: 'servicio', label: 'Servicio *', width: 310, options: initialServices, required: true },
        { key: 'peso', label: 'Peso (kg) *', width: 140, numeric: true, required: true },
        { key: 'kilo', label: 'Primer kilogramo: precio (Bs) *', width: 215, numeric: true, required: true },
        { key: 'kilo_extra', label: 'Kilogramo extra: precio (Bs) *', width: 215, numeric: true, required: true },
        { key: 'horas_entrega', label: 'Entrega (horas) *', width: 150, numeric: true, required: true },
        { key: 'direccion', label: 'Dirección (opcional)', width: 230 },
        { key: 'zona', label: 'Zona (opcional)', width: 170 },
        { key: 'provincia_origen', label: 'Provincia origen', width: 210, department: 'origen' },
        { key: 'provincia', label: 'Provincia destino', width: 210, department: 'destino' },
        { key: 'retencion', label: 'Retención (%) (opcional)', width: 180, numeric: true },
    ];
    const normalize = value => String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toUpperCase().trim().replace(/\s+/g, ' ');
    const blankRow = () => Object.fromEntries(columns.map(column => [column.key, '']));
    const entries = Object.entries(config.rows || {}).slice(0, maxRows);
    const rows = entries.map(([, row]) => Object.fromEntries(columns.map(column => [column.key, String(row?.[column.key] ?? '')])));
    const rowErrors = entries.map(([key]) => Object.fromEntries(columns.map(column => [column.key, config.errors?.[`tarifas.${key}.${column.key}`]?.[0] || ''])));
    while (rows.length < 8) { rows.push(blankRow()); rowErrors.push({}); }
    let active = { row: 0, col: 0 };
    let selection = { start: { ...active }, end: { ...active } };
    let drag = null;
    let resize = null;
    let suppressFocus = false;
    let rendering = false;
    const undoHistory = [];
    const redoHistory = [];
    let lastCellEdit = null;
    const fullscreenButton = document.getElementById('sheet-fullscreen');
    let fallbackFullscreen = false;

    function syncFullscreen() {
        const expanded = document.fullscreenElement === root || fallbackFullscreen;
        root.classList.toggle('sheet-is-fullscreen', expanded);
        document.body.classList.toggle('tarifa-sheet-expanded', expanded);
        fullscreenButton?.setAttribute('aria-pressed', String(expanded));
        if (fullscreenButton) {
            fullscreenButton.querySelector('span').textContent = expanded ? 'Salir de pantalla completa' : 'Pantalla completa';
            fullscreenButton.querySelector('i').className = `fas fa-${expanded ? 'compress' : 'expand'} mr-1`;
        }
    }

    fullscreenButton?.addEventListener('click', async () => {
        fullscreenButton.disabled = true;
        try {
            if (document.fullscreenElement === root) {
                await document.exitFullscreen();
            } else if (fallbackFullscreen) {
                fallbackFullscreen = false;
            } else {
                try {
                    if (!root.requestFullscreen || !document.fullscreenEnabled) throw new Error('Fullscreen unavailable');
                    await root.requestFullscreen();
                } catch {
                    // Keep the expanded view available when native fullscreen is unsupported or denied.
                    fallbackFullscreen = true;
                }
            }
        } catch {
            announce('No se pudo salir de pantalla completa. Presiona Esc para volver.');
        } finally {
            syncFullscreen();
            fullscreenButton.disabled = false;
            fullscreenButton.focus({ preventScroll: true });
        }
    });
    document.addEventListener('fullscreenchange', syncFullscreen);
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && fallbackFullscreen) {
            fallbackFullscreen = false;
            syncFullscreen();
            fullscreenButton?.focus({ preventScroll: true });
        }
    });

    function optionItems(column) {
        return (column.options || []).map(option => typeof option === 'string' ? { value: option, label: option, aliases: [] } : option);
    }

    function matchOption(column, value) {
        const text = normalize(value);
        const items = optionItems(column);
        const exact = items.find(option => normalize(option.value) === text || normalize(option.label) === text);
        if (exact) return exact.value;
        const matches = items.filter(option => (option.aliases || []).some(alias => alias && normalize(alias) === text));
        if (matches.length === 1) return matches[0].value;
        if (['origen', 'destino'].includes(column.key)) {
            return { CHUQUISACA: 'SUCRE', BENI: 'TRINIDAD', PANDO: 'COBIJA' }[text] || value;
        }
        if (column.key === 'servicio') {
            const service = text.replace('LOCAL (', 'LOCAL(');
            return items.find(option => normalize(option.value) === service)?.value ?? value;
        }
        return value;
    }

    function controlAt(row, col) { return body.rows[row]?.cells[col + 1]?.querySelector('.sheet-control'); }
    function bounds() {
        return {
            top: Math.min(selection.start.row, selection.end.row), bottom: Math.max(selection.start.row, selection.end.row),
            left: Math.min(selection.start.col, selection.end.col), right: Math.max(selection.start.col, selection.end.col),
        };
    }
    function hasValues(row) { return columns.some(column => String(row[column.key]).trim() !== ''); }
    function announce(message) { status.textContent = message; }
    function snapshot() {
        return {
            rows: rows.map(row => ({ ...row })),
            errors: rowErrors.map(errors => ({ ...errors })),
            active: { ...active },
            selection: { start: { ...selection.start }, end: { ...selection.end } },
            widths: columns.map(column => column.width),
            scrollTop: viewport.scrollTop,
            scrollLeft: viewport.scrollLeft,
        };
    }
    function updateHistoryButtons() {
        const undo = root.querySelector('[data-sheet-action="undo"]');
        const redo = root.querySelector('[data-sheet-action="redo"]');
        if (undo) undo.disabled = undoHistory.length === 0;
        if (redo) redo.disabled = redoHistory.length === 0;
    }
    function remember(before, label, cell = null) {
        const after = snapshot();
        if (JSON.stringify([before.rows, before.widths]) === JSON.stringify([after.rows, after.widths])) return;
        const time = Date.now();
        // Consecutive typing in one cell is one edit; bulk operations stay separate.
        if (cell && lastCellEdit?.cell === cell && time - lastCellEdit.time < 1000 && !redoHistory.length) {
            undoHistory[undoHistory.length - 1].after = after;
        } else {
            undoHistory.push({ before, after, label });
            if (undoHistory.length > 100) undoHistory.shift();
        }
        redoHistory.length = 0;
        lastCellEdit = cell ? { cell, time } : null;
        updateHistoryButtons();
    }
    function restore(state) {
        rows.splice(0, rows.length, ...state.rows.map(row => ({ ...row })));
        rowErrors.splice(0, rowErrors.length, ...state.errors.map(errors => ({ ...errors })));
        active = { ...state.active };
        selection = { start: { ...state.selection.start }, end: { ...state.selection.end } };
        columns.forEach((column, col) => {
            column.width = state.widths[col];
            colgroup.children[col + 1].style.width = `${column.width}px`;
        });
        tableWidth();
        render();
        viewport.scrollTop = state.scrollTop;
        viewport.scrollLeft = state.scrollLeft;
        suppressFocus = true;
        const control = controlAt(active.row, active.col);
        control?.focus({ preventScroll: true });
        control?.scrollIntoView({ block: 'nearest', inline: 'nearest' });
        if (control?.tagName === 'INPUT') control.setSelectionRange(control.value.length, control.value.length);
        suppressFocus = false;
    }
    function travelHistory(redo = false) {
        if (drag || resize) finishDrag();
        lastCellEdit = null;
        const source = redo ? redoHistory : undoHistory;
        const destination = redo ? undoHistory : redoHistory;
        const entry = source.pop();
        if (!entry) { announce(redo ? 'No hay cambios para rehacer.' : 'No hay cambios para deshacer.'); return; }
        restore(redo ? entry.after : entry.before);
        destination.push(entry);
        updateHistoryButtons();
        announce(`${redo ? 'Rehecho' : 'Deshecho'}: ${entry.label}.`);
    }
    function updateCount() {
        document.getElementById('sheet-filled-count').textContent = rows.filter(hasValues).length;
        root.querySelector('[data-sheet-action="add"]').disabled = rows.length >= maxRows;
        root.querySelector('[data-sheet-action="duplicate"]').disabled = rows.length >= maxRows;
        updateHistoryButtons();
    }
    function updateFormula() {
        document.getElementById('sheet-cell-name').textContent = `${String.fromCharCode(65 + active.col)}${active.row + 1}`;
        const control = controlAt(active.row, active.col);
        document.getElementById('sheet-cell-value').textContent = control?.selectedOptions?.[0]?.textContent || control?.value || '';
    }
    function paintSelection() {
        const range = bounds();
        root.querySelector('.sheet-fill-handle')?.remove();
        [...body.rows].forEach((tr, row) => {
            tr.cells[0].classList.toggle('sheet-current-row', row >= range.top && row <= range.bottom);
            columns.forEach((column, col) => {
                const td = tr.cells[col + 1];
                td.classList.toggle('sheet-selected', row >= range.top && row <= range.bottom && col >= range.left && col <= range.right);
                td.classList.toggle('sheet-active', row === active.row && col === active.col);
            });
        });
        const handle = document.createElement('button');
        handle.type = 'button';
        handle.className = 'sheet-fill-handle';
        handle.tabIndex = -1;
        handle.title = 'Arrastra hacia abajo para copiar el valor o rango';
        handle.setAttribute('aria-label', handle.title);
        body.rows[range.bottom]?.cells[range.right + 1]?.append(handle);
        updateFormula();
    }
    function activate(row, col, extend = false, focus = false) {
        row = Math.max(0, Math.min(rows.length - 1, row));
        col = Math.max(0, Math.min(columns.length - 1, col));
        active = { row, col };
        selection = { start: extend ? selection.start : { ...active }, end: { ...active } };
        paintSelection();
        if (focus) {
            suppressFocus = true;
            controlAt(row, col)?.focus({ preventScroll: true });
            controlAt(row, col)?.scrollIntoView({ block: 'nearest', inline: 'nearest' });
            suppressFocus = false;
        }
    }

    function updateProvinceList(row, column) {
        const id = `sheet-provinces-${row}-${column.key}`;
        let list = document.getElementById(id);
        if (!list) {
            list = document.createElement('datalist');
            list.id = id;
            document.getElementById('sheet-datalists').append(list);
        }
        list.replaceChildren(...(config.provinces[rows[row][column.department]] || []).map(name => new Option(name, name)));
    }

    function render() {
        rendering = true;
        // History and row duplication must also respect the current tariff's service year.
        rows.forEach(row => {
            if (row.servicio && !columns.find(column => column.key === 'servicio').options.includes(row.servicio)) row.servicio = '';
        });
        body.replaceChildren();
        document.getElementById('sheet-datalists').replaceChildren();
        rows.forEach((row, rowIndex) => {
            const tr = body.insertRow();
            const number = document.createElement('th');
            number.scope = 'row';
            number.className = 'sheet-row-number';
            number.textContent = rowIndex + 1;
            number.title = 'Seleccionar fila';
            number.dataset.row = rowIndex;
            tr.append(number);
            columns.forEach((column, colIndex) => {
                const td = tr.insertCell();
                td.dataset.row = rowIndex;
                td.dataset.col = colIndex;
                const control = document.createElement(column.options ? 'select' : 'input');
                control.className = 'sheet-control' + (column.numeric ? ' sheet-numeric' : '');
                control.name = `tarifas[${rowIndex}][${column.key}]`;
                control.dataset.row = rowIndex;
                control.dataset.col = colIndex;
                control.setAttribute('aria-label', `Fila ${rowIndex + 1}, ${column.label.replace(' *', '')}`);
                if (column.options) {
                    control.append(new Option('Seleccione…', ''));
                    optionItems(column).forEach(option => control.append(new Option(option.label, option.value)));
                    if (column.key !== 'servicio' && row[column.key] && ![...control.options].some(option => option.value === row[column.key])) {
                        control.append(new Option(row[column.key], row[column.key]));
                    }
                } else {
                    control.type = 'text';
                    control.autocomplete = 'off';
                    if (column.numeric) control.inputMode = column.key === 'horas_entrega' ? 'numeric' : 'decimal';
                    else control.maxLength = 255;
                    if (column.department) {
                        control.setAttribute('list', `sheet-provinces-${rowIndex}-${column.key}`);
                        updateProvinceList(rowIndex, column);
                    }
                }
                control.value = row[column.key];
                td.append(control);
                const error = rowErrors[rowIndex]?.[column.key];
                if (error) showError(control, error);
            });
        });
        paintSelection();
        updateCount();
        rendering = false;
    }

    function showError(control, message) {
        const td = control.closest('td');
        td.classList.add('sheet-invalid');
        control.setAttribute('aria-invalid', 'true');
        const error = document.createElement('span');
        error.className = 'sheet-error';
        error.id = `sheet-error-${control.dataset.row}-${control.dataset.col}`;
        error.textContent = message;
        control.setAttribute('aria-describedby', error.id);
        td.querySelector('.sheet-error')?.remove();
        td.append(error);
    }
    function clearError(row, col) {
        const control = controlAt(row, col);
        control?.setCustomValidity('');
        control?.removeAttribute('aria-invalid');
        control?.removeAttribute('aria-describedby');
        control?.closest('td').classList.remove('sheet-invalid');
        control?.closest('td').querySelector('.sheet-error')?.remove();
        if (rowErrors[row]) delete rowErrors[row][columns[col].key];
    }
    function setValue(row, col, value) {
        const column = columns[col];
        value = String(value ?? '').trim();
        if (column.options && value) value = matchOption(column, value);
        if (column.key === 'servicio' && value && !column.options.includes(value)) {
            rows[row].servicio = '';
            clearError(row, col);
            controlAt(row, col).value = '';
            showError(controlAt(row, col), `El servicio "${value}" no está registrado para el año del tarifario seleccionado.`);
            return;
        }
        rows[row][column.key] = value;
        clearError(row, col);
        const control = controlAt(row, col);
        if (control) {
            if (column.options && ![...control.options].some(option => option.value === value)) control.append(new Option(value, value));
            control.value = value;
        }
        if (['origen', 'destino'].includes(column.key)) {
            columns.filter(item => item.department === column.key).forEach(item => updateProvinceList(row, item));
        }
    }
    function fillDown(range = bounds(), sourceBottom = range.top) {
        if (range.bottom <= sourceBottom) { announce('Selecciona al menos dos filas para rellenar hacia abajo.'); return; }
        const before = snapshot();
        const source = rows.slice(range.top, sourceBottom + 1).map(row => ({ ...row }));
        for (let row = sourceBottom + 1; row <= range.bottom; row++) {
            for (let col = range.left; col <= range.right; col++) {
                setValue(row, col, source[(row - sourceBottom - 1) % source.length][columns[col].key]);
            }
        }
        updateCount();
        updateFormula();
        remember(before, 'rellenar hacia abajo');
        announce(`Valores copiados hasta la fila ${range.bottom + 1}.`);
    }

    const colgroup = document.getElementById('sheet-columns');
    const rowNumbers = document.createElement('col');
    rowNumbers.style.width = '46px';
    colgroup.append(rowNumbers);
    const head = document.getElementById('sheet-head');
    const letters = head.insertRow();
    const labels = head.insertRow();
    [letters, labels].forEach((tr, i) => {
        const th = document.createElement('th');
        th.className = 'sheet-row-number ' + (i ? 'sheet-heading' : 'sheet-letter');
        th.textContent = i ? '#' : '';
        tr.append(th);
    });
    columns.forEach((column, index) => {
        const col = document.createElement('col');
        col.style.width = `${column.width}px`;
        colgroup.append(col);
        const letter = document.createElement('th');
        letter.className = 'sheet-letter';
        letter.textContent = String.fromCharCode(65 + index);
        letters.append(letter);
        const label = document.createElement('th');
        label.scope = 'col';
        label.className = 'sheet-heading';
        label.textContent = column.label;
        const resizer = document.createElement('span');
        resizer.className = 'sheet-column-resizer';
        resizer.dataset.col = index;
        resizer.title = 'Arrastra para cambiar el ancho';
        label.append(resizer);
        labels.append(label);
    });
    function tableWidth() { root.querySelector('table').style.width = `${46 + columns.reduce((total, column) => total + column.width, 0)}px`; }
    tableWidth();
    render();

    root.addEventListener('tarifario-services-change', event => {
        const col = columns.findIndex(column => column.key === 'servicio');
        columns[col].options = event.detail.services;
        rows.forEach((row, rowIndex) => {
            const control = controlAt(rowIndex, col);
            const selected = row.servicio;
            control.replaceChildren(new Option('Seleccione…', ''), ...event.detail.services.map(name => new Option(name, name)));
            if (selected && !event.detail.services.includes(selected)) {
                row.servicio = '';
                showError(control, 'Este servicio no está disponible para el año seleccionado.');
            } else clearError(rowIndex, col);
            control.value = row.servicio;
        });
        updateCount();
        updateFormula();
    });

    body.addEventListener('focusin', event => {
        lastCellEdit = null;
        if (!suppressFocus && event.target.matches('.sheet-control')) activate(Number(event.target.dataset.row), Number(event.target.dataset.col));
    });
    body.addEventListener('focusout', () => { lastCellEdit = null; });
    function editCell(event) {
        if (rendering) return;
        const control = event.target;
        if (!control.matches('.sheet-control')) return;
        const row = Number(control.dataset.row), col = Number(control.dataset.col);
        if (rows[row][columns[col].key] === control.value) return;
        const before = snapshot();
        rows[row][columns[col].key] = control.value;
        clearError(row, col);
        if (['origen', 'destino'].includes(columns[col].key)) {
            const provinceCol = columns.findIndex(column => column.department === columns[col].key);
            setValue(row, provinceCol, '');
        }
        updateCount();
        updateFormula();
        remember(before, 'editar celda', control.tagName === 'INPUT' ? `${row}:${col}` : null);
    }
    body.addEventListener('input', editCell);
    body.addEventListener('change', editCell);

    root.addEventListener('pointerdown', event => {
        lastCellEdit = null;
        const resizer = event.target.closest('.sheet-column-resizer');
        if (resizer) {
            event.preventDefault();
            viewport.focus({ preventScroll: true });
            resize = { col: Number(resizer.dataset.col), x: event.clientX, width: columns[resizer.dataset.col].width, before: snapshot() };
            return;
        }
        if (event.button !== 0) return;
        if (event.target.closest('.sheet-fill-handle')) {
            event.preventDefault();
            drag = { type: 'fill', source: bounds() };
            root.classList.add('sheet-dragging');
            return;
        }
        const number = event.target.closest('tbody .sheet-row-number');
        if (number) {
            event.preventDefault();
            active = { row: Number(number.dataset.row), col: 0 };
            selection = { start: { ...active }, end: { row: active.row, col: columns.length - 1 } };
            paintSelection();
            return;
        }
        const td = event.target.closest('td[data-col]');
        if (!td) return;
        const row = Number(td.dataset.row), col = Number(td.dataset.col);
        if (event.shiftKey || event.target === td) {
            event.preventDefault();
            activate(row, col, event.shiftKey, true);
            drag = { type: 'select' };
            root.classList.add('sheet-dragging');
        } else activate(row, col);
    });
    document.addEventListener('pointermove', event => {
        if (resize) {
            const width = Math.max(100, Math.min(650, resize.width + event.clientX - resize.x));
            columns[resize.col].width = width;
            colgroup.children[resize.col + 1].style.width = `${width}px`;
            tableWidth();
            return;
        }
        if (!drag) return;
        const rect = viewport.getBoundingClientRect();
        if (event.clientY > rect.bottom - 35) viewport.scrollTop += 20;
        if (event.clientY < rect.top + 85) viewport.scrollTop -= 20;
        if (event.clientX > rect.right - 35) viewport.scrollLeft += 25;
        if (event.clientX < rect.left + 50) viewport.scrollLeft -= 25;
        const td = document.elementFromPoint(event.clientX, event.clientY)?.closest('td[data-col]');
        if (!td || !body.contains(td)) return;
        if (drag.type === 'fill') {
            selection = { start: { row: drag.source.top, col: drag.source.left }, end: { row: Math.max(drag.source.bottom, Number(td.dataset.row)), col: drag.source.right } };
        } else selection.end = { row: Number(td.dataset.row), col: Number(td.dataset.col) };
        paintSelection();
    });
    function finishDrag() {
        if (drag?.type === 'fill') fillDown(bounds(), drag.source.bottom);
        if (resize) remember(resize.before, 'ajustar ancho de columna');
        drag = null;
        resize = null;
        root.classList.remove('sheet-dragging');
    }
    document.addEventListener('pointerup', finishDrag);
    document.addEventListener('pointercancel', finishDrag);

    root.addEventListener('click', event => {
        const action = event.target.closest('[data-sheet-action]')?.dataset.sheetAction;
        if (!action) return;
        if (action === 'undo' || action === 'redo') { travelHistory(action === 'redo'); return; }
        if (action === 'fill') { fillDown(); return; }
        const before = snapshot();
        const rowIndex = active.row;
        if (action === 'remove') {
            rows.splice(rowIndex, 1); rowErrors.splice(rowIndex, 1);
            if (!rows.length) { rows.push(blankRow()); rowErrors.push({}); }
            active.row = Math.min(rowIndex, rows.length - 1);
        } else if (rows.length < maxRows) {
            if (action === 'duplicate') {
                rows.splice(rowIndex + 1, 0, { ...rows[rowIndex] }); rowErrors.splice(rowIndex + 1, 0, {});
                active.row = rowIndex + 1;
            } else {
                const start = rows.length;
                for (let n = 0, count = Math.min(5, maxRows - start); n < count; n++) { rows.push(blankRow()); rowErrors.push({}); }
                active.row = start;
            }
        }
        selection = { start: { ...active }, end: { ...active } };
        render();
        activate(active.row, active.col, false, true);
        remember(before, { remove: 'eliminar fila', duplicate: 'duplicar fila', add: 'agregar filas' }[action]);
        announce(`${rows.length} filas disponibles. Las filas vacías no se guardan.`);
    });

    // Excel-compatible TSV, including quoted cells with tabs, quotes or line breaks.
    function parseClipboard(text) {
        const result = [], line = [];
        let value = '', quoted = false;
        text = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
        for (let i = 0; i < text.length; i++) {
            const char = text[i];
            if (char === '"' && (quoted || value === '')) {
                if (quoted && text[i + 1] === '"') { value += '"'; i++; }
                else quoted = !quoted;
            } else if (!quoted && (char === '\t' || char === '\n')) {
                line.push(value); value = '';
                if (char === '\n') { result.push(line.splice(0)); }
            } else value += char;
        }
        line.push(value); result.push(line);
        if (text.endsWith('\n') && result.at(-1).length === 1 && result.at(-1)[0] === '') result.pop();
        return result;
    }
    body.addEventListener('paste', event => {
        if (!event.clipboardData) return;
        const text = event.clipboardData.getData('text/plain');
        if (!text) return;
        event.preventDefault();
        const data = parseClipboard(text);
        const start = { ...active };
        if (start.row + data.length > maxRows || data.some(line => start.col + line.length > columns.length)) {
            announce('No se pegó: el bloque excede las 50 filas o las 12 columnas. Reduce el rango y vuelve a pegar.');
            return;
        }
        const count = Math.max(rows.length, start.row + data.length);
        const before = snapshot();
        while (rows.length < count) { rows.push(blankRow()); rowErrors.push({}); }
        render();
        data.forEach((line, row) => line.forEach((value, col) => setValue(start.row + row, start.col + col, value)));
        selection = { start, end: { row: start.row + data.length - 1, col: start.col + Math.max(...data.map(line => line.length)) - 1 } };
        paintSelection(); updateCount();
        suppressFocus = true; controlAt(start.row, start.col)?.focus(); suppressFocus = false;
        remember(before, 'pegar celdas');
        announce(`Se pegaron ${data.length} filas. Revisa los datos antes de guardar.`);
    });
    body.addEventListener('copy', event => {
        const range = bounds();
        if (range.top === range.bottom && range.left === range.right && event.target.selectionStart !== event.target.selectionEnd) return;
        if (!event.clipboardData) return;
        const quote = value => /[\t\n\r"]/.test(value) ? `"${value.replace(/"/g, '""')}"` : value;
        const data = rows.slice(range.top, range.bottom + 1).map(row => columns.slice(range.left, range.right + 1).map(column => {
            const value = row[column.key];
            return quote(value);
        }).join('\t')).join('\r\n');
        event.preventDefault(); event.clipboardData.setData('text/plain', data);
    });
    body.addEventListener('keydown', event => {
        if (event.isComposing) return;
        const range = bounds();
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'd') { event.preventDefault(); fillDown(); return; }
        if (event.key === 'Escape') { activate(active.row, active.col, false, true); return; }
        if (['Delete', 'Backspace'].includes(event.key) && (range.top !== range.bottom || range.left !== range.right)) {
            event.preventDefault();
            const before = snapshot();
            for (let row = range.top; row <= range.bottom; row++) for (let col = range.left; col <= range.right; col++) setValue(row, col, '');
            updateCount(); updateFormula(); remember(before, 'borrar celdas'); return;
        }
        if (event.key === 'Enter') {
            event.preventDefault();
            const before = snapshot();
            if (!event.shiftKey && active.row === rows.length - 1 && rows.length < maxRows) { rows.push(blankRow()); rowErrors.push({}); render(); }
            activate(active.row + (event.shiftKey ? -1 : 1), active.col, false, true);
            remember(before, 'agregar fila');
        }
        if (event.key === 'Tab' && !event.shiftKey && active.col === columns.length - 1 && active.row === rows.length - 1 && rows.length < maxRows) {
            event.preventDefault();
            const before = snapshot();
            rows.push(blankRow()); rowErrors.push({}); render(); activate(rows.length - 1, 0, false, true);
            remember(before, 'agregar fila');
        }
    });

    root.addEventListener('keydown', event => {
        if (event.isComposing || event.altKey || !(event.ctrlKey || event.metaKey)) return;
        if (event.target !== root && !event.target.closest('.sheet-control, .sheet-viewport, .sheet-toolbar')) return;
        const key = event.key.toLowerCase();
        if (key !== 'z' && key !== 'y') return;
        event.preventDefault();
        event.stopPropagation();
        travelHistory(key === 'y' || event.shiftKey);
    });
    body.addEventListener('beforeinput', event => {
        if (!['historyUndo', 'historyRedo'].includes(event.inputType)) return;
        event.preventDefault();
        travelHistory(event.inputType === 'historyRedo');
    });

    form.addEventListener('submit', event => {
        let firstInvalid = null;
        rows.forEach((row, rowIndex) => {
            columns.forEach((column, col) => clearError(rowIndex, col));
            if (!hasValues(row)) return;
            columns.forEach((column, col) => {
                const value = row[column.key].trim();
                let error = '';
                if (column.required && !value) error = 'Este campo es obligatorio.';
                else if (value && column.numeric) {
                    const decimal = value.includes(',') ? value.replace(/\./g, '').replace(',', '.') : value;
                    const number = Number(decimal);
                    if (!/^(?:\d+(?:\.\d*)?|\.\d+)$/.test(decimal) || !Number.isFinite(number) || number < 0) error = 'Ingresa un número válido, mayor o igual a 0.';
                    else if (column.key === 'retencion' && number > 100) error = 'La retención debe estar entre 0 y 100.';
                    else if (column.key === 'horas_entrega' && !/^\d+$/.test(value)) error = 'Ingresa una cantidad entera de horas.';
                } else if (value && column.options && !optionItems(column).some(option => option.value === value)) error = 'Selecciona un valor válido de la lista.';
                if (error) {
                    showError(controlAt(rowIndex, col), error);
                    firstInvalid ||= { row: rowIndex, col };
                }
            });
        });
        if (!rows.some(hasValues)) { event.preventDefault(); announce('Completa al menos una fila para guardar.'); activate(0, 0, false, true); return; }
        if (firstInvalid) {
            event.preventDefault(); activate(firstInvalid.row, firstInvalid.col, false, true);
            announce('Revisa las celdas marcadas en rojo. Todavía no se guardaron las tarifas.');
            return;
        }
        const save = document.getElementById('sheet-save');
        if (save) { save.disabled = true; save.textContent = 'Guardando tarifas…'; }
    });
    window.addEventListener('pageshow', () => {
        const save = document.getElementById('sheet-save');
        if (save) { save.disabled = false; save.textContent = 'Guardar tarifas'; }
    });
})();
