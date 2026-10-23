import en from './i18n/en_US.js';
import ru from './i18n/ru_RU.js';
import tw from './i18n/zh_TW.js';

export const config = window.argonConfig ?? {};
export const $ = (selector, root = document) => root.querySelector(selector);
export const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
export const __ = text => ({ en_US: en, ru_RU: ru, zh_TW: tw }[config.language]?.[text] ?? text);
export const reducedMotion = () => matchMedia('(prefers-reduced-motion: reduce)').matches;
export function randomID() {
  // randomUUID is HTTPS-only; getRandomValues also works on ordinary HTTP sites.
  return [...crypto.getRandomValues(new Uint8Array(16))].map(byte => byte.toString(16).padStart(2, '0')).join('');
}
export function on(type, selector, handler) {
  document.addEventListener(type, event => {
    const target = event.target instanceof Element ? event.target.closest(selector) : null;
    if (target && !target.disabled) handler(event, target);
  });
}
export function scrollToElement(element) {
  if (!element) return;
  window.scrollTo({ top: element.getBoundingClientRect().top + scrollY - 90, behavior: reducedMotion() ? 'instant' : 'smooth' });
}
export const storage = {
  get(key) { try { return localStorage.getItem(key); } catch { return null; } },
  set(key, value) { try { localStorage.setItem(key, value); } catch { /* Private browsing may disable storage. */ } }
};
export function setCookie(key, value, days = 365) {
  document.cookie = `${key}=${encodeURIComponent(value)};max-age=${days * 86400};path=/;SameSite=Lax${location.protocol === 'https:' ? ';Secure' : ''}`;
}
export function notify(message, error = false) {
  let region = $('#argon-notifications');
  if (!region) {
    region = document.createElement('div');
    region.id = 'argon-notifications';
    region.setAttribute('aria-live', 'polite');
    document.body.append(region);
  }
  const item = document.createElement('button');
  item.type = 'button';
  item.className = `argon-notice${error ? ' is-error' : ''}`;
  // Server error strings can include markup. Never interpret them as HTML.
  const parsed = new DOMParser().parseFromString(String(message), 'text/html');
  item.textContent = parsed.body.textContent;
  item.addEventListener('click', () => item.remove());
  region.append(item);
  setTimeout(() => item.remove(), 7000);
}
export async function request(action, data = {}, options = {}) {
  const response = await fetch(config.ajax_url, {
    method: 'POST', credentials: 'same-origin',
    body: new URLSearchParams({ action, _wpnonce: config.ajax_nonce, ...data }),
    signal: AbortSignal.timeout(30000), ...options
  });
  const result = await response.json();
  if (!response.ok || result.status === 'failed' || result.success === false) {
    throw new Error(result.msg || result.data?.message || __('未知错误'));
  }
  return result;
}
export async function copyText(text) {
  try {
    await navigator.clipboard.writeText(text);
    notify(__('复制成功'));
  } catch {
    // HTTP/private contexts may deny clipboard access; offer a selectable value.
    window.prompt(__('请手动复制代码'), text);
  }
}
export function button(label, className, callback) {
  const element = document.createElement('button');
  element.type = 'button';
  element.className = className;
  element.textContent = label;
  element.addEventListener('click', callback);
  return element;
}
