import { test, expect } from '@playwright/test';

const transparent = /rgba\(\d+, \d+, \d+, 0\)/;
const themeScripts = /\/assets\/dist\/[^/]+\.js(?:\?.*)?$/;

for (const mobile of [false, true]) for (const dark of [false, true]) {
  test(`header stays transparent before navigation scripts load (${mobile ? 'mobile' : 'desktop'}, ${dark ? 'dark' : 'light'})`, async ({ page }) => {
    await page.setViewportSize(mobile ? { width: 390, height: 844 } : { width: 1440, height: 1000 });
    await page.addInitScript(dark => {
      sessionStorage.setItem('Argon_Enable_Dark_Mode', String(dark));
    }, dark);
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    expect(await page.locator('html').evaluate(el => el.classList.contains('darkmode'))).toBe(dark);
    const nav = page.locator('#navbar-main');
    await expect(nav).toHaveCSS('background-color', transparent);
    const initialHeight = (await nav.boundingBox()).height;

    // Hold all theme modules across a real card navigation. DOMContentLoaded
    // cannot fire yet, but the browser can already paint the destination CSS.
    let release;
    const held = new Promise(resolve => { release = resolve; });
    await page.route(themeScripts, async route => { await held; await route.continue(); });
    try {
      const card = page.locator('#main article .post-title').first();
      const destination = await card.getAttribute('href');
      await card.click({ noWaitAfter: true });
      await page.waitForURL(destination, { waitUntil: 'commit' });
      await expect(nav).toBeVisible();
      // Force layout and inspect computed appearance before releasing modules.
      // Looking only after DOMContentLoaded misses the original purple flash.
      await expect(nav).toHaveCSS('background-color', transparent);
      await expect(nav).toHaveCSS('box-shadow', 'none');
      expect((await nav.boundingBox()).height).toBeCloseTo(initialHeight, 1);
      expect(await nav.evaluate(el => el.style.backgroundColor)).toBe('');
      release();
      await page.waitForLoadState('domcontentloaded');
      await expect(nav).toHaveCSS('background-color', transparent);
      expect((await nav.boundingBox()).height).toBeCloseTo(initialHeight, 1);

      await page.evaluate(() => {
        document.body.style.minHeight = '2500px';
        scrollTo({ top: 700, behavior: 'instant' });
      });
      await expect(nav).not.toHaveCSS('background-color', transparent);
      await expect(nav).toHaveCSS('backdrop-filter', 'blur(16px)');
      await page.evaluate(() => scrollTo({ top: 0, behavior: 'instant' }));
      await expect(nav).toHaveCSS('background-color', transparent);
      await page.goBack({ waitUntil: 'domcontentloaded' });
      await expect(nav).toHaveCSS('background-color', transparent);
    } finally {
      release();
      await page.unrouteAll({ behavior: 'wait' });
    }
  });
}

test('header keeps a readable solid fallback without JavaScript', async ({ browser, baseURL }) => {
  const context = await browser.newContext({ javaScriptEnabled: false, baseURL });
  try {
    const page = await context.newPage();
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#navbar-main')).toHaveCSS('background-color', 'rgba(94, 114, 228, 0.85)');
    await expect(page.locator('#navbar-main .navbar-title')).toBeVisible();
  } finally { await context.close(); }
});
