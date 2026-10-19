import { defineConfig } from 'vitest/config';

// The negative proof of the npm dependency audit that needs the registry
// (TEST_STRATEGY section 6, THR-14). npm run lint runs it after the audit
// itself; npm run test:unit (vitest.config.ts) stays L1, without network (TS-08).
export default defineConfig({
    test: {
        include: ['tests/Network/**/*.test.ts'],
        environment: 'node',
        testTimeout: 180_000,
    },
});
