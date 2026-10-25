import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
const fixture = () => JSON.parse(readFileSync('temp/browser-fixture.json', 'utf8'));

test('catalog follows the current section and folds unrelated nested branches', async ({page}) => {
  await page.emulateMedia({reducedMotion:'reduce'});
  await page.goto(`/?page_id=${fixture().catalog}`,{waitUntil:'domcontentloaded'});
  const catalog=page.locator('#leftbar_catalog');
  await page.locator('#leftbar_tab_catalog_btn').click();
  const first=catalog.locator('a[href="#year-2026"]'), second=catalog.locator('a[href="#year-2025"]');
  const firstBranch=first.locator('..').locator(':scope > ul'), secondBranch=second.locator('..').locator(':scope > ul');
  await expect(firstBranch).toBeVisible();
  await expect(secondBranch).toBeHidden();
  await expect(catalog.locator('a[href="#week-one"]')).toBeHidden();
  const scrollToHeading=async id=>page.evaluate(id=>window.scrollTo({top:document.getElementById(id).getBoundingClientRect().top+scrollY-90,behavior:'instant'}),id);
  await scrollToHeading('week-one');
  await expect(catalog.locator('a[href="#week-one"]')).toHaveAttribute('aria-current','location');
  await expect(catalog.locator('a[href="#month-august"]')).toHaveAttribute('aria-expanded','true');
  await scrollToHeading('month-december');
  await expect(catalog.locator('a[href="#month-december"]')).toHaveAttribute('aria-current','location');
  await expect(firstBranch).toBeHidden();
  await expect(secondBranch).toBeVisible();
  await scrollToHeading('month-may');
  await expect(catalog.locator('a[href="#month-may"]')).toHaveAttribute('aria-current','location');
  await expect(secondBranch).toBeHidden();
  await second.click();
  await expect(secondBranch).toBeVisible();
  await expect(firstBranch).toBeHidden();
  await catalog.locator('a[href="#month-october"]').click();
  await expect(catalog.locator('.current > a')).toHaveAttribute('href','#month-october');
  await page.reload({waitUntil:'domcontentloaded'});
  await expect(catalog.locator('a[href="#month-october"]')).toHaveAttribute('aria-current','location');
});

test('Argon layout keeps its banner geometry, toolbar position and circular pagination', async ({page}) => {
  await page.setViewportSize({width:1440,height:1000});
  await page.goto('/', {waitUntil:'domcontentloaded'});
  const nav = page.locator('#navbar-main');
  await expect(nav).toHaveCSS('position', 'fixed');
  await expect(nav.locator('.navbar-brand').first()).toHaveCSS('font-size', '14px');
  await expect(page.locator('.banner-container')).toHaveCSS('position', 'relative');
  const banner = await page.locator('.banner').boundingBox();
  expect(banner.y).toBe(0); expect(banner.height).toBeCloseTo(718,0);
  const firstCard = await page.locator('#main article').first().boundingBox();
  expect(firstCard.y).toBeCloseTo(443,0);
  const pageLink = page.locator('.pagination:not(.pagination-mobile) .page-item').nth(1).locator('.page-link');
  await expect(pageLink).toHaveCSS('width','36px');
  await expect(pageLink).toHaveCSS('height','36px');
  await expect(pageLink).toHaveCSS('border-radius','50%');
  await expect(pageLink).toHaveCSS('margin-left','3px');
  // WordPress widgets use ordinary labels, whose missing margin compressed rows.
  await page.locator('#leftbar_tab_overview').evaluate(el => el.insertAdjacentHTML('beforeend','<div id="statistics-layout-fixture"><h6>Statistics</h6><ul><li><label>Today</label>12</li><li><label>Total</label>34</li></ul></div>'));
  await expect(page.locator('#statistics-layout-fixture label').first()).toHaveCSS('margin-bottom','8px');
  await expect(page.locator('#statistics-layout-fixture h6')).toHaveCSS('line-height','24px');
  await page.evaluate(() => {document.body.style.minHeight='2500px';window.scrollTo({top:700,behavior:'instant'});});
  await expect.poll(async () => (await nav.boundingBox()).y).toBe(0);
  await expect(nav).toHaveCSS('backdrop-filter','blur(16px)');
  await page.setViewportSize({width:390,height:844});
  await page.evaluate(() => window.scrollTo({top:0,behavior:'instant'}));
  await expect(nav).toHaveCSS('position','fixed');
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
  await expect(page.locator('.pagination-mobile .page-link').first()).toHaveCSS('width','36px');
});

test('page two folds after delayed images load and respects explicit expansion', async ({page}) => {
  let releaseImage;
  const imageReady = new Promise(resolve => {releaseImage=resolve;});
  await page.route('**/folding-delayed.svg', async route => {
    await imageReady;
    await route.fulfill({contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="800" height="1200"><rect width="800" height="1200" fill="purple"/></svg>'});
  });
  await page.goto('/', {waitUntil:'domcontentloaded'});
  await page.locator('.pagination:not(.pagination-mobile) a.page-link').filter({hasText:/^2$/}).click();
  const content = page.locator(`#post-${fixture().folding} .shuoshuo-content`);
  await expect(content).toBeVisible();
  await expect(content).not.toHaveClass(/shuoshuo-folded/);
  releaseImage();
  await expect(content).toHaveClass(/shuoshuo-folded/);
  await expect(content).toHaveCSS('max-height','400px');
  await content.locator('.show-full-shuoshuo').click();
  await expect(content).toHaveClass(/shuoshuo-unfolded/);
  await page.setViewportSize({width:390,height:844});
  await expect(content).not.toHaveClass(/shuoshuo-folded/);
  await expect(content.locator('.show-full-shuoshuo')).toHaveCount(0);
  await page.reload({waitUntil:'domcontentloaded'});
  await expect(content).toHaveClass(/shuoshuo-folded/);
  await expect(content.locator('.show-full-shuoshuo')).toHaveCount(1);
});

for (const loggedIn of [false,true]) test(`cached article nonce is refreshed for ${loggedIn?'a signed-in reader':'a guest'}`, async ({page}) => {
  if(loggedIn){
    await page.goto('/wp-login.php');await expect(page.locator('#user_login')).toBeFocused();await page.locator('#user_login').fill('admin');await page.locator('#user_pass').fill('argon-browser-local-test');
    await Promise.all([page.waitForURL('**/wp-admin/**'),page.locator('#wp-submit').click()]);
  }
  // Model an old cached document while keeping real WordPress AJAX validation.
  await page.route(`**/?p=${fixture().normal}`, async route => {
    const response=await route.fetch();
    let html=await response.text();
    html=html.replace(/"ajax_nonce":"[^"]*"/, '"ajax_nonce":"expired-cached-document"');
    html=html.replace(/"captcha_ajax":false/, '"captcha_ajax":true');
    html=html.replaceAll('no-need-captcha','captcha-required-fixture');
    return route.fulfill({response,body:html});
  });
  const statuses=[];
  page.on('response',response=>{if(response.url().includes('admin-ajax.php') && ['argon_refresh_public_nonce','get_captcha'].includes(new URLSearchParams(response.request().postData()||'').get('action')))statuses.push(response.status());});
  // Enable the challenge route in the disposable fixture only.
  await page.goto(`/?p=${fixture().normal}`,{waitUntil:'domcontentloaded'});
  await expect.poll(()=>statuses.length, {timeout:30000}).toBeGreaterThanOrEqual(2);
  expect(statuses).not.toContain(403);
  await expect(page.locator('#argon-notifications .is-error')).toHaveCount(0);
  await expect.poll(()=>page.evaluate(()=>argonConfig.ajax_nonce)).not.toBe('expired-cached-document');
});

test('floating buttons follow page type and scroll position', async ({page}) => {
  await page.goto('/', {waitUntil:'domcontentloaded'});
  const top = page.locator('#fabtn_back_to_top');
  const comments = page.locator('#fabtn_go_to_comment');
  const progress = page.locator('#fabtn_reading_progress');
  await expect(top).toHaveClass(/fabtn-hidden/);
  await expect(comments).toBeHidden();
  await expect(progress).toHaveClass(/fabtn-hidden/);
  await page.evaluate(() => { document.body.style.minHeight='2500px'; window.scrollTo({top:700,behavior:'instant'}); });
  await expect(top).not.toHaveClass(/fabtn-hidden/);
  await expect(progress).toHaveClass(/fabtn-hidden/);
  await top.click();
  await expect.poll(() => page.evaluate(() => scrollY)).toBe(0);
  await page.goto(`/?p=${fixture().normal}`, {waitUntil:'domcontentloaded'});
  await expect(comments).toBeVisible();
  await expect(top).toHaveClass(/fabtn-hidden/);
  await comments.click();
  await expect(page.locator('#post_comment_content')).toBeFocused();
  await expect(top).not.toHaveClass(/fabtn-hidden/);
});

test('header and sidebar search open independently and do not cover category links', async ({page}) => {
  await page.setViewportSize({width:1440,height:1000});
  await page.goto('/',{waitUntil:'domcontentloaded'});
  await page.locator('#navbar_search_input_container').click();
  await expect(page.locator('#navbar_search_input')).toBeFocused();
  await expect(page.locator('.leftbar-search-button')).not.toHaveClass(/open/);
  await expect(page.locator('#leftbar_search_container')).not.toHaveClass(/open/);
  await page.locator('#navbar_search_input').press('Tab');
  const search=page.locator('#leftbar_search_container'),input=page.locator('#leftbar_search_input');
  // Measure both boxes in one frame while the sidebar entrance animates.
  await expect.poll(() => search.evaluate(el => {
    const button = el.getBoundingClientRect(), input = el.querySelector('input').getBoundingClientRect();
    return input.width <= button.width + 2 && input.height <= button.height + 2;
  })).toBe(true);
  const category=page.locator('#leftbar_part1_menu a').first();
  const target=await category.getAttribute('href');
  await category.click();
  await expect(page).toHaveURL(target);
  await expect(page.locator('.leftbar-search-button')).not.toHaveClass(/open/);
  await search.click();
  await expect(input).toBeFocused();
  await expect(page.locator('#navbar_search_input_container')).not.toHaveClass(/open/);
  await expect(page.locator('.leftbar-search-button')).toHaveClass(/open/);
  await input.fill('Pagination');
  await input.press('Enter');
  await expect(page).toHaveURL(/\?s=Pagination/);
});
