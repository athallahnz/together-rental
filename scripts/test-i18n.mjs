/** Small dependency-free (beyond existing TypeScript) semantic i18n regression. */
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import vm from 'node:vm';
import ts from 'typescript';

const root = resolve(import.meta.dirname, '..');

function compile(relative, dependencies = {}) {
    const path = resolve(root, relative);
    const code = readFileSync(path, 'utf8');
    const result = ts.transpileModule(code, {
        fileName: path,
        reportDiagnostics: true,
        compilerOptions: {
            target: ts.ScriptTarget.ES2022,
            module: ts.ModuleKind.CommonJS,
            jsx: ts.JsxEmit.ReactJSX,
        },
    });
    const diagnostics = result.diagnostics ?? [];

    assert.equal(diagnostics.length, 0, `${relative}: TypeScript syntax errors`);

    const exports = {};

    vm.runInNewContext(result.outputText, {
        exports,
        require: (name) => {
            if (Object.prototype.hasOwnProperty.call(dependencies, name)) {
                return dependencies[name];
            }

            throw new Error(`Unexpected test dependency: ${name}`);
        },
        Intl,
        Date,
        Object,
        Number,
        String,
        Error,
        Set,
    }, { filename: path });

    return exports;
}

const catalog = compile('resources/js/lib/i18n-catalog.ts');
const i18n = compile('resources/js/lib/i18n.ts', {
    '@inertiajs/react': { usePage: () => ({ props: { locale: 'id' } }) },
    react: { useEffect: () => {} },
    '@/lib/i18n-catalog': catalog,
    '@/lib/locale-store': { setEffectiveLocale: () => {} },
});
const format = compile('resources/js/lib/locale-format.ts');

assert.equal(i18n.translate('Pusat Pengaturan', 'en'), 'Settings Center');
assert.equal(i18n.translateKey('nav.settings', 'id'), 'Pengaturan');
assert.equal(i18n.translateKey('nav.settings', 'en'), 'Settings');
assert.equal(i18n.translateKey('nav.notifications', 'en'), 'Notifications');
assert.equal(i18n.translateKey('common.currentBranchTooltip', 'en', { name: 'PNG' }), 'Active branch: PNG');
assert.throws(() => i18n.translateKey('common.currentBranchTooltip', 'en'), /Missing i18n placeholder/);
assert.equal(i18n.translatePlural('notifications.unread', 0, 'en'), '0 unread notifications');
assert.equal(i18n.translatePlural('notifications.unread', 1, 'en'), '1 unread notification');
assert.equal(i18n.translatePlural('notifications.unread', 3, 'en'), '3 unread notifications');
assert.equal(i18n.translatePlural('notifications.unread', 1, 'id'), 'Ada 1 notifikasi belum dibaca');
assert.equal(i18n.translatePlural('errors.more', 2, 'en'), '2 other errors');
assert.throws(() => i18n.translateKey('missing.key', 'en'), /Missing i18n key/);
assert.equal(format.formatNumber(1234567.5, 'id'), '1.234.567,5');
assert.equal(format.formatNumber(1234567.5, 'en'), '1,234,567.5');
assert.equal(format.formatMoney(1234567.5, 'id'), 'Rp 1.234.567,5');
assert.equal(format.formatMoney(1234567.5, 'en'), 'Rp 1,234,567.5');
assert.equal(format.formatDate(null, 'id'), '—');
assert.equal(format.formatDate('not a date', 'en'), '—');
assert.match(format.formatDate('2026-09-25T10:00:00Z', 'en', { timeZone: 'UTC' }), /Sep 25, 2026/);
assert.match(format.formatDate('2026-09-25T10:00:00Z', 'id', { timeZone: 'UTC' }), /25 Sep 2026/);
assert.match(format.formatDateTime('2026-09-25T10:00:00Z', 'en', { timeZone: 'UTC' }), /10:00 AM/);
console.log('i18n UNIT PASS: 21 assertions (legacy compatibility, semantic labels, plural, fallback, IDR, dates)');
