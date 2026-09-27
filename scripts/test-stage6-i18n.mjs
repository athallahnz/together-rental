import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import ts from 'typescript';

const root = resolve(import.meta.dirname, '..');
const read = (path) => readFileSync(resolve(root, path), 'utf8');
const helper = read('resources/js/components/stage6-text.tsx');
const catalog = ts.createSourceFile(
    'stage6-text.tsx',
    helper,
    ts.ScriptTarget.Latest,
    true,
    ts.ScriptKind.TSX,
);
assert.equal(catalog.parseDiagnostics.length, 0);
const object = catalog.statements
    .filter(ts.isVariableStatement)
    .flatMap((statement) => [...statement.declarationList.declarations])
    .find(
        (declaration) => declaration.name.getText(catalog) === 'english',
    )?.initializer;
assert.ok(object && ts.isObjectLiteralExpression(object));
const keys = new Set(object.properties.map((property) => property.name.text));
assert.ok(keys.size >= 130, 'Expected the full Stage 6 UI catalog');

let references = 0;

for (const file of [
    'resources/js/pages/reports/index.tsx',
    'resources/js/pages/reports/asset-analytics.tsx',
]) {
    const source = ts.createSourceFile(
        file,
        read(file),
        ts.ScriptTarget.Latest,
        true,
        ts.ScriptKind.TSX,
    );
    assert.equal(
        source.parseDiagnostics.length,
        0,
        `TSX syntax error: ${file}`,
    );
    function walk(node) {
        if (ts.isJsxText(node)) {
            const value = node.getText(source).trim();
            assert.ok(
                !/[A-Za-zÀ-ÿ]/.test(value) || ['Excel', 'PDF'].includes(value),
                `Untranslated JSX text in ${file}: ${value}`,
            );
        }

        if (
            ts.isJsxAttribute(node) &&
            node.name.text === 'text' &&
            node.initializer &&
            ts.isStringLiteral(node.initializer)
        ) {
            const key = node.initializer.text;
            assert.ok(keys.has(key), `Missing Stage 6 translation: ${key}`);
            references++;
        }

        if (
            ts.isCallExpression(node) &&
            node.expression.getText(source) === 'stage6Display'
        ) {
            const key = node.arguments[0];

            if (key && ts.isStringLiteral(key)) {
                assert.ok(
                    keys.has(key.text),
                    `Missing Stage 6 translation: ${key.text}`,
                );
                references++;
            }
        }

        ts.forEachChild(node, walk);
    }
    walk(source);
}

assert.ok(
    references >= 130,
    `Expected at least 130 references, got ${references}`,
);
console.log(
    `STAGE 6 UI PASS: ${keys.size} labels, ${references} references, no raw report JSX text`,
);
