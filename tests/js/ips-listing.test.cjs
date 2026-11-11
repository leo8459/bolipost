const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const vm = require('node:vm');
const source = readFileSync(join(__dirname, '../../public/js/ips-listing.js'), 'utf8');
const tick = () => new Promise(resolve => setImmediate(resolve));

function setup(workflowAction = '') {
    const events = {}, windowEvents = {}, pending = [], history = [];
    const workspace = {dataset: {workflowAction, scanEnabled: workflowAction ? '1' : '0'}};
    const dialog = {open: false, showModal() { this.open = true; }};
    const modalCode = {textContent: ''};
    const listing = {
        innerHTML: 'previous results',
        addEventListener: (name, fn) => { events[name] = fn; },
        setAttribute() {}, removeAttribute() {},
        querySelector: () => null,
        querySelectorAll: () => [],
        classList: {add() {}, remove() {}},
    };
    const status = {hidden: true, textContent: ''};
    vm.runInNewContext(source, {
        document: {
            getElementById: id => ({'ips-listing': listing, 'ips-listing-status': status, 'ips-already-received': dialog, 'ips-already-received-code': modalCode}[id] || null),
            querySelector: selector => selector === '.ips-workspace' ? workspace : null,
            querySelectorAll: () => [],
        },
        FormData: class {
            constructor(form) { this.fields = new Map(Object.entries(form?.fields || {})); }
            get(key) { return this.fields.get(key); }
            append(key, value) { this.fields.set(key, value); }
        },
        location: new URL('http://localhost/ips'), URL, URLSearchParams, AbortController,
        history: {pushState: (_state, _title, url) => history.push(url)},
        window: {addEventListener: (name, fn) => { windowEvents[name] = fn; }},
        fetch: (url, options) => new Promise(resolve => pending.push({url, options, resolve})),
    });
    const click = query => events.click({
        target: {closest: selector => selector === '.ips-already-received' ? null : ({href: 'http://localhost/ips?' + query})},
        button: 0, preventDefault() {},
    });
    const respond = (index, data, ok = true) => pending[index].resolve({
        ok, status: ok ? 200 : 409, headers: {get: () => 'application/json'}, json: async () => data,
    });
    return {events, windowEvents, listing, status, pending, history, click, respond, dialog, modalCode};
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
