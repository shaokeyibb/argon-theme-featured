import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
const fixture = () => JSON.parse(readFileSync('temp/browser-fixture.json', 'utf8'));

test.skip(process.platform !== 'linux', 'Reference screenshots use Ubuntu 24.04 and the locked Playwright Chromium version.');
for (const mobile of [false, true]) for (const dark of [false, true]) {
  test(`article and header visual baseline (${mobile ? 'mobile' : 'desktop'}, ${dark ? 'dark' : 'light'})`, async ({ page }) => {
    await page.setViewportSize(mobile ? { width: 390, height: 844 } : { width: 1280, height: 900 });
    await page.addInitScript(dark => sessionStorage.setItem('Argon_Enable_Dark_Mode', String(dark)), dark);
    await page.goto(`/?page_id=${fixture().design}`, { waitUntil: 'networkidle' });
    await page.evaluate(() => document.fonts.ready);
    const mode = `${mobile ? 'mobile' : 'desktop'}-${dark ? 'dark' : 'light'}`;
    await expect(page.locator('#navbar-main')).toHaveScreenshot(`header-${mode}.png`, { animations: 'disabled' });
    await expect(page.locator('.post-content')).toHaveScreenshot(`article-${mode}.png`, { animations: 'disabled', stylePath: fileURLToPath(new URL('./screenshot.css', import.meta.url)), maxDiffPixelRatio: 0.002 });
  });
}
