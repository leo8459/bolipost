// Run from the repository root: node tests/Browser/tarifa-contrato-history.mjs
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import puppeteer from 'puppeteer';

const root = fileURLToPath(new URL('../../', import.meta.url));
const fixture = execFileSync('php', [path.join(root, 'tests/Browser/fixtures/tarifa-contrato-sheet.php')], {
    cwd: root,
    encoding: 'utf8',
    env: { ...process.env, APP_ENV: 'testing', DB_CONNECTION: 'sqlite', DB_DATABASE: ':memory:', CACHE_STORE: 'array', SESSION_DRIVER: 'array' },
}).replace(/<script\b[^>]*src=[\s\S]*?<\/script>/gi, '');
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
async function run(name, test) {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    try {
        await page.setViewport({ width: 1440, height: 1000 });
        await page.setContent(fixture);
        await page.addStyleTag({ path: path.join(root, 'public/vendor/adminlte/dist/css/adminlte.min.css') });
        await page.addStyleTag({ path: path.join(root, 'public/css/tarifa-contrato-sheet.css') });
        await page.addScriptTag({ path: path.join(root, 'public/js/tarifario-contrato-select.js') });
        await page.addScriptTag({ path: path.join(root, 'public/js/tarifa-contrato-sheet.js') });
        await test(page);
        assert.deepEqual(errors, []);
        console.log(`PASS ${name}`);
    } finally { await page.close(); }
}

try {
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
        await page.click('[data-refresh-services]');
        await page.waitForFunction(() => document.querySelector('[data-services-status]').textContent === 'Lista de servicios actualizada.');
        assert.ok((await options()).includes('SERVICIO NUEVO 2027'));
        assert.equal(new URL(await page.evaluate(() => window.requestedServicesUrl)).searchParams.get('anio'), '2027');
        assert.equal(await value(page, 0, 'direccion'), 'Conservar al cambiar de año');
    });

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
        await shortcut(page, 'z');
        assert.equal(await value(page, 0, 'direccion'), '');
        await shortcut(page, 'y');
        assert.equal(await value(page, 0, 'direccion'), 'Calle nueva');
    });

    await run('multi-cell paste and automatic row growth are undone together', async page => {
        await paste(page, 0, 'direccion', Array.from({ length: 12 }, (_, index) => `Calle ${index}\tCENTRO`).join('\n'));
        assert.equal(await rowCount(page), 12);
        assert.equal(await value(page, 11, 'zona'), 'CENTRO');
        await shortcut(page, 'z');
        assert.equal(await rowCount(page), 8);
        assert.equal(await value(page, 0, 'direccion'), '');
        await shortcut(page, 'y');
        assert.equal(await rowCount(page), 12);
        assert.equal(await value(page, 11, 'direccion'), 'Calle 11');
    });

    await run('duplicate, delete and add rows restore their contents', async page => {
        await type(page, 0, 'direccion', 'Conservar');
        await page.click('[data-sheet-action="duplicate"]');
        assert.equal(await rowCount(page), 9);
        await page.click('[data-sheet-action="undo"]');
        assert.equal(await rowCount(page), 8);
        await page.click('[data-sheet-action="redo"]');
        assert.equal(await value(page, 1, 'direccion'), 'Conservar');
        await page.click('[data-sheet-action="remove"]');
        assert.equal(await rowCount(page), 8);
        await shortcut(page, 'z');
        assert.equal(await rowCount(page), 9);
        assert.equal(await value(page, 1, 'direccion'), 'Conservar');
        await page.click('[data-sheet-action="add"]');
        assert.equal(await rowCount(page), 14);
        await shortcut(page, 'z');
        assert.equal(await rowCount(page), 9);
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
        await focus(page, 7, 'retencion');
        await page.keyboard.press('Tab');
        assert.equal(await rowCount(page), 9);
        await shortcut(page, 'z');
        assert.equal(await rowCount(page), 8);
        await shortcut(page, 'y');
        assert.equal(await rowCount(page), 9);
        await page.keyboard.press('Enter');
        assert.equal(await rowCount(page), 10);
        await shortcut(page, 'z');
        assert.equal(await rowCount(page), 9);
    });
} finally { await browser.close(); }
