const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const vm = require('node:vm');
const source = readFileSync(`${__dirname}/../../public/js/session-inactivity.js`, 'utf8');
const limit = 7200000;
const key = 'bolipost:last-activity:test';

function setup(storage = new Map(), start = 10000000, unavailable = false) {
    let now = start;
    const listeners = {}, timers = new Map(), redirects = [];
    let id = 0;
    const listen = (name, callback) => (listeners[name] ??= []).push(callback);
    vm.runInNewContext(source, {
        Date: { now: () => now },
        document: {
            currentScript: { dataset: { sessionKey: 'test', logoutUrl: '/logout?motivo=inactividad' } },
            addEventListener: listen,
        },
        window: {
            addEventListener: listen,
            localStorage: {
                getItem: name => { if (unavailable) throw Error(); return storage.get(name) ?? null; },
                setItem: (name, value) => { if (unavailable) throw Error(); storage.set(name, value); },
            },
            setTimeout: (callback, delay) => { timers.set(++id, { callback, at: now + delay }); return id; },
            clearTimeout: id => timers.delete(id),
            location: { replace: url => redirects.push(url) },
        },
    });
    return {
        redirects, storage,
        advance(ms) { now += ms; },
        fire(name, event = {}) { for (const fn of listeners[name] ?? []) fn({ isTrusted: true, ...event }); },
        tick() { for (const [id, timer] of [...timers]) if (timer.at <= now) { timers.delete(id); timer.callback(); } },
    };
}

test('logs out exactly after two idle hours', () => {
    const app = setup();
    app.advance(limit - 1); app.tick();
    assert.equal(app.redirects.length, 0);
    app.advance(1); app.tick();
    assert.deepEqual(app.redirects, ['/logout?motivo=inactividad']);
});

test('real input extends the deadline, automatic events do not', () => {
    const app = setup();
    app.advance(limit / 2); app.fire('keydown');
    app.advance(limit / 2); app.tick();
    assert.equal(app.redirects.length, 0);
    app.fire('pointermove', { isTrusted: false });
    app.advance(limit / 2); app.tick();
    assert.equal(app.redirects.length, 1);
});

test('focus, visibility and input after sleeping cannot revive an expired session', () => {
    for (const event of ['focus', 'visibilitychange', 'pageshow', 'keydown']) {
        const app = setup();
        app.advance(limit + 1000); app.fire(event);
        assert.equal(app.redirects.length, 1, event);
    }
});

test('navigation preserves idle time and a new session has fresh storage', () => {
    const app = setup();
    const next = setup(app.storage, 10000000 + limit);
    assert.equal(next.redirects.length, 1);
    assert.equal(setup(new Map(), 10000000 + limit).redirects.length, 0);
});

test('activity and logout synchronize across tabs', () => {
    const storage = new Map();
    const first = setup(storage), second = setup(storage);
    first.advance(limit / 2); first.fire('click');
    second.advance(limit); second.tick();
    assert.equal(second.redirects.length, 0);
    first.advance(limit); first.tick();
    second.fire('storage', { key: key + ':logout' });
    assert.equal(first.redirects.length, 1);
    assert.equal(second.redirects.length, 1);
});

test('still expires when browser storage is blocked', () => {
    const app = setup(new Map(), 10000000, true);
    app.advance(limit); app.tick();
    assert.equal(app.redirects.length, 1);
});
