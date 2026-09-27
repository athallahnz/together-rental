/* eslint-disable curly, @stylistic/padding-line-between-statements */
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import ts from 'typescript';

const root = resolve(import.meta.dirname, '..');
const read = (filename) => readFileSync(resolve(root, filename), 'utf8');
const targets = [
    'resources/js/components/stage5-text.tsx',
    'resources/js/pages/audit/index.tsx',
    'resources/js/pages/finance/cash-sessions/index.tsx',
    'resources/js/pages/finance/cash-sessions/show.tsx',
    'resources/js/pages/finance/dashboard.tsx',
    'resources/js/pages/finance/master-data/index.tsx',
    'resources/js/pages/finance/operational-expenses/index.tsx',
    'resources/js/pages/finance/operational-expenses/show.tsx',
    'resources/js/pages/finance/payments/index.tsx',
    'resources/js/pages/finance/payments/show.tsx',
    'resources/js/pages/finance/refunds/index.tsx',
    'resources/js/pages/finance/refunds/show.tsx',
    'resources/js/pages/legacy-imports/index.tsx',
    'resources/js/pages/legacy-imports/show.tsx',
    'resources/js/pages/notifications/index.tsx',
    'resources/js/pages/operations/reset.tsx',
];

const parsed = new Map();
for (const filename of targets) {
    const source = ts.createSourceFile(filename, read(filename), ts.ScriptTarget.Latest, true, ts.ScriptKind.TSX);
    assert.equal(source.parseDiagnostics.length, 0, `TSX parse error: ${filename}`);
    parsed.set(filename, source);
}

// Duplicate object keys caused TS1117 in the generated display-label dictionary.
const helper = parsed.get(targets[0]);
let knownLabelsCount = 0;
for (const statement of helper.statements) {
    if (!ts.isVariableStatement(statement)) continue;
    for (const declaration of statement.declarationList.declarations) {
        if (declaration.name.getText(helper) !== 'knownLabels') continue;
        knownLabelsCount++;
        const object = declaration.initializer;
        assert.ok(ts.isObjectLiteralExpression(object));
        const names = new Set();
        for (const property of object.properties) {
            assert.ok(ts.isPropertyAssignment(property));
            const name = property.name.text;
            assert.ok(!names.has(name), `Duplicate display label: ${name}`);
            names.add(name);
        }
        assert.ok(names.has('Expense') && names.has('Legacy Import'));
    }
}
assert.equal(knownLabelsCount, 1);

for (const filename of targets.slice(1)) {
    const source = parsed.get(filename);
    const imports = source.statements.filter(ts.isImportDeclaration);
    const moduleName = (declaration) => declaration.moduleSpecifier.text;
    const stage5 = imports.filter((item) => moduleName(item) === '@/components/stage5-text');
    assert.equal(stage5.length, 1, `Missing/duplicate Stage 5 import: ${filename}`);
    const stage5Position = stage5[0].pos;
    for (const item of imports) {
        if (!moduleName(item).startsWith('@/')) {
            assert.ok(item.pos < stage5Position, `External import must precede Stage 5: ${filename}`);
        }
    }
}

const expense = parsed.get('resources/js/pages/finance/operational-expenses/show.tsx');
const expenseImports = expense.statements.filter(ts.isImportDeclaration);
const presentation = expenseImports.find((item) => item.moduleSpecifier.text === '@/components/stage5-text');
assert.ok(presentation.importClause.namedBindings.elements.some((item) => item.name.text === 'stage5Display'), 'Missing expense stage5Display import');

const notifications = read('resources/js/pages/notifications/index.tsx');
assert.match(notifications, /import type \{ FormEvent, ReactNode \} from 'react';/);
assert.match(notifications, /function TabButton\([\s\S]*?children: ReactNode;/);

const helperCode = read('resources/js/components/stage5-text.tsx');
for (const pattern of [
    /const label = statusLabels\[value\.toLowerCase\(\)\];\n\n {4}return/,
    /const rule = notificationRules\[code\];\n\n {4}if/,
    /const \[indonesian, english\] = rule\[field\];\n\n {4}return/,
    /const action = auditActions\[components\[components\.length - 1\]\];\n\n {4}return/,
]) {
    assert.match(helperCode, pattern, 'ESLint padding requirement');
}
console.log(`STAGE 5 COMPILER FIX STATIC PASS: ${targets.length} TSX files parsed, 2 duplicate keys removed, expense helper and notification children corrected, import order and spacing checked.`);
