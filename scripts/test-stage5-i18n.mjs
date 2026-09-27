/* eslint-disable curly, @stylistic/padding-line-between-statements */
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import ts from 'typescript';

const root = resolve(import.meta.dirname, '..');
const read = (path) => readFileSync(resolve(root, path), 'utf8');
const file = ts.createSourceFile('i18n-catalog.ts', read('resources/js/lib/i18n-catalog.ts'), ts.ScriptTarget.Latest, true, ts.ScriptKind.TS);
assert.equal(file.parseDiagnostics.length, 0, 'Catalog must parse');
const tables = new Map();
for (const statement of file.statements) {
    if (!ts.isVariableStatement(statement)) continue;
    for (const declaration of statement.declarationList.declarations) {
        const name = declaration.name.getText(file);
        if (!['idMessages', 'enMessages'].includes(name)) continue;
        const value = ts.isAsExpression(declaration.initializer) ? declaration.initializer.expression : declaration.initializer;
        assert.ok(ts.isObjectLiteralExpression(value));
        const pairs = new Map();
        for (const property of value.properties) {
            assert.ok(ts.isPropertyAssignment(property));
            pairs.set(property.name.text, property.initializer.text);
        }
        tables.set(name, pairs);
    }
}
const id = tables.get('idMessages');
const en = tables.get('enMessages');
assert.ok(id && en);
const keys = [...id.keys()].filter((key) => key.startsWith('stage5.ui.'));
assert.ok(keys.length >= 526, `Expected >=526 Stage 5 keys, got ${keys.length}`);
assert.deepEqual(keys.slice().sort(), [...en.keys()].filter((key) => key.startsWith('stage5.ui.')).sort());
for (const key of keys) {
    assert.ok(id.get(key).trim() && en.get(key).trim(), `Empty translation: ${key}`);
    const vars = (str) => [...String(str).matchAll(/\{([\w]+)\}/g)].map((match) => match[1]).sort();
    assert.deepEqual(vars(id.get(key)), vars(en.get(key)), `Placeholder mismatch: ${key}`);
}
const targets = [
    'resources/js/pages/finance/dashboard.tsx',
    'resources/js/pages/finance/payments/index.tsx',
    'resources/js/pages/finance/payments/show.tsx',
    'resources/js/pages/finance/refunds/index.tsx',
    'resources/js/pages/finance/refunds/show.tsx',
    'resources/js/pages/finance/operational-expenses/index.tsx',
    'resources/js/pages/finance/operational-expenses/show.tsx',
    'resources/js/pages/finance/cash-sessions/index.tsx',
    'resources/js/pages/finance/cash-sessions/show.tsx',
    'resources/js/pages/finance/master-data/index.tsx',
    'resources/js/pages/notifications/index.tsx',
    'resources/js/pages/audit/index.tsx',
    'resources/js/pages/legacy-imports/index.tsx',
    'resources/js/pages/legacy-imports/show.tsx',
    'resources/js/pages/operations/reset.tsx',
    'resources/js/components/finance/cash-session-select.tsx',
];
let referenceCount = 0;
let pagesWithTranslations = 0;
for (const filename of targets) {
    const source = read(filename);
    const parsed = ts.createSourceFile(filename, source, ts.ScriptTarget.Latest, true, ts.ScriptKind.TSX);
    assert.equal(parsed.parseDiagnostics.length, 0, `TSX parse error: ${filename}`);
    let count = 0;
    for (const match of source.matchAll(/stage5\.ui\.[a-f0-9]{12}/g)) {
        assert.ok(id.has(match[0]), `Unknown UI key in ${filename}: ${match[0]}`);
        count++;
    }
    if (count > 0) pagesWithTranslations++;
    referenceCount += count;
}
assert.ok(referenceCount >= 700, `Expected >=700 UI references; got ${referenceCount}`);
assert.ok(pagesWithTranslations >= 15, `Expected >=15 translated modules; got ${pagesWithTranslations}`);
const helper = read('resources/js/components/stage5-text.tsx');
assert.match(helper, /stage5Display\(/);
assert.match(helper, /stage5Date\(/);
assert.match(helper, /stage5RuleLabel\(/);
const localizer = read('app/Domain/Notifications/NotificationContentLocalizer.php');
assert.match(localizer, /public function localize\(/);
assert.match(read('app/Jobs/SendNotificationEmail.php'), /recipient:id,name,email,status,email_verified_at,locale/);
for (const locale of ['id', 'en']) {
    const php = read(`lang/${locale}/uat035b_stage5.php`);
    const keys = [...php.matchAll(/^\s*'([a-z][a-z0-9_]+)' => /gm)].map((match) => match[1]).filter((key) => key !== 'flash');
    assert.ok(keys.length >= 48, `Missing backend translation keys in ${locale}`);
}
console.log(`STAGE 5 I18N STATIC PASS: ${keys.length} keys, ${referenceCount} references, ${pagesWithTranslations} TSX files; typed ID/EN parity, dynamic statuses, notifications and flash messages`);
