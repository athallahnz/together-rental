import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import ts from 'typescript';

const read = (path) => readFileSync(path, 'utf8');
const catalog = read('resources/js/lib/i18n-catalog.ts');
const known = new Set(
    [...catalog.matchAll(/^\s*'(auth\.[^']+|public\.[^']+)':/gm)]
        .map((match) => match[1]),
);
const files = [
    ...[
        'confirm-password',
        'forgot-password',
        'login',
        'reset-password',
        'two-factor-challenge',
        'verify-email',
    ].map((name) => `resources/js/pages/auth/${name}.tsx`),
    'resources/js/layouts/auth-layout.tsx',
    'resources/js/components/guest-language-switcher.tsx',
    'resources/js/components/passkey-verify.tsx',
    'resources/js/components/password-input.tsx',
    'resources/js/components/public/public-shell.tsx',
];
let assertions = 0;

for (const file of files) {
    const source = read(file);
    const ast = ts.createSourceFile(file, source, ts.ScriptTarget.Latest, true, ts.ScriptKind.TSX);
    assert.equal(ast.parseDiagnostics.length, 0, `Syntax: ${file}`);
    assertions++;

    for (const [, key] of source.matchAll(/(?:tr\(|title:\s*|description:\s*)['"](auth\.[a-zA-Z.]+|public\.[a-zA-Z.]+)['"]/g)) {
        assert(known.has(key), `${file}: missing semantic key ${key}`);
        assertions++;
    }
}

const middleware = read('app/Http/Middleware/SetUserLocale.php');
const sharing = read('app/Http/Middleware/HandleInertiaRequests.php');
const routes = read('routes/web.php');
assert(middleware.includes("$request->cookie('guest_locale')"));
assert(sharing.includes("'locale' => app()->getLocale()"));
assert(routes.includes("Route::post('/language/guest'"));
assert(routes.includes("->middleware(['guest', 'throttle:20,1'])"));
assert(read('app/Http/Controllers/GuestLanguageController.php').includes("Rule::in(['id', 'en'])"));
assert(read('resources/js/components/public/public-shell.tsx').includes('GuestLanguageSwitcher'));
assert(read('resources/js/layouts/auth-layout.tsx').includes('GuestLanguageSwitcher'));
assertions += 7;

console.log(`GUEST/AUTH I18N PASS: ${assertions} static assertions, keys, syntax and cookie contracts`);
