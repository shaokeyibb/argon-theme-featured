import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
const fixture = () => JSON.parse(readFileSync('temp/browser-fixture.json', 'utf8'));

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
