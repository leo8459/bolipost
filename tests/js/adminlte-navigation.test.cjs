const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const http = require('node:http');
const puppeteer = require('puppeteer');

test('sidebar works while an image is stalled and keeps a single handler after window.load', async () => {
    const heldImages = new Map();
    const assets = {
        '/jquery.js': 'vendor/jquery/jquery.min.js',
        '/bootstrap.js': 'vendor/bootstrap/js/bootstrap.bundle.min.js',
        '/adminlte.js': 'vendor/adminlte/dist/js/adminlte.min.js',
        '/navigation.js': 'js/adminlte-navigation.js',
        '/adminlte.css': 'vendor/adminlte/dist/css/adminlte.min.css',
    };
    const server = http.createServer((req, res) => {
        const url = new URL(req.url, 'http://localhost');
        if (url.pathname === '/slow-avatar') {
            heldImages.set(url.searchParams.get('page'), res);
        } else if (assets[url.pathname]) {
            res.setHeader('Content-Type', url.pathname.endsWith('.css') ? 'text/css' : 'application/javascript');
            res.end(readFileSync(join(__dirname, '../../public', assets[url.pathname])));
        } else {
            const before = url.pathname === '/before';
            res.setHeader('Content-Type', 'text/html');
            res.end(`<!doctype html><html><head><link rel="stylesheet" href="/adminlte.css"></head>
                <body class="sidebar-mini"><div class="wrapper"><aside class="main-sidebar">
                <ul class="nav nav-sidebar flex-column" data-widget="treeview" id="navigation" data-animation-speed="0">
                <li class="nav-item" id="adminMenu"><a href="#" class="nav-link" id="adminToggle">Administrador</a>
                <ul class="nav nav-treeview"><li class="nav-item"><a href="/usuarios" class="nav-link" id="usersLink">Usuarios</a></li></ul>
                </li></ul></aside><div class="content-wrapper"><img src="/slow-avatar?page=${before ? 'before' : 'after'}" alt="avatar"></div></div>
                <script src="/jquery.js"></script><script src="/bootstrap.js"></script><script src="/adminlte.js"></script>
                ${before ? '' : '<script src="/navigation.js"></script>'}
                <script>window.pageLoaded = false; window.addEventListener('load', () => { window.pageLoaded = true; });
                window.expanded = 0; jQuery('#navigation').on('expanded.lte.treeview', () => { window.expanded++; });</script>
                </body></html>`);
        }
    });
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    const baseUrl = 'http://127.0.0.1:' + server.address().port;
    let browser;
    try {
        browser = await puppeteer.launch({ headless: true });
        const before = await browser.newPage();
        await before.goto(baseUrl + '/before', { waitUntil: 'domcontentloaded' });
        assert.equal(await before.evaluate(() => window.pageLoaded), false);
        await before.locator('#adminToggle').click();
        assert.equal(await before.$eval('#adminMenu', menu => menu.classList.contains('menu-open')), false,
            'Reproduce the original failure: sidebar cannot expand before the image finishes');

        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.goto(baseUrl + '/after', { waitUntil: 'domcontentloaded' });
        assert.equal(await page.evaluate(() => window.pageLoaded), false);
        await page.locator('#adminToggle').click();
        await page.waitForSelector('#adminMenu.menu-open');
        assert.equal(await page.$eval('#usersLink', link => link.getBoundingClientRect().height > 0), true);
        assert.equal(await page.evaluate(() => window.expanded), 1);

        const image = heldImages.get('after');
        image.setHeader('Content-Type', 'image/svg+xml');
        image.end(readFileSync(join(__dirname, '../../public/images/user-avatar.svg')));
        await page.waitForFunction(() => window.pageLoaded);
        await page.evaluate(() => document.dispatchEvent(new Event('livewire:navigated')));
        await page.locator('#adminToggle').click();
        await page.waitForSelector('#adminMenu:not(.menu-open)');
        await page.locator('#adminToggle').click();
        await page.waitForSelector('#adminMenu.menu-open');
        assert.equal(await page.evaluate(() => window.expanded), 2, 'No duplicate initialization after load or navigation');
        assert.deepEqual(errors, []);
    } finally {
        for (const response of heldImages.values()) response.end();
        if (browser) await browser.close();
        await new Promise(resolve => server.close(resolve));
    }
});
