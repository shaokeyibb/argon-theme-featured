import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
const fixture = () => JSON.parse(readFileSync('temp/browser-fixture.json', 'utf8'));
const entry = JSON.parse(readFileSync('assets/dist/manifest.json', 'utf8'))['src/modern/index.js'];

test('sharing survives cache-versioned entry scripts and copies exactly once after lazy imports', async ({ page, context }) => {
  await context.grantPermissions(['clipboard-read', 'clipboard-write']);
  await page.addInitScript(() => {
    window.copies = [];
    const write = navigator.clipboard.writeText.bind(navigator.clipboard);
    navigator.clipboard.writeText = text => { window.copies.push(text); return write(text); };
    Object.defineProperty(navigator, 'share', { configurable: true, value: undefined });
  });
  await page.route('**/*', async route => {
    if (route.request().resourceType() !== 'document') return route.continue();
    const response = await route.fetch();
    const html = (await response.text()).replace(new RegExp(`${entry.file.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}(?:\\?[^"']*)?(?=["'])`, 'g'), `${entry.file}?cache_version=regression`);
    await route.fulfill({ response, body: html });
  });
  const errors = []; page.on('pageerror', error => errors.push(error.message));
  await page.goto(`/?p=${fixture().normal}`, { waitUntil: 'domcontentloaded' });
  await expect(page.locator('.hljs-control-copy')).toBeVisible(); // Lazy code module has loaded.
  await page.locator('#share_show').click();
  await expect(page.locator('#share_native')).toBeHidden();
  await page.locator('#share_qrcode').click();
  await expect(page.locator('.argon-qr-dialog img')).toHaveAttribute('src', /^data:image\/png/);
  await expect(page.locator('.argon-qr-dialog')).toHaveCount(1);
  await page.keyboard.press('Escape'); await expect(page.locator('.argon-qr-dialog')).toHaveCount(0);
  await page.locator('#share_copy_link').click();
  await expect(page.locator('.argon-notice')).toHaveCount(1);
  const info = await page.evaluate(() => window.shareInfo);
  await expect.poll(() => page.evaluate(() => navigator.clipboard.readText())).toBe(info.url);
  expect(await page.evaluate(() => window.copies)).toEqual([info.url]);
  expect(await page.evaluate(file => performance.getEntriesByType('resource').filter(r => r.name.includes('/' + file)).map(r => r.name), entry.file)).toHaveLength(1);
  const links = await page.locator('#share a[data-share-platform]').evaluateAll(links => links.map(a => ({ platform: a.dataset.sharePlatform, href: a.href, rel: a.rel, icon: getComputedStyle(a.querySelector('i'), '::before').content })));
  expect(links.map(l => l.platform)).toEqual(['weibo', 'x', 'telegram', 'whatsapp', 'facebook', 'reddit', 'bluesky']);
  for (const link of links) {
    expect(new URL(link.href).protocol).toBe('https:'); expect(link.rel).toBe('noopener noreferrer');
    expect(link.icon).not.toBe('none');
    const params = new URL(link.href).searchParams;
    if (['whatsapp', 'bluesky'].includes(link.platform)) expect(params.get('text')).toBe(`${info.title} ${info.url}`);
    else expect(params.get('url') || params.get('u')).toBe(info.url);
  }
  await context.route('https://t.me/**', route => route.fulfill({ contentType: 'text/html', body: '<title>Share destination</title>' }));
  const popup = page.waitForEvent('popup'); await page.locator('[data-share-platform="telegram"]').click();
  const target = await popup; await expect(target).toHaveURL(/https:\/\/t.me\/share\/url/); await target.close();
  await page.setViewportSize({ width: 390, height: 844 });
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBe(390);
  expect(errors).toEqual([]);
});

test('system share supports success, cancellation and failure without duplicate feedback', async ({ page }) => {
  await page.addInitScript(() => {
    window.shared = []; window.shareMode = 'success';
    Object.defineProperty(navigator, 'canShare', { configurable: true, value: () => true });
    Object.defineProperty(navigator, 'share', { configurable: true, value: async data => {
      window.shared.push(data);
      if (window.shareMode !== 'success') throw new DOMException('Share result', window.shareMode);
    } });
  });
  await page.goto(`/?p=${fixture().normal}`, { waitUntil: 'domcontentloaded' });
  await page.locator('#share_show').click();
  const native = page.locator('#share_native');
  await native.click(); await expect(native).toBeEnabled();
  expect(await page.evaluate(() => window.shared)).toEqual([await page.evaluate(() => ({ ...window.shareInfo, text: window.shareInfo.title }))]);
  await page.evaluate(() => window.shareMode = 'AbortError'); await native.click();
  await expect(page.locator('.argon-notice')).toHaveCount(0);
  await page.evaluate(() => window.shareMode = 'NotAllowedError'); await native.click();
  await expect(page.locator('.argon-notice.is-error')).toHaveCount(1);
  await expect(native).toBeEnabled();
});

test('share links work without JavaScript and blocked clipboard offers the canonical URL', async ({ browser, page }) => {
  const context = await browser.newContext({ javaScriptEnabled: false }); const plain = await context.newPage();
  await plain.goto(`http://127.0.0.1:9400/?p=${fixture().normal}`);
  await expect(plain.locator('#share [data-share-platform="bluesky"]')).toBeVisible();
  await expect(plain.locator('#share_show')).toBeHidden(); await context.close();
  await page.addInitScript(() => { navigator.clipboard.writeText = async () => { throw new DOMException('Denied', 'NotAllowedError'); }; });
  await page.goto(`/?p=${fixture().normal}`, { waitUntil: 'domcontentloaded' }); await page.locator('#share_show').click();
  const url = await page.evaluate(() => window.shareInfo.url);
  const dialog = page.waitForEvent('dialog'); const click = page.locator('#share_copy_link').click();
  const prompt = await dialog; expect(prompt.defaultValue()).toBe(url); await prompt.dismiss(); await click;
  await expect(page.locator('.argon-notice')).toHaveCount(0);
});
