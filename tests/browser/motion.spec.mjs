import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
const fixture = () => JSON.parse(readFileSync('temp/browser-fixture.json', 'utf8'));

async function midway(locator) {
  await expect.poll(() => locator.evaluate(el => el.getAnimations().filter(a => a.playState === 'running').length)).toBeGreaterThan(0);
  return locator.evaluate(el => {
    for (const animation of el.getAnimations()) {
      animation.pause(); animation.currentTime = Number(animation.effect.getTiming().duration) / 2;
    }
    const rect = el.getBoundingClientRect();
    return { height: rect.height, fullHeight: el.scrollHeight, left: rect.left };
  });
}
const finish = locator => locator.evaluate(el => el.getAnimations().forEach(animation => animation.finish()));

test('catalog branches animate, reverse cleanly and respect reduced motion', async ({ page }) => {
  await page.emulateMedia({ reducedMotion: 'no-preference' });
  await page.goto(`/?page_id=${fixture().catalog}`, { waitUntil: 'domcontentloaded' });
  await page.locator('#leftbar_tab_catalog_btn').click();
  const first = page.locator('#leftbar_catalog a[href="#year-2026"]').locator('..').locator(':scope > ul');
  const second = page.locator('#leftbar_catalog a[href="#year-2025"]').locator('..').locator(':scope > ul');
  await expect(first).toBeVisible(); await expect(second).toBeHidden();
  const move = id => page.evaluate(id => scrollTo({ top: document.getElementById(id).getBoundingClientRect().top + scrollY - 90, behavior: 'instant' }), id);
  await move('month-december');
  const open = await midway(second);
  expect(open.height).toBeGreaterThan(0); expect(open.height).toBeLessThan(open.fullHeight);
  await move('month-may'); // Reverse before the first animation completes.
  await expect(first.locator('..').locator(':scope > a')).toHaveAttribute('aria-expanded', 'true');
  await move('month-october');
  await expect(second.locator('..').locator(':scope > a')).toHaveAttribute('aria-expanded', 'true');
  await finish(first); await finish(second);
  await expect(first).toBeHidden(); await expect(second).toBeVisible();
  expect(await second.evaluate(el => el.style.height)).toBe(''); // Natural height survives font/viewport changes.
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await move('month-may');
  await expect(second).toBeHidden(); await expect(first).toBeVisible();
  expect(await first.evaluate(el => el.getAnimations().length)).toBe(0);
});

test('disclosures, comments, notices, navigation and dialogs retain their transitions', async ({ page }) => {
  await page.emulateMedia({ reducedMotion: 'no-preference' });
  await page.goto(`/?p=${fixture().normal}`, { waitUntil: 'domcontentloaded' });
  const collapse = page.locator('.collapse-block-title'), body = page.locator('.collapse-block-body');
  await collapse.click();
  const opening = await midway(body);
  expect(opening.height).toBeGreaterThan(0); expect(opening.height).toBeLessThan(opening.fullHeight);
  await collapse.press('Enter'); // Closing reverses the paused opening frame.
  await midway(body); await finish(body); await expect(body).toBeHidden();
  await collapse.press('Enter'); await finish(body); await expect(body).toBeVisible();

  const textarea = page.locator('#post_comment_content');
  await expect(textarea).toHaveCSS('min-height', '120px');
  await expect(page.locator('#post_comment_name')).toHaveCSS('height', '48px');
  await expect(page.locator('#post_comment_name')).toHaveCSS('padding-left', '14px');
  const extra = page.locator('#post_comment_extra_input');
  await page.locator('#post_comment_toggle_extra_input').click();
  await midway(extra); await finish(extra); await expect(extra).toBeVisible();
  await page.locator('#post_comment_toggle_extra_input').click();
  await midway(extra); await finish(extra); await expect(extra).toBeHidden();
  const comment = page.locator(`#comment-${fixture().comment}`);
  await comment.hover(); await comment.locator('.comment-reply').click();
  const reply = page.locator('#post_comment_reply_info');
  await midway(reply); await finish(reply); await expect(reply).toBeVisible();
  await page.locator('#post_comment_reply_cancel').click();
  await midway(reply); await finish(reply); await expect(reply).toBeHidden();
  await page.locator('#post_comment_send').click(); // Empty content produces a local notice only.
  const notice = page.locator('.argon-notice').last();
  await midway(notice); await finish(notice); await notice.click();
  await expect(notice).toHaveCount(0);

  await page.locator('#share_show').click(); await page.locator('#share_qrcode').click();
  const dialog = page.locator('.argon-qr-dialog');
  await midway(dialog); await finish(dialog); await page.keyboard.press('Escape');
  await expect(dialog).toHaveCount(0);

  await page.evaluate(() => {
    scrollTo({ top: 0, behavior: 'instant' });
    document.querySelector('#navbar-main .container').insertAdjacentHTML('beforeend', '<div class="dropdown"><button id="motion-menu" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">Motion menu</button><div id="motion-menu-body" class="dropdown-menu"><a class="dropdown-item" href="#">Menu entry</a></div></div>');
  });
  await page.locator('#motion-menu').click();
  await midway(page.locator('#motion-menu-body')); await finish(page.locator('#motion-menu-body'));
  await page.locator('#motion-menu').press('Escape');
  await expect(page.locator('#motion-menu-body')).toBeHidden();
  await page.setViewportSize({ width: 390, height: 844 });
  await page.locator('#open_sidebar').click();
  const sidebar = page.locator('#leftbar');
  const moving = await midway(sidebar); expect(moving.left).toBeLessThan(0); expect(moving.left).toBeGreaterThan(-285);
  await finish(sidebar); await page.locator('#sidebar_mask').click({ position: { x: 370, y: 500 } });
  await expect(page.locator('html')).not.toHaveClass(/leftbar-opened/);
});
