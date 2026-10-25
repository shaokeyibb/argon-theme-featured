import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
const fixture = () => JSON.parse(readFileSync('temp/browser-fixture.json', 'utf8'));

async function openDocument(page) {
  await page.goto(`/?p=${fixture().html}`, { waitUntil: 'domcontentloaded' });
  const frame = page.frameLocator('.argon-html-frame');
  await expect(frame.locator('#script-status')).toHaveText('脚本已执行');
  return frame;
}

async function expectContentFits(frame) {
  await expect.poll(() => frame.locator('body').evaluate(body => {
    const root = document.documentElement;
    const bottom = body.getBoundingClientRect().bottom + scrollY + parseFloat(getComputedStyle(body).marginBottom);
    return Math.max(root.scrollHeight - root.clientHeight, Math.abs(innerHeight - Math.ceil(bottom)));
  })).toBeLessThanOrEqual(1);
}

test('HTML articles grow and shrink through repeated desktop/mobile resizing', async ({ page }) => {
  // Keep motion enabled: the old primary-column width transition squeezed long
  // documents at breakpoints, permanently triggering the height safety limit.
  await page.emulateMedia({ reducedMotion: 'no-preference' });
  await page.setViewportSize({ width: 1440, height: 900 });
  const frame = await openDocument(page);
  await frame.locator('main').evaluate(main => {
    for (let i = 0; i < 60; i++) {
      const p = document.createElement('p');
      p.textContent = 'Responsive article content should wrap naturally as the reading width changes. '.repeat(8);
      main.append(p);
    }
  });
  for (const width of [1440, 320, 1440, 390, 844, 320, 1440]) {
    await page.setViewportSize({ width, height: 900 });
    await expectContentFits(frame);
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth - innerWidth)).toBeLessThanOrEqual(1);
    if (width <= 600) {
      const bounds = await page.locator('.argon-html-frame').boundingBox();
      expect(bounds.x).toBeCloseTo(0, 0);
      // Desktop WebKit reserves space for the outer page's scrollbar.
      expect(bounds.width).toBeCloseTo(await page.evaluate(() => document.documentElement.clientWidth), 0);
    }
  }
  await page.setViewportSize({ width: 390, height: 844 });
  await page.evaluate(() => document.documentElement.classList.add('single-column'));
  await expectContentFits(frame);
  const singleColumn = await page.locator('.argon-html-frame').boundingBox();
  expect(singleColumn.x).toBeCloseTo(0, 0);
  expect(singleColumn.width).toBeCloseTo(await page.evaluate(() => document.documentElement.clientWidth), 0);
});

test('automatic height includes body margins and recovers after oversized content collapses', async ({ page }) => {
  const frame = await openDocument(page);
  await frame.locator('body').evaluate(body => { body.style.margin = '12px 0 28px'; });
  await expectContentFits(frame);
  const before = await page.locator('.argon-html-frame').evaluate(el => el.clientHeight);
  await frame.locator('#expand').click();
  await expectContentFits(frame);
  expect(await page.locator('.argon-html-frame').evaluate(el => el.clientHeight)).toBeGreaterThan(before + 300);
  await frame.locator('#expanded').evaluate(el => { el.style.height = '61000px'; });
  await expect.poll(() => page.locator('.argon-html-frame').evaluate(el => el.clientHeight)).toBeLessThan(1201);
  await frame.locator('#expanded').evaluate(el => { el.style.display = 'none'; });
  await expectContentFits(frame);
  expect(Math.abs(await page.locator('.argon-html-frame').evaluate(el => el.clientHeight) - before)).toBeLessThanOrEqual(1);
});

test('viewport-sized documents stop feedback growth and keep internal scrolling usable', async ({ page }) => {
  const frame = await openDocument(page);
  await frame.locator('body').evaluate(body => {
    body.innerHTML = '<main style="min-height:100vh;padding:20px">Viewport application</main>';
    body.style.margin = '8px';
  });
  await expect.poll(() => page.locator('.argon-html-frame').evaluate(el => el.clientHeight), {timeout: 10000}).toBe(612);
  await page.waitForTimeout(1200);
  expect(await page.locator('.argon-html-frame').evaluate(el => el.clientHeight)).toBe(612);
  expect(await frame.locator('body').evaluate(() => document.documentElement.scrollHeight > innerHeight)).toBe(true);
});
