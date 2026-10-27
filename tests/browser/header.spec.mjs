import { test, expect } from '@playwright/test';

const transparent = /rgba\(\d+, \d+, \d+, 0\)/;
const themeScripts = /\/assets\/dist\/[^/]+\.js(?:\?.*)?$/;

// Desktop engines report zero hardware insets. Override only their CSS inputs;
// all layout, scrolling and interactions still use the real theme and browser.
const setInsets = (page, { top = 0, right = 0, bottom = 0, left = 0 }) => page.evaluate(insets => {
  for (const [side, value] of Object.entries(insets)) document.documentElement.style.setProperty(`--argon-safe-${side}`, `${value}px`);
}, { top, right, bottom, left });

test('iOS uses one solid toolbar without a strip or extra layout space at zero inset', async ({ page }) => {
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/', { waitUntil: 'domcontentloaded' });
  await setInsets(page, {});
  await page.evaluate(() => document.fonts.ready);
  const geometry = () => page.evaluate(() => {
    const box = selector => document.querySelector(selector).getBoundingClientRect();
    return { headerHeight: box('#navbar-main').height, titleTop: box('#navbar-main .navbar-title').top, bannerTop: box('#banner').top, contentTop: box('#content').top };
  });
  const originalGeometry = await geometry();
  // Desktop engines do not expose iOS browser chrome. Exercise the same CSS
  // inputs selected by the iOS feature query, including the JS scroll updates.
  await page.evaluate(() => {
    document.documentElement.style.setProperty('--argon-toolbar-min-opacity', '1');
    document.documentElement.style.setProperty('--argon-toolbar-backdrop', 'none');
    document.body.style.minHeight = '3000px';
  });
  const surface = page.locator('#argon-viewport-top-surface'), nav = page.locator('#navbar-main');
  await expect(surface).toHaveAttribute('aria-hidden', 'true');
  await expect(surface).toHaveCSS('height', '0px');
  await expect(surface).toBeHidden();
  await expect(page.locator('body')).toHaveCSS('padding-top', '0px');
  await expect(page.locator('html')).toHaveCSS('scroll-padding-top', '90px');
  await expect.poll(geometry).toEqual(originalGeometry);
  for (const dark of [false, true]) {
    await page.evaluate(dark => document.documentElement.classList.toggle('darkmode', dark), dark);
    const color = await nav.evaluate(el => {
      const sample = document.createElement('div');
      sample.style.backgroundColor = `rgb(${getComputedStyle(el).getPropertyValue('--toolbar-color')})`;
      el.append(sample);
      const color = getComputedStyle(sample).backgroundColor;
      sample.remove();
      return color;
    });
    for (const top of [0, 700, 0]) {
      await page.evaluate(top => scrollTo({ top, behavior: 'instant' }), top);
      await expect(nav).toHaveCSS('background-color', color);
      await expect(nav).toHaveCSS('backdrop-filter', 'none');
      expect(await page.evaluate(() => document.elementFromPoint(document.documentElement.clientWidth / 2, 6)?.closest('#navbar-main')?.id)).toBe('navbar-main');
      await expect(surface).toHaveCSS('height', '0px');
    }
  }
  await setInsets(page, { top: 59 });
  await expect(surface).toHaveCSS('height', '59px');
  await expect(page.locator('body')).toHaveCSS('padding-top', '59px');
  await page.emulateMedia({ media: 'print' });
  await expect(surface).toBeHidden();
});

test('safe areas protect status bar, navigation and controls across rotation', async ({ page }) => {
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('meta[name="viewport"]')).toHaveAttribute('content', /viewport-fit=cover/);
  const nav = page.locator('#navbar-main');
  const zeroHeight = (await nav.boundingBox()).height;
  await setInsets(page, { top: 59, bottom: 34 });
  await expect.poll(async () => (await nav.boundingBox()).height).toBeCloseTo(zeroHeight + 59, 1);
  expect((await nav.locator('.navbar-title').boundingBox()).y).toBeGreaterThanOrEqual(59);
  await page.evaluate(() => document.documentElement.classList.add('is-home', 'banner-as-cover'));
  await expect.poll(async () => {
    const cover = await page.locator('#banner').boundingBox();
    return { top: cover.y, bottom: Math.round(cover.y + cover.height) };
  }).toEqual({ top: 59, bottom: 844 });
  await page.evaluate(() => document.documentElement.classList.remove('banner-as-cover'));

  for (const dark of [false, true]) {
    await page.evaluate(dark => document.documentElement.classList.toggle('darkmode', dark), dark);
    const surface = await page.evaluate(() => {
      const style = getComputedStyle(document.querySelector('#argon-viewport-top-surface'));
      const sample = document.createElement('div');
      sample.style.backgroundColor = `rgb(${getComputedStyle(document.querySelector('#navbar-main')).getPropertyValue('--toolbar-color')})`;
      document.body.append(sample);
      const expected = getComputedStyle(sample).backgroundColor;
      sample.remove();
      return { height: style.height, position: style.position, color: style.backgroundColor, expected, pointer: style.pointerEvents };
    });
    expect(surface).toMatchObject({ height: '59px', position: 'fixed', pointer: 'auto' });
    await expect.poll(() => page.evaluate(() => getComputedStyle(document.querySelector('#argon-viewport-top-surface')).backgroundColor)).toBe(surface.expected);
    expect(surface.expected).not.toMatch(transparent);
  }
  await page.evaluate(() => {
    document.body.style.minHeight = '3000px';
    scrollTo({ top: 700, behavior: 'instant' });
  });
  await expect(nav).not.toHaveCSS('background-color', transparent);
  // The independent status surface survives both alternate header modes.
  await page.evaluate(() => document.body.classList.add('leftbar-can-headroom', 'headroom---unpinned'));
  await expect.poll(async () => (await nav.boundingBox()).y + (await nav.boundingBox()).height).toBeLessThanOrEqual(1);
  expect(await page.evaluate(() => getComputedStyle(document.querySelector('#argon-viewport-top-surface')).height)).toBe('59px');
  await page.evaluate(() => {
    document.body.classList.remove('headroom---unpinned');
    document.documentElement.classList.add('navbar-absolute');
  });
  await expect(nav).toHaveCSS('position', 'absolute');
  expect(await page.evaluate(() => getComputedStyle(document.querySelector('#argon-viewport-top-surface')).position)).toBe('fixed');
  await page.evaluate(() => {
    document.documentElement.classList.remove('navbar-absolute');
    scrollTo({ top: 0, behavior: 'instant' });
  });

  await page.setViewportSize({ width: 844, height: 390 });
  await setInsets(page, { left: 47, right: 47, bottom: 21 });
  await expect(page.locator('body')).toHaveCSS('padding-left', '47px');
  const title = await nav.locator('.navbar-title').boundingBox();
  expect(title.x).toBeGreaterThanOrEqual(47);
  expect(title.x + title.width).toBeLessThanOrEqual(844 - 47);
  await nav.locator('[data-target="#navbar_global"]').last().click();
  const menu = page.locator('#navbar_global');
  await expect(menu).toBeVisible();
  const menuBox = await menu.boundingBox();
  expect(menuBox.x).toBeGreaterThanOrEqual(47);
  expect(menuBox.x + menuBox.width).toBeLessThanOrEqual(844 - 47);
  expect(menuBox.y + menuBox.height).toBeLessThanOrEqual(390 - 21);
  await page.locator('#navbar_menu_mask').click({ position: { x: 1, y: 380 } });
  await expect(menu).toBeHidden();
  await page.locator('#open_sidebar').click();
  await expect(page.locator('html')).toHaveClass(/leftbar-opened/);
  await expect(page.locator('#leftbar')).toHaveCSS('padding-left', '47px');
  await page.locator('#sidebar_mask').click({ position: { x: 800, y: 150 } });
  await expect(page.locator('html')).not.toHaveClass(/leftbar-opened/);
  const fab = page.locator('#float_action_buttons');
  await expect(fab).toHaveCSS('right', '67px');
  await expect(fab).toHaveCSS('bottom', '56px');
  await fab.evaluate(el => el.classList.add('fabtns-float-left'));
  await expect(fab).toHaveCSS('left', '67px');
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);

  await page.setViewportSize({ width: 390, height: 844 });
  await setInsets(page, { top: 59, bottom: 34 });
  await page.locator('#fabtn_toggle_blog_settings_popup').click();
  await expect(page.locator('#fabtn_blog_settings_popup')).toHaveCSS('padding-bottom', '44px');
  await setInsets(page, {});
  await expect(page.locator('body')).toHaveCSS('padding-top', '0px');
  await expect.poll(async () => (await nav.boundingBox()).height).toBeCloseTo(zeroHeight, 1);
});

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
      await page.goBack({ waitUntil: 'load' });
      // Clicking a card can scroll the source page (96px on mobile in CI).
      // History restores that position; check transparency after returning to top.
      await page.evaluate(() => scrollTo({ top: 0, behavior: 'instant' }));
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
    await setInsets(page, { top: 44, bottom: 34 });
    await expect.poll(async () => (await page.locator('#navbar-main .navbar-title').boundingBox()).y).toBeGreaterThanOrEqual(44);
    expect(await page.evaluate(() => getComputedStyle(document.querySelector('#argon-viewport-top-surface')).height)).toBe('44px');
  } finally { await context.close(); }
});

test('hidden banner and anchor navigation leave room for the safe header', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await page.route('**/?safe-area-no-banner', async route => {
    const response = await route.fetch();
    const html = (await response.text())
      .replace(/(<html\b[^>]*class=")/, '$1no-banner ')
      .replace('navbar-ontop', '');
    await route.fulfill({ response, body: html });
  });
  await page.goto('/?safe-area-no-banner', { waitUntil: 'domcontentloaded' });
  await setInsets(page, { top: 59, bottom: 34 });
  const nav = page.locator('#navbar-main');
  await expect(nav).not.toHaveCSS('background-color', transparent);
  const navBox = await nav.boundingBox();
  expect((await page.locator('#content').boundingBox()).y).toBeGreaterThanOrEqual(navBox.y + navBox.height);
  // Exercise native fragment scrolling as used by the table of contents.
  await page.evaluate(() => {
    document.body.style.minHeight = '4000px';
    const target = document.createElement('h2');
    target.id = 'safe-area-anchor';
    target.textContent = 'Anchor destination';
    target.style.marginTop = '1500px';
    document.querySelector('#main').append(target);
    location.hash = target.id;
  });
  await expect.poll(async () => (await page.locator('#safe-area-anchor').boundingBox()).y).toBeCloseTo(149, 0);
  // Returning to a rectangular display removes all additional spacing.
  await setInsets(page, {});
  await expect(page.locator('html')).toHaveCSS('scroll-padding-top', '90px');
});
