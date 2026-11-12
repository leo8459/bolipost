const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const { spawnSync } = require('node:child_process');
const http = require('node:http');
const puppeteer = require('puppeteer');

test('billing loads only on demand, initializes its real dialog, retries errors and restores pending tracking', async () => {
    let fixture;
    let requests = 0;
    let failNext = false;
    const assets = {
        '/js/facturacion-shortcut-loader.js': 'js/facturacion-shortcut-loader.js',
        '/js/bolivian-number.js': 'js/bolivian-number.js',
        '/css/facturacion-shortcut-launcher.css': 'css/facturacion-shortcut-launcher.css',
        '/css/adminlte.css': 'vendor/adminlte/dist/css/adminlte.min.css',
    };
    const server = http.createServer((req, res) => {
        const pathname = new URL(req.url, 'http://localhost').pathname;
        if (pathname === '/facturacion/shortcut') {
            requests++;
            res.setHeader('Content-Type', 'text/html');
            if (failNext) {
                failNext = false;
                res.writeHead(503).end('Unavailable');
            } else {
                res.end(fixture.fragment);
            }
        } else if (assets[pathname]) {
            res.setHeader('Content-Type', pathname.endsWith('.css') ? 'text/css' : 'application/javascript');
            res.end(readFileSync(join(__dirname, '../../public', assets[pathname])));
        } else if (pathname === '/inicio') {
            res.setHeader('Content-Type', 'text/html');
            res.end('<!doctype html><html><head><meta name="csrf-token" content="test">'
                + '<link rel="stylesheet" href="/css/adminlte.css">'
                + '<script src="/js/bolivian-number.js"></script></head><body>' + fixture.launcher + '</body></html>');
        } else {
            res.writeHead(404).end();
        }
    });
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    const baseUrl = 'http://127.0.0.1:' + server.address().port;
    let browser;
    try {
        const render = spawnSync(process.env.PHP_BINARY || 'php', [
            join(__dirname, 'fixtures/render-facturacion-shortcut.php'), baseUrl,
        ], { encoding: 'utf8', cwd: join(__dirname, '../..') });
        assert.equal(render.status, 0, render.stderr || render.stdout);
        fixture = JSON.parse(render.stdout);
        assert.ok(fixture.launcher.length < 5000);
        browser = await puppeteer.launch({ headless: true });
        const page = await browser.newPage();
        page.setDefaultTimeout(7000);
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));

        await page.goto(baseUrl + '/inicio', { waitUntil: 'networkidle0' });
        assert.equal(requests, 0, 'Navigation must not load billing');
        await page.locator('#openFacturacionShortcut').click();
        await page.waitForSelector('#facturacionShortcutModal.is-open');
        assert.equal(requests, 1);
        await page.locator('#closeFacturacionShortcut').click();
        await page.waitForSelector('#facturacionShortcutModal:not(.is-open)');
        await page.locator('#openFacturacionShortcut').click();
        await page.waitForSelector('#facturacionShortcutModal.is-open');
        assert.equal(requests, 1, 'Opening the loaded dialog must reuse it');
        await page.evaluate(() => window.syncFacturacionShortcutSections());
        assert.equal(requests, 2, 'Cart refresh must request the fragment endpoint');

        await page.goto(baseUrl + '/inicio', { waitUntil: 'networkidle0' });
        failNext = true;
        await page.locator('#openFacturacionShortcut').click();
        await page.waitForFunction(() => document.getElementById('facturacionShortcutLoadStatus').textContent.includes('reintentar'));
        assert.equal(await page.$eval('#openFacturacionShortcut', button => button.disabled), false);
        await page.locator('#openFacturacionShortcut').click();
        await page.waitForSelector('#facturacionShortcutModal.is-open');
        assert.equal(requests, 4);

        await page.evaluate(() => sessionStorage.setItem('facturacion-pending-emit', '{}'));
        await page.goto(baseUrl + '/inicio', { waitUntil: 'networkidle0' });
        await page.waitForSelector('#facturacionShortcutModal');
        assert.equal(requests, 5, 'Pending tracking must load without a click');
        assert.deepEqual(errors, [], 'The real billing initializer must run without browser errors');
    } finally {
        if (browser) await browser.close();
        await new Promise(resolve => server.close(resolve));
    }
});
