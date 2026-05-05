import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        environment: 'happy-dom',
        globals: true,
        include: ['tests/js/**/*.test.js'],
    },
    resolve: {
        alias: {
            // Stub @statamic/cms so imports don't fail in test context
            '@statamic/cms': new URL('./tests/js/__stubs__/statamic.js', import.meta.url).pathname,
        },
    },
});
