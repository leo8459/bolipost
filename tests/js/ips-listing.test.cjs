const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const vm = require('node:vm');
const source = readFileSync(join(__dirname, '../../public/js/ips-listing.js'), 'utf8');
const tick = () => new Promise(resolve => setImmediate(resolve));

function setup(workflowAction = '', officeScope = {}) {
    const events = {}, windowEvents = {}, pending = [], history = [], redirects = [], dispatched = [];
    const location = new URL('http://localhost/ips' + (officeScope.officeCd ? '?office_cd=' + officeScope.officeCd : ''));
    location.assign = url => redirects.push(url);
    const workspace = {dataset: {workflowAction, scanEnabled: workflowAction ? '1' : '0', combinedOffices: officeScope.combinedOffices || ''}};
    const storageReads = [];
    const dialog = {open: false, showModal() { this.open = true; }};
    const modalCode = {textContent: ''};
    const listing = {
        innerHTML: 'previous results',
        addEventListener: (name, fn) => { events[name] = fn; },
        setAttribute() {}, removeAttribute() {},
        querySelector: selector => selector === '.ips-search' ? {action: 'http://localhost/ips', fields: {q: ''}}
            : (selector === '#ips-code' && officeScope.initialCode ? {value: officeScope.initialCode, setAttribute() {}, focus() {}} : null),
        querySelectorAll: () => [],
        classList: {add() {}, remove() {}},
    };
    const status = {hidden: true, textContent: ''};
    vm.runInNewContext(source, {
        document: {
            getElementById: id => ({'ips-listing': listing, 'ips-listing-status': status, 'ips-already-received': dialog, 'ips-already-received-code': modalCode}[id] || null),
            querySelector: selector => selector === '.ips-workspace' ? workspace : null,
            querySelectorAll: selector => selector === '.ips-tray-item' ? (officeScope.trayRows || []) : [],
        },
        FormData: class {
            constructor(form) { this.fields = new Map(Object.entries(form?.fields || {})); }
            get(key) { return this.fields.get(key); }
            append(key, value) { this.fields.set(key, value); }
            [Symbol.iterator]() { return this.fields.entries(); }
        },
        location, URL, URLSearchParams, AbortController,
        history: {pushState: (_state, _title, url) => history.push(url)},
        sessionStorage: {getItem: key => { storageReads.push(key); return null; }, removeItem() {}, setItem() {}},
        CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } },
        Event: class { constructor(type) { this.type = type; } },
        window: {addEventListener: (name, fn) => { windowEvents[name] = fn; }, dispatchEvent: event => { dispatched.push(event); }},
        fetch: (url, options) => new Promise(resolve => pending.push({url, options, resolve})),
    });
    const click = query => events.click({
        target: {closest: selector => selector === '.ips-already-received' ? null : ({href: 'http://localhost/ips?' + query})},
        button: 0, preventDefault() {},
    });
    const respond = (index, data, ok = true) => pending[index].resolve({
        ok, status: ok ? 200 : (data.http_status || 409), headers: {get: () => 'application/json'}, json: async () => data,
    });
    return {events, windowEvents, listing, status, pending, history, redirects, dispatched, storageReads, click, respond, dialog, modalCode};
}

test('pagination requests a read-only fragment and replaces just the listing', async () => {
    const app = setup();
    app.click('page=2');
    assert.equal(app.pending[0].options.headers.Accept, 'application/json');
    assert.equal(app.pending[0].options.method, undefined); // default GET
    app.respond(0, {html: 'page two'});
    await tick();
    assert.equal(app.listing.innerHTML, 'page two');
    assert.deepEqual(app.history, ['http://localhost/ips?page=2']);
    assert.equal(app.status.hidden, true);
});

test('a delayed previous search cannot overwrite newer results', async () => {
    const app = setup();
    app.click('q=FIRST'); app.click('q=SECOND');
    assert.equal(app.pending[0].options.signal.aborted, true);
    app.respond(1, {html: 'second result'});
    await tick();
    app.respond(0, {html: 'old result'});
    await tick();
    assert.equal(app.listing.innerHTML, 'second result');
    assert.equal(app.history.length, 1);
});

test('an API failure retains the current results and URL with a visible explanation', async () => {
    const app = setup();
    app.click('stage=pending');
    app.respond(0, {message: 'IPS unavailable'}, false);
    await tick();
    assert.equal(app.listing.innerHTML, 'previous results');
    assert.equal(app.history.length, 0);
    assert.match(app.status.textContent, /No se pudo consultar IPS/);
    assert.equal(app.status.hidden, false);
});

test('selection and delivery forms are not intercepted by list navigation', () => {
    const app = setup();
    app.events.submit({target: {closest: () => null}, preventDefault: () => assert.fail('intercepted operation')});
    assert.equal(app.pending.length, 0);
});

test('scanning an already received package opens the modal without adding it', async () => {
    const app = setup('EMG');
    const form = {fields: {q: 'PACKAGE01'}, querySelector: () => null};
    app.events.submit({
        target: {closest: selector => selector === '.ips-search' ? form : null},
        preventDefault() {},
    });
    assert.equal(app.pending[0].options.method, 'POST');
    app.respond(0, {error_code: 'already_received', code: 'PACKAGE01', message: 'Este paquete ya está recibido.'}, false);
    await tick();
    assert.equal(app.dialog.open, true);
    assert.equal(app.modalCode.textContent, 'PACKAGE01');
    assert.equal(app.status.textContent, 'Este paquete ya está recibido.');
});

test('selecting the received indicator opens the modal without a request', () => {
    const app = setup('EMG');
    app.events.click({target: {closest: () => ({dataset: {code: 'PACKAGE01'}})}});
    assert.equal(app.dialog.open, true);
    assert.equal(app.modalCode.textContent, 'PACKAGE01');
    assert.equal(app.pending.length, 0);
});

test('scanning an unregistered L letter opens its IPS creation form', async () => {
    const app = setup('EMG');
    const form = {fields: {q: 'LW945318163DE'}, querySelector: () => null};
    app.events.submit({
        target: {closest: selector => selector === '.ips-search' ? form : null},
        preventDefault() {},
    });
    app.respond(0, {http_status: 404, message: 'Paquete no encontrado en IPS.'}, false);
    await tick();
    assert.deepEqual(app.redirects, ['http://localhost/ips?q=LW945318163DE']);
});

test('the unified scan queue uses the same scope when opening an old office URL', () => {
    const group = '17_18_19';
    const current = setup('EMG', {combinedOffices: group});
    const oldOfficeLink = setup('EMG', {combinedOffices: group, officeCd: '19'});
    assert.deepEqual(current.storageReads, ['ips-inbound-next-scans:/ips:office:group-17_18_19']);
    assert.deepEqual(oldOfficeLink.storageReads, current.storageReads);
});

test('a self-routed letter scan prepares the receipt and its office choices without posting a receipt event', async () => {
    const app = setup('EMG', {combinedOffices: '17_18_19'});
    const form = {fields: {q: 'LW545318163DE'}, querySelector: () => null};
    app.events.submit({target: {closest: selector => selector === '.ips-search' ? form : null}, preventDefault() {}});
    app.respond(0, {code: 'LW545318163DE', count: 1, package: {allowed_actions: ['EMG'], needs_receipt_office: true, receipt_office_options: [{office_cd: 18, office_name: 'EMS'}]}});
    await tick();
    assert.equal(app.pending.length, 1);
    assert.equal(app.dispatched[0].type, 'ips:selection-added');
    assert.equal(app.dispatched[0].detail.scanned, true);
    assert.equal(app.dispatched[0].detail.package.needs_receipt_office, true);
    assert.match(app.status.textContent, /modal de recepción/);
});

test('opening an exact reception search automatically prepares that code after the page initializes', async () => {
    const app = setup('EMG', {initialCode: 'LW545318163DE'});
    assert.equal(app.pending.length, 0);
    app.windowEvents.DOMContentLoaded();
    assert.equal(app.pending[0].options.method, 'POST');
    assert.equal(app.pending[0].options.body.get('codigo'), 'LW545318163DE');
    app.respond(0, {code: 'LW545318163DE', count: 1, package: {allowed_actions: ['EMG']}});
    await tick();
    assert.equal(app.dispatched[0].detail.scanned, true);
    assert.equal(app.pending.length, 1);
});

test('rescanning a parcel selects it again in the tray without adding a duplicate or receiving it', () => {
    const changes = [];
    const checkbox = {checked: false, disabled: false, dispatchEvent: event => changes.push(event.type)};
    const trayRow = {dataset: {code: 'LW545318163DE', status: 'ready'}, querySelector: () => checkbox};
    const app = setup('EMG', {trayRows: [trayRow]});
    const form = {fields: {q: 'LW545318163DE'}, querySelector: () => null};
    app.events.submit({target: {closest: selector => selector === '.ips-search' ? form : null}, preventDefault() {}});
    assert.equal(checkbox.checked, true);
    assert.deepEqual(changes, ['change']);
    assert.equal(app.pending.length, 0);
    assert.equal(app.dispatched[0].type, 'ips:receipt-requested');
    assert.equal(app.dispatched[0].detail.code, 'LW545318163DE');
});

test('rescanning a delivery parcel does not request a reception modal', () => {
    const checkbox = {checked: false, disabled: false, dispatchEvent() {}};
    const trayRow = {dataset: {code: 'LW545318163DE', status: 'ready'}, querySelector: () => checkbox};
    const app = setup('EMI', {trayRows: [trayRow]});
    const form = {fields: {q: 'LW545318163DE'}, querySelector: () => null};
    app.events.submit({target: {closest: selector => selector === '.ips-search' ? form : null}, preventDefault() {}});
    assert.equal(app.dispatched.length, 0);
    assert.equal(app.pending.length, 0);
});
