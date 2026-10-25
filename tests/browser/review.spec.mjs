import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
const fixture = () => JSON.parse(readFileSync('temp/browser-fixture.json', 'utf8'));
const errors = [];
test.beforeEach(({page}) => { errors.length = 0; page.on('pageerror', error => errors.push(error.message)); });
test.afterEach(() => expect(errors).toEqual([]));

test('code lines track wrapping, copy exact source, and escape fullscreen fallback', async ({page, context}) => {
  await context.grantPermissions(['clipboard-read', 'clipboard-write']);
  await page.addInitScript(() => { Element.prototype.requestFullscreen = async () => { throw new Error('Unavailable'); }; });
  await page.goto(`/?p=${fixture().normal}`, {waitUntil:'domcontentloaded'});
  const pre = page.locator('pre.hljs-codeblock').first(), code = pre.locator('code'), gutter = pre.locator('.argon-code-gutter');
  await expect(code).toHaveAttribute('data-highlighted', 'yes');
  const source = await code.textContent();
  await pre.hover();
  await pre.locator('.hljs-control-copy').click();
  await expect.poll(() => page.evaluate(async () => (await navigator.clipboard.readText()).replace(/\r\n/g, '\n'))).toBe(source);
  await page.setViewportSize({width:390,height:844});
  await code.evaluate(el => { el.style.width = '160px'; });
  if (!(await pre.getAttribute('class')).includes('hljs-break-line')) await pre.locator('.hljs-control-toggle-break-line').click();
  await expect(gutter).toBeVisible();
  await expect.poll(() => gutter.locator('span').nth(1).evaluate(el => parseFloat(el.style.top))).toBeGreaterThan(24);
  await pre.evaluate(el => el.classList.add('hljs-transparent-linenumber'));
  expect(await gutter.evaluate(el => getComputedStyle(el).backgroundColor)).toBe('rgba(0, 0, 0, 0)');
  await pre.locator('.hljs-control-fullscreen').click();
  await expect(page.locator('html')).toHaveClass(/argon-code-fullscreen/);
  await page.keyboard.press('Escape');
  await expect(page.locator('html')).not.toHaveClass(/argon-code-fullscreen/);
  await pre.locator('.hljs-control-toggle-linenumber').click();
  await expect(gutter).toBeHidden();
});

test('gallery uses full image dimensions and leaves core lightbox in control', async ({page}) => {
  await page.route('**/gallery-*.svg', route => {
    const width = route.request().url().includes('full-1') ? 1200 : route.request().url().includes('full-2') ? 1800 : 160;
    return route.fulfill({contentType:'image/svg+xml',body:`<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${width/2}"><rect width="100%" height="100%" fill="purple"/></svg>`});
  });
  await page.goto(`/?p=${fixture().normal}`, {waitUntil:'domcontentloaded'});
  await page.locator('#gallery-review-1').click();
  await expect(page.locator('.pswp')).toBeVisible();
  await expect(page.locator('.pswp__counter')).toHaveText('2 / 3');
  await expect(page.locator('.pswp')).toBeFocused();
  await page.keyboard.press('ArrowRight');
  await expect(page.locator('.pswp__counter')).toHaveText('3 / 3');
  const full = page.locator('.pswp__img[src*="full-2"]');
  await expect.poll(() => full.evaluate(el => el.width)).toBeGreaterThan(160);
  await page.keyboard.press('Escape');
  await expect(page.locator('.pswp')).toHaveCount(0);
  const core = page.locator('.wp-lightbox-container').filter({has:page.locator('#core-lightbox-fixture')});
  await expect(core).toBeVisible();
  const dimensions = await page.locator('#core-lightbox-fixture').boundingBox();
  expect(dimensions.width / dimensions.height).toBeCloseTo(1, 1);
  await core.locator('button').click();
  await expect(page.locator('.wp-lightbox-overlay.active')).toBeVisible();
  await expect(page.locator('.pswp')).toHaveCount(0);
  await page.keyboard.press('Escape');
  await page.locator('#post_content').evaluate(el => el.insertAdjacentHTML('beforeend', '<p><img id="legacy-image-inset" src="/gallery-full-1.svg" alt="Classic image"></p>'));
  const inset=page.locator('#legacy-image-inset');
  await expect.poll(()=>inset.evaluate(el=>el.naturalWidth)).toBe(1200);
  const box=await inset.boundingBox(), parent=await inset.locator('..').boundingBox();
  const viewport=page.viewportSize();
  expect(box.width).toBeCloseTo(parent.width-Math.min(viewport.width*.2,parent.width*.4),0);
  expect(box.x-parent.x).toBeCloseTo((parent.width-box.width)/2,0);
});

test('AI regeneration stops after the editor fails to save', async ({page}) => {
  await page.goto('/wp-login.php', {waitUntil:'domcontentloaded'});
  // Core focuses this field on a 200 ms timer; wait before typing passwords.
  await expect(page.locator('#user_login')).toBeFocused();
  await page.locator('#user_login').fill('admin'); await page.locator('#user_pass').fill('argon-browser-local-test');
  await Promise.all([page.waitForURL('**/wp-admin/**', {waitUntil:'domcontentloaded'}), page.locator('#wp-submit').click()]);
  await page.goto(`/wp-admin/post.php?post=${fixture().normal}&action=edit`, {waitUntil:'domcontentloaded'});
  await page.waitForFunction(() => window.wp?.data?.select('core/editor').getCurrentPostId());
  await expect(page.getByRole('dialog', {name:'Welcome to the editor'})).toBeHidden();
  const settings = page.getByRole('button', {name:'Argon 文章设置', exact:true});
  if (await settings.getAttribute('aria-expanded') !== 'true') await settings.click();
  await expect(settings).toHaveAttribute('aria-expanded', 'true');
  await page.evaluate(() => wp.data.dispatch('core/editor').editPost({title:'Unsaved review fixture'}));
  let regenerated = false;
  page.on('request', request => { if (request.postData()?.includes('action=regenerate_ai_post_summary')) regenerated = true; });
  await page.route('**/*', route => {
    const request = route.request();
    if (request.method() === 'POST' && /wp\/v2\/posts/.test(decodeURIComponent(request.url()))) return route.fulfill({status:403,contentType:'application/json',body:JSON.stringify({code:'rest_forbidden',message:'Synthetic save failure',data:{status:403}})});
    return route.continue();
  });
  await page.getByRole('button', {name:'重新生成 AI 摘要', exact:true}).click();
  await expect(page.locator('.components-notice__content').filter({hasText:'文章保存失败，请先保存后再生成摘要'})).toBeVisible();
  expect(regenerated).toBe(false);
});
