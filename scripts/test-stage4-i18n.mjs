/* eslint-disable curly, @stylistic/padding-line-between-statements */
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import ts from 'typescript';

const root = resolve(import.meta.dirname, '..');
const read = (path) => readFileSync(resolve(root, path), 'utf8');
const file = ts.createSourceFile('i18n-catalog.ts', read('resources/js/lib/i18n-catalog.ts'), ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);
const catalog = new Map();

for (const statement of file.statements) {
    if (!ts.isVariableStatement(statement)) continue;
    for (const decl of statement.declarationList.declarations) {
        const name = decl.name.getText(file);
        if (!['idMessages', 'enMessages'].includes(name)) continue;
        const node = ts.isAsExpression(decl.initializer) ? decl.initializer.expression : decl.initializer;
        const values = new Map();
        for (const property of node.properties) {
            const key = property.name.text;
            assert(!values.has(key), `Duplicate catalog key: ${key}`);
            values.set(key, property.initializer.text);
        }
        catalog.set(name, values);
    }
}

const id = catalog.get('idMessages');
const en = catalog.get('enMessages');
assert(id && en, 'Missing i18n catalogs');
const stage4 = [...id.keys()].filter((key) => key.startsWith('stage4.ui.'));
assert(stage4.length >= 570, `Only ${stage4.length} Stage 4 keys`);
const variables = (msg) => [...msg.matchAll(/\{([a-zA-Z][a-zA-Z0-9_]*)\}/g)].map((item) => item[1]).sort();

for (const key of stage4) {
    assert(en.has(key), `Missing EN key: ${key}`);
    assert(id.get(key).trim() && en.get(key).trim(), `Empty translation for ${key}`);
    assert.deepEqual(variables(id.get(key)), variables(en.get(key)), `Placeholder mismatch ${key}`);
}

const pages = [
    'pages/bookings/index.tsx', 'pages/bookings/form.tsx', 'pages/bookings/show.tsx',
    'pages/rentals/index.tsx', 'pages/rentals/checkout.tsx', 'pages/rentals/show.tsx',
    'pages/rentals/extend.tsx', 'pages/rentals/return.tsx',
    'pages/transfers/index.tsx', 'pages/transfers/form.tsx', 'pages/transfers/show.tsx',
    'pages/transfers/settings.tsx', 'pages/documents/index.tsx',
    'components/bookings/search-picker-dialog.tsx',
    'components/documents/transaction-document-actions.tsx',
    'components/rentals/collateral-fields.tsx',
    'components/transfers/transfer-action-dialog.tsx',
    'components/transfers/transfer-camera-dialog.tsx',
];
let references = 0;
for (const path of pages) {
    const name = `resources/js/${path}`;
    const source = read(name);
    const ast = ts.createSourceFile(name, source, ts.ScriptTarget.Latest, true, ts.ScriptKind.TSX);
    assert.equal(ast.parseDiagnostics.length, 0, `Invalid TSX in ${name}`);
    assert(source.includes('stage4.ui.'), `No typed Stage 4 translations in ${name}`);
    for (const match of source.matchAll(/stage4\.ui\.[a-z0-9]+/g)) {
        assert(id.has(match[0]) && en.has(match[0]), `Unrecognized key: ${name}:${match[0]}`);
        references++;
    }
}
assert(references >= 700, `Only ${references} Stage 4 references`);

const helper = read('resources/js/components/stage4-text.tsx');
assert(helper.includes('timeZone: \'Asia/Jakarta\''), 'Operational date-time locale missing');
assert(helper.includes('stage4TranslateDynamic'), 'Dynamic status label translation missing');
const pdf = read('app/Domain/Documents/TransactionDocumentPdfRenderer.php');
assert(pdf.includes("trans('uat035b_stage4.pdf')"), 'Actual PDF renderer lacks translations');
assert(pdf.includes("$this->l('INVOICE')"), 'Actual PDF document titles are not translated');
console.log(`STAGE 4 I18N STATIC PASS: ${stage4.length} keys, ${references} references, ${pages.length} TSX files, dynamic statuses, dates and live PDF renderer`);
