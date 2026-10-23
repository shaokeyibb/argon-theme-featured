import Modal from 'bootstrap/js/dist/modal';
import Collapse from 'bootstrap/js/dist/collapse';
import Dropdown from 'bootstrap/js/dist/dropdown';
import Tab from 'bootstrap/js/dist/tab';
import Popover from 'bootstrap/js/dist/popover';
import { $, $$, on, config, __, storage, setCookie, scrollToElement, reducedMotion } from './dom.js';
import { hex2rgb, rgb2hsl, rgb2gray } from './color.js';

export { Modal };

export function initUI() {
  const html = document.documentElement;
  // Preserve existing shortcodes and saved block markup while using Bootstrap 5.
  $$('[data-toggle], [data-dismiss], [data-target], [data-parent]').forEach(element => {
    for (const name of ['toggle', 'dismiss', 'target', 'parent']) {
      if (element.hasAttribute(`data-${name}`)) element.setAttribute(`data-bs-${name}`, element.getAttribute(`data-${name}`));
    }
  });
  // Imports install the Bootstrap data APIs; make their lifecycle explicit to bundlers.
  $$('[data-bs-toggle="dropdown"]').forEach(el => Dropdown.getOrCreateInstance(el));
  $$('[data-bs-toggle="tab"]').forEach(el => Tab.getOrCreateInstance(el));
  $$('.collapse').forEach(el => Collapse.getOrCreateInstance(el, { toggle: false }));
  $$('[tooltip]').forEach(el => { if (!el.title) el.title = el.getAttribute('tooltip'); });
  $$('sup.reference[data-content]').forEach(el => Popover.getOrCreateInstance(el, {
    content: el.dataset.content, html: true, sanitize: true, trigger: 'hover focus', container: 'body'
  }));
  document.addEventListener('wheel', event => {
    const target = event.target.closest?.('.horizontal-scroll');
    if (!target || target.scrollWidth <= target.clientWidth || Math.abs(event.deltaX) > Math.abs(event.deltaY)) return;
    event.preventDefault(); target.scrollLeft += event.deltaY;
  }, { passive: false });

  on('click', '#fabtn_open_sidebar, #open_sidebar', () => html.classList.add('leftbar-opened'));
  on('click', '#sidebar_mask', () => html.classList.remove('leftbar-opened'));
  on('keydown', 'body', event => { if (event.key === 'Escape') html.classList.remove('leftbar-opened'); });
  on('click', '#fabtn_back_to_top', () => window.scrollTo({ top: 0, behavior: reducedMotion() ? 'instant' : 'smooth' }));
  on('click', '#fabtn_toggle_darkmode, #blog_setting_darkmode_switch .custom-toggle-slider', () => window.toggleDarkmode?.());
  on('click', '#blog_setting_toggle_darkmode_and_amoledarkmode', () => window.toggleAmoledDarkMode?.());
  on('click', '#fabtn_go_to_comment', () => { scrollToElement($('#post_comment')); $('#post_comment_content')?.focus({ preventScroll: true }); });
  $('#fabtn_go_to_comment')?.classList.toggle('d-none', !$('#post_comment'));
  const fab = $('#float_action_buttons');
  fab?.classList.toggle('fabtns-float-left', storage.get('Argon_fabs_Floating_Status') === 'left');
  fab?.classList.remove('fabtns-unloaded');
  on('click', '#fabtn_toggle_sides', () => {
    const left = fab.classList.toggle('fabtns-float-left');
    storage.set('Argon_fabs_Floating_Status', left ? 'left' : 'right');
  });
  const settingsPopup = $('#fabtn_blog_settings_popup');
  const setSettingsOpen = open => {
    if (!settingsPopup) return;
    fab.classList.toggle('blog_settings_opened', open);
    settingsPopup.inert = !open; settingsPopup.setAttribute('aria-hidden', String(!open));
    $('#fabtn_toggle_blog_settings_popup')?.setAttribute('aria-expanded', String(open));
  };
  setSettingsOpen(false);
  on('click', '#fabtn_toggle_blog_settings_popup', () => setSettingsOpen(!fab.classList.contains('blog_settings_opened')));
  on('click', '#close_blog_settings', () => { setSettingsOpen(false); $('#fabtn_toggle_blog_settings_popup')?.focus(); });
  for (const el of $$('#close_blog_settings, #blog_setting_darkmode_switch .custom-toggle-slider, #blog_setting_toggle_darkmode_and_amoledarkmode')) {
    el.setAttribute('role', 'button'); el.tabIndex = 0; el.dataset.argonKeyboardButton = 'true';
  }
  $('#close_blog_settings')?.setAttribute('aria-label', __('关闭'));
  $('#blog_setting_darkmode_switch .custom-toggle-slider')?.setAttribute('aria-label', __('夜间模式'));
  on('keydown', '[data-argon-keyboard-button]', (event, el) => { if ([' ', 'Enter'].includes(event.key)) { event.preventDefault(); el.click(); } });
  document.addEventListener('keydown', event => { if (event.key === 'Escape') setSettingsOpen(false); });
  const serif = storage.get('Argon_Use_Serif');
  if (serif !== null) html.classList.toggle('use-serif', serif === 'true');
  on('click', '#blog_setting_font_sans_serif, #blog_setting_font_serif', (_, el) => {
    const enabled = el.id === 'blog_setting_font_serif';
    html.classList.toggle('use-serif', enabled);
    storage.set('Argon_Use_Serif', String(enabled));
  });
  function setFilter(name) {
    if (!['sunset', 'darkness', 'grayscale'].includes(name)) name = 'off';
    for (const value of ['sunset', 'darkness', 'grayscale']) html.classList.toggle(`filter-${value}`, name === value);
    $$('.blog-setting-filter-btn').forEach(el => el.classList.toggle('active', el.getAttribute('filter-name') === name));
    storage.set('Argon_Filter', name);
  }
  setFilter(storage.get('Argon_Filter'));
  on('click', '.blog-setting-filter-btn', (_, el) => setFilter(el.getAttribute('filter-name')));
  initPersonalization();

  on('focusin', '.form-control', (_, el) => el.closest('.form-group')?.classList.add('focused'));
  on('focusout', '.form-control', (_, el) => el.closest('.form-group')?.classList.toggle('focused', !!el.value));
  on('click', '#navbar_search_input_container, #leftbar_search_container', (_, el) => {
    el.classList.add('open');
    const input = $('input', el);
    if (input) { input.readOnly = false; input.focus(); }
    $('.leftbar-search-button')?.classList.add('open');
  });
  on('focusout', '#navbar_search_input, #leftbar_search_input', () => {
    $('#navbar_search_input_container')?.classList.remove('open');
    $('.leftbar-search-button')?.classList.remove('open');
  });
  on('keydown', '#navbar_search_input, #navbar_search_input_mobile, #leftbar_search_input', (event, el) => {
    if (event.key !== 'Enter' || !el.value.trim()) return;
    event.preventDefault();
    const url = new URL(config.home_url);
    url.searchParams.set('s', el.value.trim());
    location.assign(url);
  });
  on('change', '.search-filter', () => {
    const url = new URL(location.href);
    url.searchParams.set('post_type', $$('.search-filter:checked').map(el => el.name).join(',') || 'none');
    url.searchParams.delete('paged');
    url.pathname = url.pathname.replace(/\/page\/\d+\/?$/, '/');
    location.assign(url);
  });
  on('click', '.collapse-block-title', (_, el) => {
    const block = el.closest('.collapse-block');
    const collapsed = block.classList.toggle('collapsed');
    el.setAttribute('aria-expanded', String(!collapsed));
    $('.collapse-block-body', block).style.display = collapsed ? 'none' : '';
  });
  $$('.collapse-block-title').forEach(el => {
    el.setAttribute('role', 'button'); el.tabIndex = 0;
    el.setAttribute('aria-expanded', String(!el.closest('.collapse-block').classList.contains('collapsed')));
  });
  $$('.shortcode-todo input[type="checkbox"]').forEach((input, index) => {
    if (!input.id) input.id = `argon-todo-${index + 1}`;
    const label = input.parentElement.querySelector('label');
    if (label && !label.htmlFor) label.htmlFor = input.id;
  });
  on('keydown', '.collapse-block-title', (event, el) => {
    if (['Enter', ' '].includes(event.key)) { event.preventDefault(); el.click(); }
  });
  on('click', '.cover-scroll-down', () => scrollToElement($('#content')));
  html.classList.toggle('is-home', !!$('#main.article-list-home'));
  initCatalog();
  initScroll();
  initBanner();
}

function initPersonalization() {
  const html = document.documentElement;
  const meta = name => $(`meta[name="${name}"]`)?.content;
  const container = $('#theme-color-picker');
  if (container && meta('argon-enable-custom-theme-color') === 'true') {
    const picker = document.createElement('input');
    picker.type = 'color'; picker.value = meta('theme-color') || '#5e72e4'; picker.setAttribute('aria-label', __('主题色'));
    container.replaceChildren(picker);
    const apply = value => {
      const rgb = hex2rgb(value), hsl = rgb2hsl(rgb.R, rgb.G, rgb.B);
      html.style.setProperty('--themecolor', value);
      for (const key of ['R', 'G', 'B']) html.style.setProperty(`--themecolor-${key}`, rgb[key]);
      for (const key of ['H', 'S', 'L']) html.style.setProperty(`--themecolor-${key}`, hsl[key]);
      html.classList.toggle('themecolor-toodark', rgb2gray(rgb.R, rgb.G, rgb.B) < 50);
      $('meta[name="theme-color"]').content = value;
      setCookie('argon_custom_theme_color', value);
    };
    picker.addEventListener('input', () => apply(picker.value));
    const reset = document.createElement('button'); reset.type = 'button'; reset.textContent = __('恢复博客默认');
    reset.addEventListener('click', () => { picker.value = meta('theme-color-origin'); apply(picker.value); setCookie('argon_custom_theme_color', '', 0); });
    container.append(reset);
  }
  const radius = $('#blog_setting_card_radius');
  if (radius) {
    const input = document.createElement('input'); input.type = 'range'; input.min = '0'; input.max = '30'; input.step = '0.5';
    input.value = meta('theme-card-radius') || '4'; input.setAttribute('aria-label', __('卡片圆角'));
    radius.replaceChildren(input);
    input.addEventListener('input', () => { html.style.setProperty('--card-radius', `${input.value}px`); setCookie('argon_card_radius', input.value); });
    on('click', '#blog_setting_card_radius_to_default', () => { input.value = meta('theme-card-radius-origin') || '4'; input.dispatchEvent(new Event('input')); setCookie('argon_card_radius', '', 0); });
  }
}

function initCatalog() {
  const container = $('#leftbar_catalog');
  if (!container || $('.argon-html-frame')) return; // HTML document bridge owns its outline.
  const headings = $$('#post_content h1, #post_content h2, #post_content h3, #post_content h4, #post_content h5, #post_content h6');
  const list = document.createElement('ul'); list.className = 'index-subItem-box';
  const links = new Map(), stack = [{ level: 0, list, last: null }];
  headings.forEach((heading, index) => {
    if (!heading.id) {
      let id = `argon-heading-${index + 1}`;
      while (document.getElementById(id)) id += '-';
      heading.id = id;
    }
    const item = document.createElement('li'); item.className = 'index-item';
    const level = Number(heading.tagName[1]);
    while (stack.length > 1 && level <= stack.at(-1).level) stack.pop();
    const parent = stack.at(-1);
    if (parent.last && level > Number(parent.last.dataset.level)) {
      const nested = document.createElement('ul'); nested.className = 'index-subItem-box'; parent.last.append(nested);
      stack.push({ level: Number(parent.last.dataset.level), list: nested, last: null });
    }
    item.dataset.level = String(level);
    const link = document.createElement('a'); link.className = 'index-link'; link.href = `#${encodeURIComponent(heading.id)}`; link.textContent = heading.textContent;
    item.append(link); stack.at(-1).list.append(item); stack.at(-1).last = item; links.set(heading, link);
  });
  container.replaceChildren(list);
  const observer = new IntersectionObserver(entries => {
    const current = entries.find(entry => entry.isIntersecting);
    if (!current) return;
    links.forEach(link => { link.classList.remove('active'); link.removeAttribute('aria-current'); });
    links.get(current.target).classList.add('active'); links.get(current.target).setAttribute('aria-current', 'location');
  }, { rootMargin: '-80px 0px -65% 0px' });
  headings.forEach(heading => observer.observe(heading));
}

function initScroll() {
  const toolbar = $('#navbar-main'), content = $('#content'), banner = $('#banner_container_main');
  const part1 = $('#leftbar_part1'), part2 = $('#leftbar_part2');
  const article = $('article.post.post-full');
  let lastY = scrollY, scheduled = false;
  const update = () => {
    scheduled = false;
    const y = scrollY;
    $('#fabtn_back_to_top')?.classList.toggle('fabtn-hidden', y < 400);
    if (toolbar) {
      toolbar.classList.toggle('navbar-no-blur', y < 30);
      if (banner && content && !document.documentElement.classList.contains('no-banner')) {
        const start = banner.getBoundingClientRect().top + y - 75;
        const end = content.getBoundingClientRect().top + y - 75;
        const amount = config.headroom === 'absolute' ? 0 : Math.max(0, Math.min(1, (y - start) / Math.max(1, end - start)));
        const blur = document.documentElement.classList.contains('toolbar-blur');
        toolbar.style.setProperty('background-color', `rgba(var(--toolbar-color), ${amount * (blur ? .65 : .85)})`, 'important');
        toolbar.style.boxShadow = amount === 0 ? 'none' : '';
        if (blur) toolbar.style.backdropFilter = `blur(${amount > .3 ? 16 : 0}px)`;
        toolbar.classList.toggle('navbar-ontop', amount === 0);
      }
      if (config.headroom === 'true') {
        document.body.classList.add('with-headroom');
        if (Math.abs(y - lastY) > 10 || y < 100) {
          document.body.classList.toggle('headroom---unpinned', y > lastY && y > 100);
          document.body.classList.toggle('headroom---pinned', y <= lastY || y <= 100);
        }
      }
    }
    if (part1 && part2) {
      const bottom = part1.getBoundingClientRect().bottom + 10;
      part2.classList.toggle('sticky', bottom <= (config.headroom === 'absolute' ? 18 : 90));
      document.body.classList.toggle('leftbar-can-headroom', bottom <= 20);
    } else if (content) {
      document.body.classList.toggle('leftbar-can-headroom', content.getBoundingClientRect().top <= 30);
    }
    if (article) {
      const height = article.offsetHeight + 50 - innerHeight;
      const progress = (80 - article.getBoundingClientRect().top) / height;
      $('#fabtn_reading_progress')?.classList.toggle('fabtn-hidden', height <= 0 || progress < 0 || progress > 1);
      if ($('#fabtn_reading_progress_details')) $('#fabtn_reading_progress_details').textContent = `${Math.round(Math.max(0, Math.min(1, progress)) * 100)}%`;
      if ($('#fabtn_reading_progress_bar')) $('#fabtn_reading_progress_bar').style.width = `${Math.max(0, Math.min(1, progress)) * 100}%`;
    }
    if (document.documentElement.matches('.banner-as-cover.is-home')) {
      $('#float_action_buttons')?.classList.toggle('hidden', y < innerHeight * .2);
      $('.cover-scroll-down')?.classList.toggle('hidden', y >= innerHeight * .6);
    }
    lastY = y;
  };
  const schedule = () => { if (!scheduled) { scheduled = true; requestAnimationFrame(update); } };
  addEventListener('scroll', schedule, { passive: true }); addEventListener('resize', schedule, { passive: true });
  if (part1) new ResizeObserver(schedule).observe(part1);
  update();
}

function initBanner() {
  const title = $('.banner-title[data-interval]');
  if (!title) return;
  const interval = Math.max(10, Math.min(1000, Number(title.dataset.interval) || 100));
  const type = async element => {
    if (!element) return;
    const text = element.dataset.text || element.textContent;
    if (reducedMotion()) { element.textContent = text; return; }
    element.classList.add('typing-effect');
    const characters = [...text];
    for (let index = 1; index <= characters.length; index++) {
      element.textContent = characters.slice(0, index).join('');
      await new Promise(resolve => setTimeout(resolve, interval));
    }
    element.classList.remove('typing-effect');
  };
  type($('.banner-title-inner')).then(() => type($('.banner-subtitle')));
}
