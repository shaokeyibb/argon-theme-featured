import {test,expect} from '@playwright/test';
test('official image plugins optimize Markdown, linked images and native blocks',async({page})=>{
  const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto('/media-compatibility/',{waitUntil:'networkidle'});
  const image=page.locator('#markdown-media');
  await image.scrollIntoViewIfNeeded();
  await expect.poll(()=>image.evaluate(el=>el.currentSrc)).toMatch(/\.webp$/);
  await expect(image).toHaveAttribute('width','1200');await expect(image).toHaveAttribute('height','675');
  await expect(image).toHaveAttribute('data-dominant-color',/^[0-9a-f]{6}$/i);
  await expect(image).toHaveAttribute('srcset',/300w/);
  await expect(image.locator('..')).toHaveJSProperty('tagName','PICTURE');
  await expect(page.locator('#post_content picture picture')).toHaveCount(0);
  await expect(page.locator('#post_content .wp-lightbox-container')).toHaveCount(1);
  await expect(page.locator('#post_content source')).not.toHaveCount(0);
  expect(await page.locator('#post_content source').evaluateAll(els=>els.every(el=>!!el.srcset))).toBe(true);
  const inset=await image.evaluate(el=>({image:el.getBoundingClientRect().width,parent:el.closest('p').getBoundingClientRect().width}));
  expect(inset.image).toBeLessThan(inset.parent*.85);
  await page.setViewportSize({width:390,height:844});await page.reload({waitUntil:'networkidle'});await image.scrollIntoViewIfNeeded();
  await expect.poll(()=>image.evaluate(el=>el.complete&&el.naturalWidth>0)).toBe(true);
  expect(await page.evaluate(()=>document.body.scrollWidth)).toBe(390);
  const trigger=page.locator('.wp-lightbox-container .lightbox-trigger');
  await trigger.scrollIntoViewIfNeeded();
  const triggerBox=await trigger.boundingBox();expect(triggerBox.x).toBeGreaterThanOrEqual(0);expect(triggerBox.x+triggerBox.width).toBeLessThanOrEqual(390);
  await trigger.click();await expect(page.locator('.wp-lightbox-overlay.active')).toBeVisible();
  await page.keyboard.press('Escape');await expect(page.locator('.wp-lightbox-overlay.active')).toHaveCount(0);
  await expect(page.locator('#linked-media').locator('..').locator('..')).toHaveJSProperty('tagName','A');
  const legacy=page.locator('#legacy-media');await legacy.scrollIntoViewIfNeeded();
  await expect.poll(()=>legacy.evaluate(el=>el.complete&&el.naturalWidth>0)).toBe(true);
  await page.waitForTimeout(500);await expect(legacy).toHaveCSS('opacity','1');
  await expect(legacy).toHaveAttribute('loading','eager');await expect(legacy).toHaveAttribute('fetchpriority','high');
  await page.locator('#linked-media').click();await expect(page.locator('.pswp')).toBeVisible();
  await page.keyboard.press('Escape');
  expect(errors).toEqual([]);
});
test('banner picture retains dimensions, cropping and sources with JavaScript disabled',async({browser})=>{
  const context=await browser.newContext({javaScriptEnabled:false,viewport:{width:1440,height:1000}}),page=await context.newPage();
  await page.goto('/featured-media/',{waitUntil:'networkidle'});
  const image=page.locator('#banner img.argon-banner-image');
  await expect(image).toHaveAttribute('loading','eager');await expect(image).toHaveAttribute('sizes','100vw');
  await expect.poll(()=>image.evaluate(el=>el.currentSrc)).toMatch(/\.webp$/);
  const [banner,rect]=await Promise.all([page.locator('#banner').boundingBox(),image.boundingBox()]);
  expect(rect.width).toBeCloseTo(banner.width,0);expect(rect.height).toBeCloseTo(banner.height,0);
  await expect(image).toHaveCSS('object-fit','cover');await expect(image).toHaveCSS('opacity','1');
  await context.close();
});
test('all archive pictures stay visible after loading and on cached reloads',async({page})=>{
  await page.goto('/',{waitUntil:'networkidle'});
  const images=page.locator('.post-header img.post-thumbnail');
  await expect(images).toHaveCount(3);
  for(const image of await images.all()) {
    await image.scrollIntoViewIfNeeded();
    await expect.poll(()=>image.evaluate(el=>el.complete&&el.naturalWidth>0)).toBe(true);
    await expect(image).toHaveCSS('opacity','1');
    expect(await image.evaluate(el=>el.currentSrc)).toMatch(/\.webp$/);
  }
  await page.waitForTimeout(800);
  expect(await images.evaluateAll(els=>els.every(el=>getComputedStyle(el).opacity==='1'))).toBe(true);
  await page.reload({waitUntil:'networkidle'});
  for(const image of await images.all()) { await image.scrollIntoViewIfNeeded(); await expect(image).toHaveCSS('opacity','1'); }
  await page.setViewportSize({width:390,height:844});
  for(const image of await images.all()) { await image.scrollIntoViewIfNeeded(); await expect(image).toBeVisible(); await expect(image).toHaveCSS('opacity','1'); }
});
test('speculation and Optimization Detective remain on the native page lifecycle',async({page})=>{
  const scripts=[];page.on('response',r=>{if(/optimization-detective|image-prioritizer/.test(r.url()))scripts.push({url:r.url(),status:r.status()});});
  await page.goto('/media-compatibility/',{waitUntil:'networkidle'});
  await expect(page.locator('script[type="speculationrules"]')).toHaveCount(1);
  const rules=JSON.parse(await page.locator('script[type="speculationrules"]').textContent());
  expect(rules.prerender?.length||rules.prefetch?.length).toBeGreaterThan(0);
  await expect.poll(()=>scripts.some(r=>r.url.includes('optimization-detective')&&r.status===200)).toBe(true);
  expect(scripts.filter(r=>r.status>=400)).toEqual([]);
  await page.locator('#markdown-media').scrollIntoViewIfNeeded();
  expect(await page.locator('img[data-original]').count()).toBe(0);
  expect(await page.locator('img[loading="lazy"][fetchpriority="high"]').count()).toBe(0);
});
