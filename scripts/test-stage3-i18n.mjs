import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import ts from 'typescript';

const root = resolve(import.meta.dirname, '..');
const read = (name) => readFileSync(resolve(root, name), 'utf8');
const source = read('resources/js/lib/i18n-catalog.ts');
const file = ts.createSourceFile('i18n-catalog.ts', source, ts.ScriptTarget.Latest, true);
const catalogs = new Map();

for (const statement of file.statements) {
    if (!ts.isVariableStatement(statement)) {
continue;
}

    for (const declaration of statement.declarationList.declarations) {
        const name = declaration.name.getText(file);

        if (name !== 'idMessages' && name !== 'enMessages') {
continue;
}

        const node = ts.isAsExpression(declaration.initializer)
            ? declaration.initializer.expression : declaration.initializer;
        const entries = new Map();

        for (const item of node.properties) {
            const key = item.name.text;
            assert(!entries.has(key), `Duplicate key: ${key}`);
            entries.set(key, item.initializer.text);
        }

        catalogs.set(name, entries);
    }
}

const id = catalogs.get('idMessages');
const en = catalogs.get('enMessages');
assert(id && en, 'Missing base catalog');
const keys = [...id.keys()].filter((key) => key.startsWith('stage3.ui.'));
assert(keys.length >= 450, 'Unexpectedly small Stage 3 catalog');
const vars = (value) => [...value.matchAll(/\{([a-zA-Z][a-zA-Z0-9_]*)\}/g)]
    .map((match) => match[1]).sort();

for (const key of keys) {
    assert(en.has(key), `Missing EN key: ${key}`);
    assert(id.get(key).trim() && en.get(key).trim(), `Blank translation: ${key}`);
    assert.deepEqual(vars(id.get(key)), vars(en.get(key)), `Placeholder mismatch: ${key}`);
}

const files = [
    'resources/js/pages/catalog/index.tsx',
    'resources/js/pages/catalog/product-show.tsx',
    'resources/js/pages/catalog/package-show.tsx',
    'resources/js/components/catalog/catalog-dialogs.tsx',
    'resources/js/pages/customers/index.tsx',
    'resources/js/pages/customers/show.tsx',
    'resources/js/components/customers/customer-form-dialog.tsx',
    'resources/js/pages/assets/lifecycle.tsx',
    'resources/js/pages/branches/index.tsx',
    'resources/js/pages/branches/public-profile.tsx',
    'resources/js/pages/employees/index.tsx',
    'resources/js/pages/users/index.tsx',
    'resources/js/pages/roles/index.tsx',
];
let references = 0;

for (const relative of files) {
    const code = read(relative);
    const ast = ts.createSourceFile(relative, code, ts.ScriptTarget.Latest, true, ts.ScriptKind.TSX);
    assert.equal(ast.parseDiagnostics.length, 0, `${relative}: TSX parse error`);
    assert(code.includes('stage3.ui.'), `${relative}: no Stage 3 translations`);

    for (const match of code.matchAll(/stage3\.ui\.[a-z0-9.]+/g)) {
        assert(id.has(match[0]) && en.has(match[0]), `${relative}: unknown key ${match[0]}`);
        references++;
    }
}

assert(references >= 600, `Missing localized references: ${references}`);
console.log(`STAGE 3 I18N STATIC PASS: ${keys.length} keys, ${references} references, 13 files; ID/EN parity, placeholder matching and TSX parsing`);
