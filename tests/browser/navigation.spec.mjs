import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
const fixture = () => JSON.parse(readFileSync('temp/browser-fixture.json', 'utf8'));

for (const reducedMotion of ['no-preference', 'reduce']) test(`back, reload and forward restore scroll, theme and a usable toolbar (${reducedMotion})`, async ({ page }, testInfo) => {
  await page.emulateMedia({ reducedMotion });
  await page.addInitScript(() => {
    sessionStorage.setItem('Argon_Enable_Dark_Mode', 'true');
    addEventListener('pageshow', event => { window.argonPageRestored = event.persisted; });
  });
  await page.goto(`/?page_id=${fixture().catalog}`, { waitUntil: 'domcontentloaded' });
  await page.evaluate(async () => {
    await document.fonts.ready;
    await Promise.all(document.querySelector('#post_content').closest('article').getAnimations().map(animation => animation.finished));
    scrollTo({ top: 700, behavior: 'instant' });
  });
  await expect(page.locator('#navbar-main')).toHaveCSS('backdrop-filter', 'blur(16px)');
  const source = page.url();
  const scroll = await page.evaluate(() => scrollY);
  await page.evaluate(url => {
    const link = document.createElement('a'); link.href = url; document.body.append(link); link.click();
  }, `/?page_id=${fixture().design}`);
  await expect(page.locator('#design-primary')).toBeVisible();
  const destination = page.url();
  await page.goBack({ waitUntil: 'domcontentloaded' });
  await expect(page).toHaveURL(source);
  await expect.poll(() => page.evaluate(() => scrollY)).toBeCloseTo(scroll, 0);
  await page.reload({ waitUntil: 'load' });
  await expect.poll(() => page.evaluate(() => scrollY)).toBeCloseTo(scroll, 0);
  await expect(page.locator('html')).toHaveClass(/darkmode/);
  await expect(page.locator('#navbar-main')).toHaveCSS('backdrop-filter', 'blur(16px)');
  await page.locator('#navbar_search_input_container').click();
  await expect(page.locator('#navbar_search_input')).toBeFocused();
  await testInfo.attach('history-restoration', { body: JSON.stringify(await page.evaluate(() => ({ persisted: window.argonPageRestored, scroll: scrollY, navigation: performance.getEntriesByType('navigation')[0]?.type }))), contentType: 'application/json' });
  await page.goForward({ waitUntil: 'domcontentloaded' });
  await expect(page).toHaveURL(destination);
  await expect(page.locator('#design-primary')).toBeVisible();
});

test('reload retains asset URLs, transparent header and reader preference', async ({ page }, testInfo) => {
  await page.addInitScript(() => localStorage.setItem('Argon_Use_Serif', 'true'));
  await page.goto(`/?page_id=${fixture().design}`, { waitUntil: 'load' });
  const styles = await page.locator('link[id^="argon-"][rel="stylesheet"]').evaluateAll(elements => elements.map(el => el.href));
  await page.reload({ waitUntil: 'load' });
  expect(await page.locator('link[id^="argon-"][rel="stylesheet"]').evaluateAll(elements => elements.map(el => el.href))).toEqual(styles);
  await expect(page.locator('#navbar-main')).toHaveCSS('background-color', /rgba\(\d+, \d+, \d+, 0\)/);
  await expect(page.locator('.post-content')).toHaveCSS('font-family', /Noto Serif/);
  await testInfo.attach('reload-asset-timing', { body: JSON.stringify(await page.evaluate(() => performance.getEntriesByType('resource').filter(entry => entry.name.includes('/assets/dist/')).map(({ name, transferSize, decodedBodySize }) => ({ name, transferSize, decodedBodySize })))), contentType: 'application/json' });
});
