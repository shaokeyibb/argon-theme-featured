import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
const fixture = () => JSON.parse(readFileSync('temp/browser-fixture.json', 'utf8'));

test('shuoshuo, sidebar widgets and metadata share the reader text palette', async ({ page }) => {
  await page.goto('/?paged=2', { waitUntil: 'domcontentloaded' });
  await page.addStyleTag({ content: '* { transition: none !important; }' });
  for (const width of [1440, 390]) {
    await page.setViewportSize({ width, height: 900 });
    for (const mode of ['', 'darkmode', 'darkmode color-immersion', 'darkmode amoled-dark', 'darkmode amoled-dark color-immersion', '']) {
      await page.evaluate(mode => {
        document.documentElement.classList.remove('darkmode', 'color-immersion', 'amoled-dark');
        if (mode) document.documentElement.classList.add(...mode.split(' '));
      }, mode);
      const color = mode ? 'rgb(238, 238, 238)' : 'rgb(82, 95, 127)';
      for (const selector of ['.shuoshuo-content p', '.shuoshuo-preview-meta', '.post-meta-detail', '#text-2 li', '#text-2 label', '#blog_setting_toggle_darkmode_and_amoledarkmode span']) {
        await expect(page.locator(selector).first(), `${selector} at ${width}px in ${mode || 'light'}`).toHaveCSS('color', color);
      }
      await expect(page.locator('#text-2 h6')).toHaveCSS('color', mode ? color : 'rgb(50, 50, 93)');
      // Preserve the original secondary hierarchy instead of brightening every label.
      await expect(page.locator('.post-meta-detail').first()).toHaveCSS('opacity', '0.8');
      await expect(page.locator('.shuoshuo-preview-meta')).toHaveCSS('opacity', '0.6');
    }
  }
});

test('dark text tokens reach comments and Bootstrap components without replacing author colors', async ({ page }) => {
  await page.goto(`/?p=${fixture().normal}`, { waitUntil: 'domcontentloaded' });
  await page.addStyleTag({ content: '* { transition: none !important; }' });
  await page.locator('.post-content').evaluate(content => {
    content.insertAdjacentHTML('beforeend', `<div id="text-token-fixture">
      <div class="card"><div class="card-body">Nested card</div></div>
      <div class="modal-content">Dialog text</div>
      <ul class="list-group"><li class="list-group-item">List item</li></ul>
      <table class="table"><tbody><tr><td>Table cell</td></tr></tbody></table>
      <p class="text-body">Body utility</p><p class="text-muted">Secondary text</p>
      <p class="text-body-emphasis">Emphasized text</p><h3 class="text-black">Archive heading</h3>
      <p class="has-vivid-red-color has-text-color">Author palette color</p>
      <p style="color:#d12233">Author custom color</p>
    </div>`);
  });
  const authorColors = await page.locator('#text-token-fixture p').evaluateAll(elements => elements.slice(-2).map(el => getComputedStyle(el).color));
  await page.evaluate(() => document.documentElement.classList.add('darkmode'));
  for (const selector of ['.post-content > p', '.comment-item-text', '#text-token-fixture .card-body', '#text-token-fixture .modal-content', '#text-token-fixture .list-group-item', '#text-token-fixture td', '#text-token-fixture .text-body', '#text-token-fixture .text-body-emphasis', '#text-token-fixture h3']) {
    await expect(page.locator(selector).first()).toHaveCSS('color', 'rgb(238, 238, 238)');
  }
  await expect(page.locator('#text-token-fixture .text-muted')).toHaveCSS('color', 'rgba(238, 238, 238, 0.75)');
  await expect(page.locator('#text-token-fixture .list-group-item')).toHaveCSS('background-color', 'rgb(66, 66, 66)');
  await expect(page.locator('#text-token-fixture table')).toHaveCSS('--bs-table-bg', '#424242');
  expect(await page.locator('#text-token-fixture p').evaluateAll(elements => elements.slice(-2).map(el => getComputedStyle(el).color))).toEqual(authorColors);
  await page.evaluate(() => document.documentElement.classList.remove('darkmode'));
  await expect(page.locator('#text-token-fixture .card-body')).toHaveCSS('color', 'rgb(82, 95, 127)');
});

test('short articles and HTML documents fill the available card width', async ({ page }) => {
  await page.emulateMedia({ reducedMotion: 'reduce' });
  for (const post of [fixture().home, fixture().html]) {
    await page.goto(`/?p=${post}`, { waitUntil: 'domcontentloaded' });
    for (const width of [1440, 390]) {
      await page.setViewportSize({ width, height: 900 });
      await expect.poll(() => page.locator('.post-content').evaluate(content => {
        const card = content.parentElement;
        const style = getComputedStyle(card);
        const available = card.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
        return Math.abs(content.getBoundingClientRect().width - available);
      })).toBeLessThanOrEqual(1);
      const frame = page.locator('.argon-html-frame');
      if (await frame.count()) {
        expect(await frame.evaluate(el => Math.abs(el.getBoundingClientRect().width - el.parentElement.clientWidth))).toBeLessThanOrEqual(1);
      }
    }
  }
});

test('existing reader colors, fonts and radius also style native blocks', async ({ page, context }) => {
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await page.goto(`/?page_id=${fixture().design}`, { waitUntil: 'domcontentloaded' });
  const accent = page.locator('#design-primary');
  await expect(accent).toHaveCSS('color', 'rgb(94, 114, 228)');
  await expect(page.locator('#design-custom-size')).toHaveCSS('font-size', '22px');
  // Exercise the real settings controls, including the ES-module color converter.
  await page.locator('#fabtn_toggle_blog_settings_popup').click();
  const picker = page.locator('#theme-color-picker input');
  await picker.fill('#009688');
  await picker.dispatchEvent('input');
  await expect(accent).toHaveCSS('color', 'rgb(0, 150, 136)');
  await page.locator('#blog_setting_font_serif').click();
  await expect(page.locator('.post-content')).toHaveCSS('font-family', /Noto Serif/);
  // Saved reader radius remains supported even without a toolbar control.
  await context.addCookies([{ name: 'argon_card_radius', value: '9.5', url: page.url() }]);
  await page.reload({ waitUntil: 'domcontentloaded' });
  await expect(accent).toHaveCSS('color', 'rgb(0, 150, 136)');
  await expect(page.locator('.post-content')).toHaveCSS('font-family', /Noto Serif/);
  expect(await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--card-radius').trim())).toBe('9.5px');
  await page.locator('#fabtn_toggle_blog_settings_popup').click();
  await page.locator('#blog_setting_font_sans_serif').click();
  await expect(page.locator('.post-content')).not.toHaveCSS('font-family', /Noto Serif/);
  await page.locator('#theme-color-picker button').click();
  await expect(accent).toHaveCSS('color', 'rgb(94, 114, 228)');
  expect(errors).toEqual([]);
});

test('shared icon CSS renders arbitrary legacy, regular and brand names', async ({ page }) => {
  await page.goto(`/?page_id=${fixture().design}`, { waitUntil: 'domcontentloaded' });
  await expect(page.locator('link#argon-icons-css')).toHaveCount(1);
  const icons = await page.locator('#design-icons i').evaluateAll(async elements => {
    await document.fonts.ready;
    return elements.map(el => {
      const style = getComputedStyle(el, '::before');
      return { content: style.content, family: style.fontFamily, loaded: document.fonts.check(`${style.fontWeight} 16px ${style.fontFamily}`) };
    });
  });
  expect(icons).toHaveLength(3);
  for (const icon of icons) {
    expect(icon.content).not.toMatch(/^(none|normal|"")$/);
    expect(icon.family).toContain('Font Awesome');
    expect(icon.loaded).toBe(true);
  }
});
