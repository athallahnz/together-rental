/* eslint-disable curly, @stylistic/padding-line-between-statements */
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import ts from 'typescript';

const root = resolve(import.meta.dirname, '..');
const read = (name) => readFileSync(resolve(root, name), 'utf8');
const pages = [
    'finance/dashboard', 'finance/master-data/index',
    'finance/operational-expenses/index', 'finance/operational-expenses/show',
    'finance/payments/index', 'finance/payments/show',
    'finance/refunds/index', 'finance/refunds/show',
    'finance/cash-sessions/index', 'finance/cash-sessions/show',
    'notifications/index', 'audit/index',
    'legacy-imports/index', 'legacy-imports/show', 'operations/reset',
];
const helpers = new Map([
    ['stage5Translate', 1], ['stage5Display', 1],
    ['stage5Choice', 2], ['stage5Date', 1],
    ['stage5Number', 1], ['stage5IntlLocale', 0],
    ['stage5RuleLabel', 3], ['stage5PaginatorLabel', 1],
    ['stage5Money', 1],
]);
let explicit = 0;
let localizedComponents = 0;
for (const name of pages) {
    const filename = `resources/js/pages/${name}.tsx`;
    const content = read(filename);
    const parsed = ts.createSourceFile(filename, content, ts.ScriptTarget.Latest, true, ts.ScriptKind.TSX);
    assert.equal(parsed.parseDiagnostics.length, 0, `${filename}: invalid TSX`);
    assert.match(content, /import \{ useAppLocale \} from '@\/lib\/i18n';/, `${filename}: missing locale hook import`);
    const componentCalls = new Map();
    function walk(node, owner) {
        if (ts.isFunctionDeclaration(node) && node.parent.kind === ts.SyntaxKind.SourceFile) {
            owner = node.name?.text ?? '';
        }
        if (ts.isCallExpression(node) && ts.isIdentifier(node.expression) && helpers.has(node.expression.text) &&
            owner && /^[A-Z]/.test(owner)) {
            const defaultArity = helpers.get(node.expression.text);
            assert.equal(node.arguments.length, defaultArity + 1, `${filename}: ${owner}.${node.expression.text} must pass explicit locale`);
            const locale = node.arguments[node.arguments.length - 1];
            assert.equal(locale.getText(parsed), 'stage5Locale', `${filename}: ${owner}.${node.expression.text} has wrong locale`);
            componentCalls.set(owner, (componentCalls.get(owner) ?? 0) + 1);
            explicit++;
        }
        ts.forEachChild(node, (child) => walk(child, owner));
    }
    walk(parsed, '');
    for (const [owner] of componentCalls) {
        const declaration = parsed.statements.find((node) => ts.isFunctionDeclaration(node) && node.name?.text === owner);
        assert.ok(declaration?.body, `${filename}: missing ${owner} body`);
        const first = declaration.body.statements[0];
        assert.match(first?.getText(parsed) ?? '', /locale: stage5Locale.*useAppLocale\(\)/, `${filename}: ${owner} must read page locale before other logic`);
        localizedComponents++;
    }
}
const master = read('resources/js/pages/finance/master-data/index.tsx');
for (const phrase of ['Add payment method', 'Add finance category', 'Add cash register',
    'Bank transfer', 'Income', 'Cash registers & sessions']) {
    const file = phrase === 'Cash registers & sessions' ? read('resources/js/lib/i18n-catalog.ts') : master;
    if (['Bank transfer', 'Income'].includes(phrase)) continue;
    assert.ok(file.includes(phrase), `Missing localized visual text: ${phrase}`);
}
assert.match(master, /stage5Display\(methodTypeLabels\[method\.type\], stage5Locale\)/);
assert.match(master, /stage5Display\(categoryTypeLabels\[category\.type\], stage5Locale\)/);
assert.match(master, /stage5Display\(label, stage5Locale\)/);
const helper = read('resources/js/components/stage5-text.tsx');
assert.match(helper, /"Transfer bank": \{ id: 'Transfer bank', en: 'Bank transfer' \}/);
assert.match(helper, /"Pendapatan": \{ id: 'Pendapatan', en: 'Income' \}/);
assert.match(helper, /"Nonaktifkan": \{ id: 'Nonaktifkan', en: 'Deactivate' \}/);
const helperAst = ts.createSourceFile('stage5-text.tsx', helper, ts.ScriptTarget.Latest, true, ts.ScriptKind.TSX);
assert.equal(helperAst.parseDiagnostics.length, 0);
for (const statement of helperAst.statements) {
    if (!ts.isVariableStatement(statement)) continue;
    for (const declaration of statement.declarationList.declarations) {
        if (declaration.name.getText(helperAst) !== 'knownLabels') continue;
        const names = new Set();
        for (const property of declaration.initializer.properties) {
            const name = property.name.text;
            assert.ok(!names.has(name), `Duplicate display key: ${name}`);
            names.add(name);
        }
    }
}
const notifications = read('resources/js/pages/notifications/index.tsx');
assert.match(notifications, /stage5Choice\('Simpan preferensi', 'Save preferences', stage5Locale\)/);
assert.match(notifications, /stage5Choice\('Simpan aturan', 'Save rule', stage5Locale\)/);
const expense = read('resources/js/pages/finance/operational-expenses/index.tsx');
assert.match(expense, /title=\{stage5Choice\('Filter & pencarian', 'Filters & search', stage5Locale\)\}/);
const legacy = read('resources/js/pages/legacy-imports/index.tsx');
assert.match(legacy, /stage5Choice\('Unggah dan buat batch', 'Upload and create batch', stage5Locale\)/);
const catalog = read('resources/js/lib/i18n-catalog.ts');
assert.match(catalog, /'stage5\.ui\.51de34c002c1': "Finance & Cashier Master Data"/);
assert.ok(localizedComponents >= 20 && explicit >= 200, `Unexpected coverage: ${localizedComponents} components, ${explicit} calls`);
console.log(`STAGE 5 VISUAL R2 STATIC PASS: ${pages.length} TSX pages, ${localizedComponents} locale-aware components, ${explicit} explicit-localized calls, modal titles/options, dashboard, filters, notifications and import text.`);
