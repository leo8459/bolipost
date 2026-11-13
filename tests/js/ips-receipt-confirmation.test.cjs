const {test} = require('node:test');
const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const {join} = require('node:path');
const vm = require('node:vm');
const source = readFileSync(join(__dirname, '../../public/js/ips-receipt-confirmation.js'), 'utf8');

class Element {
    constructor(tag = 'div') { this.tag = tag; this.children = []; this.dataset = {}; this.checked = false; this.value = ''; }
    appendChild(child) { this.children.push(child); return child; }
    replaceChildren() { this.children = []; }
    querySelectorAll(selector) {
        const all = this.children.flatMap(child => [child, ...child.querySelectorAll('*')]);
        if (selector === '[data-upu-field]') return all.filter(child => child.dataset.upuField);
        if (selector === '[data-upu-read-only]') return all.filter(child => child.dataset.upuReadOnly);
        if (selector === 'fieldset[data-code]') return all.filter(child => child.tag === 'fieldset' && child.dataset.code);
        return all;
    }
}

function setup() {
    const fields = {};
    for (const id of ['ips-receipt-confirm-fields', 'ips-confirm-receipt-offices', 'ips-confirm-physical-receipt', 'ips-confirm-route-fields', 'ips-confirm-receipt-route', 'ips-confirm-upu-fields']) fields[id] = new Element();
    const selects = () => fields['ips-confirm-receipt-offices'].children.flatMap(label => label.children);
    const upuSections = () => fields['ips-confirm-upu-fields'].querySelectorAll('fieldset[data-code]');
    const dialog = {querySelector: selector => fields[selector.slice(1)], querySelectorAll: selector => selector.startsWith('#ips-confirm-upu-fields') ? upuSections() : selects()};
    const context = {window: {}, document: {createElement: tag => new Element(tag)}};
    vm.runInNewContext(source, context);
    return {api: context.window.IpsReceiptConfirmation, dialog, fields, selects, upuSections};
}

function row(options = [], extra = {}) {
    return {dataset: {code: 'LW545318163DE', receiptOffices: JSON.stringify(options), status: 'ready', routeMismatch: '0', ...extra}};
}

test('automatic parcel selection still requires explicit physical confirmation in the modal', () => {
    const app = setup();
    app.api.prepare(app.dialog, [row()]);
    assert.equal(app.fields['ips-receipt-confirm-fields'].disabled, false);
    assert.equal(app.fields['ips-receipt-confirm-fields'].hidden, false);
    assert.equal(app.fields['ips-confirm-physical-receipt'].required, true);
    assert.equal(app.fields['ips-confirm-physical-receipt'].checked, false);
    assert.equal(app.fields['ips-confirm-receipt-route'].disabled, true);
    assert.equal(app.selects().length, 0);
});

test('an ambiguous route preselects the authorized office and locks the selector', () => {
    const app = setup();
    app.api.prepare(app.dialog, [row([
        {office_cd: 2, office_name: 'LA PAZ EMS', office_fcd: 'BOLPBB'},
        {office_cd: 3, office_name: 'LA PAZ CP-AVION', office_fcd: 'BOLPBC'},
    ])], '', '3');
    const select = app.selects()[0];
    assert.equal(select.required, true);
    assert.equal(select.value, '3');
    assert.equal(select.disabled, true);
    assert.deepEqual(select.children.map(option => option.value), ['', '2', '3']);
    assert.equal(app.fields['ips-confirm-receipt-route'].required, true);
    assert.equal(app.fields['ips-confirm-receipt-route'].checked, false);
    select.value = '2';
    app.fields['ips-confirm-physical-receipt'].checked = true;
    app.fields['ips-confirm-receipt-route'].checked = true;
    const confirmation = app.api.read(app.dialog);
    assert.equal(confirmation.offices.LW545318163DE, '3');
    assert.equal(confirmation.physical, true);
    assert.equal(confirmation.routeMismatch, true);
});

test('a known package office takes precedence over the working office', () => {
    const app = setup();
    app.api.prepare(app.dialog, [row([
        {office_cd: 2, office_name: 'EMS'}, {office_cd: 3, office_name: 'CP-AVION'},
    ], {workbenchOfficeCd: '3'})], '', '2');
    assert.equal(app.selects()[0].value, '3');
});

test('the first compatible office is selected when the recorded office is outside the receipt choices', () => {
    const app = setup();
    app.api.prepare(app.dialog, [row([
        {office_cd: 2, office_name: 'EMS'}, {office_cd: 3, office_name: 'CP-AVION'},
    ], {workbenchOfficeCd: '1'})], '', '99');
    assert.equal(app.selects()[0].value, '2');
    assert.equal(app.fields['ips-confirm-physical-receipt'].checked, false);
    assert.equal(app.fields['ips-confirm-receipt-route'].checked, false);
});

test('changing the disabled select value cannot change the office submitted from the modal', () => {
    const app = setup();
    const parcel = row([{office_cd: 2, office_name: 'EMS'}, {office_cd: 3, office_name: 'CP-AVION'}]);
    app.api.prepare(app.dialog, [parcel], '', '2');
    app.selects()[0].value = '3';
    assert.equal(app.api.read(app.dialog).offices.LW545318163DE, '2');
    app.api.prepare(app.dialog, [parcel], '', '2');
    assert.equal(app.selects()[0].value, '2');
});

test('the office fixed by the server takes precedence over other receipt suggestions', () => {
    const app = setup();
    app.api.prepare(app.dialog, [row([{office_cd: 2, office_name: 'EMS'}, {office_cd: 3, office_name: 'CP-AVION'}],
        {receiptLockedOffice: '2', workbenchOfficeCd: '3'})], '', '3');
    assert.equal(app.selects()[0].value, '2');
    assert.equal(app.selects()[0].disabled, true);
});

test('pending receipt retries retain their original office instead of asking for another office', () => {
    const app = setup();
    app.api.prepare(app.dialog, [row([{office_cd: 2, office_name: 'EMS'}], {status: 'uncertain', routeMismatch: '1'})]);
    assert.equal(app.selects().length, 0);
    assert.equal(app.fields['ips-confirm-receipt-route'].required, true);
    assert.equal(app.fields['ips-confirm-physical-receipt'].checked, false);
    assert.equal(app.upuSections()[0].disabled, true);
    assert.equal(Object.keys(app.api.read(app.dialog).upu).length, 0);
});

test('delivery is not blocked by hidden reception requirements in the shared dialog', () => {
    const app = setup();
    app.api.prepare(app.dialog, [row()]);
    app.api.reset(app.dialog);
    assert.equal(app.fields['ips-receipt-confirm-fields'].disabled, true);
    assert.equal(app.fields['ips-receipt-confirm-fields'].hidden, true);
});

test('the receipt modal prefills UPU fields and keeps identity and department read-only', () => {
    const app = setup();
    app.api.prepare(app.dialog, [row([], {upuReceipt: JSON.stringify({
        destinatario: 'Destinatario IPS', telefono: '+591 70000000', zona: 'Zona Central', peso: 0.140,
    })})], 'LA PAZ');
    const section = app.upuSections()[0];
    const inputs = Object.fromEntries(section.querySelectorAll('[data-upu-field]').map(input => [input.dataset.upuField, input]));
    const fixed = Object.fromEntries(section.querySelectorAll('[data-upu-read-only]').map(input => [input.dataset.upuReadOnly, input]));
    assert.equal(inputs.destinatario.value, 'Destinatario IPS');
    assert.equal(inputs.telefono.value, '+591 70000000');
    assert.equal(inputs.zona.value, 'Zona Central');
    assert.equal(inputs.peso.value, '0.14');
    assert.equal(inputs.destinatario.required, true);
    assert.equal(inputs.peso.required, true);
    assert.equal(fixed.codigo.value, 'LW545318163DE');
    assert.equal(fixed.destino.value, 'LA PAZ');
    assert.equal(fixed.codigo.readOnly, true);
    assert.equal(fixed.destino.readOnly, true);
});

test('UPU edits are submitted per parcel and synchronized with the tray draft', () => {
    const app = setup();
    const parcel = row();
    app.api.prepare(app.dialog, [parcel], 'LA PAZ');
    const section = app.upuSections()[0];
    const values = {destinatario: 'Destinatario corregido', telefono: '70011122', zona: 'Zona Sur', peso: '0.140'};
    section.querySelectorAll('[data-upu-field]').forEach(input => { input.value = values[input.dataset.upuField]; });
    const confirmation = app.api.read(app.dialog);
    assert.deepEqual(JSON.parse(JSON.stringify(confirmation.upu.LW545318163DE)), values);
    assert.equal(confirmation.upu.LW545318163DE.codigo, undefined);
    assert.equal(confirmation.upu.LW545318163DE.destino, undefined);
    assert.equal(JSON.parse(parcel.dataset.upuReceipt).zona, 'Zona Sur');
});

test('a search opens its own confirmation immediately and serializes additional scans', () => {
    const {api} = setup();
    const first = row(), second = row([], {code: 'RR123456789DE'});
    const parcels = [first, second], opened = [];
    let blocked = false;
    const queue = api.createSearchQueue({
        find: code => parcels.find(parcel => parcel.dataset.code === code),
        isBlocked: () => blocked,
        open: parcel => opened.push(parcel),
    });
    queue.request(first.dataset.code);
    assert.deepEqual(opened, [first]);
    queue.request(first.dataset.code);
    queue.request(second.dataset.code);
    queue.request(second.dataset.code);
    assert.deepEqual(opened, [first]);
    blocked = true; // Receipt request and its result modal are still active.
    queue.complete();
    assert.deepEqual(opened, [first]);
    blocked = false;
    queue.resume();
    assert.deepEqual(opened, [first, second]);
    queue.complete();
    assert.equal(opened.length, 2);
});

test('cancelling a search confirmation allows another search for that same parcel', () => {
    const {api} = setup();
    const parcel = row(), opened = [];
    const queue = api.createSearchQueue({find: () => parcel, isBlocked: () => false, open: item => opened.push(item)});
    queue.request(parcel.dataset.code);
    queue.complete(); // Closing the modal does not invoke any receipt operation.
    queue.request(parcel.dataset.code);
    assert.deepEqual(opened, [parcel, parcel]);
});

test('queued scans wait for other dialogs and skip parcels no longer eligible', () => {
    const {api} = setup();
    const parcel = row(), opened = [];
    let blocked = true;
    const queue = api.createSearchQueue({
        find: code => code === parcel.dataset.code ? parcel : null,
        isBlocked: () => blocked,
        open: item => opened.push(item),
    });
    queue.request('REMOVED-PARCEL');
    queue.request(parcel.dataset.code);
    assert.equal(opened.length, 0);
    blocked = false;
    queue.resume();
    assert.deepEqual(opened, [parcel]);
});
