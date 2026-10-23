import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
const fixture = () => JSON.parse(readFileSync('temp/browser-fixture.json', 'utf8'));
const errors = [];
test.beforeEach(async ({page}) => {
  errors.length = 0;
  page.on('pageerror', error => errors.push(error.message));
  await page.route('https://secure.gravatar.com/**', route => route.abort());
  await page.route('**/gallery-*.svg', route => {
    const width = route.request().url().includes('full-1') ? 1200 : route.request().url().includes('full-2') ? 1800 : 160;
    return route.fulfill({contentType:'image/svg+xml', body:`<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${width/2}"><rect width="100%" height="100%" fill="#5e72e4"/></svg>`});
  });
});
test.afterEach(() => expect(errors).toEqual([]));
async function login(page) {
  await page.goto('/wp-login.php', {waitUntil:'domcontentloaded'});
  await page.locator('#user_login').fill('admin');
  await page.locator('#user_pass').fill('argon-browser-local-test');
  await Promise.all([page.waitForURL('**/wp-admin/**', {waitUntil:'domcontentloaded'}), page.locator('#wp-submit').click()]);
}
async function toggleDarkMode(page) {
  await page.locator('#fabtn_toggle_blog_settings_popup').click();
  await page.locator('#blog_setting_darkmode_switch .custom-toggle-slider').click();
  await page.locator('#close_blog_settings').click();
}
test('native frontend, shortcodes, preferences, lightbox and responsive layout', async ({page,context}) => {
  await page.addInitScript(() => Object.defineProperty(crypto, 'randomUUID', {value:undefined}));
  await page.goto(`/?p=${fixture().normal}`, {waitUntil:'domcontentloaded'});
  await expect(page.locator('.katex')).toBeVisible();
  await expect(page.locator('pre code[data-highlighted]')).toBeVisible();
  await expect(page.locator('#pangu-fixture')).toHaveText('中文 English 自动排版');
  await page.locator('#dropdown-fixture').click();
  await expect(page.locator('#dropdown-fixture + .dropdown-menu')).toBeVisible();
  await page.locator('#dropdown-fixture').press('Escape');
  await expect(page.locator('#dropdown-fixture + .dropdown-menu')).toBeHidden();
  await page.locator('#tab-review-link').click();
  await expect(page.locator('#tab-review-b')).toBeVisible();
  await expect(page.locator('#tab-review-a')).toBeHidden();
  expect(await page.evaluate(() => typeof window.jQuery)).toBe('undefined');
  expect((await context.cookies()).some(cookie => cookie.name === 'argon_user_token')).toBe(false);
  await page.locator('#navbar_search_input_container').click();
  await expect(page.locator('#navbar_search_input')).toBeFocused();
  await page.locator('#navbar_search_input').press('Tab');
  await page.locator('.collapse-block-title').click();
  await expect(page.locator('#collapse-fixture')).toBeVisible();
  await page.locator('.collapse-block-title').press('Enter');
  await expect(page.locator('#collapse-fixture')).toBeHidden();
  await toggleDarkMode(page);
  await expect(page.locator('html')).toHaveClass(/darkmode/);
  await page.reload({waitUntil:'domcontentloaded'});
  await expect(page.locator('html')).toHaveClass(/darkmode/);
  await toggleDarkMode(page);
  await page.locator('#post_content img').first().click();
  await expect(page.locator('.pswp')).toBeVisible();
  await expect(page.locator('.pswp')).toBeFocused();
  await page.keyboard.press('Escape');
  await expect(page.locator('.pswp')).toHaveCount(0);
  await page.screenshot({path:'temp/browser-desktop.png',fullPage:true});
  await page.setViewportSize({width:390,height:844});
  await page.evaluate(() => window.scrollTo(0,0));
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
  await page.locator('#open_sidebar').click();
  await expect(page.locator('html')).toHaveClass(/leftbar-opened/);
  await page.locator('#sidebar_mask').click({position:{x:370,y:500}});
  await expect(page.locator('html')).not.toHaveClass(/leftbar-opened/);
  await page.screenshot({path:'temp/browser-mobile.png',fullPage:true});
  expect(await page.locator('#navbar-main').evaluate(el => getComputedStyle(el).backgroundColor)).not.toBe('rgba(0, 0, 0, 0)');
  await page.locator('#navbar-main > .container > button[aria-label="Toggle navigation"]').click();
  await expect(page.locator('#navbar_search_input_mobile')).toBeVisible();
  await page.locator('#navbar_search_input_mobile').fill('Native');
  await Promise.all([page.waitForURL(/\?s=Native/, {waitUntil:'domcontentloaded'}), page.locator('#navbar_search_input_mobile').press('Enter')]);
  await expect(page.locator('.post-title').first()).toBeVisible();
});
test('anonymous comments can post, edit, reply and retain ownership', async ({page,context}) => {
  test.setTimeout(90000);
  await context.setExtraHTTPHeaders({
    'sec-ch-ua': '"Chromium";v="140", "Microsoft Edge";v="140", "Not_A Brand";v="99"',
    'sec-ch-ua-platform': '"Windows"',
    'sec-ch-ua-platform-version': '"19.0.0"',
    'sec-ch-ua-full-version-list': '"Microsoft Edge";v="140.0.3485.81", "Chromium";v="140.0.7339.81"',
  });
  await page.goto(`/?p=${fixture().normal}`, {waitUntil:'domcontentloaded'});
  await page.locator('#post_comment_name').fill('Modern browser reader');
  await page.locator('#post_comment_email').fill('reader@example.test');
  await page.locator('#post_comment_content').fill('中文English **modern** comment');
  const submitted = page.waitForResponse(r => r.url().includes('admin-ajax') && r.request().postData()?.includes('action=ajax_post_comment'));
  await page.locator('#post_comment_send').click();
  const result = await (await submitted).json();
  expect(result.status, JSON.stringify(result)).toBe('success');
  const comment = page.locator(`#comment-${result.id}`);
  await expect(comment).toContainText('modern');
  await expect(comment.locator('.comment-useragent')).toContainText('Windows 11');
  await expect(comment.locator('.comment-useragent')).toContainText('Edge 140.0.3485.81');
  await expect(comment.locator('.comment-item-text')).toContainText('中文 English');
  expect((await context.cookies()).find(c => c.name === 'argon_user_token')?.httpOnly).toBe(true);
  await comment.hover();
  await comment.locator('.comment-edit').click();
  await page.locator('#post_comment_content').fill('Edited modern comment 中文English');
  await page.locator('#post_comment_send').click();
  await expect(comment.locator('.comment-item-text')).toContainText('Edited modern comment');
  await expect(comment.locator('.comment-item-text')).toContainText('中文 English');
  await comment.hover();
  await comment.locator('.comment-reply').click();
  await expect(page.locator('#post_comment_reply_info')).toBeVisible();
  await page.locator('#post_comment_reply_cancel').click();
  await expect(page.locator('#post_comment_reply_info')).toBeHidden();
  await comment.hover();
  await comment.locator('.comment-reply').click();
  await page.locator('#post_comment_content').fill('A nested reply');
  // Respect WordPress's 15-second anonymous comment flood protection.
  await page.waitForTimeout(16000);
  const replied = page.waitForResponse(r => r.url().includes('admin-ajax') && r.request().postData()?.includes('action=ajax_post_comment'));
  await page.locator('#post_comment_send').click();
  const reply = await (await replied).json();
  expect(reply.status, JSON.stringify(reply)).toBe('success');
  expect(Number(reply.parentID)).toBe(Number(result.id));
  await expect(page.locator(`ul.children #comment-${reply.id}`)).toContainText('A nested reply');
  await page.reload({waitUntil:'domcontentloaded'});
  await expect(page.locator(`#comment-${result.id} .comment-edit`)).toHaveCount(1);
  await expect(comment.locator('.comment-useragent')).toContainText('Edge 140.0.3485.81');
  await login(page);
  await page.goto(`/?p=${fixture().normal}`, {waitUntil:'domcontentloaded'});
  await comment.hover();
  await comment.locator('.comment-pin').click();
  await page.locator('#comment_pin_comfirm_dialog .btn-comfirm').click();
  await expect(comment.locator('.badge-pinned')).toBeVisible();
  await comment.locator('.comment-edited.comment-edithistory-accessible').click();
  await expect(page.locator('#comment_edit_history .modal-body')).toContainText('modern');
});
test('HTML document remains sandboxed, resizes and syncs theme', async ({page}) => {
  await page.goto(`/?p=${fixture().html}`, {waitUntil:'domcontentloaded'});
  const iframe = page.locator('.argon-html-frame');
  await expect(iframe).toBeVisible();
  const frame = page.frameLocator('.argon-html-frame');
  await expect(frame.locator('#script-status')).toHaveText('脚本已执行');
  expect(await frame.locator('body').evaluate(() => window.parentAccessBlocked && window.cookieAccessBlocked)).toBe(true);
  const before = (await iframe.boundingBox()).height;
  await frame.locator('#expand').click();
  await expect.poll(async () => (await iframe.boundingBox()).height).toBeGreaterThan(before + 300);
  await toggleDarkMode(page);
  await expect(frame.locator('html')).toHaveAttribute('data-argon-theme','dark');
});
test('HTML documents keep nested grids, code and media within mobile and desktop widths', async ({page,browser}) => {
  await page.goto(`/?p=${fixture().html}`, {waitUntil:'domcontentloaded'});
  const frame = page.frameLocator('.argon-html-frame');
  await expect(frame.locator('#nested-layout')).toBeVisible();
  for (const width of [1440,390,320,844,1440]) {
    await page.setViewportSize({width,height:844});
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await expect.poll(() => frame.locator('body').evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    const layout = await frame.locator('#nested-layout').evaluate(el => {
      const code = el.querySelector('pre'), image = el.querySelector('img');
      const links = [...el.querySelectorAll('nav a')].map(a => a.getBoundingClientRect());
      code.scrollLeft = 120;
      return {
        containersFit: [...el.querySelectorAll('.timeline,.nested-body,.code-container')].every(node => node.scrollWidth <= node.clientWidth + 1),
        codeScrolls: code.scrollWidth > code.clientWidth && code.scrollLeft > 0,
        imageFits: image.getBoundingClientRect().right <= el.getBoundingClientRect().right,
        imageRatio: image.getBoundingClientRect().width / image.getBoundingClientRect().height,
        linksOverlap: links[0].right > links[1].left,
        authoredWidth: el.querySelector('#authored-size').getBoundingClientRect().width,
      };
    });
    expect(layout, `viewport ${width}`).toMatchObject({containersFit:true,codeScrolls:true,imageFits:true,linksOverlap:false,authoredWidth:48});
    expect(layout.imageRatio).toBeCloseTo(10, 1);
  }
  await page.setViewportSize({width:390,height:844});
  await page.reload({waitUntil:'domcontentloaded'});
  await expect(frame.locator('#wide-code')).toBeVisible();
  await expect.poll(() => frame.locator('body').evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
  const noScript = await browser.newPage({javaScriptEnabled:false,viewport:{width:390,height:844}});
  try {
    await noScript.goto(`http://127.0.0.1:9400/?p=${fixture().html}`, {waitUntil:'domcontentloaded'});
    const content = noScript.frameLocator('.argon-html-frame');
    await expect(content.locator('#script-status')).toHaveText('脚本尚未执行');
    expect(await content.locator('body').evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    expect(await content.locator('#wide-code').evaluate(el => el.scrollWidth > el.clientWidth && getComputedStyle(el).overflowX === 'auto')).toBe(true);
  } finally { await noScript.close(); }
});
test('settings load native controls and round-trip exports', async ({page}) => {
  await login(page);
  await page.goto('/wp-admin/admin.php?page=admin.php', {waitUntil:'domcontentloaded'});
  await expect(page.locator('#main_form')).toBeVisible();
  await expect(page.locator('script[src*="/assets/dist/admin-"]')).toHaveCount(1);
  const roundTrip = await page.evaluate(() => {
    const original = JSON.parse(window.exportArgonSettings());
    window.importArgonSettings({...original, argon_enable_lightbox:'false'});
    const changed = JSON.parse(window.exportArgonSettings());
    window.importArgonSettings(original);
    return {changed:changed.argon_enable_lightbox, restored:window.exportArgonSettings() === JSON.stringify(original,null,2)};
  });
  expect(roundTrip).toEqual({changed:'false',restored:true});
});
test('block editor registers API 3 blocks and saves revision-aware settings', async ({page}) => {
  await login(page);
  await page.goto(`/wp-admin/post.php?post=${fixture().normal}&action=edit`, {waitUntil:'domcontentloaded'});
  await page.waitForFunction(() => window.wp?.blocks?.getBlockType('argon/alert'));
  const welcome = page.getByRole('dialog', {name:'Welcome to the editor'});
  if (await welcome.isVisible()) await welcome.getByRole('button', {name:'Close',exact:true}).click();
  const legacyFixtures = JSON.parse(readFileSync('tests/fixtures/legacy-blocks.json', 'utf8'));
  const result = await page.evaluate((legacyFixtures) => {
    const types = wp.blocks.getBlockTypes().filter(type => type.name.startsWith('argon/'));
    const cases = types.filter(type => type.name !== 'argon/html-document').map(type => {
      const attrs = Object.fromEntries(Object.entries(type.attributes).filter(([,schema]) => 'default' in schema).map(([key,schema])=>[key,schema.default]));
      if ('content' in attrs) attrs.content = '<p>Preserved content</p>';
      if ('title' in attrs) attrs.title = 'Preserved heading';
      const block = wp.blocks.createBlock(type.name, attrs);
      const serialized = wp.blocks.serialize(block);
      const parsed = wp.blocks.parse(serialized)[0];
      const legacyParsed = wp.blocks.parse(legacyFixtures.find(item => item.name === type.name).html)[0];
      return {name:type.name,api:type.apiVersion,valid:parsed.isValid,legacyValid:legacyParsed.isValid};
    });
    return {count:types.length,cases};
  }, legacyFixtures);
  expect(result.count).toBe(9);
  for (const item of result.cases) expect(item, JSON.stringify(item)).toMatchObject({api:3,valid:true,legacyValid:true});
  await page.evaluate(() => {
    wp.data.dispatch('core/preferences').set('core/edit-post', 'welcomeGuide', false);
    const blocks = wp.blocks.getBlockTypes().filter(type => type.name.startsWith('argon/') && type.name !== 'argon/html-document')
      .map(type => wp.blocks.createBlock(type.name));
    wp.data.dispatch('core/block-editor').insertBlocks(blocks);
  });
  const canvas = page.frameLocator('iframe[name="editor-canvas"]');
  for (const item of result.cases) await expect(canvas.locator(`[data-type="${item.name}"]`).first()).toBeVisible();
  await page.evaluate(async () => {
    wp.data.dispatch('core/editor').editPost({meta:{argon_show_post_outdated_info:'never'}});
    await wp.data.dispatch('core/editor').savePost();
  });
  await expect.poll(() => page.evaluate(() => wp.data.select('core/editor').getCurrentPost().meta.argon_show_post_outdated_info)).toBe('never');
  await page.screenshot({path:'temp/browser-editor.png',fullPage:true});
});
test('ordinary navigation and article content work without JavaScript', async ({browser}) => {
  const page = await browser.newPage({javaScriptEnabled:false});
  await page.goto(`http://127.0.0.1:9400/?p=${fixture().normal}`, {waitUntil:'domcontentloaded'});
  await expect(page.locator('#post_content')).toContainText('Native blocks remain editable');
  expect(await page.locator('a').filter({hasText:'HTML Document'}).first().getAttribute('href')).toBeTruthy();
  await page.close();
});
