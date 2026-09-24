const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const vm = require('node:vm');
const source = readFileSync(join(__dirname, '../../public/js/ips-listing.js'), 'utf8');
const tick = () => new Promise(resolve => setImmediate(resolve));

function setup() {
    const events = {}, pending = [], history = [];
    const listing = {
        innerHTML: 'previous results',
        addEventListener: (name, fn) => { events[name] = fn; },
        setAttribute() {}, removeAttribute() {},
    };
    const status = {hidden: true, textContent: ''};
    vm.runInNewContext(source, {
        document: {getElementById: id => id === 'ips-listing' ? listing : status},
        location: new URL('http://localhost/ips'), URL, URLSearchParams, AbortController,
        history: {pushState: (_state, _title, url) => history.push(url)},
        window: {addEventListener() {}},
        fetch: (url, options) => new Promise(resolve => pending.push({url, options, resolve})),
    });
    const click = query => events.click({
        target: {closest: () => ({href: 'http://localhost/ips?' + query})},
        button: 0, preventDefault() {},
    });
    const respond = (index, data, ok = true) => pending[index].resolve({
        ok, headers: {get: () => 'application/json'}, json: async () => data,
    });
    return {events, listing, status, pending, history, click, respond};
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
    assert.match(app.status.textContent, /IPS unavailable/);
    assert.equal(app.status.hidden, false);
});

test('selection and delivery forms are not intercepted by list navigation', () => {
    const app = setup();
    app.events.submit({target: {closest: () => null}, preventDefault: () => assert.fail('intercepted operation')});
    assert.equal(app.pending.length, 0);
});
