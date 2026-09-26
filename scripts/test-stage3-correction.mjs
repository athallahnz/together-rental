/** UAT-035B Stage 3 — targeted correction regression; UI source and display helpers only. */
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import vm from 'node:vm';
import ts from 'typescript';

const root = resolve(import.meta.dirname, '..');
const read = (relative) => readFileSync(resolve(root, relative), 'utf8');
const sourceFiles = [
    'resources/js/pages/catalog/index.tsx',
    'resources/js/components/catalog/catalog-dialogs.tsx',
    'resources/js/pages/customers/index.tsx',
    'resources/js/components/customers/customer-form-dialog.tsx',
    'resources/js/pages/assets/lifecycle.tsx',
    'resources/js/pages/branches/index.tsx',
    'resources/js/pages/employees/index.tsx',
    'resources/js/pages/users/index.tsx',
    'resources/js/pages/roles/index.tsx',
    'resources/js/components/access-nav.tsx',
];

for (const relative of sourceFiles) {
    const source = read(relative);
    const parsed = ts.createSourceFile(relative, source, ts.ScriptTarget.Latest, true, ts.ScriptKind.TSX);
    assert.equal(parsed.parseDiagnostics.length, 0, `${relative} TSX syntax`);
}

const catalog = read('resources/js/lib/i18n-catalog.ts');
const sf = ts.createSourceFile('i18n-catalog.ts', catalog, ts.ScriptTarget.Latest, true);
const dictionaries = {};

for (const statement of sf.statements) {
    if (!ts.isVariableStatement(statement)) {
continue;
}

    for (const declaration of statement.declarationList.declarations) {
        const name = declaration.name.getText(sf);

        if (name !== 'idMessages' && name !== 'enMessages') {
continue;
}

        const init = ts.isAsExpression(declaration.initializer)
            ? declaration.initializer.expression
            : declaration.initializer;
        dictionaries[name] = new Map(init.properties.map((entry) => [entry.name.text, entry.initializer.text]));
    }
}

const id = dictionaries.idMessages;
const en = dictionaries.enMessages;
assert(id && en, 'Both locale dictionaries exist');
assert.equal(id.size, en.size, 'Dictionary parity');
const keys = [...id.keys()].filter((key) => key.startsWith('stage3.ui.correction.'));
assert(keys.length >= 160, 'Correction key count');

for (const key of keys) {
    assert(en.has(key), `Missing EN key: ${key}`);
    assert.deepEqual(
        [...id.get(key).matchAll(/\{([a-z][a-z0-9_]*)\}/gi)].map((m) => m[1]).sort(),
        [...en.get(key).matchAll(/\{([a-z][a-z0-9_]*)\}/gi)].map((m) => m[1]).sort(),
        `Placeholder mismatch: ${key}`,
    );
}

for (const [relative, marker] of [
    ['resources/js/components/catalog/catalog-dialogs.tsx', 'correction.tambah.produk'],
    ['resources/js/components/customers/customer-form-dialog.tsx', 'correction.tambah.pelanggan'],
    ['resources/js/pages/branches/index.tsx', 'correction.tambah.cabang.baru'],
    ['resources/js/pages/employees/index.tsx', 'correction.tambah.jabatan'],
    ['resources/js/pages/users/index.tsx', 'correction.tambah.pengguna'],
    ['resources/js/pages/roles/index.tsx', 'correction.buat.role.khusus'],
]) {
    assert(read(relative).includes(marker), `Missing localized modal title: ${relative}`);
}

assert(read('resources/js/components/access-nav.tsx').includes('stage3Translate(item.titleKey'), 'Admin tabs localized');
assert(!read('resources/js/pages/assets/lifecycle.tsx').includes('{row.acquisition_date}'), 'Acquisition date formatted');
assert(!read('resources/js/pages/assets/lifecycle.tsx').includes('{asset.status}'), 'Asset statuses localized');
assert(read('resources/js/pages/roles/index.tsx').includes('stage3PermissionModule(module, stage3Locale)'), 'Role module labels localized');
assert(read('resources/js/pages/catalog/index.tsx').includes("label === stage3Translate('stage3.ui.correction.belum.punya.harga"), 'Metric severity independent of locale');

const tsSource = read('resources/js/lib/stage3-display.ts');
const compiled = ts.transpileModule(tsSource, {
    fileName: 'stage3-display.ts',
    compilerOptions: { target: ts.ScriptTarget.ES2022, module: ts.ModuleKind.CommonJS },
});
const exports = {};
vm.runInNewContext(compiled.outputText, { exports, Intl, Date, Number, Object, String }, {
    filename: 'stage3-display.js',
});
assert.equal(exports.stage3AssetStatus('available', 'id'), 'Tersedia');
assert.equal(exports.stage3AssetStatus('available', 'en'), 'Available');
assert.equal(exports.stage3DisposalMethod('sold', 'id'), 'Dijual');
assert.equal(exports.stage3DisposalMethod('sold', 'en'), 'Sold');
assert.equal(exports.stage3AssetStatus('future_status', 'id'), 'future_status');
assert.equal(exports.formatStage3Date('2026-09-22T17:00:00.000000Z', 'id'), '23 September 2026');
assert.equal(exports.formatStage3Date('2026-09-23', 'en'), '23 September 2026');
assert.equal(exports.stage3PermissionModule('branches', 'id'), 'Cabang');
assert.equal(exports.stage3PermissionModule('branches', 'en'), 'Branches');
assert.equal(exports.stage3PermissionName('assets.inspect', 'Inspect asset condition', 'id'), 'Periksa kondisi aset');
assert.equal(exports.stage3PermissionName('assets.inspect', 'Inspect asset condition', 'en'), 'Inspect asset condition');

console.log(`STAGE 3 CORRECTION STATIC PASS: ${sourceFiles.length} TSX files, ${keys.length} typed correction keys, localized titles/tabs/status/permission/date checks`);
