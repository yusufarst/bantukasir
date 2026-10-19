import js from '@eslint/js';
import react from 'eslint-plugin-react';
import globals from 'globals';
import tseslint from 'typescript-eslint';

// Non-linguistic characters that may appear as JSX text (ADR-005 item 5).
const nonLinguistic = ['·', '•', '–', '—', '-', '/', '|', ':', ',', '.', '…', '(', ')', '[', ']', '+', '×', '%', '#', '*', '→', '←'];

// Every script extension of interface code (TASK-003 finding A-03).
const interfaceScripts = 'resources/js/**/*.{ts,tsx,mts,cts,js,jsx}';

// The two modules that may assert a TranslationKey: the generated key type
// and t() itself (TASK-003 finding A-06).
const keyModules = ['resources/js/lib/i18n.ts', 'resources/js/types/translation-keys.ts'];

// The names that read the browser's locale data or local time, or that turn
// an exact value into a float: as a computed member they are refused too.
const localTime = 'get(FullYear|Month|Date|Day|Hours|Minutes|Seconds|Milliseconds|Year|TimezoneOffset)|set(FullYear|Month|Date|Hours|Minutes|Seconds|Milliseconds|Year)|toDateString|toTimeString';
const forbiddenMembers = `Number|parseFloat|parseInt|Intl|JSON|Date|parse|NumberFormat|DateTimeFormat|RelativeTimeFormat|PluralRules|ListFormat|Collator|Segmenter|DisplayNames|DurationFormat|Locale|toLocale\\w*|${localTime}`;

// Exclusions shared by the identifier selectors: a non-computed property name
// (`settings.parseInt`) and the key of an object or of an object type name
// nothing.
const notAName = ':not(MemberExpression[computed=false] > Identifier.property):not(Property > Identifier.key):not(:matches(TSPropertySignature, TSMethodSignature) > Identifier.key)';

// ADR-006 items 5 and 6, ADR-005 item 6 (TASK-003, finding A-03, reading
// R-34): money, quantity and cost stay canonical strings and scaled BigInt in
// interface code, and formatting never reads the browser's locale data or
// local time. Without type information a selector cannot tell those values
// from other numbers, so the conversions are refused in all interface code,
// wherever the name is referenced — called, passed as a callback, aliased,
// destructured or reached by computed access — and resources/js/lib/exact.ts
// and format.ts work without them. Allowed: the integer-only members of
// Number, JSON.stringify, Date.now() for a timer, and Date as a type.
// JSON.parse is refused everywhere: a later Task that needs it may allow it in
// one module that keeps exact values as strings.
const exactNumbers = [
    {
        selector: `Identifier[name='Number']:not(MemberExpression[property.name=/^(isSafeInteger|isInteger|MAX_SAFE_INTEGER|MIN_SAFE_INTEGER)$/] > Identifier.object)${notAName}:not(TSTypeReference > Identifier)`,
        message: 'Number turns an exact value into a float; use resources/js/lib/exact.ts (ADR-006 item 5).',
    },
    {
        selector: `Identifier[name=/^(parseFloat|parseInt)$/]${notAName}`,
        message: 'parseFloat and parseInt read an exact value as a float or truncate it; use resources/js/lib/exact.ts (ADR-006 item 5).',
    },
    {
        selector: `Identifier[name='Intl']${notAName}`,
        message: 'Intl formats with the browser locale data and floats; use resources/js/lib/format.ts (ADR-005 item 6, ADR-006 item 5).',
    },
    {
        selector: `Identifier[name='JSON']:not(MemberExpression[property.name='stringify'] > Identifier.object)${notAName}`,
        message: 'JSON.parse turns a canonical string such as "1.5" into a float; exact values arrive as strings (ADR-006 item 2).',
    },
    {
        selector: `Identifier[name='Date']:not(MemberExpression[property.name='now'] > Identifier.object)${notAName}:not(TSTypeReference > Identifier)`,
        message: 'Date reads the browser time zone and maps two-digit years; use resources/js/lib/format.ts (ADR-005 item 6, R-12).',
    },
    {
        selector: "MemberExpression[object.name=/^(window|globalThis|self|global)$/][property.name=/^(Number|parseFloat|parseInt|Intl|JSON|Date)$/]",
        message: 'Number, parseFloat, parseInt, Intl, JSON and Date are refused through the global object too (ADR-006 item 5).',
    },
    {
        selector: `MemberExpression[computed=true][property.value=/^(${forbiddenMembers})$/]`,
        message: 'A computed access to a conversion, locale formatting or local time is refused like the name itself (ADR-005 item 6, ADR-006 item 5).',
    },
    {
        selector: 'MemberExpression[computed=true] > TemplateLiteral.property[expressions.length=0]',
        message: 'A member is named by an identifier or a string, not a template literal, so that the exact-number rules see it.',
    },
    {
        selector: `MemberExpression[computed=false][property.name=/^(toLocale\\w*|${localTime})$/]`,
        message: 'Locale formatting and local time depend on the browser; use resources/js/lib/format.ts (ADR-005 item 6, R-12).',
    },
    {
        selector: "UnaryExpression[operator='+']",
        message: 'Unary + turns a value into a float; use resources/js/lib/exact.ts (ADR-006 item 5).',
    },
];

// ADR-005 item 5 (TASK-003 finding A-06, reading R-35): a type assertion
// makes any string pass the typecheck of t(), so interface code never asserts
// a TranslationKey, the type of the placeholders, the type of t(), or an alias
// that only renames or narrows the key type. A key typed TranslationKey
// without an assertion, for example a value of a typed map, stays allowed: the
// typecheck proves that it exists.
const keyAssertion = 'TranslationKey|TranslationPlaceholders';
const keyAssertions = [
    {
        selector: `:matches(TSAsExpression, TSTypeAssertion) > TSTypeReference.typeAnnotation[typeName.name=/^(${keyAssertion})$/]`,
        message: 'A translation key is never asserted: write the key as a literal or type the value TranslationKey (ADR-005 item 5).',
    },
    {
        selector: `:matches(TSAsExpression, TSTypeAssertion) > .typeAnnotation TSTypeReference[typeName.name=/^(${keyAssertion})$/]`,
        message: 'A translation key is never asserted, alone or in another type (ADR-005 item 5).',
    },
    {
        selector: ":matches(TSAsExpression, TSTypeAssertion) > .typeAnnotation TSTypeQuery[exprName.name='t']",
        message: 'A translation key is never asserted through the type of t() (ADR-005 item 5).',
    },
    {
        selector: "TSTypeReference[typeName.name='TranslationPlaceholders']",
        message: 'TranslationPlaceholders is the internal of t(); use TranslationKey (ADR-005 item 5).',
    },
    {
        selector: "TSTypeAliasDeclaration > TSTypeReference.typeAnnotation[typeName.name='TranslationKey']",
        message: 'Use TranslationKey itself, not an alias of it, so that an assertion to it is seen (ADR-005 item 5).',
    },
    {
        selector: "TSTypeAliasDeclaration > TSTypeReference.typeAnnotation[typeName.name=/^(Extract|Exclude|NonNullable)$/] TSTypeReference[typeName.name='TranslationKey']",
        message: 'A narrowed alias of TranslationKey would let an assertion pass; type the value TranslationKey (ADR-005 item 5).',
    },
];

// ADR-005 items 1 and 5 (TASK-003 finding A-08, reading R-28): user-facing
// text comes from a translation key, never from a string literal anywhere
// inside a JSX expression container or a spread attribute — in a conditional,
// a logical expression, a concatenation, an array, a call or a spread object.
// Allowed: the key of t(); the attributes that never hold text; object keys;
// the operands of a comparison and a computed member's name, which are never
// shown. The seventh form of the review, a literal bound to a variable and
// rendered later, is a limit of lint that code review covers.
const textCharacter = '/[\\p{L}\\p{N}]/u';
const nonTextAttributes =
    'className|class|type|role|key|id|htmlFor|for|name|href|src|srcSet|rel|target|method|action|autoComplete|inputMode|encType|accept|dir|lang|tabIndex|form|style|data-testid|aria-(controls|describedby|labelledby|live|current|haspopup|hidden|expanded|pressed|selected|checked|disabled|invalid|required|atomic|busy|relevant|orientation|sort|autocomplete|owns|activedescendant|errormessage|details|level|modal|multiline|multiselectable|readonly)';
const notShown = [
    ":not(:matches(CallExpression[callee.name='t'], CallExpression[callee.property.name='t']) > Literal:first-child)",
    `:not(JSXAttribute[name.name=/^(${nonTextAttributes})$/] *)`,
    `:not(JSXSpreadAttribute Property[key.name=/^(${nonTextAttributes})$/] > *.value *)`,
    `:not(JSXSpreadAttribute Property[key.name=/^(${nonTextAttributes})$/] > Literal.value)`,
    ':not(Property > Literal.key)',
    ':not(BinaryExpression[operator=/^(===|!==|==|!=)$/] > Literal)',
    ':not(MemberExpression[computed=true] > Literal.property)',
].join('');
const jsxLiterals = [
    {
        selector: `:matches(JSXExpressionContainer, JSXSpreadAttribute) Literal[value=${textCharacter}]${notShown}`,
        message: 'User-facing text comes from a translation key (ADR-005), never from a string literal.',
    },
    {
        selector: `:matches(JSXExpressionContainer, JSXSpreadAttribute) TemplateElement[value.raw=${textCharacter}]:not(JSXAttribute[name.name=/^(${nonTextAttributes})$/] *)`,
        message: 'User-facing text comes from a translation key (ADR-005), never from a template literal.',
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
        files: [interfaceScripts],
        languageOptions: {
            parser: tseslint.parser,
            parserOptions: { ecmaFeatures: { jsx: true } },
            globals: { ...globals.browser },
        },
        rules: {
            'no-restricted-syntax': ['error', ...exactNumbers, ...keyAssertions],
        },
    },
    {
        // A later block replaces the rule's options instead of adding to them.
        files: keyModules,
        rules: {
            'no-restricted-syntax': ['error', ...exactNumbers],
        },
    },
    {
        // ADR-005 item 5: user-facing text comes from translation keys, never
        // from a literal in interface code.
        files: ['resources/js/**/*.{tsx,jsx}'],
        plugins: { react },
        settings: { react: { version: '19.3' } },
        rules: {
            'react/jsx-no-literals': [
                'error',
                { noStrings: true, noAttributeStrings: true, allowedStrings: nonLinguistic, ignoreProps: false },
            ],
            // jsx-no-literals 7.37.5 reports a literal only where it is the
            // direct child of the braces, so these selectors refuse one anywhere
            // inside them; the block repeats exactNumbers and keyAssertions.
            'no-restricted-syntax': ['error', ...jsxLiterals, ...exactNumbers, ...keyAssertions],
        },
    },
);
