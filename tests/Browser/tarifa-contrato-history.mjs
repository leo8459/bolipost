// Run from the repository root: node tests/Browser/tarifa-contrato-history.mjs
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import puppeteer from 'puppeteer';

const root = fileURLToPath(new URL('../../', import.meta.url));
const renderFixture = (...args) => execFileSync('php', [path.join(root, 'tests/Browser/fixtures/tarifa-contrato-sheet.php'), ...args], {
    cwd: root,
    encoding: 'utf8',
    env: { ...process.env, APP_ENV: 'testing', DB_CONNECTION: 'sqlite', DB_DATABASE: ':memory:', CACHE_STORE: 'array', SESSION_DRIVER: 'array' },
}).replace(/<script\b[^>]*src=[\s\S]*?<\/script>/gi, '');
const fixture = renderFixture();
const executablePath = [
    process.env.PUPPETEER_EXECUTABLE_PATH,
    'C:/Program Files/Google/Chrome/Application/chrome.exe',
    'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
].find(candidate => candidate && existsSync(candidate));
const browser = await puppeteer.launch({ headless: true, ...(executablePath ? { executablePath } : {}) });
const cell = (row, key) => `[name="tarifas[${row}][${key}]"]`;
const value = (page, row, key) => page.$eval(cell(row, key), control => control.value);
const rowCount = page => page.$$eval('#sheet-body tr', rows => rows.length);

async function shortcut(page, key, shift = false) {
    await page.keyboard.down('Control');
    if (shift) await page.keyboard.down('Shift');
    await page.keyboard.press(key);
    if (shift) await page.keyboard.up('Shift');
    await page.keyboard.up('Control');
}
async function focus(page, row, key) {
    await page.$eval(cell(row, key), control => { control.blur(); control.focus(); control.scrollIntoView({ block: 'nearest', inline: 'nearest' }); });
}
async function type(page, row, key, text) {
    await focus(page, row, key);
    await page.$eval(cell(row, key), control => control.select());
    await page.keyboard.type(text);
}
async function paste(page, row, key, text) {
    await focus(page, row, key);
    await page.$eval(cell(row, key), (control, text) => {
        const data = new DataTransfer();
        data.setData('text/plain', text);
        control.dispatchEvent(new ClipboardEvent('paste', { bubbles: true, cancelable: true, clipboardData: data }));
    }, text);
}
async function run(name, test, html = fixture) {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    try {
        await page.setViewport({ width: 1440, height: 1000 });
        await page.setContent(html);
        await page.addStyleTag({ path: path.join(root, 'public/vendor/adminlte/dist/css/adminlte.min.css') });
        await page.addStyleTag({ path: path.join(root, 'public/css/tarifarios-contrato.css') });
        await page.addStyleTag({ path: path.join(root, 'public/css/tarifa-contrato-sheet.css') });
        await page.addScriptTag({ path: path.join(root, 'public/js/tarifario-contrato-select.js') });
        await page.addScriptTag({ path: path.join(root, 'public/js/tarifa-contrato-sheet.js') });
        await test(page);
        assert.deepEqual(errors, []);
        console.log(`PASS ${name}`);
    } finally { await page.close(); }
}

try {
    await run('the sheet has no weight column and accepts the 11-column Excel layout', async page => {
        assert.equal(await page.$('[name="tarifas[0][peso]"]'), null);
        assert.deepEqual(await page.$$eval('.sheet-step-number', numbers => numbers.map(number => number.textContent.trim())), ['1', '2', '3']);
        const titles = await page.$$eval('.sheet-heading', headings => headings.slice(1).map(heading => heading.textContent));
        assert.equal(titles.length, 11);
        assert.equal(titles[3], 'Primer kilogramo: precio (Bs) *');
        assert.deepEqual(await page.$$eval('#sheet-zonas-locales option', options => options.map(option => option.value)), ['Zona 1', 'Zona 2', 'Zona 3']);
        assert.equal(await rowCount(page), 9);
        assert.equal(await page.$eval('[data-add-rows-label]', label => label.textContent), 'Agregar 9 filas');
        await paste(page, 0, 'origen', 'LA PAZ\tORURO\tENVIO NACIONAL (REGULAR)\t25,50\t5\t24\tCalle 1\tCENTRO\tMURILLO\tCERCADO\t0');
        assert.equal(await value(page, 0, 'kilo'), '25,50');
        assert.equal(await value(page, 0, 'kilo_extra'), '5');
        assert.equal(await value(page, 0, 'horas_entrega'), '24');
        assert.equal(await value(page, 0, 'direccion'), 'Calle 1');
        assert.equal(await value(page, 0, 'retencion'), '0');
        await page.evaluate(() => {
            const form = document.getElementById('tarifas-sheet-form');
            form.addEventListener('submit', event => { window.sheetBlocked = event.defaultPrevented; event.preventDefault(); });
            form.requestSubmit();
        });
        assert.equal(await page.evaluate(() => window.sheetBlocked), false);
        const modal = await page.$('#sheet-save-dialog:modal');
        assert.ok(modal);
        assert.equal(await modal.$eval('h2', title => title.textContent), 'Cargando tarifas…');
        assert.equal(await modal.evaluate(dialog => dialog.dispatchEvent(new Event('cancel', { cancelable: true }))), false);
        assert.equal(await page.$eval('#sheet-save', button => button.disabled), true);
        const duplicateBlocked = await page.$eval('#tarifas-sheet-form', form => !form.dispatchEvent(new SubmitEvent('submit', { cancelable: true })));
        assert.equal(duplicateBlocked, true);
        await page.evaluate(() => window.dispatchEvent(new PageTransitionEvent('pageshow', { persisted: true })));
        assert.equal(await page.$('#sheet-save-dialog[open]'), null);
        assert.equal(await page.$eval('#sheet-save', button => button.disabled), false);
    });

    await run('the preload panel submits only the chosen source and destination', async page => {
        assert.equal(await page.$eval('.sheet-load-option', panel => panel.open), false);
        await page.click('.sheet-load-option summary');
        await page.select('#tarifario-origen', '72');
        await page.evaluate(() => {
            const form = document.getElementById('tarifario-preload-form');
            form.addEventListener('submit', event => {
                event.preventDefault();
                window.precarga = Object.fromEntries(new FormData(form));
            });
        });
        await page.click('button[form="tarifario-preload-form"]');
        assert.deepEqual(await page.evaluate(() => window.precarga), { tarifario_id: '70', tarifario_origen_id: '72' });
    });

    await run('service catalogs and template downloads follow the selected year', async page => {
        const options = () => page.$$eval('[name="tarifas[0][servicio]"] option', options => options.map(option => option.value));
        assert.ok((await options()).includes('SERVICIO SOLO 2026'));
        assert.ok(!(await options()).includes('SERVICIO SOLO 2027'));
        await type(page, 0, 'direccion', 'Conservar al cambiar de año');
        await paste(page, 0, 'servicio', 'SERVICIO SOLO 2026');
        await page.select('#tarifario_anio', '2027');
        await page.select('#tarifario_contrato_id', '71');
        assert.ok((await options()).includes('SERVICIO SOLO 2027'));
        assert.equal(await value(page, 0, 'servicio'), '');
        assert.ok(!(await options()).includes('SERVICIO SOLO 2026'));
        assert.equal(await value(page, 0, 'direccion'), 'Conservar al cambiar de año');
        const download = new URL(await page.$eval('#sheet-download-template', link => link.href));
        assert.equal(download.searchParams.get('anio'), '2027');
        assert.equal(download.searchParams.get('tarifario_id'), '71');
        await page.select('[name="tarifas[0][servicio]"]', 'SERVICIO SOLO 2027');
        assert.equal(await page.$eval('[name="tarifas[0][servicio]"]', control => control.getAttribute('aria-invalid')), null);
        await page.evaluate(() => {
            window.fetch = async url => {
                window.requestedServicesUrl = String(url);
                return { ok: true, json: async () => ({ anio: 2027, servicios: ['SERVICIO SOLO 2027', 'SERVICIO NUEVO 2027'] }) };
            };
        });
        await page.click('[data-services-help-toggle]');
        await page.click('[data-refresh-services]');
        await page.waitForFunction(() => document.querySelector('[data-services-status]').textContent === 'Lista de servicios actualizada.');
        assert.ok((await options()).includes('SERVICIO NUEVO 2027'));
        assert.equal(new URL(await page.evaluate(() => window.requestedServicesUrl)).searchParams.get('anio'), '2027');
        assert.equal(await value(page, 0, 'direccion'), 'Conservar al cambiar de año');
    });

    await run('origin and destination use the exact uppercase catalog and flag invalid pasted values', async page => {
        const catalog = ['', 'LA PAZ', 'COCHABAMBA', 'SANTA CRUZ', 'ORURO', 'POTOSI', 'TARIJA', 'SUCRE', 'TRINIDAD', 'COBIJA'];
        for (const field of ['origen', 'destino']) {
            assert.deepEqual(await page.$$eval(cell(0, field) + ' option', options => options.map(option => option.value)), catalog);
        }
        await paste(page, 0, 'origen', ' la paz \tpando\tENVIO NACIONAL (REGULAR)\t20\t5\t24');
        assert.equal(await value(page, 0, 'origen'), 'LA PAZ');
        assert.equal(await value(page, 0, 'destino'), '');
        assert.equal(await page.$eval(cell(0, 'destino'), control => control.getAttribute('aria-invalid')), 'true');
        assert.deepEqual(await page.$$eval(cell(0, 'destino') + ' option', options => options.map(option => option.value)), catalog);

        const blocked = await page.$eval('#tarifas-sheet-form', form => {
            let blocked = false;
            form.addEventListener('submit', event => {
                blocked = event.defaultPrevented;
                event.preventDefault();
            }, { once: true });
            form.requestSubmit();
            return blocked;
        });
        assert.equal(blocked, true);
        assert.equal(await page.$('#sheet-save-dialog[open]'), null);
        await page.select(cell(0, 'destino'), 'COBIJA');
        assert.equal(await page.$eval(cell(0, 'destino'), control => control.getAttribute('aria-invalid')), null);
    });

    await run('Excel upload uses the selected tarifario without submitting manual rows', async page => {
        await page.select('#tarifario_anio', '2027');
        await page.click('.sheet-import summary');
        await page.evaluate(() => {
            const file = new File(['test'], 'tarifas.xlsx');
            const data = new DataTransfer();
            data.items.add(file);
            document.getElementById('sheet-excel-file').files = data.files;
            const form = document.getElementById('tarifas-excel-import-form');
            form.addEventListener('submit', event => {
                if (!event.defaultPrevented) {
                    const submitted = new FormData(form);
                    window.excelImport = {
                        path: new URL(form.action).pathname,
                        encoding: form.enctype,
                        tarifario: submitted.get('tarifario_contrato_id'),
                        year: submitted.get('tarifario_anio'),
                        file: submitted.get('archivo').name,
                        includesManualRows: [...submitted.keys()].some(key => key.startsWith('tarifas[')),
                    };
                }
                event.preventDefault();
            });
        });
        const importButton = 'button[form="tarifas-excel-import-form"]';
        await page.click(importButton);
        assert.equal(await page.evaluate(() => window.excelImport), undefined);
        await page.select('#tarifario_contrato_id', '71');
        await page.click(importButton);
        assert.deepEqual(await page.evaluate(() => window.excelImport), {
            path: '/tarifa-contrato/import',
            encoding: 'multipart/form-data',
            tarifario: '71',
            year: '2027',
            file: 'tarifas.xlsx',
            includesManualRows: false,
        });
    });

    await run('preloaded tariffs preserve their services and submit all 261 rows to the selected destination', async page => {
        assert.equal(await rowCount(page), 261);
        assert.equal(await page.$eval('#tarifario-origen', option => option.value), '72');
        assert.equal(await page.$('#tarifario_contrato_id option[value="72"]'), null);
        await page.select('#tarifario_anio', '2027');
        await page.select('#tarifario_contrato_id', '71');
        assert.equal(await page.$eval('.sheet-control[data-row="0"][data-col="2"]', control => control.value), 'SERVICIO LOCAL ENTEL');
        await page.click('#sheet-fullscreen');
        await page.waitForSelector('#tarifas-sheet.sheet-is-fullscreen');

        await page.$eval('.sheet-control[data-row="0"][data-col="3"]', control => {
            control.value = '27,50';
            control.dispatchEvent(new Event('input', { bubbles: true }));
        });
        const submission = await page.$eval('#tarifas-sheet-form', form => {
            let submitted = null;
            form.addEventListener('submit', event => {
                if (!event.defaultPrevented) submitted = Object.fromEntries(new FormData(form));
                event.preventDefault();
            });
            form.requestSubmit();
            return submitted;
        });
        assert.equal(submission.tarifario_origen_id, '72');
        assert.equal(submission.tarifario_contrato_id, '71');
        assert.equal(Object.keys(submission).some(key => key.startsWith('tarifas[')), false);
        const rows = JSON.parse(submission.tarifas_serializadas);
        assert.equal(rows.length, 261);
        assert.equal(rows[0].kilo, '27,50');
        assert.equal(rows[0].peso, '1.50');
        assert.equal(rows[260].provincia, 'PROVINCIA 261');
        assert.equal(await page.$eval('#sheet-save-dialog', dialog => {
            const { x, y, width, height } = dialog.getBoundingClientRect();
            return dialog.matches(':modal') && dialog.contains(document.elementFromPoint(x + width / 2, y + height / 2));
        }), true);
    }, renderFixture('--precarga'));

    await run('paste, undo and row duplication never add services from another year', async page => {
        await paste(page, 0, 'servicio', 'SERVICIO SOLO 2026');
        await page.select('#tarifario_anio', '2027');
        await page.select('#tarifario_contrato_id', '71');
        const options = () => page.$$eval('[name="tarifas[0][servicio]"] option', options => options.map(option => option.value));
        await shortcut(page, 'z');
        await focus(page, 0, 'servicio');
        await shortcut(page, 'z');
        await shortcut(page, 'y');
        assert.deepEqual(await options(), ['', 'SERVICIO SOLO 2027']);
        assert.equal(await value(page, 0, 'servicio'), '');
        await paste(page, 0, 'servicio', 'SERVICIO SOLO 2026');
        assert.deepEqual(await options(), ['', 'SERVICIO SOLO 2027']);
        assert.equal(await value(page, 0, 'servicio'), '');
        await page.click('[data-sheet-action="duplicate"]');
        assert.deepEqual(await page.$$eval('[name="tarifas[1][servicio]"] option', options => options.map(option => option.value)), ['', 'SERVICIO SOLO 2027']);
    });

    await run('typing, keyboard undo/redo, and Shift+Ctrl+Z', async page => {
        assert.equal(await page.$eval('[data-sheet-action="undo"]', button => button.disabled), true);
        await type(page, 0, 'direccion', 'Calle nueva');
        await shortcut(page, 'z');
        assert.equal(await value(page, 0, 'direccion'), '');
        await shortcut(page, 'y');
        assert.equal(await value(page, 0, 'direccion'), 'Calle nueva');
        await shortcut(page, 'z');
        await shortcut(page, 'z', true);
        assert.equal(await value(page, 0, 'direccion'), 'Calle nueva');
        await page.click('#sheet-fullscreen');
        await page.waitForFunction(() => document.fullscreenElement);
        const layout = await page.$eval('#tarifas-sheet', sheet => {
            const visible = selector => sheet.querySelector(selector).getClientRects().length > 0;
            return {
                hiddenPanels: ['.sheet-setup', '.sheet-entry-heading', '.sheet-load-options', '.sheet-guide', '#sheet-download-template'].every(selector => !visible(selector)),
                visibleControls: ['.sheet-fullscreen-target', '.sheet-edit-tools', '.sheet-formula-bar', '#sheet-save', '#sheet-fullscreen'].every(visible),
                destinationLabel: sheet.querySelector('.sheet-fullscreen-target span').textContent.trim(),
                destination: sheet.querySelector('#sheet-fullscreen-destination').textContent,
                gridShare: sheet.querySelector('.sheet-viewport').clientHeight / sheet.clientHeight,
            };
        });
        assert.equal(layout.hiddenPanels, true);
        assert.equal(layout.visibleControls, true);
        assert.equal(layout.destinationLabel, 'Tarifario de destino');
        assert.match(layout.destination, /Tarifario A.*2026/);
        assert.ok(layout.gridShare > 0.75);
        await shortcut(page, 'z');
        assert.equal(await value(page, 0, 'direccion'), '');
        await shortcut(page, 'y');
        assert.equal(await value(page, 0, 'direccion'), 'Calle nueva');
        await page.click('#sheet-fullscreen');
        await page.waitForFunction(() => !document.fullscreenElement && !document.querySelector('#tarifas-sheet').classList.contains('sheet-is-fullscreen'));
        assert.equal(await page.$eval('.sheet-setup', element => element.getClientRects().length > 0), true);
        assert.equal(await value(page, 0, 'direccion'), 'Calle nueva');
    });

    await run('the compact fullscreen fallback restores the destination selector when required', async page => {
        await page.setViewport({ width: 390, height: 844 });
        await page.select('#tarifario_contrato_id', '');
        await page.$eval('#tarifas-sheet', sheet => { sheet.requestFullscreen = undefined; });
        await page.click('#sheet-fullscreen');
        await page.waitForSelector('#tarifas-sheet.sheet-is-fullscreen');
        assert.equal(await page.$eval('#sheet-fullscreen-destination', element => element.textContent), 'Destino por elegir');
        assert.equal(await page.$eval('#outside-sheet', element => element.inert), true);
        assert.ok(await page.$eval('.sheet-viewport', viewport => viewport.clientHeight > 580));

        await paste(page, 0, 'origen', 'LA PAZ');
        await page.click('#sheet-save');
        await page.waitForFunction(() => document.activeElement.id === 'tarifario_contrato_id');
        assert.equal(await page.$('.sheet-is-fullscreen'), null);
        assert.equal(await page.$eval('#outside-sheet', element => element.inert), false);
        assert.equal(await value(page, 0, 'origen'), 'LA PAZ');

        await page.select('#tarifario_anio', '2027');
        await page.select('#tarifario_contrato_id', '71');
        await page.click('#sheet-fullscreen');
        await page.waitForSelector('#tarifas-sheet.sheet-is-fullscreen');
        assert.match(await page.$eval('#sheet-fullscreen-destination', element => element.textContent), /Tarifario A.*2027/);
        await page.keyboard.press('Escape');
        assert.equal(await page.$('.sheet-is-fullscreen'), null);
    });

    await run('multi-cell paste and automatic row growth are undone together', async page => {
        await paste(page, 0, 'direccion', Array.from({ length: 12 }, (_, index) => `Calle ${index}\tCENTRO`).join('\n'));
        assert.equal(await rowCount(page), 12);
        assert.equal(await value(page, 11, 'zona'), 'CENTRO');
        await shortcut(page, 'z');
        assert.equal(await rowCount(page), 9);
        assert.equal(await value(page, 0, 'direccion'), '');
        await shortcut(page, 'y');
        assert.equal(await rowCount(page), 12);
        assert.equal(await value(page, 11, 'direccion'), 'Calle 11');
    });

    await run('duplicate, delete and add rows restore their contents', async page => {
        await type(page, 0, 'direccion', 'Conservar');
        await page.click('[data-sheet-action="duplicate"]');
        assert.equal(await rowCount(page), 10);
        await page.click('[data-sheet-action="undo"]');
        assert.equal(await rowCount(page), 9);
        await page.click('[data-sheet-action="redo"]');
        assert.equal(await value(page, 1, 'direccion'), 'Conservar');
        await page.click('[data-sheet-action="remove"]');
        assert.equal(await rowCount(page), 9);
        await shortcut(page, 'z');
        assert.equal(await rowCount(page), 10);
        assert.equal(await value(page, 1, 'direccion'), 'Conservar');
        await page.click('[data-sheet-action="add"]');
        assert.equal(await rowCount(page), 19);
        await shortcut(page, 'z');
        assert.equal(await rowCount(page), 10);
    });

    await run('dependent provinces are restored with department changes', async page => {
        await paste(page, 0, 'origen', 'LA PAZ');
        await paste(page, 0, 'provincia_origen', 'MURILLO');
        await page.select(cell(0, 'origen'), 'ORURO');
        assert.equal(await value(page, 0, 'provincia_origen'), '');
        await shortcut(page, 'z');
        assert.equal(await value(page, 0, 'origen'), 'LA PAZ');
        assert.equal(await value(page, 0, 'provincia_origen'), 'MURILLO');
        await shortcut(page, 'y');
        assert.equal(await value(page, 0, 'origen'), 'ORURO');
        assert.equal(await value(page, 0, 'provincia_origen'), '');
    });

    await run('fill down and range deletion are each a single operation', async page => {
        await paste(page, 0, 'direccion', 'Repetir');
        await page.click(cell(0, 'direccion'));
        await page.keyboard.down('Shift');
        await page.click(cell(3, 'direccion'));
        await page.keyboard.up('Shift');
        await shortcut(page, 'd');
        assert.equal(await value(page, 3, 'direccion'), 'Repetir');
        await shortcut(page, 'z');
        assert.equal(await value(page, 0, 'direccion'), 'Repetir');
        assert.equal(await value(page, 3, 'direccion'), '');
        await shortcut(page, 'y');
        await page.keyboard.press('Delete');
        assert.equal(await value(page, 0, 'direccion'), '');
        await shortcut(page, 'z');
        assert.equal(await value(page, 3, 'direccion'), 'Repetir');
    });

    await run('drag fill and column resizing can be undone and redone', async page => {
        await paste(page, 0, 'origen', 'LA PAZ');
        await page.$eval('.sheet-viewport', sheet => sheet.scrollIntoView({ block: 'center' }));
        const point = async selector => page.$eval(selector, element => { const rect = element.getBoundingClientRect(); return { x: rect.x + rect.width / 2, y: rect.y + rect.height / 2 }; });
        let start = await point('.sheet-fill-handle'), end = await point(cell(4, 'origen'));
        await page.mouse.move(start.x, start.y); await page.mouse.down(); await page.mouse.move(end.x, end.y, { steps: 10 }); await page.mouse.up();
        assert.equal(await value(page, 4, 'origen'), 'LA PAZ');
        await shortcut(page, 'z');
        assert.equal(await value(page, 4, 'origen'), '');
        await shortcut(page, 'y');
        assert.equal(await value(page, 4, 'origen'), 'LA PAZ');
        const width = await page.$eval('#sheet-columns col:nth-child(2)', col => parseFloat(col.style.width));
        start = await point('.sheet-column-resizer');
        await page.mouse.move(start.x, start.y); await page.mouse.down(); await page.mouse.move(start.x + 60, start.y, { steps: 5 }); await page.mouse.up();
        assert.equal(await page.$eval('#sheet-columns col:nth-child(2)', col => parseFloat(col.style.width)), width + 60);
        await shortcut(page, 'z');
        assert.equal(await page.$eval('#sheet-columns col:nth-child(2)', col => parseFloat(col.style.width)), width);
        await shortcut(page, 'y');
        assert.equal(await page.$eval('#sheet-columns col:nth-child(2)', col => parseFloat(col.style.width)), width + 60);
    });

    await run('a new edit clears redo, while unrelated inputs keep native undo', async page => {
        await type(page, 0, 'direccion', 'Primero');
        await shortcut(page, 'z');
        await type(page, 0, 'direccion', 'Nuevo');
        assert.equal(await page.$eval('[data-sheet-action="redo"]', button => button.disabled), true);
        await shortcut(page, 'y');
        assert.equal(await value(page, 0, 'direccion'), 'Nuevo');
        await page.type('#outside-sheet', 'Fuera');
        await shortcut(page, 'z');
        assert.equal(await value(page, 0, 'direccion'), 'Nuevo');
        await focus(page, 0, 'direccion');
        await page.$eval(cell(0, 'direccion'), control => control.dispatchEvent(new InputEvent('beforeinput', { bubbles: true, cancelable: true, inputType: 'historyUndo' })));
        assert.equal(await value(page, 0, 'direccion'), '');
    });

    await run('keyboard-created rows and rejected paste preserve history correctly', async page => {
        await paste(page, 0, 'origen', Array(51).fill('LA PAZ').join('\n'));
        assert.equal(await page.$eval('[data-sheet-action="undo"]', button => button.disabled), true);
        await focus(page, 8, 'retencion');
        await page.keyboard.press('Tab');
        assert.equal(await rowCount(page), 10);
        await shortcut(page, 'z');
        assert.equal(await rowCount(page), 9);
        await shortcut(page, 'y');
        assert.equal(await rowCount(page), 10);
        await page.keyboard.press('Enter');
        assert.equal(await rowCount(page), 11);
        await shortcut(page, 'z');
        assert.equal(await rowCount(page), 10);
    });

    await run('adding nine rows respects the 50-row limit and can be undone', async page => {
        assert.equal(await rowCount(page), 9);
        for (const expected of [18, 27, 36, 45, 50]) {
            await page.click('[data-sheet-action="add"]');
            assert.equal(await rowCount(page), expected);
            assert.equal(await page.$eval('#sheet-row-count', counter => Number(counter.textContent)), expected);
        }
        assert.equal(await page.$eval('[data-sheet-action="add"]', button => button.disabled), true);
        await shortcut(page, 'z');
        assert.equal(await rowCount(page), 45);
        assert.equal(await page.$eval('[data-add-rows-label]', label => label.textContent), 'Agregar 5 filas');
        await shortcut(page, 'y');
        assert.equal(await rowCount(page), 50);
    });
} finally { await browser.close(); }
