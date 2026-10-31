import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

const fixture = () => JSON.parse(readFileSync('temp/browser-fixture.json', 'utf8'));
const frames = page => page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(() => requestAnimationFrame(resolve)))));
const boundaries = [280, 320, 390, 450, 575, 576, 600, 601, 767, 768, 844, 899, 900, 901, 1000, 1099, 1100, 1101, 1199, 1200, 1201, 1440, 1699, 1700, 1701, 1920, 2560];

async function toolbar(page) {
  await page.goto('/', { waitUntil: 'domcontentloaded' });
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await page.locator('#navbar-main .navbar-title').evaluate(el => { el.textContent = "HikariLan's Blog"; });
  // Match the seven icon + Chinese label entries from the reported real site.
  await page.locator('#navbar_global .navbar-search').evaluate(search => search.insertAdjacentHTML('beforebegin', `<ul id="responsive-menu" class="navbar-nav navbar-nav-hover">
    ${['主页', '博客', '时光轴', '朋友们', '留言板', '关于我', '开往'].map((label, index) => `<li data-menu-index="${index}" class="nav-item ${index === 6 ? 'dropdown' : ''}">
      <a class="nav-link" href="#content" ${index === 6 ? 'data-bs-toggle="dropdown"' : ''}><i class="fa fa-home"></i><span class="nav-link-inner--text">${label}</span></a>
      ${index === 6 ? '<div class="dropdown-menu"><a class="dropdown-item" href="#content">Archive</a></div>' : ''}</li>`).join('')}
    </ul>`));
  await page.evaluate(() => document.fonts.ready);
}

test('toolbar stays on one line through intermediate widths, search expansion and larger fonts', async ({ page }) => {
  test.setTimeout(180000);
  await toolbar(page);
  const widths = [...new Set([...boundaries, ...Array.from({ length: 58 }, (_, i) => 280 + i * 40)])].sort((a, b) => a - b);
  let overflow = 0, expanded = 0;
  for (const font of [16, 24]) {
    await page.evaluate(font => { document.documentElement.style.fontSize = `${font}px`; }, font);
    for (const width of widths) {
      await page.setViewportSize({ width, height: 900 });
      await frames(page);
      const nav = page.locator('#navbar-main');
      const folded = width < 900;
      if (!folded) {
        await page.locator('#navbar_search_input_container').evaluate(el => el.classList.add('open'));
        await frames(page);
        if (await page.locator('#navbar_more').evaluate(el => !el.hidden)) overflow++; else expanded++;
      }
      const geometry = await nav.evaluate(nav => {
        const line = el => {
          const style = getComputedStyle(el);
          return el.getBoundingClientRect().height - parseFloat(style.paddingTop) - parseFloat(style.paddingBottom) <= parseFloat(style.lineHeight) + 2;
        };
        const title = nav.querySelector('.navbar-title').getBoundingClientRect();
        return {
          overflow: nav.scrollWidth - nav.clientWidth,
          lines: [...nav.querySelectorAll('#responsive-menu .nav-link')].filter(el => el.getClientRects().length).every(line),
          titleFits: title.left >= 0 && title.right <= document.documentElement.clientWidth,
          titleClipped: nav.querySelector('.navbar-title').scrollWidth > nav.querySelector('.navbar-title').clientWidth + 1,
          pageOverflow: document.documentElement.scrollWidth - document.documentElement.clientWidth
        };
      });
      expect(geometry, `font ${font}px, viewport ${width}px`).toMatchObject({ lines: true, titleFits: true });
      expect(geometry.overflow, `${width}px`).toBeLessThanOrEqual(1);
      expect(geometry.pageOverflow, `${width}px`).toBeLessThanOrEqual(1);
      if (!folded) expect(geometry.titleClipped, `${width}px`).toBe(false);
    }
  }
  expect(overflow).toBeGreaterThan(0);
  expect(expanded).toBeGreaterThan(0);
});

test('reader settings fit narrow screens and scroll within short viewports', async ({ page }) => {
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await page.goto('/', { waitUntil: 'domcontentloaded' });
  await page.locator('#fabtn_toggle_blog_settings_popup').click();
  const panel = page.locator('#fabtn_blog_settings_popup');
  for (const font of [16, 24]) {
    await page.evaluate(font => { document.documentElement.style.fontSize = `${font}px`; }, font);
    for (const width of [280, 320, 390, 450, 600, 899, 900, 901, 1100, 1440, 1920, 2560]) {
      await page.setViewportSize({ width, height: 360 });
      await frames(page);
      const box = await panel.boundingBox();
      expect(box.x).toBeGreaterThanOrEqual(0);
      expect(box.y).toBeGreaterThanOrEqual(0);
      expect(box.x + box.width).toBeLessThanOrEqual(width + 1);
      expect(box.y + box.height).toBeLessThanOrEqual(361);
      await expect(panel).toHaveCSS('overflow-y', 'auto');
      expect(await panel.evaluate(el => el.scrollWidth - el.clientWidth)).toBeLessThanOrEqual(1);
    }
  }
});

test('desktop keeps visible links, authored icons, search and gutters while only overflow moves to More', async ({ page }) => {
  await toolbar(page);
  await page.setViewportSize({ width: 1200, height: 900 });
  await page.evaluate(() => { document.documentElement.style.fontSize = '24px'; });
  const nav = page.locator('#navbar-main'), main = page.locator('#responsive-menu');
  const panel = page.locator('#navbar_more_menu'), more = page.locator('#navbar_more_toggle');
  const search = page.locator('#navbar_search_input');
  const original = await nav.locator('[data-menu-index] > .nav-link').evaluateAll(els => { window.originalNavLinks = els; return els.map(el => el.textContent); });
  await expect(more).toBeVisible();
  const gutter = async () => nav.evaluate(el => ({
    title: el.querySelector('.navbar-title').getBoundingClientRect().left,
    search: el.querySelector('#navbar_search_input_container').getBoundingClientRect().right
  }));
  const before = await gutter();
  expect(before.title).toBeGreaterThanOrEqual(28);
  const visible = await main.locator(':scope > [data-menu-index]').count();
  expect(visible).toBeGreaterThan(0); expect(visible).toBeLessThan(7);
  for (const dark of [false, true]) {
    await page.evaluate(dark => document.documentElement.classList.toggle('darkmode', dark), dark);
    await more.click();
    await expect(panel).toBeVisible();
    await expect(page.locator('#navbar_search_container')).toBeVisible();
    await expect(panel.locator('input')).toHaveCount(0);
    await expect(panel.locator('.nav-link').first()).toHaveCSS('color', dark ? 'rgb(238, 238, 238)' : 'rgb(23, 43, 77)');
    const links = panel.locator('[data-menu-index] > .nav-link');
    expect(await links.evaluateAll(els => els.every(el => el.querySelectorAll('i').length === 1 && el.querySelector('i').className === 'fa fa-home'))).toBe(true);
    await panel.locator('[data-bs-toggle="dropdown"]').click();
    await expect(panel.locator('.dropdown-menu .dropdown-item')).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(panel.locator('.dropdown-menu')).toBeHidden();
    await expect(panel).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(panel).toBeHidden();
    await expect(more).toBeFocused();
  }
  await page.locator('#navbar_search_input_container').click();
  await search.fill('搜索保持原样');
  await expect(search).toBeFocused();
  await expect(search).toHaveValue('搜索保持原样');
  await expect.poll(() => main.locator(':scope > [data-menu-index]').count()).toBeLessThan(visible);
  expect((await gutter()).title).toBeCloseTo(before.title, 1);
  expect((await gutter()).search).toBeCloseTo(before.search, 1);
  await search.press('Tab');
  await expect.poll(() => search.evaluate(el => el.getBoundingClientRect().width)).toBeLessThanOrEqual(1);
  await expect.poll(() => main.locator(':scope > [data-menu-index]').count()).toBe(visible);
  await page.evaluate(() => { document.documentElement.style.cssText = ''; });
  await page.setViewportSize({ width: 1920, height: 900 });
  await expect(more).toBeHidden();
  await expect(main.locator(':scope > [data-menu-index]')).toHaveCount(7);
  expect(await main.locator(':scope > [data-menu-index] > .nav-link').evaluateAll(els => els.every((el, i) => el === window.originalNavLinks[i]))).toBe(true);
  expect(await main.locator(':scope > [data-menu-index] > .nav-link').allTextContents()).toEqual(original);
});

test('overflow links restore to the original mobile menu and keep search and keyboard focus', async ({ page }) => {
  await toolbar(page);
  await page.setViewportSize({ width: 1000, height: 700 });
  await page.evaluate(() => { document.documentElement.style.fontSize = '24px'; });
  const nav = page.locator('#navbar-main'), menu = page.locator('#navbar_global');
  const more = page.locator('#navbar_more_toggle'), panel = page.locator('#navbar_more_menu');
  await expect(more).toBeVisible();
  await page.evaluate(() => {
    for (const [side, value] of Object.entries({ top: 59, right: 47, bottom: 21, left: 47 })) document.documentElement.style.setProperty(`--argon-safe-${side}`, `${value}px`);
  });
  await more.click();
  const box = await panel.boundingBox();
  expect(box.x).toBeGreaterThanOrEqual(47);
  expect(box.x + box.width).toBeLessThanOrEqual(953);
  expect(box.y + box.height).toBeLessThanOrEqual(679);
  await page.locator('#banner').click({ position: { x: 1, y: 500 } });
  await expect(panel).toBeHidden();
  await page.evaluate(() => { document.documentElement.style.cssText = ''; });
  await page.setViewportSize({ width: 1920, height: 900 });
  await expect(more).toBeHidden();
  await page.locator('#navbar_search_input_container').click();
  const search = page.locator('#navbar_search_input'), mobile = page.locator('#navbar_search_input_mobile');
  await search.fill('保留搜索内容');
  await page.setViewportSize({ width: 390, height: 900 });
  await expect(more).toBeHidden();
  await expect(page.locator('#responsive-menu > [data-menu-index]')).toHaveCount(7);
  await expect(mobile).toBeFocused(); await expect(mobile).toHaveValue('保留搜索内容');
  await page.setViewportSize({ width: 1920, height: 900 });
  await expect(search).toBeFocused(); await expect(search).toHaveValue('保留搜索内容');
  await expect(more).toBeHidden();
  const last = nav.locator('[data-menu-index="6"] > .nav-link');
  await last.focus();
  await expect(last).toBeFocused();
  await page.evaluate(() => { document.documentElement.style.fontSize = '24px'; });
  await page.setViewportSize({ width: 900, height: 900 });
  await expect(panel).toBeVisible(); await expect(last).toBeFocused();
  await page.keyboard.press('Escape');
  await expect(panel).toBeHidden(); await expect(more).toBeFocused();
});

test('long toolbar titles and oversized configured or WordPress logos fit small screens', async ({ page }) => {
  await toolbar(page);
  const brand = page.locator('#navbar-main > .container > .navbar-brand');
  for (const wordpress of [false, true]) {
    await brand.evaluate((el, wordpress) => {
      const logo = '<img width="1200" height="400" src="data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%271200%27 height=%27400%27/%3E">';
      el.innerHTML = wordpress ? `<a href="#" class="custom-logo-link">${logo.replace('<img ', '<img class="custom-logo" ')}</a>` : `<a href="#" class="navbar-brand navbar-icon">${logo}</a><a href="#" class="navbar-brand navbar-title">A very long site title that should not cover the navigation controls</a>`;
    }, wordpress);
    for (const width of [280, 320, 390, 844, 900]) {
      await page.setViewportSize({ width, height: 900 });
      await frames(page);
      expect(await page.locator('#navbar-main').evaluate(el => el.scrollWidth - el.clientWidth)).toBeLessThanOrEqual(1);
      const toggle = width < 900 ? page.locator('#navbar-main button[data-target="#navbar_global"]').last() : page.locator('#navbar_search_input_container');
      const box = await toggle.boundingBox();
      expect(box.x + box.width).toBeLessThanOrEqual(width);
      expect(await brand.evaluate(el => el.getBoundingClientRect().right)).toBeLessThanOrEqual(box.x);
    }
  }
});

test('article, native blocks and HTML documents fit every layout around all breakpoints', async ({ page }) => {
  test.setTimeout(180000);
  await page.emulateMedia({ reducedMotion: 'reduce' });
  for (const post of ['normal', 'design', 'html']) {
    await page.goto(`/?p=${fixture()[post]}`, { waitUntil: 'domcontentloaded' });
    await page.addStyleTag({ content: '* { transition: none !important; animation: none !important; }' });
    for (const layout of ['', 'single-column', 'double-column-reverse', 'triple-column']) {
      await page.evaluate(layout => {
        document.documentElement.classList.remove('single-column', 'double-column-reverse', 'triple-column');
        document.querySelector('#rightbar')?.remove();
        if (layout) document.documentElement.classList.add(layout);
        if (layout === 'triple-column') document.querySelector('#content').insertAdjacentHTML('beforeend', '<aside id="rightbar"><section class="card">Right sidebar widget</section></aside>');
      }, layout);
      for (const width of boundaries) {
        await page.setViewportSize({ width, height: 900 });
        await frames(page);
        const bad = await page.evaluate(() => {
          const width = document.documentElement.clientWidth;
          const boxes = [...document.querySelectorAll('#primary, #main, .post-content, .post-full, #post_comment, #footer, #rightbar')]
            .filter(el => el.getClientRects().length)
            .map(el => ({ name: el.id || el.className, left: el.getBoundingClientRect().left, right: el.getBoundingClientRect().right }))
            .filter(box => box.left < -1 || box.right > width + 1);
          return { overflow: document.documentElement.scrollWidth - width, boxes };
        });
        expect(bad.boxes, `${post}, ${layout}, ${width}px`).toEqual([]);
        expect(bad.overflow, `${post}, ${layout}, ${width}px`).toBeLessThanOrEqual(1);
        if (layout === 'triple-column' && width > 900) {
          const unused = await page.evaluate(() => {
            const content = document.querySelector('#content'), primary = document.querySelector('#primary');
            const style = getComputedStyle(content);
            const width = content.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
            const sidebar = id => document.querySelector(id).getBoundingClientRect().width;
            return Math.abs(width - sidebar('#leftbar') - sidebar('#rightbar') - primary.getBoundingClientRect().width);
          });
          expect(unused, `triple-column at ${width}px`).toBeLessThanOrEqual(1);
        }
      }
    }
  }
});


test('pagination wraps without stretching very narrow homepages or shrinking circular controls', async ({ page }) => {
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await page.goto('/', { waitUntil: 'domcontentloaded' });
  // Middle pages have the most controls: first/previous, five numbers, next/last.
  await page.locator('.pagination').evaluateAll(lists => lists.forEach(list => {
    list.innerHTML = ['«', '‹', '48', '49', '50', '51', '52', '›', '»'].map(label =>
      `<li class="page-item${label === '50' ? ' active' : ''}"><a class="page-link" href="?paged=49">${label}</a></li>`).join('');
  }));
  await page.evaluate(() => document.fonts.ready);
  for (const font of [16, 24]) {
    await page.evaluate(font => { document.documentElement.style.fontSize = `${font}px`; }, font);
    for (const width of [200, 220, 250, 280, 320, 390, 600, 601, 900, 1440]) {
      await page.setViewportSize({ width, height: 844 });
      await frames(page);
      const geometry = await page.locator('.pagination:visible').evaluate(list => {
        const box = list.getBoundingClientRect();
        return { left: box.left, right: box.right,
          overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
          rows: new Set([...list.children].map(el => Math.round(el.getBoundingClientRect().top))).size,
          links: [...list.querySelectorAll('.page-link')].map(el => {
            const rect = el.getBoundingClientRect();
            return { left: rect.left, right: rect.right, width: rect.width, height: rect.height, radius: getComputedStyle(el).borderRadius };
          }) };
      });
      expect(geometry.overflow, `${width}px, font ${font}px`).toBeLessThanOrEqual(1);
      expect(geometry.left).toBeGreaterThanOrEqual(0); expect(geometry.right).toBeLessThanOrEqual(width + 1);
      for (const link of geometry.links) {
        expect(link.left).toBeGreaterThanOrEqual(0); expect(link.right).toBeLessThanOrEqual(width + 1);
        expect(link.width).toBe(36); expect(link.height).toBe(36); expect(link.radius).toBe('50%');
      }
      if (width <= 250) expect(geometry.rows).toBeGreaterThan(1);
      if (width >= 900) expect(geometry.rows).toBe(1);
    }
  }
  const link = page.locator('.pagination:visible .page-link').first();
  await link.focus(); await expect(link).toBeFocused();
  await expect(link).toHaveAttribute('href', '?paged=49');
});


test('detail cover corners match the card on mobile without clipping the article body', async ({ page }) => {
  await page.goto(`/?p=${fixture().normal}`, { waitUntil: 'domcontentloaded' });
  await page.addStyleTag({ content: '* { transition: none !important; animation: none !important; }' });
  const card = page.locator('article.post-full').first(), header = card.locator(':scope > .post-header');
  await header.evaluate(el => {
    const image = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="1200" height="600"%3E%3Crect width="1200" height="600" fill="teal"/%3E%3C/svg%3E';
    el.classList.add('post-header-with-thumbnail');
    el.innerHTML = `<picture><img class="post-thumbnail" width="1200" height="600" src='${image}'></picture><div class="post-header-text-container">${el.innerHTML}</div>`;
  });
  await header.locator('img').evaluate(el => el.decode());
  await page.evaluate(() => document.fonts.ready);
  for (const dark of [false, true]) {
    await page.evaluate(dark => {
      document.documentElement.classList.toggle('darkmode', dark);
      document.documentElement.style.setProperty('--card-radius', '28px');
    }, dark);
    for (const width of [250, 320, 390, 600, 601, 844, 1440]) {
      await page.setViewportSize({ width, height: 900 });
      await frames(page);
      await header.evaluate(el => scrollTo({ top: scrollY + el.getBoundingClientRect().top - 110, behavior: 'instant' }));
      const geometry = await header.evaluate(el => {
        const card = el.parentElement, h = getComputedStyle(el), c = getComputedStyle(card), box = el.getBoundingClientRect();
        return { cardLeft: c.borderTopLeftRadius, cardRight: c.borderTopRightRadius,
          coverLeft: h.borderTopLeftRadius, coverRight: h.borderTopRightRadius, coverBottom: h.borderBottomLeftRadius,
          bodyOverflow: c.overflow,
          cornersCovered: [box.left + 1, box.right - 1].every(x => el.contains(document.elementFromPoint(x, box.top + 1))) };
      });
      expect(geometry.coverLeft).toBe(geometry.cardLeft); expect(geometry.coverRight).toBe(geometry.cardRight);
      expect(geometry.coverBottom).toBe('0px'); expect(geometry.bodyOverflow).toBe('visible');
      if (width <= 600) { expect(geometry.cardLeft).toBe('0px'); expect(geometry.cornersCovered).toBe(true); }
      else expect(geometry.cardLeft).toBe('28px');
    }
  }
});
