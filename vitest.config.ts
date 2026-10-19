import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

// L1 tests of TypeScript code and of the frontend static checks (TEST_STRATEGY
// sections 2 and 6), which need no network (TS-08). The proof that needs the
// registry is in vitest.network.config.ts, which npm run lint runs. The alias
// "@" is the one of vite.config.ts and tsconfig.json.
export default defineConfig({
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        include: ['tests/Unit/**/*.test.ts', 'resources/js/**/*.test.{ts,tsx}'],
        environment: 'node',
        testTimeout: 180_000,
    },
});
