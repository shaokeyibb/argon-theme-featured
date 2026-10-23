import { defineConfig } from '@playwright/test';
import { fileURLToPath } from 'node:url';
export default defineConfig({
  testDir: './browser', testMatch: 'media.spec.mjs', timeout: 60000, workers: 1,
  use: {baseURL:'http://127.0.0.1:9401',headless:true,trace:'retain-on-failure',channel:process.env.ARGON_BROWSER_CHANNEL||undefined,launchOptions:{ignoreDefaultArgs:['--disable-back-forward-cache']}},
  outputDir:'../temp/media-browser-results',
  webServer:{
    cwd:fileURLToPath(new URL('..',import.meta.url)),
    command:'node --experimental-wasm-jspi node_modules/@wp-playground/cli/wp-playground.js server --port=9401 --workers=6 --define-bool DISABLE_WP_CRON true --mount-dir . /wordpress/wp-content/themes/argon-theme --blueprint tests/media-browser-blueprint.json',
    // Wait for the whole blueprint, including media generation and rewrite rules.
    wait:{stdout:/WordPress is running on http:\/\/127\.0\.0\.1:9401\b/},timeout:300000
  }
});
