const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const vm = require('node:vm');
const source = readFileSync(join(__dirname, '../../public/js/browser-restrictions.js'), 'utf8');

function setup() {
    const listeners = {}, attributes = {}, classes = new Set();
    const context = vm.createContext({
        document: { documentElement: {
            setAttribute: (key, value) => { attributes[key] = value; },
            classList: { add: value => classes.add(value) },
        } },
        window: { addEventListener: (name, fn) => { (listeners[name] ??= []).push(fn); } },
    });
    const run = () => vm.runInContext(source, context);
    run();
    const dispatch = (name, options = {}) => {
        const event = {
            prevented: false, stopped: false,
            preventDefault() { this.prevented = true; },
            stopImmediatePropagation() { this.stopped = true; },
            ...options,
        };
        for (const listener of listeners[name]) listener(event);
        return event;
    };
    return { attributes, classes, listeners, run, dispatch };
}

test('sets inherited translation hints and installs listeners only once', () => {
    const app = setup();
    app.run();
    assert.equal(app.attributes.translate, 'no');
    assert.ok(app.classes.has('notranslate'));
    assert.equal(app.listeners.contextmenu.length, 1);
    assert.equal(app.listeners.keydown.length, 1);
});

test('cancels context menu and common Windows/macOS inspection shortcuts', () => {
    const app = setup();
    assert.equal(app.dispatch('contextmenu').prevented, true);
    for (const options of [
        { key: 'F12' },
        ...['I', 'j', 'c'].map(key => ({ key, ctrlKey: true, shiftKey: true })),
        ...['i', 'J', 'c'].map(key => ({ key, metaKey: true, altKey: true })),
    ]) {
        const event = app.dispatch('keydown', options);
        assert.equal(event.prevented, true);
        assert.equal(event.stopped, true);
    }
});

test('preserves typing, copy/paste, printing, search and keyboard navigation', () => {
    const app = setup();
    for (const options of [
        ...['a', 'c', 'v', 'x', 'p', 'f'].flatMap(key => [
            { key, ctrlKey: true }, { key, metaKey: true },
        ]),
        ...['i', 'j', 'c', 'Tab', 'Enter', 'Escape', 'ArrowDown'].map(key => ({ key })),
        { key: 'Tab', shiftKey: true },
    ]) {
        const event = app.dispatch('keydown', options);
        assert.equal(event.prevented, false);
        assert.equal(event.stopped, false);
    }
});
