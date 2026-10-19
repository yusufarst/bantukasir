import js from '@eslint/js';
import react from 'eslint-plugin-react';
import globals from 'globals';
import tseslint from 'typescript-eslint';

// Non-linguistic characters that may appear as JSX text (ADR-005 item 5).
const nonLinguistic = ['·', '•', '–', '—', '-', '/', '|', ':', ',', '.', '…', '(', ')', '[', ']', '+', '×', '%', '#', '*', '→', '←'];

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
            // this selector refuses it too.
            'no-restricted-syntax': [
                'error',
                {
                    selector: 'JSXExpressionContainer > Literal[value=/[A-Za-z0-9]/]',
                    message: 'User-facing text comes from a translation key (ADR-005), never from a string literal.',
                },
            ],
        },
    },
);
