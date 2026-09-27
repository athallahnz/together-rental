/** UAT-035B Stage 3 R2 — customer detail, asset labels and branch-based access regression. */
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import vm from 'node:vm';
import ts from 'typescript';

const root = resolve(import.meta.dirname, '..');
const read = (relative) => readFileSync(resolve(root, relative), 'utf8');
const catalog = read('resources/js/lib/i18n-catalog.ts');
const parsed = ts.createSourceFile('i18n-catalog.ts', catalog, ts.ScriptTarget.Latest, true);
assert.equal(parsed.parseDiagnostics.length, 0, 'Catalog must parse');
const dict = {};

for (const statement of parsed.statements) {
    if (!ts.isVariableStatement(statement)) {
        continue;
    }

    for (const declaration of statement.declarationList.declarations) {
        const name = declaration.name.getText(parsed);

        if (name !== 'idMessages' && name !== 'enMessages') {
            continue;
        }

        const expr = ts.isAsExpression(declaration.initializer)
            ? declaration.initializer.expression
            : declaration.initializer;
        dict[name] = new Map(expr.properties.map((entry) => [entry.name.text, entry.initializer.text]));
    }
}

const id = dict.idMessages;
const en = dict.enMessages;
assert(id && en, 'Both dictionaries must exist');
assert.equal(id.size, en.size, 'ID/EN key parity');
assert.equal(id.size, 1205, 'Expected R1 + five typed R2 labels');
const labelExamples = {
    'stage3.ui.total.rental.27c39': 'Total penyewaan',
    'stage3.ui.outstanding.f8ee5': 'Sisa tagihan',
    'stage3.ui.acquisition.terbaru.f8f12': 'Perolehan terbaru',
    'stage3.ui.disposal.terbaru.7e271': 'Pelepasan terbaru',
    'stage3.ui.catat.acquisition.bc362': 'Catat perolehan',
    'stage3.ui.correction.akses.berbasis.cabang.a7198': 'Akses berbasis cabang',
    'stage3.ui.r2.customer.empty.identities': 'Identitas belum ditambahkan.',
    'stage3.ui.r2.customer.empty.addresses': 'Alamat belum ditambahkan.',
    'stage3.ui.r2.customer.empty.rentals': 'Belum ada riwayat penyewaan.',
    'stage3.ui.r2.customer.empty.bookings': 'Belum ada riwayat pemesanan.',
    'stage3.ui.r2.customer.valid.until': 'Berlaku sampai {date}',
};

for (const [key, label] of Object.entries(labelExamples)) {
    assert.equal(id.get(key), label, `${key} ID value`);
    assert(en.has(key), `${key} EN key`);
}

for (const key of id.keys()) {
    assert(en.has(key), `Missing EN key: ${key}`);
    const placeholders = (str) => [...str.matchAll(/\{([a-z][a-z0-9_]*)\}/gi)]
        .map((match) => match[1]).sort();
    assert.deepEqual(placeholders(id.get(key)), placeholders(en.get(key)), `Placeholders: ${key}`);
}

const pages = [
    'resources/js/pages/customers/show.tsx',
    'resources/js/pages/users/index.tsx',
];

for (const relative of pages) {
    const source = read(relative);
    const sf = ts.createSourceFile(relative, source, ts.ScriptTarget.Latest, true, ts.ScriptKind.TSX);
    assert.equal(sf.parseDiagnostics.length, 0, `${relative} TSX parses`);
}

const customer = read(pages[0]);
const users = read(pages[1]);
assert(customer.includes("stage3Translate('stage3.ui.correction.belum.diisi.098d4', stage3Locale)"));
assert(customer.includes("stage3Translate('stage3.ui.r2.customer.empty.identities', stage3Locale)"));
assert(customer.includes("stage3Translate('stage3.ui.r2.customer.empty.addresses', stage3Locale)"));
assert(customer.includes("stage3CustomerHistoryStatus(rental.status, 'rental', stage3Locale)"));
assert(customer.includes("stage3CustomerHistoryStatus(booking.status, 'booking', stage3Locale)"));
assert(users.includes("stage3Translate('stage3.ui.correction.akses.berbasis.cabang.a7198', stage3Locale)"));
assert(!users.includes("'Akses berbasis cabang'}"), 'Hardcoded fallback still present');

const helper = read('resources/js/lib/stage3-customer-history.ts');
const compiled = ts.transpileModule(helper, {
    fileName: 'stage3-customer-history.ts',
    compilerOptions: { target: ts.ScriptTarget.ES2022, module: ts.ModuleKind.CommonJS },
});
const exports = {};
vm.runInNewContext(compiled.outputText, { exports, Object, String }, { filename: 'stage3-customer-history.js' });
assert.equal(exports.stage3CustomerHistoryStatus('returned', 'rental', 'id'), 'Dikembalikan');
assert.equal(exports.stage3CustomerHistoryStatus('returned', 'rental', 'en'), 'Returned');
assert.equal(exports.stage3CustomerHistoryStatus('converted', 'booking', 'id'), 'Dikonversi');
assert.equal(exports.stage3CustomerHistoryStatus('cancelled', 'booking', 'en'), 'Cancelled');
assert.equal(exports.stage3CustomerHistoryStatus('future', 'booking', 'id'), 'future');
assert.equal(exports.stage3LoyaltyTier('regular', 'id'), 'Reguler');
assert.equal(exports.stage3LoyaltyTier('regular', 'en'), 'Regular');
console.log('STAGE 3 R2 PASS: 34 improved ID labels, 5 additional bilingual keys, localized customer history and branch-based access.');
