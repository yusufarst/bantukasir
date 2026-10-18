import { defineConfig } from 'vitest/config';

// L1 tests of TypeScript code and of the frontend static checks (TEST_STRATEGY
// sections 2 and 6).
export default defineConfig({
    test: {
        include: ['tests/Unit/**/*.test.ts', 'resources/js/**/*.test.{ts,tsx}'],
        environment: 'node',
        testTimeout: 180_000,
    },
});
