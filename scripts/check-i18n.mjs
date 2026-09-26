/* eslint-disable curly, @stylistic/padding-line-between-statements */
/** UAT-035B semantic catalog and migration gate. Requires local TypeScript dependency. */
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join, relative, resolve } from 'node:path';
import process from 'node:process';
import ts from 'typescript';

const root = resolve(import.meta.dirname, '..');
const catalogPath = join(root, 'resources/js/lib/i18n-catalog.ts');
const catalogSource = readFileSync(catalogPath, 'utf8');
const file = ts.createSourceFile(catalogPath, catalogSource, ts.ScriptTarget.Latest, true);
const catalogs = new Map();
const errors = [];

function unwrap(node) {
    while (ts.isAsExpression(node) || ts.isSatisfiesExpression(node)) {
        node = node.expression;
    }
    return node;
}

function objectEntries(node) {
    const result = new Map();
    const unwrapped = unwrap(node);
    if (!ts.isObjectLiteralExpression(unwrapped)) {
        throw new Error('Expected static object literal for i18n catalog');
    }
    for (const entry of unwrapped.properties) {
        if (!ts.isPropertyAssignment(entry)) {
            throw new Error('Use plain static i18n catalog entries only');
        }
        const key = entry.name && ts.isStringLiteral(entry.name)
            ? entry.name.text : entry.name.getText(file);
        if (result.has(key)) errors.push(`Duplicate catalog key: ${key}`);
        result.set(key, unwrap(entry.initializer));
    }
    return result;
}

for (const statement of file.statements) {
    if (!ts.isVariableStatement(statement)) continue;
    for (const decl of statement.declarationList.declarations) {
        const name = decl.name.getText(file);
        if (['idMessages', 'enMessages', 'idPlural', 'enPlural'].includes(name)) {
            catalogs.set(name, objectEntries(decl.initializer));
        }
    }
}

function textValue(node, name) {
    if (!ts.isStringLiteral(node) && !ts.isNoSubstitutionTemplateLiteral(node)) {
        errors.push(`${name}: translation must be a static string`);
        return '';
    }
    if (!node.text.trim()) errors.push(`${name}: translation cannot be empty`);
    return node.text;
}
function placeholders(str) {
    return [...str.matchAll(/\{([a-zA-Z][a-zA-Z0-9_]*)\}/g)].map((x) => x[1]).sort().join(',');
}
function compare(a, b, label, plural = false) {
    const left = catalogs.get(a);
    const right = catalogs.get(b);
    if (!left || !right) {
 errors.push(`${label}: catalog missing`); return;
}
    for (const key of left.keys()) if (!right.has(key)) errors.push(`${label}: missing ${b}.${key}`);
    for (const key of right.keys()) if (!left.has(key)) errors.push(`${label}: extra ${b}.${key}`);
    for (const [key, value] of left) {
        if (!right.has(key)) continue;
        if (plural) {
            const src = objectEntries(value);
            const dst = objectEntries(right.get(key));
            for (const form of ['one', 'other']) {
                if (!src.has(form) || !dst.has(form)) {
 errors.push(`${label}.${key}: missing ${form}`); continue;
}
                if (placeholders(textValue(src.get(form), `${a}.${key}.${form}`))
                    !== placeholders(textValue(dst.get(form), `${b}.${key}.${form}`))) {
                    errors.push(`${label}.${key}.${form}: placeholder mismatch`);
                }
            }
        } else if (placeholders(textValue(value, `${a}.${key}`)) !==
            placeholders(textValue(right.get(key), `${b}.${key}`))) {
            errors.push(`${label}.${key}: placeholder mismatch`);
        }
    }
}
compare('idMessages', 'enMessages', 'Messages');
compare('idPlural', 'enPlural', 'Plural', true);

// New semantic calls are compile-time typed; this static check additionally catches
// literal unknown keys in both TS and TSX, without flagging legacy t() calls.
function* files(dir) {
    for (const name of readdirSync(dir)) {
        const path = join(dir, name);
        if (statSync(path).isDirectory()) {
            if (!['actions', 'routes', 'wayfinder'].includes(name)) yield* files(path);
        } else if (/\.tsx?$/.test(name)) yield path;
    }
}
const knownMessages = catalogs.get('idMessages') ?? new Map();
const knownPlural = catalogs.get('idPlural') ?? new Map();
for (const path of files(join(root, 'resources/js'))) {
    if (path === catalogPath) continue;
    const source = ts.createSourceFile(path, readFileSync(path,'utf8'), ts.ScriptTarget.Latest, true,
        path.endsWith('.tsx') ? ts.ScriptKind.TSX : ts.ScriptKind.TS);
    const checkNode = (node) => {
        if (ts.isCallExpression(node) && ts.isIdentifier(node.expression)) {
            const fn = node.expression.text;
            const first = node.arguments[0];
            const isMessage = ['tr', 'translateKey'].includes(fn);
            const isPlural = ['tp', 'translatePlural'].includes(fn);
            if ((isMessage || isPlural) && first && ts.isStringLiteral(first)) {
                const map = isMessage ? knownMessages : knownPlural;
                if (!map.has(first.text)) {
                    errors.push(`${relative(root,path)}: ${fn} references unknown key '${first.text}'`);
                }
            }
        }
        ts.forEachChild(node, checkNode);
    };
    checkNode(source);
}

if (errors.length) {
    console.error(errors.join('\n'));
    process.exitCode = 1;
} else {
    console.log(`i18n CHECK PASS: ${knownMessages.size} typed keys, ${knownPlural.size} plural groups, ID/EN parity and placeholders`);
}
