import js from '@eslint/js';
import react from 'eslint-plugin-react';
import globals from 'globals';
import tseslint from 'typescript-eslint';

// Non-linguistic characters that may appear as JSX text (ADR-005 item 5).
const nonLinguistic = ['·', '•', '–', '—', '-', '/', '|', ':', ',', '.', '…', '(', ')', '[', ']', '+', '×', '%', '#', '*', '→', '←'];

// ADR-006 items 5 and 6, ADR-005 item 6 (TASK-003): money, quantity and cost
// stay canonical strings and scaled BigInt in interface code, and formatting
// never reads the browser's locale data. Without type information a selector
// cannot tell those values from other numbers, so the conversions are refused
// in all interface code; resources/js/lib/exact.ts and format.ts work without
// them.
const exactNumbers = [
    {
        selector: "CallExpression[callee.type='Identifier'][callee.name='Number'], NewExpression[callee.name='Number']",
        message: 'Number() turns an exact value into a float; use resources/js/lib/exact.ts (ADR-006 item 5).',
    },
    {
        selector: "CallExpression[callee.type='Identifier'][callee.name=/^(parseFloat|parseInt)$/]",
        message: 'parseFloat and parseInt read an exact value as a float or truncate it; use resources/js/lib/exact.ts (ADR-006 item 5).',
    },
    {
        selector: "MemberExpression[object.name=/^(Number|window|globalThis|self)$/][property.name=/^(Number|parseFloat|parseInt)$/]",
        message: 'Number, parseFloat and parseInt turn an exact value into a float; use resources/js/lib/exact.ts (ADR-006 item 5).',
    },
    {
        selector: "UnaryExpression[operator='+']",
        message: 'Unary + turns a value into a float; use resources/js/lib/exact.ts (ADR-006 item 5).',
    },
    {
        selector: "MemberExpression[object.name='Intl'][property.name=/^(NumberFormat|DateTimeFormat)$/]",
        message: 'Intl formats with the browser locale data and floats; use resources/js/lib/format.ts (ADR-005 item 6, ADR-006 item 5).',
    },
    {
        selector: "CallExpression[callee.property.name=/^toLocale(Date|Time)?String$/]",
        message: 'toLocaleString formats with the browser locale data; use resources/js/lib/format.ts (ADR-005 item 6).',
    },
];

export default tseslint.config(
    {
        // Dependencies, build and test output, the negative samples, local tool
        // folders, and the documents with the approved P7 previews, which are
        // review material, not interface code (EXC-54).
        ignores: [
            'vendor/**',
            'node_modules/**',
            'public/**',
            'storage/**',
            'bootstrap/cache/**',
            'tests/Fixtures/**',
            'playwright-report/**',
            'test-results/**',
            'blob-report/**',
            'coverage/**',
            'docs/**',
            'assets/**',
            '.agents/**',
            '.local/**',
            'Claude outputs/**',
        ],
    },
    js.configs.recommended,
    ...tseslint.configs.recommended,
    {
        files: ['**/*.{js,mjs,ts}'],
        languageOptions: {
            globals: { ...globals.node },
        },
    },
    {
        files: ['resources/js/**/*.{ts,tsx}'],
        languageOptions: {
            globals: { ...globals.browser },
        },
        rules: {
            'no-restricted-syntax': ['error', ...exactNumbers],
        },
    },
    {
        // ADR-005 item 5: user-facing text comes from translation keys, never
        // from a literal in interface code.
        files: ['resources/js/**/*.tsx'],
        plugins: { react },
        languageOptions: {
            parserOptions: { ecmaFeatures: { jsx: true } },
        },
        settings: { react: { version: '19.3' } },
        rules: {
            'react/jsx-no-literals': [
                'error',
                { noStrings: true, noAttributeStrings: true, allowedStrings: nonLinguistic, ignoreProps: false },
            ],
            // With noAttributeStrings set, jsx-no-literals 7.37.5 does not report a
            // string literal inside braces, such as {'text'} or title={'text'};
            // this selector refuses it too. A later block replaces the rule's
            // options instead of adding to them, so it repeats exactNumbers.
            'no-restricted-syntax': [
                'error',
                {
                    selector: 'JSXExpressionContainer > Literal[value=/[A-Za-z0-9]/]',
                    message: 'User-facing text comes from a translation key (ADR-005), never from a string literal.',
                },
                ...exactNumbers,
            ],
        },
    },
);
