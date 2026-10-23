import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

// Colors sampled from the deployed Argon 2 stylesheet, before the framework
// migration. Keep this baseline independent of the Sass color calculations.
const palette = [
  ['primary', '#5e72e4', '#2643e9', '#cbd2f6', '#7889e8', '#324cdd'],
  ['secondary', '#f4f5f7', '#32325d', '#ffffff', '#f6f7f8', '#d6dae2'],
  ['success', '#2dce89', '#1aae6f', '#93e7c3', '#4fd69c', '#24a46d'],
  ['info', '#11cdef', '#03acca', '#88e6f7', '#37d5f2', '#0da5c0'],
  ['warning', '#fb6340', '#ff3709', '#fec9be', '#fc7c5f', '#fa3a0e'],
  ['danger', '#f5365c', '#f80031', '#fbafbe', '#f75676', '#ec0c38'],
  ['light', '#adb5bd', '#879cb0', '#f4f5f6', '#bac1c8', '#919ca6'],
  ['dark', '#212529', '#090c0e', '#5a6570', '#45484b', '#0a0c0d'],
  ['default', '#172b4d', '#091428', '#3462af', '#3c4d69', '#0b1526'],
];
const rgba = (hex, alpha = 1) => [...hex.slice(1).match(/../g).map(n => parseInt(n, 16)), alpha];
function sameColor(actual, expected) {
  const channels = actual.match(/[\d.]+/g).map(Number);
  if (channels.length === 3) channels.push(1);
  // Legacy and modern Sass differ by one RGB unit when rounding HSL colors.
  expected.forEach((value, index) => expect(Math.abs(channels[index] - value), `${actual} should match ${expected}`).toBeLessThanOrEqual(index === 3 ? .005 : 1));
}

test('Argon component colors survive framework changes in light and dark mode', async ({ page }) => {
  await page.goto('/', { waitUntil: 'domcontentloaded' });
  await page.addStyleTag({ content: '* { transition: none !important; scroll-behavior: auto !important; } #palette-fixture { position: fixed !important; inset: 0; overflow: auto; z-index: 2000 !important; }' });
  await page.evaluate(names => {
    const fixture = document.createElement('div');
    fixture.id = 'palette-fixture';
    fixture.style.cssText = 'position:relative;z-index:1;background:var(--color-foreground);padding:30px';
    fixture.innerHTML = names.map(name => `<div data-color="${name}"><div class="alert alert-${name}">Notice <a class="alert-link" href="#">Link</a></div><span class="badge badge-${name}">Badge</span><button class="btn btn-${name}">Button</button><button class="btn btn-outline-${name}">Outline</button><div class="bg-gradient-${name}" style="height:24px"></div></div>`).join('');
    document.body.append(fixture);
  }, [...palette.map(row => row[0]), 'neutral', 'white']);
  for (const dark of [false, true]) {
    await page.evaluate(dark => document.documentElement.classList.toggle('darkmode', dark), dark);
    for (const [name, base, badgeText, badgeBg, alertBg, pressed] of palette) {
      const row = page.locator(`#palette-fixture [data-color="${name}"]`);
      const values = await row.evaluate(el => Object.fromEntries(['alert', 'badge', 'alert-link'].map(cls => {
        const style = getComputedStyle(el.querySelector('.' + cls));
        return [cls, { color: style.color, background: style.backgroundColor }];
      })));
      sameColor(values.alert.background, rgba(alertBg));
      sameColor(values.alert.color, rgba(name === 'secondary' ? '#212529' : '#ffffff'));
      sameColor(values['alert-link'].color, rgba('#ffffff'));
      sameColor(values.badge.background, rgba(badgeBg, .5));
      sameColor(values.badge.color, rgba(dark ? '#eeeeee' : badgeText));
      await expect(row.locator(`[class="bg-gradient-${name}"]`)).toHaveCSS('background-image', /linear-gradient\(87deg,/);
      if (name === 'primary' || name === 'secondary') continue; // Theme color and widget overrides apply here.
      for (const variant of ['btn-', 'btn-outline-']) {
        const button = row.locator(`.${variant}${name}`);
        await button.hover();
        await expect(button).toHaveCSS('color', 'rgb(255, 255, 255)');
        sameColor(await button.evaluate(e => getComputedStyle(e).backgroundColor), rgba(base));
        await page.mouse.down();
        await expect.poll(() => button.evaluate(e => e.matches(':active'))).toBe(true);
        sameColor(await button.evaluate(e => getComputedStyle(e).backgroundColor), rgba(variant === 'btn-' ? pressed : base));
        await page.mouse.up();
      }
    }
    // Keep pale neutral alerts readable instead of restoring white-on-white.
    for (const name of ['neutral', 'white']) {
      const row = page.locator(`#palette-fixture [data-color="${name}"]`);
      await expect(row.locator('.alert')).toHaveCSS('color', 'rgb(33, 37, 41)');
      await expect(row.locator('.alert-link')).toHaveCSS('color', 'rgb(33, 37, 41)');
    }
  }
  // Existing site color customization must still control primary buttons.
  await page.evaluate(() => {
    document.documentElement.classList.remove('darkmode');
    document.documentElement.style.setProperty('--themecolor', '#9352bf');
    document.documentElement.style.setProperty('--themecolor-dark', '#703593');
  });
  const primary = page.locator('#palette-fixture .btn-primary');
  await primary.hover();
  await expect(primary).toHaveCSS('background-color', 'rgb(112, 53, 147)');
});

test('comment upvote icon follows the site color before and after a real vote', async ({ page }) => {
  const fixture = JSON.parse(readFileSync('temp/browser-fixture.json', 'utf8'));
  await page.goto(`/?p=${fixture.normal}`, { waitUntil: 'domcontentloaded' });
  await page.addStyleTag({ content: '* { transition: none !important; }' });
  const button = page.locator(`#comment-${fixture.comment} .comment-upvote`);
  const icon = button.locator('.fa-caret-up');
  const colors = await page.evaluate(() => {
    const sample = document.createElement('span'); document.body.append(sample);
    sample.style.color = 'var(--themecolor)'; const normal = getComputedStyle(sample).color;
    sample.style.color = 'var(--themecolor-light)'; const light = getComputedStyle(sample).color;
    sample.remove(); return { normal, light };
  });
  for (const dark of [false, true]) {
    await page.mouse.move(0, 0);
    await page.evaluate(dark => document.documentElement.classList.toggle('darkmode', dark), dark);
    await expect(icon).toHaveCSS('color', dark ? colors.light : colors.normal);
    await expect(button).toHaveCSS('border-top-color', colors.normal);
    await expect(button).toHaveCSS('background-color', /rgba\([^)]*, 0\.(15|25)\)/);
    await button.hover();
    await expect(icon).toHaveCSS('color', 'rgb(255, 255, 255)');
    await expect(button).toHaveCSS('background-color', colors.normal);
  }
  await page.evaluate(() => document.documentElement.classList.remove('darkmode'));
  const voted = page.waitForResponse(r => r.url().includes('admin-ajax') && r.request().postData()?.includes('action=upvote_comment'));
  await button.click();
  expect((await (await voted).json()).status).toBe('success');
  await page.mouse.move(0, 0);
  await expect(button).toHaveClass(/upvoted/);
  await expect(icon).toHaveCSS('color', 'rgb(255, 255, 255)');
  await expect(button).toHaveCSS('background-color', colors.normal);
  await expect(button).toHaveCSS('pointer-events', 'none');
  await page.reload({ waitUntil: 'domcontentloaded' });
  await expect(button).toHaveClass(/upvoted/);
  await expect(icon).toHaveCSS('color', 'rgb(255, 255, 255)');
});
