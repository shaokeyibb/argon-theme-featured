const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
const t = text => window.wp?.i18n?.__(text, 'argon') || text;
const field = name => document.getElementsByName(name)[0];

function initSettings() {
  const form = $('#main_form'); if (!form) return;
  const color = field('argon_theme_color'), preview = field('argon_theme_color_hex_preview');
  const syncColor = () => { if (preview) preview.value = color.value; };
  color?.addEventListener('input', syncColor);
  preview?.addEventListener('click', () => color?.click());
  $$('.themecolor-preview').forEach(el => el.addEventListener('click', () => { color.value = el.getAttribute('color'); syncColor(); }));
  $$('[data-argon-color-picker]').forEach(el => el.addEventListener('click', () => color?.click()));
  $$('[data-argon-gravatar]').forEach(el => el.addEventListener('click', () => { field('argon_gravatar_cdn').value = el.textContent; }));
  const bind = (name, fn) => { const el = field(name); if (el) { el.addEventListener('change', () => fn(el.value)); fn(el.value); } };
  bind('argon_assets_path', value => { field('argon_custom_assets_path').hidden = value !== 'custom'; $$('.custom-assets-path-desctiption').forEach(el => { el.hidden = value !== 'custom'; }); });
  bind('argon_openai_baseurl', value => { field('argon_custom_openai_baseurl').hidden = value !== 'custom'; });
  bind('argon_enable_search_filters', value => $$('.argon-search-filters-type').forEach(el => { el.style.display = value === 'true' ? '' : 'none'; }));
  bind('argon_comment_pagination_type', value => $('.go-to-wp-comment-settings')?.classList.toggle('using-feed', value === 'feed'));
  $$('.radio-with-img .radio-img').forEach(el => el.addEventListener('click', () => $('input', el.parentElement)?.click()));
  const syncMeta = sortable('article_meta', 'article-meta-item', 'meta-name', 'argon_article_meta', '|');
  const syncFilters = sortable('search_filters', 'search-filter-item', 'filter-name', 'argon_search_filters_type', ',');
  const headings = $$('h2, h3', form), list = document.createElement('ul');
  headings.forEach((heading, index) => {
    if (!heading.id) heading.id = `argon-setting-${index}`;
    const item = document.createElement('li'), link = document.createElement('a');
    item.className = 'index-item'; link.className = 'index-link'; link.href = `#${heading.id}`; link.textContent = heading.textContent; item.append(link); list.append(item);
  });
  $('#headindex')?.replaceChildren(list);
  $('#headindex_toggler')?.addEventListener('click', event => { event.preventDefault(); $('#headindex_box').classList.toggle('folded'); });
  $$('[data-argon-scroll]').forEach(el => el.addEventListener('click', () => window.scrollTo({ top: el.dataset.argonScroll === 'top' ? 0 : document.documentElement.scrollHeight, behavior: 'smooth' })));
  $('[data-argon-show-ids]')?.addEventListener('click', () => { $('#id_of_categories_and_tags').style.display = ''; });
  $('#exported_settings_json')?.addEventListener('click', event => event.target.select());
  $('[data-argon-close-export]')?.addEventListener('click', () => $('#exported_settings_json_box').classList.add('closed'));

  window.setInputValue = (name, value) => {
    const inputs = [...document.getElementsByName(name)];
    for (const input of inputs) {
      if (input.type === 'radio') input.checked = input.value === String(value);
      else if (input.type === 'checkbox') input.checked = value === true || value === 'true';
      else input.value = String(value ?? '');
      input.dispatchEvent(new Event('change', { bubbles: true }));
    }
  };
  window.exportArgonSettings = () => {
    const data = {};
    $$('input[name^="argon_"], select[name^="argon_"], textarea[name^="argon_"]', form).forEach(input => {
      if (input.type === 'radio' && !input.checked || input.name.endsWith('_nonce')) return;
      data[input.name] = input.type === 'checkbox' ? input.checked : input.value;
    });
    return JSON.stringify(data, null, 2);
  };
  window.importArgonSettings = input => {
    const data = typeof input === 'string' ? JSON.parse(input) : input;
    if (!data || Array.isArray(data) || typeof data !== 'object') throw new Error(t('配置必须是 JSON 对象'));
    for (const [name, value] of Object.entries(data)) {
      if (!name.startsWith('argon_') || name.endsWith('_nonce') || !field(name) || !['string', 'number', 'boolean'].includes(typeof value)) continue;
      window.setInputValue(name, value);
    }
    syncColor(); syncMeta(); syncFilters();
    return '';
  };
  window.exportSettings = () => { $('#exported_settings_json').value = window.exportArgonSettings(); $('#exported_settings_json_box').classList.remove('closed'); $('#exported_settings_json').select(); };
  window.importSettings = () => {
    const value = prompt(t('请输入要导入的备份 JSON')); if (!value) return;
    try { window.importArgonSettings(value); alert(t('已导入，请保存更改')); } catch (error) { alert(error.message); }
  };
}

function sortable(prefix, itemClass, attribute, inputName, separator) {
  const active = $(`#${prefix}_active`), inactive = $(`#${prefix}_inactive`), input = field(inputName);
  if (!active || !inactive || !input) return () => {};
  const isFilter = prefix === 'search_filters';
  const write = () => { input.value = [...active.children].map(el => (isFilter && el.classList.contains('active') ? '*' : '') + el.getAttribute(attribute)).join(separator); };
  const read = () => {
    const values = input.value.split(separator);
    [...active.children].forEach(el => inactive.append(el));
    [...inactive.children].forEach(el => el.classList.remove('active'));
    values.forEach(value => {
      const name = value.replace(/^\*/, '');
      const item = [...inactive.children].find(el => el.getAttribute(attribute) === name);
      if (item) { active.append(item); item.classList.toggle('active', value.startsWith('*')); }
    });
  };
  read();
  let dragged;
  $$(`.${itemClass}`).forEach(el => {
    el.draggable = true;
    el.addEventListener('dragstart', event => { dragged = el; event.dataTransfer.effectAllowed = 'move'; event.dataTransfer.setData('text/plain', el.getAttribute(attribute)); });
    el.addEventListener('dragend', () => { dragged = null; write(); });
    for (const [label, action] of [[t('上移'), () => el.previousElementSibling?.before(el)], [t('下移'), () => el.nextElementSibling?.after(el)], [t('启用 / 停用'), () => (el.parentElement === active ? inactive : active).append(el)]]) {
      const control = document.createElement('button'); control.type = 'button'; control.className = 'button button-small'; control.textContent = label;
      control.addEventListener('click', event => { event.stopPropagation(); action(); write(); }); el.append(control);
    }
    if (isFilter) el.addEventListener('click', () => { if (el.parentElement === active) { el.classList.toggle('active'); write(); } });
  });
  [active, inactive].forEach(container => {
    container.addEventListener('dragover', event => {
      if (!dragged) return; event.preventDefault();
      const target = event.target.closest(`.${itemClass}`);
      if (target && target !== dragged) (event.clientY > target.getBoundingClientRect().top + target.offsetHeight / 2 ? target.after(dragged) : target.before(dragged));
      else if (!target) container.append(dragged);
    });
    container.addEventListener('drop', event => { event.preventDefault(); write(); });
  });
  return read;
}

function initClassicMeta() {
  if (!$('#argon_meta_box_nonce')) return;
  const notice = (message, error = false) => {
    const notices = window.wp?.data?.dispatch('core/notices');
    if (notices) notices.createNotice(error ? 'error' : 'success', message, { type: 'snackbar', isDismissible: true });
    else alert(message);
  };
  async function send(action, data) {
    const response = await fetch(window.ajaxurl, { method: 'POST', credentials: 'same-origin', body: new URLSearchParams({ action,
      argon_meta_box_nonce: $('#argon_meta_box_nonce').value, post_id: $('#post_ID').value, ...data }), signal: AbortSignal.timeout(40000) });
    const result = await response.json();
    if (!response.ok || result.status !== 'success') throw new Error(t('操作失败'));
  }
  for (const [key, id] of [['argon_show_post_outdated_info', 'apply_show_post_outdated_info'], ['argon_ai_no_update_post_summary', 'apply_ai_no_update_post_summary'], ['argon_ai_extra_prompt_mode', 'apply_ai_extra_prompt_mode']]) {
    const input = $(`#${key}`), control = $(`#${id}`); if (!input || !control) continue;
    input.addEventListener('change', () => { control.style.display = ''; });
    control.addEventListener('click', async () => {
      control.disabled = true;
      try {
        await send('update_post_meta_ajax', { meta_key: key, meta_value: input.value });
        if (key === 'argon_ai_extra_prompt_mode') await send('update_post_meta_ajax', { meta_key: 'argon_ai_extra_prompt', meta_value: $('#argon_ai_extra_prompt').value });
        control.style.display = 'none'; notice(t('应用成功'));
      } catch (error) { notice(error.message, true); } finally { control.disabled = false; }
    });
  }
  $('#argon_ai_extra_prompt')?.addEventListener('input', () => { $('#apply_ai_extra_prompt_mode').style.display = ''; });
  $('#regenerate_ai_post_summary')?.addEventListener('click', async event => {
    const control = event.currentTarget; control.disabled = true;
    try { await send('regenerate_ai_post_summary', {}); notice(t('重新生成成功')); }
    catch (error) { notice(error.message, true); } finally { control.disabled = false; }
  });
}
initSettings(); initClassicMeta();
