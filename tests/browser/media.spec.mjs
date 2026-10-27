import {test,expect} from '@playwright/test';
import {readFileSync} from 'node:fs';

test('image layout groups only consecutive media and preserves picture sources, authorship and links',async({page,browser})=>{
  const fixture=JSON.parse(readFileSync('temp/media-browser-fixture.json','utf8'));
  const errors=[];page.on('pageerror',error=>errors.push(error.message));
  await page.setViewportSize({width:1440,height:1000});
  await page.route('**/layout-delayed.svg',route=>route.fulfill({contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="600" height="1800"/>'}));
  const original=await (await page.request.get(fixture.layout_page)).text();
  const baseline=await browser.newContext({javaScriptEnabled:false,viewport:{width:1440,height:1000}});
  const staticPage=await baseline.newPage();await staticPage.goto(fixture.layout_page,{waitUntil:'domcontentloaded'});
  const authoredHeight=staticPage.locator('#layout-height');await authoredHeight.scrollIntoViewIfNeeded();
  await expect.poll(()=>authoredHeight.evaluate(img=>img.complete&&img.naturalWidth>0)).toBe(true);
  const originalHeight=(await authoredHeight.boundingBox()).height;await baseline.close();
  await page.goto(fixture.layout_page,{waitUntil:'networkidle'});
  const grid=page.locator('.argon-image-grid');await expect(grid).toHaveCount(1);
  // A preview reached through a different hostname can render CSS while module
  // scripts fail CORS. Confirm the actual document uses its configured origin.
  expect(await page.locator('script[type="module"][src]').evaluateAll(scripts=>scripts.every(script=>new URL(script.src).origin===location.origin))).toBe(true);
  await expect(grid.locator('img')).toHaveCount(4);
  expect(await grid.locator('img').evaluateAll(images=>images.map(image=>image.id))).toEqual(['layout-grid-1','layout-grid-2','layout-grid-3','layout-grid-4']);
  const preserved=await page.evaluate(original=>{
    const before=new DOMParser().parseFromString(original,'text/html');
    return [...before.querySelectorAll('#post_content img')].every(image=>{
      const after=document.getElementById(image.id);
      return ['src','srcset','sizes','alt','width','height','loading'].every(name=>after.getAttribute(name)===image.getAttribute(name))
        && (image.closest('picture')?.querySelector('source')?.outerHTML||'')===(after.closest('picture')?.querySelector('source')?.outerHTML||'');
    });
  },original);
  expect(preserved).toBe(true);
  for(const id of ['layout-captioned','layout-sized','layout-height','layout-inline','layout-block']) {
    expect(await page.locator('#'+id).evaluate(image=>!!image.closest('.argon-image-layout'))).toBe(false);
  }
  await expect(page.locator('.layout-shortcode > .argon-image-layout #layout-shortcode-image')).toHaveCount(1);
  // Preserve existing authored layout, including core's own global image CSS.
  expect((await page.locator('#layout-height').boundingBox()).height).toBeCloseTo(originalHeight,0);
  await expect(page.locator('#layout-text')).toHaveText('Text between image groups must stay here.');
  await expect(page.locator('#layout-external-link')).toHaveAttribute('href','https://example.test/article');
  await expect(page.locator('#layout-external-link')).not.toHaveClass(/argon-image-view/);
  const gridRects=()=>grid.locator('.argon-image-cell').evaluateAll(cells=>cells.map(cell=>{const r=cell.getBoundingClientRect();return {x:r.x,y:r.y,width:r.width,height:r.height};}));
  let rects=await gridRects();expect(rects[1].y).toBeCloseTo(rects[0].y,0);expect(rects[1].x).toBeGreaterThan(rects[0].x+rects[0].width);
  expect(rects[2].y).toBeGreaterThan(rects[0].y);
  expect(rects[2].y).toBeLessThan(rects[1].y+rects[1].height);
  // Breakpoint follows the content container, even on a wide desktop viewport.
  await grid.locator('..').evaluate(el=>el.style.width='480px');
  await expect.poll(async()=>{const rows=await gridRects();return rows[1].y>rows[0].y;}).toBe(true);
  rects=await gridRects();expect(rects[1].x).toBeCloseTo(rects[0].x,0);
  await grid.locator('..').evaluate(el=>el.style.removeProperty('width'));
  for(const frame of await page.locator('.argon-image-frame').all()) {
    const cell=await frame.locator('..').boundingBox(),rect=await frame.boundingBox();
    expect(rect.x+rect.width/2).toBeCloseTo(cell.x+cell.width/2,0);
  }
  await page.setViewportSize({width:390,height:844});
  await expect.poll(async()=>(await gridRects())[1].x).toBeCloseTo((await gridRects())[0].x,0);
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=document.documentElement.clientWidth)).toBe(true);
  expect(errors).toEqual([]);
});

test('image layout long previews expand, collapse and open the full image with keyboard controls',async({page})=>{
  const fixture=JSON.parse(readFileSync('temp/media-browser-fixture.json','utf8'));
  await page.emulateMedia({reducedMotion:'reduce'});
  await page.setViewportSize({width:1440,height:1000});
  await page.route('**/image-service?id=1',route=>route.fulfill({contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="600" height="1800"><rect width="600" height="1800" fill="teal"/></svg>'}));
  await page.route(fixture.layout_page,async route=>{
    const response=await route.fetch();
    await route.fulfill({response,body:(await response.text()).replace('<h2>Single long image</h2>','<h2>Image service</h2><p><img id="layout-service" src="/image-service?id=1" alt="Extensionless image service"></p><h2>Single long image</h2>')});
  });
  await page.goto(fixture.layout_page,{waitUntil:'networkidle'});
  for(const id of ['layout-single','layout-grid-2','layout-service']) {
    const image=page.locator('#'+id),cell=image.locator('xpath=ancestor::span[contains(@class,"argon-image-cell")]');
    const frame=cell.locator('.argon-image-frame'),toggle=cell.locator('.argon-image-toggle');
    await expect(toggle).toBeVisible();await expect(toggle).toHaveAttribute('aria-expanded','false');
    expect((await frame.boundingBox()).height).toBeLessThanOrEqual(601);
    await toggle.focus();await page.keyboard.press('Enter');await expect(toggle).toHaveAttribute('aria-expanded','true');
    expect((await frame.boundingBox()).height).toBeCloseTo((await image.boundingBox()).height,0);
    expect((await frame.boundingBox()).height).toBeGreaterThan(560);
    await page.setViewportSize({width:390,height:844});await expect(toggle).toHaveAttribute('aria-expanded','true');
    await toggle.focus();await page.keyboard.press('Space');await expect(toggle).toHaveAttribute('aria-expanded','false');
    // aria-expanded updates in the click handler; image geometry follows in rAF.
    await expect.poll(async()=>(await frame.boundingBox()).height).toBeLessThanOrEqual(507);
    const link=cell.locator('a.argon-image-view');await link.focus();await page.keyboard.press('Enter');
    await expect(page.locator('.pswp')).toBeVisible();
    const full=page.locator('.pswp__item[aria-hidden="false"] img.pswp__img:not(.pswp__img--placeholder)');
    await expect.poll(()=>full.evaluate(img=>img.naturalHeight/img.naturalWidth)).toBe(3);
    await page.keyboard.press('Escape');await expect(page.locator('.pswp')).toHaveCount(0);
    await page.setViewportSize({width:1440,height:1000});
  }
  await page.locator('#layout-single').locator('xpath=ancestor::span[contains(@class,"argon-image-cell")]').locator('.argon-image-toggle').click();
  await page.emulateMedia({media:'print'});
  for(const frame of await page.locator('.is-long .argon-image-frame').all()) await expect(frame).toHaveCSS('max-height','none');
  await expect(page.locator('.argon-image-toggle:visible')).toHaveCount(0);
});

test('image layout waits for lazy media and also enhances shuoshuo without requiring the lightbox',async({page})=>{
  const fixture=JSON.parse(readFileSync('temp/media-browser-fixture.json','utf8'));
  let release;
  const ready=new Promise(resolve=>release=resolve);
  await page.route('**/layout-delayed.svg',async route=>{await ready;await route.fulfill({contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="600" height="1800"><rect width="600" height="1800" fill="teal"/></svg>'});});
  await page.route(/\/theme-[\w-]+\.js(?:\?|$)/,async route=>{
    // Disable the viewer before the theme module evaluates, as the site option does.
    const response=await route.fetch();await route.fulfill({response,body:'window.argonConfig.lightbox=false;\n'+await response.text()});
  });
  await page.goto(fixture.layout_page,{waitUntil:'domcontentloaded'});
  const delayed=page.locator('#layout-delayed');await delayed.scrollIntoViewIfNeeded();
  const cell=delayed.locator('xpath=ancestor::span[contains(@class,"argon-image-cell")]');
  await expect(cell.locator('.argon-image-toggle')).toBeHidden();
  release();await expect(cell.locator('.argon-image-toggle')).toBeVisible();
  await expect(cell.locator('a')).toHaveCount(0);
  await cell.locator('.argon-image-toggle').click();await expect(cell).toHaveClass(/is-expanded/);
  for(const url of [fixture.portrait_shuoshuo,'/']) {
    await page.goto(url,{waitUntil:'networkidle'});
    const group=page.locator('#portrait-plain').locator('xpath=ancestor::div[contains(@class,"argon-image-grid")]');
    await expect(group.locator('img')).toHaveCount(3);
    await expect(group.locator('.argon-image-toggle:visible')).toHaveCount(3);
    await expect(group.locator('a.argon-image-view')).toHaveCount(0);
  }
});

test('portrait images fit desktop reading space through picture wrappers in articles and shuoshuo',async({browser})=>{
  const fixture=JSON.parse(readFileSync('temp/media-browser-fixture.json','utf8'));
  // The size limit must work before enhancement and when JavaScript is disabled.
  const context=await browser.newContext({javaScriptEnabled:false,viewport:{width:1440,height:1000}});
  const page=await context.newPage();
  for(const url of [fixture.portrait_page,fixture.portrait_shuoshuo,'/']) {
    await page.goto(url,{waitUntil:'domcontentloaded'});
    for(const id of ['portrait-plain','portrait-linked','portrait-authored-picture']) {
      const img=page.locator('#'+id);await img.scrollIntoViewIfNeeded();
      await expect.poll(()=>img.evaluate(el=>el.complete&&el.naturalWidth>0)).toBe(true);
      expect(await img.evaluate(el=>el.parentElement.tagName)).toBe('PICTURE');
      const size=await img.boundingBox();
      expect(size.height).toBeCloseTo(750,0);
      expect(size.width).toBeCloseTo(250,0);
      const paragraph=await img.locator('xpath=ancestor::p').boundingBox();
      expect(size.x+size.width/2).toBeCloseTo(paragraph.x+paragraph.width/2,0);
    }
    const sized=await page.locator('#portrait-sized').boundingBox();
    expect(sized.width).toBeCloseTo(100,0);expect(sized.height).toBeCloseTo(300,0);
    const inline=await page.locator('#portrait-inline-sized').boundingBox();
    expect(inline.width).toBeCloseTo(300,0);expect(inline.height).toBeCloseTo(900,0);
    await expect(page.locator('#portrait-block')).toHaveCSS('max-height','none');
    await page.setViewportSize({width:1024,height:600});
    expect((await page.locator('#portrait-plain').boundingBox()).height).toBeCloseTo(450,0);
    await page.setViewportSize({width:390,height:844});
    await expect(page.locator('#portrait-plain')).toHaveCSS('max-height','none');
    const mobile=await page.locator('#portrait-plain').boundingBox();
    expect(mobile.height/mobile.width).toBeCloseTo(3,1);
    expect(await page.evaluate(()=>document.documentElement.scrollWidth<=document.documentElement.clientWidth)).toBe(true);
    await page.setViewportSize({width:1440,height:1000});
  }
  await context.close();
});

test('bounded portrait media still opens uncropped and navigates in the lightbox',async({page})=>{
  const fixture=JSON.parse(readFileSync('temp/media-browser-fixture.json','utf8'));
  await page.setViewportSize({width:1440,height:1000});
  await page.goto(fixture.portrait_shuoshuo,{waitUntil:'networkidle'});
  await page.locator('#portrait-linked').click();
  const viewer=page.locator('.pswp');await expect(viewer).toBeVisible();
  await expect(viewer.locator('.pswp__counter')).toHaveText('2 / 5');
  const image=viewer.locator('.pswp__item[aria-hidden="false"] img.pswp__img:not(.pswp__img--placeholder)');
  await expect.poll(()=>image.evaluate(el=>el.naturalHeight/el.naturalWidth)).toBe(3);
  await page.keyboard.press('ArrowRight');await expect(viewer.locator('.pswp__counter')).toHaveText('3 / 5');
  await page.keyboard.press('Escape');await expect(viewer).toHaveCount(0);
});
test('Githuber Markdown inside nested shortcodes renders, expands and retains responsive media',async({page})=>{
  const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto('/markdown-shortcodes/',{waitUntil:'domcontentloaded'});
  const body=page.locator('.collapse-block-body');
  await expect(body).toBeHidden();
  await page.locator('.collapse-block-title').click();
  await expect(body).toBeVisible();
  await expect(body.locator('h2')).toHaveText('Markdown heading');
  await expect(body.locator('strong')).toHaveText('Bold text');
  await expect(body.locator('li')).toHaveCount(2);
  await expect(body.locator('.admonition-body em')).toHaveText('Nested emphasis');
  await expect(body.locator('pre code')).toContainText('[label]code sample[/label]');
  await expect(body.locator('.badge')).toHaveCount(0);
  const image=body.locator('img');await image.scrollIntoViewIfNeeded();
  await expect.poll(()=>image.evaluate(el=>el.currentSrc)).toMatch(/\.webp$/);
  await expect(image).toHaveAttribute('srcset',/300w/);
  await expect(body.locator('.argon-image-layout img')).toHaveCount(1);
  await page.setViewportSize({width:390,height:844});
  expect(await page.evaluate(()=>document.documentElement.scrollWidth)).toBe(390);
  await page.locator('.collapse-block-title').click();await expect(body).toBeHidden();
  await page.locator('.collapse-block-title').press('Enter');await expect(body).toBeVisible();
  expect(errors).toEqual([]);
});

test('image layout keeps nested content containers separate and reflows after hidden ancestors open',async({page})=>{
  const fixture=JSON.parse(readFileSync('temp/media-browser-fixture.json','utf8'));
  const errors=[];page.on('pageerror',error=>errors.push(error.message));
  await page.route('**/nested-*.svg',route=>{
    const tall=route.request().url().includes('tall');
    return route.fulfill({contentType:'image/svg+xml',body:`<svg xmlns="http://www.w3.org/2000/svg" width="${tall?600:1200}" height="${tall?1800:675}"><rect width="100%" height="100%" fill="teal"/></svg>`});
  });
  const img=(id,kind='wide',attrs='')=>`<picture><source srcset="/nested-${kind}.svg"><img id="${id}" src="/nested-${kind}.svg" alt="${id}" ${attrs}></picture>`;
  const pair=(id)=>`<p>${img(id+'-tall','tall','loading="lazy"')}</p><p>${img(id+'-wide')}</p>`;
  const fold=(id,body)=>`<div id="${id}" class="collapse-block collapsed"><div class="collapse-block-title">${id}</div><div class="collapse-block-body" style="display:none">${body}</div></div>`;
  const html=`<p>${img('nested-outside-before')}</p>${fold('nested-outer',`
    <p>${img('nested-first')}</p><figure>${img('nested-captioned','tall')}<figcaption>${'Long caption stays with this image. '.repeat(25)}</figcaption></figure>
    <p id="nested-boundary">Text keeps these image runs separate.</p>
    <div class="admonition"><div class="admonition-title">Nested admonition</div><div class="admonition-body">${pair('nested-admonition')}</div></div>
    ${fold('nested-inner',pair('nested-inner'))}
    <section id="nested-custom" style="max-width:450px">${pair('nested-custom')}</section>
    <details id="nested-details"><summary>Native disclosure</summary>${pair('nested-details')}</details>
    <p>${img('nested-last')}</p>
  `)}<p>${img('nested-outside-after')}</p>
    <div class="no-fancybox"><p>${img('nested-opt-out')}</p></div>
    <table><tbody><tr><td><p>${img('nested-table')}</p></td></tr></tbody></table>
    <figure class="wp-block-image">${img('nested-native')}</figure>
    <figure style="width:150px"><p>${img('nested-authored-figure')}</p><figcaption>Authored structure</figcaption></figure>
    <div><p>${img('nested-authored-size','tall','width="100" height="300"')}</p></div>`;
  await page.route(/\/theme-[\w-]+\.js(?:\?|$)/,async route=>{
    const response=await route.fetch();
    const setup=`document.querySelector('#post_content').innerHTML=${JSON.stringify(html)};window.nestedMediaOriginals=[...document.querySelectorAll('#post_content img')].map(el=>({el,parent:el.parentElement,src:el.getAttribute('src'),source:el.parentElement.querySelector('source')?.outerHTML}));`;
    await route.fulfill({response,body:setup+'\n'+await response.text()});
  });
  await page.emulateMedia({reducedMotion:'reduce'});await page.setViewportSize({width:1440,height:1000});
  await page.goto(fixture.layout_page,{waitUntil:'networkidle'});
  const outer=page.locator('#nested-outer > .collapse-block-body'),inner=page.locator('#nested-inner > .collapse-block-body');
  await expect(outer).toBeHidden();await expect(page.locator('.argon-image-layout')).toHaveCount(8);
  for(const id of ['nested-opt-out','nested-table','nested-native','nested-authored-figure','nested-authored-size']) {
    expect(await page.locator('#'+id).evaluate(el=>!!el.closest('.argon-image-layout'))).toBe(false);
  }
  await page.locator('#nested-outer > .collapse-block-title').click();await expect(outer).toBeVisible();
  await expect(outer.locator(':scope > .argon-image-layout')).toHaveCount(2);
  await expect(outer.locator(':scope > .argon-image-layout').first().locator('img')).toHaveCount(2);
  await expect(outer.locator('.admonition-body > .argon-image-layout img')).toHaveCount(2);
  await expect(inner).toBeHidden();await page.locator('#nested-inner > .collapse-block-title').click();await expect(inner).toBeVisible();
  await page.locator('#nested-details > summary').click();
  for(const image of await page.locator('#post_content .argon-image-layout img').all()) {
    await image.scrollIntoViewIfNeeded();await expect.poll(()=>image.evaluate(el=>el.complete&&el.naturalWidth>0)).toBe(true);
  }
  const verify=async()=>{
    await settleMasonry(page);
    const groups=await page.locator('.argon-image-layout').evaluateAll(groups=>groups.map(el=>({outer:el.getBoundingClientRect().toJSON(),parent:el.parentElement.getBoundingClientRect().toJSON(),cells:[...el.querySelectorAll('.argon-image-cell')].map(cell=>cell.getBoundingClientRect().toJSON())})));
    for(const group of groups) {
      expect(group.outer.left).toBeGreaterThanOrEqual(group.parent.left-1);expect(group.outer.right).toBeLessThanOrEqual(group.parent.right+1);
      expectMasonry(group.cells,group.outer);
    }
    expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1)).toBe(true);
  };
  await verify();
  const cell=page.locator('#nested-custom-tall').locator('xpath=ancestor::span[contains(@class,"argon-image-cell")]');
  await cell.locator('.argon-image-toggle').click();await expect(cell.locator('button')).toHaveAttribute('aria-expanded','true');await verify();
  await page.locator('#nested-outer > .collapse-block-title').click();await expect(outer).toBeHidden();
  await page.setViewportSize({width:390,height:844});
  await page.locator('#nested-outer > .collapse-block-title').press('Enter');await expect(outer).toBeVisible();await verify();
  await expect(cell.locator('button')).toHaveAttribute('aria-expanded','true');
  await page.setViewportSize({width:1440,height:1000});await verify();
  await expect(page.locator('#nested-boundary')).toHaveText('Text keeps these image runs separate.');
  expect(await page.evaluate(()=>window.nestedMediaOriginals.every(({el,parent,src,source})=>el.isConnected&&el.parentElement===parent&&el.getAttribute('src')===src&&parent.querySelector('source')?.outerHTML===source))).toBe(true);
  expect(errors).toEqual([]);
});

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
  const inset=await image.evaluate(el=>({image:el.getBoundingClientRect().width,parent:(el.closest('.argon-image-grid')||el.closest('p')).getBoundingClientRect().width}));
  expect(inset.image).toBeLessThan(inset.parent*.85);
  await page.setViewportSize({width:390,height:844});await page.reload({waitUntil:'networkidle'});await image.scrollIntoViewIfNeeded();
  await expect.poll(()=>image.evaluate(el=>el.complete&&el.naturalWidth>0)).toBe(true);
  expect(await page.evaluate(()=>document.body.scrollWidth)).toBe(390);
  const trigger=page.locator('.wp-lightbox-container .lightbox-trigger');
  await trigger.scrollIntoViewIfNeeded();
  const triggerBox=await trigger.boundingBox();expect(triggerBox.x).toBeGreaterThanOrEqual(0);expect(triggerBox.x+triggerBox.width).toBeLessThanOrEqual(390);
  await trigger.click();await expect(page.locator('.wp-lightbox-overlay.active')).toBeVisible();
  // Core transfers keyboard focus after its opening animation. Sending Escape
  // while the trigger still owns focus never reaches the overlay's handler.
  await expect(page.locator('.wp-lightbox-overlay.active')).toBeFocused();
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

async function masonryFixture(page, cases) {
  const fixture=JSON.parse(readFileSync('temp/media-browser-fixture.json','utf8'));
  const sizes={wide:[1200,675],portrait:[600,1080],tall:[720,2520],skinny:[100,500],tiny:[80,80]};
  await page.route('**/masonry-*.svg',route=>{
    const kind=new URL(route.request().url()).pathname.match(/masonry-(\w+)\.svg/)[1];
    if(kind==='broken')return route.fulfill({status:404,body:'Unavailable'});
    const [width,height]=sizes[kind];
    return route.fulfill({contentType:'image/svg+xml',body:`<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}"><rect width="${width}" height="${height}" fill="teal"/></svg>`});
  });
  const html=Object.entries(cases).map(([name,kinds])=>`<h2 id="case-${name}">${name}</h2>`+kinds.map((kind,index)=>`<p><picture><source srcset="/masonry-${kind}.svg"><img id="${name}-${index}" src="/masonry-${kind}.svg" alt="${name} image ${index}"></picture></p>`).join('')).join('');
  await page.route(/\/theme-[\w-]+\.js(?:\?|$)/,async route=>{
    const response=await route.fetch();
    await route.fulfill({response,body:`document.querySelector('#post_content').innerHTML=${JSON.stringify(html)};\n`+await response.text()});
  });
  await page.emulateMedia({reducedMotion:'reduce'});await page.setViewportSize({width:1440,height:1000});
  await page.goto(fixture.layout_page,{waitUntil:'networkidle'});
  await expect.poll(()=>page.locator('#post_content img').evaluateAll(images=>images.every(img=>img.complete))).toBe(true);
}
async function masonryBounds(group) {
  return group.locator('.argon-image-cell').evaluateAll(cells=>cells.map(cell=>({id:cell.querySelector('img').id,...cell.getBoundingClientRect().toJSON()})));
}
function expectMasonry(bounds, outer) {
  for(let i=0;i<bounds.length;i++) {
    const rect=bounds[i];expect(rect.width).toBeGreaterThan(0);expect(rect.height).toBeGreaterThan(0);
    expect(rect.left).toBeGreaterThanOrEqual(outer.left-.5);expect(rect.right).toBeLessThanOrEqual(outer.right+.5);
    expect(rect.bottom).toBeLessThanOrEqual(outer.bottom+1);
    if(i)expect(rect.top).toBeGreaterThanOrEqual(bounds[i-1].top-.5);
    for(let j=0;j<i;j++) {
      const other=bounds[j];
      expect(rect.right<=other.left+.5||rect.left>=other.right-.5||rect.bottom<=other.top+.5||rect.top>=other.bottom-.5,`Images ${i} and ${j} overlap`).toBe(true);
    }
  }
}
async function settleMasonry(page) {
  await page.locator('#post_content').evaluate(el=>new Promise(resolve=>{
    let width=el.clientWidth,changed=performance.now();
    const tick=()=>{if(el.clientWidth!==width){width=el.clientWidth;changed=performance.now();}if(performance.now()-changed>150)resolve();else requestAnimationFrame(tick);};requestAnimationFrame(tick);
  }));
}

test('image layout masonry preserves counts, order and spacing from one through twenty-five images',async({page})=>{
  const counts=[1,2,3,4,5,6,7,8,9,12,25];
  const cases=Object.fromEntries(counts.map(count=>['count'+count,Array.from({length:count},(_,index)=>index%3===1?'portrait':'wide')]));
  const errors=[];page.on('pageerror',error=>errors.push(error.message));
  await masonryFixture(page,cases);
  for(const width of [1440,390,1440]) {
    await page.setViewportSize({width,height:1000});await settleMasonry(page);
    for(const count of counts) {
      const group=page.locator(`#case-count${count} + .argon-image-layout`);
      await expect(group.locator('img')).toHaveCount(count);
      const bounds=await masonryBounds(group),outer=await group.boundingBox();
      expect(bounds.map(rect=>rect.id)).toEqual(Array.from({length:count},(_,i)=>`count${count}-${i}`));
      expectMasonry(bounds,{left:outer.x,right:outer.x+outer.width,bottom:outer.y+outer.height});
      if(width===390)expect(bounds.every(rect=>Math.abs(rect.left-outer.x)<1)).toBe(true);
      if(count>1&&width===1440)expect(new Set(bounds.map(rect=>Math.round(rect.x))).size).toBe(2);
    }
    expect(await page.evaluate(()=>document.documentElement.scrollWidth<=document.documentElement.clientWidth)).toBe(true);
  }
  expect(errors).toEqual([]);
});

test('image layout masonry mixes long, portrait, small and failed images and reflows expanded rows',async({page})=>{
  const cases={first:['tall','wide','wide'],middle:['wide','wide','tall','wide','wide'],unbalanced:['portrait','wide','tall','wide'],last:['wide','wide','tall'],many:['tall','wide','tall','wide','tall'],skinny:['skinny','wide'],small:['tiny','wide','portrait'],broken:['wide','broken','wide']};
  const errors=[];page.on('pageerror',error=>errors.push(error.message));
  await masonryFixture(page,cases);await settleMasonry(page);
  const group=name=>page.locator(`#case-${name} + .argon-image-layout`);
  for(const [name,index] of [['first',0],['middle',2],['last',2]]) {
    const cells=group(name).locator('.argon-image-cell');
    await expect(cells.nth(index)).toHaveCSS('grid-column','1 / -1');
  }
  await expect(group('unbalanced').locator('.argon-image-cell').nth(2)).not.toHaveCSS('grid-column','1 / -1');
  // A narrow 500px image fits this viewport; aspect ratio alone must not fold it.
  await expect(group('skinny').locator('.argon-image-toggle').first()).toBeHidden();
  expect((await group('small').locator('img').first().boundingBox()).width).toBeLessThanOrEqual(80);
  await expect(group('broken').locator('.is-unavailable')).toHaveCount(1);
  await expect(group('broken').locator('.is-unavailable .argon-image-toggle')).toBeHidden();
  const verify=async()=>{
    for(const name of Object.keys(cases)) {
      const outer=await group(name).boundingBox();expectMasonry(await masonryBounds(group(name)),{left:outer.x,right:outer.x+outer.width,bottom:outer.y+outer.height});
    }
  };
  await verify();
  const longCells=group('many').locator('.is-long');await expect(longCells).toHaveCount(3);
  for(const cell of await longCells.all()) {await cell.locator('button').click();await expect(cell.locator('button')).toHaveAttribute('aria-expanded','true');}
  await settleMasonry(page);await verify();
  for(const cell of await longCells.all()) await expect(cell).toHaveCSS('grid-column','1 / -1');
  await page.setViewportSize({width:390,height:1000});await settleMasonry(page);await verify();
  for(const cell of await longCells.all()) await expect(cell.locator('button')).toHaveAttribute('aria-expanded','true');
  for(const cell of await longCells.all()) await cell.locator('button').click();
  await page.setViewportSize({width:1440,height:1000});await settleMasonry(page);await verify();
  await group('many').evaluate(el=>el.hidden=true);await group('many').evaluate(el=>el.hidden=false);await settleMasonry(page);await verify();
  await group('middle').locator('a').nth(2).focus();await page.keyboard.press('Enter');await expect(page.locator('.pswp')).toBeVisible();await page.keyboard.press('Escape');
  await page.emulateMedia({media:'print'});
  await expect(group('many').locator('.argon-image-grid')).toHaveCSS('display','block');
  for(const frame of await group('many').locator('.argon-image-frame').all()) await expect(frame).toHaveCSS('max-height','none');
  expect(errors).toEqual([]);
});

async function captionFixture(page, imageSizes={}) {
  const fixture=JSON.parse(readFileSync('temp/media-browser-fixture.json','utf8'));
  const sizes={photo:[768,1024],screen:[603,1304],wide:[1200,675],...imageSizes};
  await page.route('**/caption-*.svg',route=>{
    const kind=new URL(route.request().url()).pathname.match(/caption-(\w+)\.svg/)[1], [width,height]=sizes[kind];
    return route.fulfill({contentType:'image/svg+xml',body:`<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}"><rect width="100%" height="100%" fill="teal"/></svg>`});
  });
  const picture=(id,kind)=>`<picture><source srcset="/caption-${kind}.svg"><img id="${id}" src="/caption-${kind}.svg" alt="${id}"></picture>`;
  const longText='这段图注需要完整显示，中英文混排 with a longer description，不能截断或省略。'.repeat(12);
  const url='https://example.test/'+ 'unbroken-caption-address'.repeat(20);
  const html=`<h2 id="caption-group">Consecutive captioned and plain media</h2>
    <p><figure id="caption-figure-one">${picture('caption-photo','photo')}<figcaption>${longText}<em>最后一句也必须可见。</em></figcaption></figure></p>
    <p> </p><figure><a href="/caption-screen.svg">${picture('caption-screen','screen')}</a><figcaption>Screenshot with a ratio below 2.5.</figcaption></figure>
    <p>${picture('caption-plain','wide')}</p>
    <div class="wp-caption">${picture('caption-classic','wide')}<p class="wp-caption-text"><a href="${url}">${url}</a><br>最后一行。</p></div>
    <figure><figcaption>Caption first is valid HTML too.</figcaption>${picture('caption-first','wide')}</figure>
    <p id="caption-boundary">Prose still separates the next image.</p>
    <h2 id="caption-single-heading">A screen-sized image need not have an extreme ratio</h2>
    <figure>${picture('caption-single','screen')}<figcaption>Keep this caption below the preview and the expanded image.</figcaption></figure>
    <h2>Authored figure geometry</h2><figure style="width:160px">${picture('caption-authored','photo')}<figcaption>Keep the author's width.</figcaption></figure>`;
  await page.route(/\/theme-[\w-]+\.js(?:\?|$)/,async route=>{
    const response=await route.fetch();
    const setup=`document.querySelector('#post_content').innerHTML=${JSON.stringify(html)};window.captionOriginals=[...document.querySelectorAll('#post_content figcaption,#post_content .wp-caption-text')].map(el=>({el,html:el.innerHTML}));`;
    await route.fulfill({response,body:setup+'\n'+await response.text()});
  });
  await page.emulateMedia({reducedMotion:'reduce'});await page.setViewportSize({width:1440,height:1000});
  await page.goto(fixture.layout_page,{waitUntil:'networkidle'});
  await expect.poll(()=>page.locator('#post_content img').evaluateAll(images=>images.every(img=>img.complete&&img.naturalWidth>0))).toBe(true);
  await settleMasonry(page);
}

test('image layout measures full captions, centers figures and reflows when caption height changes',async({page})=>{
  const errors=[];page.on('pageerror',error=>errors.push(error.message));
  await captionFixture(page);
  const group=page.locator('#caption-photo').locator('xpath=ancestor::div[contains(@class,"argon-image-layout")]');
  await expect(group.locator('img')).toHaveCount(5);
  await expect(group.locator('.argon-image-caption')).toHaveCount(4);
  expect(await page.evaluate(()=>window.captionOriginals.every(({el,html})=>el.isConnected&&el.innerHTML===html))).toBe(true);
  expect(await page.locator('#caption-photo').evaluate(el=>el.closest('figure').id)).toBe('caption-figure-one');
  expect(await page.locator('#caption-authored').evaluate(el=>!!el.closest('.argon-image-layout'))).toBe(false);
  await expect(page.locator('#caption-authored').locator('xpath=ancestor::figure')).toHaveCSS('width','160px');
  const verify=async()=>{
    const outer=await group.boundingBox();expectMasonry(await masonryBounds(group),{left:outer.x,right:outer.x+outer.width,bottom:outer.y+outer.height});
    for(const caption of await group.locator('.argon-image-caption').all()) {
      const box=await caption.boundingBox(),cell=await caption.locator('..').boundingBox();
      expect(box.x).toBeGreaterThanOrEqual(cell.x-.5);expect(box.x+box.width).toBeLessThanOrEqual(cell.x+cell.width+.5);
      expect(box.y+box.height).toBeLessThanOrEqual(cell.y+cell.height+1);
      expect(await caption.evaluate(el=>el.scrollWidth<=el.clientWidth+1&&el.scrollHeight<=el.clientHeight+1)).toBe(true);
      await expect(caption).toHaveCSS('overflow','visible');await expect(caption).toHaveCSS('white-space','normal');
    }
    for(const frame of await group.locator('.argon-image-frame').all()) {
      const rect=await frame.boundingBox(),cell=await frame.locator('..').boundingBox();
      expect(rect.x+rect.width/2).toBeCloseTo(cell.x+cell.width/2,0);
    }
    expect(await page.evaluate(()=>document.documentElement.scrollWidth<=document.documentElement.clientWidth)).toBe(true);
  };
  await verify();
  const caption=group.locator('.argon-image-caption').first(),before=(await caption.boundingBox()).height;
  await caption.evaluate(el=>el.style.fontSize='26px');
  await expect.poll(async()=>(await caption.boundingBox()).height).toBeGreaterThan(before);
  await settleMasonry(page);await verify();
  for(const width of [390,1440]) {await page.setViewportSize({width,height:1000});await settleMasonry(page);await verify();}
  await expect(page.locator('#caption-boundary')).toHaveText('Prose still separates the next image.');
  expect(errors).toEqual([]);
});

test('image layout bounds tall displayed photos below the old ratio cutoff without clipping captions',async({page})=>{
  await captionFixture(page);
  // Mobile browser chrome can make CSS vh disagree with the measured viewport.
  // Even when the CSS fallback is taller, the preview must use the JS limit.
  await page.addStyleTag({content:'.argon-image-layout { --argon-image-preview-height: 100vh; }'});
  const cell=page.locator('#caption-single').locator('xpath=ancestor::figure');
  const frame=cell.locator('.argon-image-frame'),toggle=cell.locator('.argon-image-toggle'),caption=cell.locator('figcaption');
  for(const [width,height] of [[1440,1000],[1024,600],[390,844]]) {
    await page.setViewportSize({width,height});await settleMasonry(page);
    await expect(toggle).toBeVisible();await expect(toggle).toHaveAttribute('aria-expanded','false');
    expect((await frame.boundingBox()).height).toBeLessThanOrEqual(height*.6+1);
    const imageBox=await page.locator('#caption-single').boundingBox(),cellBox=await cell.boundingBox();
    expect(imageBox.x+imageBox.width/2).toBeCloseTo(cellBox.x+cellBox.width/2,0);
    await toggle.focus();await page.keyboard.press('Enter');await settleMasonry(page);
    await expect(toggle).toHaveAttribute('aria-expanded','true');
    expect((await frame.boundingBox()).height).toBeCloseTo((await page.locator('#caption-single').boundingBox()).height,0);
    const f=await frame.boundingBox(),c=await caption.boundingBox();expect(c.y).toBeGreaterThanOrEqual(f.y+f.height);
    await toggle.click();await settleMasonry(page);await expect(toggle).toHaveAttribute('aria-expanded','false');
  }
  await cell.locator('a.argon-image-view').focus();await page.keyboard.press('Enter');await expect(page.locator('.pswp')).toBeVisible();await page.keyboard.press('Escape');
  await page.emulateMedia({media:'print'});await expect(frame).toHaveCSS('max-height','none');await expect(toggle).toBeHidden();await expect(caption).toBeVisible();
});

test('image layout keeps document height stable while iOS browser toolbars resize the visual viewport',async({page})=>{
  // This portrait also crosses the old dynamic folding threshold, so fixing
  // only the preview height cannot accidentally make the regression pass.
  await captionFixture(page,{photo:[768,1152]});
  await page.setViewportSize({width:402,height:681});await settleMasonry(page);
  const geometry=()=>page.evaluate(()=>({
    height:document.documentElement.scrollHeight,
    frames:[...document.querySelectorAll('.argon-image-frame')].map(el=>el.getBoundingClientRect().height),
    long:[...document.querySelectorAll('.argon-image-cell')].map(el=>el.classList.contains('is-long')),
  }));
  await expect(page.locator('.argon-image-cell.is-long')).not.toHaveCount(0);
  const before=await geometry();
  const innerHeightDescriptor=await page.evaluateHandle(()=>Object.getOwnPropertyDescriptor(window,'innerHeight'));
  // Replay the captured iPhone Chrome dimensions: layout height stays 681px
  // while innerHeight changes as browser toolbars retract and reappear.
  for(const height of [780,742,702,681,780,681]) {
    await page.evaluate(height=>{
      Object.defineProperty(window,'innerHeight',{configurable:true,value:height});
      dispatchEvent(new Event('resize'));
      // Lazy loads and ResizeObserver updates must use the stable height too,
      // even when the window resize listener correctly ignores browser chrome.
      document.querySelector('#caption-single').dispatchEvent(new Event('load'));
    },height);
    await settleMasonry(page);
    expect(await page.evaluate(()=>document.documentElement.clientHeight)).toBe(681);
    expect(await geometry()).toEqual(before);
  }
  await page.evaluate(descriptor=>Object.defineProperty(window,'innerHeight',descriptor),innerHeightDescriptor);
  await innerHeightDescriptor.dispose();
  // A real viewport resize still needs to resize previews.
  await page.setViewportSize({width:402,height:520});await settleMasonry(page);
  expect((await geometry()).frames).not.toEqual(before.frames);
});

async function pauseImageAnimation(group, time) {
  await expect(group).toHaveClass(/is-animating/);
  await group.evaluate((el,time)=>{
    for(const animation of el.getAnimations({subtree:true})) {
      if ('transitionProperty' in animation) continue;
      animation.pause(); animation.currentTime=time;
    }
  },time);
}

test('image layout unfolds downward without scrolling when its top is already above the viewport',async({page})=>{
  await captionFixture(page);
  const cell=page.locator('#caption-single').locator('xpath=ancestor::figure');
  const frame=cell.locator('.argon-image-frame'),toggle=cell.locator('.argon-image-toggle');
  for(const [width,height] of [[1440,1000],[390,844]]) {
    await page.setViewportSize({width,height});await settleMasonry(page);
    for(const reducedMotion of ['no-preference','reduce']) {
      await page.emulateMedia({reducedMotion:'reduce'});
      if(await toggle.getAttribute('aria-expanded')==='true') await toggle.evaluate(el=>el.click());
      await settleMasonry(page);
      // Leave the image top offscreen with the control and following prose
      // visible, where browser anchoring previously held the bottom in place.
      await frame.evaluate(el=>scrollTo({top:scrollY+el.getBoundingClientRect().top+200,behavior:'instant'}));
      await page.waitForTimeout(100);
      await page.emulateMedia({reducedMotion});
      await frame.evaluate(el=>{
        const samples=window.imageDirectionSamples=[],start=performance.now();
        const sample=()=>{
          const rect=el.getBoundingClientRect(),time=performance.now()-start;
          samples.push({time,scroll:scrollY,top:rect.top,bottom:rect.bottom});
          if(time<600)requestAnimationFrame(sample);
        };sample();
      });
      // A real pointer click must not scroll the control into view for us.
      const box=await toggle.boundingBox();
      await page.mouse.click(box.x+box.width/2,box.y+box.height/2);
      await expect(toggle).toHaveAttribute('aria-expanded','true');
      await expect.poll(()=>page.evaluate(()=>window.imageDirectionSamples.at(-1).time)).toBeGreaterThanOrEqual(600);
      const samples=await page.evaluate(()=>window.imageDirectionSamples),first=samples[0],last=samples.at(-1);
      expect(first.top).toBeLessThan(-190);
      expect(last.bottom-first.bottom).toBeGreaterThan(100);
      expect(Math.max(...samples.map(s=>Math.abs(s.top-first.top)))).toBeLessThan(1);
      expect(Math.max(...samples.map(s=>Math.abs(s.scroll-first.scroll)))).toBeLessThan(1);
      await expect(page.locator('html')).not.toHaveCSS('overflow-anchor','none');
    }
  }
});

test('image layout animates visible height and captions and reverses rapid toggles from the current frame',async({page})=>{
  await captionFixture(page);await page.emulateMedia({reducedMotion:'no-preference'});
  const cell=page.locator('#caption-single').locator('xpath=ancestor::figure');
  const group=cell.locator('xpath=ancestor::div[contains(@class,"argon-image-layout")]');
  const frame=cell.locator('.argon-image-frame'),toggle=cell.locator('.argon-image-toggle'),caption=cell.locator('figcaption');
  const collapsed=(await frame.boundingBox()).height,captionStart=(await caption.boundingBox()).y-(await frame.boundingBox()).y;
  await toggle.focus();await page.keyboard.press('Enter');await pauseImageAnimation(group,100);
  const middle=(await frame.boundingBox()).height,full=(await cell.locator('img').boundingBox()).height;
  expect(middle).toBeGreaterThan(collapsed+20);expect(middle).toBeLessThan(full-20);
  expect((await caption.boundingBox()).y-(await frame.boundingBox()).y).toBeGreaterThan(captionStart+20);
  // The focused control remains operable while it moves, including mid-animation.
  await page.keyboard.press('Enter');await pauseImageAnimation(group,0);
  expect((await frame.boundingBox()).height).toBeCloseTo(middle,0);
  await pauseImageAnimation(group,100);
  const reversing=(await frame.boundingBox()).height;
  expect(reversing).toBeGreaterThan(collapsed);expect(reversing).toBeLessThan(middle);
  await page.keyboard.press('Enter');await pauseImageAnimation(group,0);
  expect((await frame.boundingBox()).height).toBeCloseTo(reversing,0);
  await group.evaluate(el=>el.getAnimations({subtree:true}).forEach(animation=>animation.play()));
  await expect(group).not.toHaveClass(/is-animating/);
  expect((await frame.boundingBox()).height).toBeCloseTo((await cell.locator('img').boundingBox()).height,0);
  const f=await frame.boundingBox(),c=await caption.boundingBox();expect(c.y).toBeGreaterThanOrEqual(f.y+f.height);
  await page.keyboard.press('Enter');await expect(group).toHaveClass(/is-animating/);
  await page.emulateMedia({reducedMotion:'reduce'});await expect(group).not.toHaveClass(/is-animating/);
  expect((await frame.boundingBox()).height).toBeCloseTo(collapsed,0);
  await page.keyboard.press('Enter');await settleMasonry(page);
  await expect(group).not.toHaveClass(/is-animating/);
  expect((await frame.boundingBox()).height).toBeCloseTo((await cell.locator('img').boundingBox()).height,0);
});

test('image layout animation moves masonry neighbours and settles on resize or print',async({page})=>{
  await masonryFixture(page,{motion:['portrait','wide','tall','wide'],after:['wide']});
  await page.emulateMedia({reducedMotion:'no-preference'});
  const group=page.locator('#case-motion + .argon-image-layout'),cell=group.locator('.argon-image-cell').nth(2);
  const verify=async()=>{
    // Collapse may also smoothly scroll to the image. Sample every rectangle
    // in one frame so scrolling between protocol calls cannot mimic overflow.
    const boxes=await group.evaluate(el=>({outer:el.getBoundingClientRect().toJSON(),cells:[...el.querySelectorAll('.argon-image-cell')].map(cell=>cell.getBoundingClientRect().toJSON())}));
    expectMasonry(boxes.cells,boxes.outer);
  };
  const toggle=cell.locator('.argon-image-toggle'),before=await cell.boundingBox(),belowBefore=(await page.locator('#case-after').boundingBox()).y-(await group.boundingBox()).y;
  await toggle.focus();await page.keyboard.press('Enter');await pauseImageAnimation(group,100);
  const middle=await cell.boundingBox(),outer=await group.boundingBox(),belowMiddle=await page.locator('#case-after').boundingBox();
  expect(middle.width).toBeGreaterThan(before.width+10);expect(middle.width).toBeLessThan(outer.width-10);
  expect(belowMiddle.y-outer.y).toBeGreaterThan(belowBefore+10);
  await page.setViewportSize({width:390,height:844});await settleMasonry(page);
  await expect(group).not.toHaveClass(/is-animating/);
  await verify();
  await expect(toggle).toHaveAttribute('aria-expanded','true');
  await toggle.focus();await page.keyboard.press('Enter');await pauseImageAnimation(group,100);
  await page.emulateMedia({media:'print'});await expect(group).not.toHaveClass(/is-animating/);
  await expect(cell.locator('.argon-image-frame')).toHaveCSS('max-height','none');
  await expect(toggle).toBeHidden();
  await page.emulateMedia({media:'screen'});await page.setViewportSize({width:1440,height:1000});await settleMasonry(page);
  await verify();
  await expect(toggle).toHaveAttribute('aria-expanded','false');
  expect(await group.evaluate(el=>el.getAnimations({subtree:true}).filter(a=>a.playState==='running'||a.playState==='paused').length)).toBe(0);
});
