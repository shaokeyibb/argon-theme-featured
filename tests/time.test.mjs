import test from 'node:test';
import assert from 'node:assert/strict';
import { humanTime } from '../assets/src/modern/time.js';

test('comment timestamps honor date ordering, same-year omission and invalid input', () => {
  const now = new Date(2026, 9, 24).getTime();
  const timestamp = new Date(2025, 1, 3).getTime() / 1000;
  for (const [dateFormat, expected] of Object.entries({ YMD:'2025-02-03', MDY:'02-03-2025', DMY:'03-02-2025' })) {
    assert.equal(humanTime(timestamp, {now, dateFormat}), expected);
  }
  assert.equal(humanTime(new Date(2026, 1, 3).getTime()/1000, {now, dateFormat:'DMY'}), '03-02');
  assert.equal(humanTime(NaN, {now}), '');
  assert.equal(humanTime(new Date(2026, 9, 22, 8, 5).getTime()/1000, {now, locale:'zh-CN'}), '前天 8:05');
});
test('relative comment times floor elapsed units, update and tolerate future clocks', () => {
  const now = Date.now(), options = {now, locale:'en', justNow:'Now'};
  assert.equal(humanTime(now/1000+300, options), 'Now');
  assert.equal(humanTime(now/1000-119, options), '1 minute ago');
  assert.equal(humanTime(now/1000-119, {...options, now:now+60000}), '2 minutes ago');
  assert.equal(humanTime(now/1000-3600, options), '1 hour ago');
});
