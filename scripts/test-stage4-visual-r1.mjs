/* eslint-disable curly, @stylistic/padding-line-between-statements */
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import ts from 'typescript';

const root = resolve(import.meta.dirname, '..');
const read = (path) => readFileSync(resolve(root, path), 'utf8');
const catalog = ts.createSourceFile('i18n-catalog.ts', read('resources/js/lib/i18n-catalog.ts'), ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);
const languages = new Map();

for (const statement of catalog.statements) {
    if (!ts.isVariableStatement(statement)) continue;
    for (const declaration of statement.declarationList.declarations) {
        const name = declaration.name.getText(catalog);
        if (name !== 'idMessages' && name !== 'enMessages') continue;
        const initializer = ts.isAsExpression(declaration.initializer) ? declaration.initializer.expression : declaration.initializer;
        assert(ts.isObjectLiteralExpression(initializer), `Invalid catalog object: ${name}`);
        const values = new Map();
        for (const property of initializer.properties) {
            if (!ts.isPropertyAssignment(property)) continue;
            const key = property.name.text;
            assert(!values.has(key), `Duplicate translation key: ${name}:${key}`);
            values.set(key, property.initializer.text);
        }
        languages.set(name, values);
    }
}

const id = languages.get('idMessages');
const en = languages.get('enMessages');
assert(id && en);
assert.equal(id.size, en.size, 'ID/EN catalog parity');
assert.equal([...id.keys()].filter((key) => key.startsWith('stage4.ui.')).length, 623, 'Unexpected Stage 4 catalog baseline');
for (const key of id.keys()) assert(en.has(key), `Missing English key: ${key}`);

const knownPairs = [
    ['stage4.ui.23d33e22acfc', 'Draf', 'Draft'],
    ['stage4.ui.41b81eb8db1b', 'Disetujui', 'Approved'],
    ['stage4.ui.046e5cee819c', 'Dalam pengiriman', 'In transit'],
    ['stage4.ui.001d34d78ee8', 'Selisih', 'Discrepancy'],
    ['stage4.ui.2c2ba8388f81', 'Estimasi saldo setelah perpanjangan', 'Estimated balance after extension'],
    ['stage4.ui.53dcc7737065', 'Faktur, Nota & Perjanjian', 'Invoices, Receipts & Agreements'],
    ['stage4.ui.f9f38818c406', 'Faktur', 'Invoice'],
    ['stage4.ui.c8fee8eabe07', 'Perjanjian', 'Agreement'],
    ['stage4.ui.ee21c3756070', 'Perjanjian sewa', 'Rental agreement'],
    ['stage4.ui.348e3295d626', 'Pencarian operasional', 'Operational search'],
];

for (const [key, idLabel, enLabel] of knownPairs) {
    assert.equal(id.get(key), idLabel, `Incorrect ID: ${key}`);
    assert.equal(en.get(key), enLabel, `Incorrect EN: ${key}`);
}

const targets = [
    'resources/js/components/bookings/search-picker-dialog.tsx',
    'resources/js/components/pagination-links.tsx',
    'resources/js/components/stage4-text.tsx',
    'resources/js/pages/bookings/form.tsx',
    'resources/js/pages/bookings/show.tsx',
    'resources/js/pages/bookings/index.tsx',
    'resources/js/pages/rentals/index.tsx',
    'resources/js/pages/rentals/checkout.tsx',
    'resources/js/pages/rentals/show.tsx',
    'resources/js/pages/rentals/return.tsx',
    'resources/js/pages/transfers/index.tsx',
    'resources/js/pages/transfers/form.tsx',
    'resources/js/pages/transfers/settings.tsx',
    'resources/js/pages/transfers/show.tsx',
];
for (const target of targets) {
    const source = read(target);
    const tree = ts.createSourceFile(target, source, ts.ScriptTarget.Latest, true, ts.ScriptKind.TSX);
    assert.equal(tree.parseDiagnostics.length, 0, `Invalid TSX: ${target}`);
}

const picker = read(targets[0]);
assert(picker.includes("{ customer: 'customer', product: 'product', package: 'package' }[type]"), 'Search picker EN placeholders must be localized');
const pagination = read(targets[1]);
assert(pagination.includes('localizeNavigation(link.label)'), 'Laravel pagination Previous/Next not localized');
assert(pagination.includes("locale === 'en' ? 'Showing' : 'Menampilkan'"), 'Pagination summary not localized');
const helper = read(targets[2]);
assert(helper.includes('stage4RawDisplayKeys[value.toLowerCase()]'), 'Raw backend statuses are not translated');
assert(helper.includes('stage4ItemCount'), 'Item count formatting missing');
const settings = read('resources/js/pages/transfers/settings.tsx');
assert(!settings.includes("label: 'Kamera wajib'"), 'Transfer camera option is still hardcoded');
assert(settings.includes('stage4Translate(option.label'), 'Camera mode labels are not localized');
const booking = read('resources/js/pages/bookings/show.tsx');
assert(booking.includes('stage4TranslateDynamic(history.to_status, stage4Locale)'), 'Booking status history still exposes raw statuses');
const rental = read('resources/js/pages/rentals/show.tsx');
assert(rental.includes('stage4TranslateDynamic(rental.status, stage4Locale)'), 'Rental detail still exposes raw status');
const transfers = read('resources/js/pages/transfers/index.tsx');
assert(transfers.includes('stage4ItemCount(transfer.items_count ?? 0, stage4Locale)'), 'Transfer item count spacing/pluralization missing');
console.log(`STAGE 4 VISUAL R1 STATIC PASS: ${targets.length} TSX files, ${knownPairs.length} critical catalog pairs, 623 Stage 4 keys, ID/EN parity.`);
