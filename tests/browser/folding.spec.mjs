import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
const fixture = () => JSON.parse(readFileSync('temp/browser-fixture.json', 'utf8'));

test('comment folding rechecks natural height and keeps its compact expand button inside the clip', async ({ page }) => {
  await page.goto(`/?p=${fixture().normal}`, { waitUntil: 'domcontentloaded' });
  const comment = page.locator(`#comment-inner-${fixture().comment}`), text = comment.locator('.comment-item-text');
  await text.evaluate(el => el.innerHTML = '<div style="height:1200px">Long comment</div>');
  await expect(comment).toHaveClass(/comment-folded/);
  await expect(comment).toHaveCSS('max-height', '200px');
  const expand = comment.locator('.argon-fold-toggle');
  await expect(expand).toHaveCSS('height', '32px');
  await expect(expand).toHaveCSS('font-size', '13px');
  const bounds = await comment.boundingBox(), control = await expand.boundingBox();
  expect(control.y + control.height).toBeLessThanOrEqual(bounds.y + bounds.height - 6);
  expect(control.y).toBeGreaterThanOrEqual(bounds.y);
  await expand.focus(); await expect(expand).toBeFocused();
  // Still taller than the 200px clip, but shorter than the 800px threshold.
  // Observing only the clipped parent cannot notice this change.
  await text.locator('div').evaluate(el => el.style.height = '400px');
  await expect(comment).not.toHaveClass(/comment-folded/);
  await expect(expand).toHaveCount(0);
  await text.evaluate(el => el.innerHTML = '<p>' + 'A longer comment<br>'.repeat(70) + '</p>');
  await expect(comment).toHaveClass(/comment-folded/);
  await expect(expand).toHaveCount(1);
  await expand.focus(); await page.keyboard.press('Enter');
  await expect(comment).toHaveClass(/comment-unfolded/);
  await expect(expand).toHaveCount(0);
  await page.setViewportSize({ width: 390, height: 844 });
  await expect(comment).not.toHaveClass(/comment-folded/);
  expect((await comment.boundingBox()).height).toBeGreaterThan(800);
});

test('responsive width transitions never leave short comments or shuoshuo folded', async ({ page }) => {
  await page.emulateMedia({ reducedMotion: 'no-preference' });
  await page.goto(`/?p=${fixture().normal}`, { waitUntil: 'domcontentloaded' });
  const comment = page.locator(`#comment-inner-${fixture().comment}`);
  await expect(comment).not.toHaveClass(/comment-folded/);
  await verifyWidths(page, comment, 'comment');
  await page.route('**/folding-delayed.svg', route => route.fulfill({ contentType: 'image/svg+xml', body: '<svg xmlns="http://www.w3.org/2000/svg" width="800" height="1200"></svg>' }));
  await page.goto('/?paged=2', { waitUntil: 'domcontentloaded' });
  const status = page.locator(`#post-${fixture().folding} .shuoshuo-content`);
  await expect(status).toHaveClass(/shuoshuo-folded/);
  await status.evaluate(el => { el.querySelector('p').textContent = 'A short status.'; el.querySelector('img').remove(); });
  await expect(status).not.toHaveClass(/shuoshuo-folded/);
  await expect(status.locator('.show-full-shuoshuo')).toHaveCount(0);
  await verifyWidths(page, status, 'shuoshuo');
});

async function verifyWidths(page, locator, prefix) {
  await locator.evaluate((el, prefix) => {
    window.falseFolds = [];
    new MutationObserver(() => { if (el.classList.contains(prefix + '-folded')) window.falseFolds.push(el.clientWidth); }).observe(el, { attributes: true, attributeFilter: ['class'] });
  }, prefix);
  for (const width of [390, 1440, 390, 1440]) {
    await page.setViewportSize({ width, height: 844 });
    await locator.evaluate(el => new Promise(resolve => {
      let previous = el.clientWidth, changed = performance.now();
      const tick = () => {
        if (el.clientWidth !== previous) { previous = el.clientWidth; changed = performance.now(); }
        if (performance.now() - changed > 250) resolve(); else requestAnimationFrame(tick);
      }; requestAnimationFrame(tick);
    }));
    await expect(locator).not.toHaveClass(new RegExp(prefix + '-folded'));
    await expect(locator.locator('.show-full-' + prefix)).toHaveCount(0);
  }
  expect(await page.evaluate(() => window.falseFolds)).toEqual([]);
}
