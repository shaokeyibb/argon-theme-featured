import { defineConfig } from '@playwright/test';
export default defineConfig({
  testDir: './tests/browser', timeout: 60000, workers: 1,
  testIgnore: 'media.spec.mjs',
  use: { baseURL: 'http://127.0.0.1:9400', headless: true, trace: 'retain-on-failure', channel: process.env.ARGON_BROWSER_CHANNEL || undefined },
  outputDir: 'temp/browser-results',
  webServer: {
    command: 'node --experimental-wasm-jspi node_modules/@wp-playground/cli/wp-playground.js server --port=9400 --workers=6 --define-bool DISABLE_WP_CRON true --mount-dir . /wordpress/wp-content/themes/argon-theme --blueprint tests/browser-blueprint.json',
    // Playground accepts HTTP requests before its blueprint creates the fixtures.
    // Its ready message is emitted only after every blueprint step has finished.
    wait: { stdout: /WordPress is running on http:\/\/127\.0\.0\.1:9400\b/ }, timeout: 180000
  }
});
