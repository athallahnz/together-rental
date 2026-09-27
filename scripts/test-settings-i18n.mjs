import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import ts from 'typescript';

const root = resolve(import.meta.dirname, '..');
const catalog = resolve(root, 'resources/js/lib/i18n-catalog.ts');
const ast = ts.createSourceFile(catalog, readFileSync(catalog, 'utf8'), ts.ScriptTarget.Latest, true);
const messages = new Map();

for (const statement of ast.statements) {
    if (!ts.isVariableStatement(statement)) {
continue;
}

    for (const declaration of statement.declarationList.declarations) {
        const name = declaration.name.getText(ast);

        if (!['idMessages', 'enMessages'].includes(name)) {
continue;
}

        const object = ts.isAsExpression(declaration.initializer) ? declaration.initializer.expression : declaration.initializer;
        const entries = new Map();

        for (const item of object.properties) {
            const key = item.name.text;
            assert(!entries.has(key), `Duplicate key ${key}`);
            entries.set(key, item.initializer.text);
        }

        messages.set(name, entries);
    }
}

const id = messages.get('idMessages');
const en = messages.get('enMessages');
assert(id && en);
assert.equal(id.size, en.size, 'ID/EN key parity');
const settingsKeys = [...id.keys()].filter((key) => key.startsWith('settings.'));
assert(settingsKeys.length >= 100);

for (const key of settingsKeys) {
    assert(en.has(key), `Missing English ${key}`);
    assert(id.get(key).trim() && en.get(key).trim(), `Empty translation ${key}`);
    const vars = (value) => [...value.matchAll(/\{([a-z][A-Za-z]*)\}/g)].map((x) => x[1]).sort();
    assert.deepEqual(vars(id.get(key)), vars(en.get(key)), `Placeholder mismatch ${key}`);
}

for (const [relative, expected] of [
    ['resources/js/pages/settings/center.tsx', 'settings.center.head'],
    ['resources/js/pages/settings/profile.tsx', 'settings.profile.title'],
    ['resources/js/pages/settings/security.tsx', 'settings.security.title'],
    ['resources/js/components/manage-passkeys.tsx', 'settings.passkeys.title'],
    ['resources/js/components/manage-two-factor.tsx', 'settings.twoFactor.title'],
    ['resources/js/components/two-factor-recovery-codes.tsx', 'settings.recovery.title'],
    ['resources/js/components/two-factor-setup-modal.tsx', 'settings.twoFactorSetup.enableTitle'],
]) {
    const path = resolve(root, relative);
    const code = readFileSync(path, 'utf8');
    assert(code.includes(expected), `${relative}: missing localized UI`);
    const result = ts.transpileModule(code, { fileName: path, reportDiagnostics: true, compilerOptions: { jsx: ts.JsxEmit.ReactJSX } });
    assert.equal(result.diagnostics?.length ?? 0, 0, `${relative}: TypeScript syntax`);
}

assert.notEqual(id.get('settings.security.title'), en.get('settings.security.title'));
assert.notEqual(id.get('settings.passkeys.register'), en.get('settings.passkeys.register'));
console.log(`SETTINGS I18N STATIC PASS: ${settingsKeys.length} settings keys; ID/EN parity, placeholders, and seven UI smoke checks`);
