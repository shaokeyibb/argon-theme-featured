import { $, $$, on, config, __, request, notify, scrollToElement, button } from './dom.js';
import { Modal } from './ui.js';
import { spaceContent } from './typography.js';
import { humanTime } from './time.js';
import { slide, fadeIn } from './motion.js';

let replyID = 0, editID = 0;
const value = id => $(`#${id}`)?.value ?? '';
const checked = id => !!$(`#${id}`)?.checked;

function resetForm(clear = false) {
  replyID = 0; editID = 0;
  $('#post_comment')?.classList.remove('editing', 'post-comment-force-privatemode-on', 'post-comment-force-privatemode-off');
  slide($('#post_comment_reply_info'), false);
  if (clear && $('#post_comment_content')) $('#post_comment_content').value = '';
}
function setPrivacy(comment) {
  const privateComment = !!$('.badge-private-comment', comment);
  $('#post_comment').classList.add(privateComment ? 'post-comment-force-privatemode-on' : 'post-comment-force-privatemode-off');
}
function refreshCaptcha(result) {
  const seed = result.newCaptchaSeed ?? result.captchaSeed;
  const nonce = result.newCaptchaNonce ?? result.captchaNonce;
  const text = result.newCaptcha ?? result.captcha;
  if (seed !== undefined && $('#post_comment_captcha_seed')) $('#post_comment_captcha_seed').value = seed;
  if (nonce !== undefined && $('#post_comment_captcha_nonce')) $('#post_comment_captcha_nonce').value = nonce;
  if (text !== undefined) $('.post-comment-captcha-container')?.setAttribute('captcha', text);
  if ($('#post_comment_captcha')) $('#post_comment_captcha').value = result.newCaptchaAnswer ?? '';
}

export function initComments() {
  setInterval(() => { if (!document.hidden) refreshTimes(); }, 60000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshTimes(); });
  on('click', '.comment-reply', (_, el) => {
    resetForm(); replyID = Number(el.dataset.id);
    const comment = $(`#comment-${replyID}`);
    if (!comment || !$('#post_comment')) return;
    $('#post_comment_reply_name').textContent = $('.comment-name', comment)?.textContent || '';
    $('#post_comment_reply_preview').textContent = $('.comment-item-source', comment)?.textContent || $('.comment-item-text', comment)?.textContent || '';
    slide($('#post_comment_reply_info'), true, { duration: 500 });
    setPrivacy(comment); scrollToElement($('#post_comment')); $('#post_comment_content').focus({ preventScroll: true });
  });
  on('click', '#post_comment_reply_cancel, #post_comment_edit_cancel', () => resetForm(editID !== 0));
  on('click', '.comment-edit', (_, el) => {
    resetForm(); editID = Number(el.dataset.id);
    const comment = $(`#comment-${editID}`);
    if (!comment || !$('#post_comment')) return;
    $('#post_comment').classList.add('editing');
    $('#post_comment_content').value = $('.comment-item-source', comment)?.textContent || '';
    if ($('#comment_post_use_markdown')) $('#comment_post_use_markdown').checked = comment.dataset.useMarkdown === 'true';
    setPrivacy(comment); scrollToElement($('#post_comment')); $('#post_comment_content').focus({ preventScroll: true });
    $('#post_comment_content').dispatchEvent(new Event('input', { bubbles: true }));
  });
  on('click', '#post_comment_send', async (event, el) => {
    event.preventDefault();
    const form = $('#post_comment');
    if (form.classList.contains('sending')) return;
    const content = value('post_comment_content');
    if (!content.trim()) { notify(__('评论内容不能为空'), true); return; }
    const controls = $$('input, textarea, button', form).filter(control => !control.disabled);
    const currentEditID = editID;
    const data = currentEditID ? { comment: content, id: currentEditID } : {
      comment: content, author: value('post_comment_name'), email: value('post_comment_email'), url: value('post_comment_link'),
      comment_post_ID: value('post_comment_post_id'), comment_parent: replyID,
      comment_captcha_seed: value('post_comment_captcha_seed'), comment_captcha_nonce: value('post_comment_captcha_nonce'),
      comment_captcha: value('post_comment_captcha'), 'wp-comment-cookies-consent': 'yes',
      use_markdown: checked('comment_post_use_markdown'),
      private_mode: form.classList.contains('post-comment-force-privatemode-on') || (!form.classList.contains('post-comment-force-privatemode-off') && checked('comment_post_privatemode')),
      enable_mailnotice: checked('comment_post_mailnotice')
    };
    form.classList.add('sending'); form.setAttribute('aria-busy', 'true'); controls.forEach(control => { control.disabled = true; });
    try {
      const result = await request(currentEditID ? 'user_edit_comment' : 'ajax_post_comment', data);
      if (currentEditID) {
        const comment = $(`#comment-${currentEditID}`);
        $('.comment-item-text', comment).innerHTML = result.new_comment;
        // Backend returns escaped source for storage in the hidden source element.
        $('.comment-item-source', comment).innerHTML = result.new_comment_source;
        let edited = $('.comment-edited', comment);
        if (!edited) { edited = document.createElement('div'); edited.className = 'comment-edited'; edited.textContent = __('已编辑'); $('.comment-info', comment)?.prepend(edited); }
        edited.classList.toggle('comment-edithistory-accessible', !!result.can_visit_edit_history);
        scrollToElement(comment);
      } else {
        const parent = Number(result.parentID || 0);
        let list;
        if (parent) {
          const divider = $(`#comment-${parent}`)?.nextElementSibling;
          list = divider?.nextElementSibling?.querySelector('ul.children');
          if (!list && divider) {
            const item = document.createElement('li'); list = document.createElement('ul'); list.className = 'children'; item.append(list); divider.after(item);
          }
        } else {
          list = $('#comments > .card-body > ol.comment-list');
          if (!list) { list = document.createElement('ol'); list.className = 'comment-list'; $('#comments > .card-body')?.append(list); }
        }
        if (list) list.insertAdjacentHTML(!parent && result.commentOrder === 'desc' ? 'afterbegin' : 'beforeend', result.html);
        refreshCaptcha(result); scrollToElement($(`#comment-${Number(result.id)}`));
      }
      resetForm(true); enhanceComments();
      notify(currentEditID ? __('您的评论已编辑') : __('您的评论已发送'));
    } catch (error) { notify(error.message, true); }
    finally { form.classList.remove('sending'); form.removeAttribute('aria-busy'); controls.forEach(control => { control.disabled = false; }); }
  });
  on('click', '#post_comment_toggle_extra_input', () => {
    const show = $('#post_comment').classList.toggle('show-extra-input');
    slide($('#post_comment_extra_input'), show);
  });
  on('input', '#post_comment_content', (_, el) => { el.style.height = 'auto'; el.style.height = `${Math.max(parseFloat(getComputedStyle(el).minHeight) || 0, el.scrollHeight)}px`; });
  on('focusin', '#post_comment_link, #post_comment_captcha', (_, el) => el.closest('.input-group')?.classList.add('active'));
  on('focusout', '#post_comment_link, #post_comment_captcha', (_, el) => el.closest('.input-group')?.classList.remove('active'));

  on('click', '.comment-upvote, .shuoshuo-upvote', async (_, el) => {
    if (el.dataset.busy) return;
    const type = el.classList.contains('comment-upvote') ? 'comment' : 'shuoshuo';
    el.dataset.busy = 'true'; el.classList.add(`${type}-upvoting`);
    try {
      const result = await request(`upvote_${type}`, { [`${type}_id`]: el.dataset.id });
      if ($(`.${type}-upvote-num`, el)) $(`.${type}-upvote-num`, el).textContent = result.total_upvote ?? '';
      if (result.status === 'success') {
        el.classList.add('upvoted');
        if (type === 'shuoshuo') el.classList.add('shuoshuo-upvoted-animation');
      }
      if (result.msg) notify(result.msg, result.status !== 'success');
    } catch (error) { notify(error.message, true); }
    finally { delete el.dataset.busy; el.classList.remove(`${type}-upvoting`); }
  });
  on('click', '.comment-pin, .comment-unpin', (_, el) => {
    const pinned = el.classList.contains('comment-unpin'), id = Number(el.dataset.id);
    const dialog = $('#comment_pin_comfirm_dialog');
    if (!dialog) return;
    $('.modal-title', dialog).textContent = pinned ? __('取消置顶评论') : __('置顶评论');
    $('.modal-body', dialog).textContent = `${pinned ? __('是否要取消置顶评论 #') : __('是否要置顶评论 #')}${id}?`;
    const confirm = $('.btn-comfirm', dialog); confirm.disabled = false;
    confirm.onclick = async () => {
      confirm.disabled = true;
      try {
        await request('pin_comment', { id, pinned: !pinned });
        const comment = $(`#comment-${id}`);
        $('.badge-pinned', comment)?.remove();
        if (!pinned) { const badge = document.createElement('span'); badge.className = 'badge badge-danger badge-pinned'; badge.textContent = __('置顶'); $('.comment-name', comment)?.append(badge); }
        el.classList.toggle('comment-pin', pinned); el.classList.toggle('comment-unpin', !pinned); el.textContent = pinned ? __('置顶') : __('取消置顶');
        Modal.getOrCreateInstance(dialog).hide();
      } catch (error) { notify(error.message, true); }
      finally { confirm.disabled = false; }
    };
    Modal.getOrCreateInstance(dialog).show();
  });
  let historyRequest = 0;
  on('click', '.comment-edited.comment-edithistory-accessible', async (_, el) => {
    const id = el.closest('[data-id]')?.dataset.id, dialog = $('#comment_edit_history');
    if (!id || !dialog) return;
    const sequence = ++historyRequest;
    $('.modal-title', dialog).textContent = `${__('评论 #')}${id} ${__('的编辑记录')}`;
    $('.modal-body', dialog).textContent = __('加载中'); Modal.getOrCreateInstance(dialog).show();
    try {
      const result = await request('get_comment_edit_history', { id });
      if (sequence === historyRequest) { $('.modal-body', dialog).innerHTML = result.history; fadeIn($('.modal-body', dialog)); }
    } catch (error) { if (sequence === historyRequest) { $('.modal-body', dialog).textContent = error.message; fadeIn($('.modal-body', dialog)); } }
  });
  on('click', '#comments_navigation .page-item > div, #comments_more', loadCommentPage);
  on('click', '.comment-parent-info', (_, el) => scrollToElement(document.getElementById(`comment-${el.dataset.parentId}`)));
  on('click', '#comment_emotion_btn', () => $('#comment_emotion_btn').classList.toggle('comment-emotion-keyboard-open'));
  on('click', '.emotion-group-name', (_, el) => {
    $$('.emotion-group-name').forEach(item => item.classList.toggle('active', item === el));
    $$('.emotion-group').forEach(item => item.classList.toggle('d-none', item.getAttribute('index') !== el.getAttribute('index')));
  });
  on('click', '.emotion-item', (_, el) => {
    const input = $('#post_comment_content'); if (!input) return;
    const text = el.classList.contains('emotion-item-sticker') ? ` :${el.getAttribute('code')}: ` : el.getAttribute('text');
    input.setRangeText(text || '', input.selectionStart, input.selectionEnd, 'end'); input.focus(); input.dispatchEvent(new Event('input', { bubbles: true }));
    $('#comment_emotion_btn')?.classList.remove('comment-emotion-keyboard-open');
  });
  document.addEventListener('click', event => { if (!event.target.closest('#comment_emotion_btn, #emotion_keyboard')) $('#comment_emotion_btn')?.classList.remove('comment-emotion-keyboard-open'); });
  on('click', '.show-full-comment, .show-full-shuoshuo', (_, el) => {
    const prefix = el.classList.contains('show-full-comment') ? 'comment' : 'shuoshuo';
    el.parentElement.classList.remove(`${prefix}-folded`); el.parentElement.classList.add(`${prefix}-unfolded`); el.remove();
  });
  document.addEventListener('error', event => { if (event.target.matches?.('img.avatar')) textAvatar(event.target); }, true);
  if (config.captcha_ajax && $('#post_comment') && !$('#post_comment').classList.contains('no-need-captcha')) request('get_captcha').then(refreshCaptcha).catch(error => notify(error.message, true));
}

async function loadCommentPage(event, el) {
  event.preventDefault();
  if (el.dataset.busy) return;
  const url = new URL(el.getAttribute('href'), location.href);
  if (url.origin !== location.origin) return;
  const more = el.id === 'comments_more', comments = $('#comments');
  el.dataset.busy = 'true'; comments.classList.add('comments-loading');
  if (more) el.disabled = true;
  try {
    const response = await fetch(url, { credentials: 'same-origin', signal: AbortSignal.timeout(30000) });
    if (!response.ok) throw new Error(__('加载失败'));
    const page = new DOMParser().parseFromString(await response.text(), 'text/html');
    if (!$('#comments', page)) throw new Error(__('加载失败'));
    if (more) {
      const list = $('#comments > .card-body > ol.comment-list', page);
      if (list) $('#comments > .card-body > ol.comment-list')?.append(...list.children);
      const next = $('#comments_more', page);
      if (next) el.setAttribute('href', next.getAttribute('href')); else el.remove();
    } else {
      comments.replaceChildren(...$('#comments', page).childNodes); scrollToElement(comments);
    }
    enhanceComments();
  } catch (error) { notify(error.message, true); }
  finally { delete el.dataset.busy; if (more) el.disabled = false; comments.classList.remove('comments-loading'); }
}

function textAvatar(img) {
  const name = $('.comment-name', img.closest('.comment-item') || img.parentElement.parentElement)?.textContent.trim() || '?';
  const replacement = document.createElement('div'); replacement.className = `${img.className} text-avatar`;
  replacement.textContent = [...name][0]; replacement.setAttribute('role', 'img'); replacement.setAttribute('aria-label', img.alt || name);
  let hue = 0; for (const char of name) hue = (hue * 31 + char.codePointAt(0)) % 360;
  replacement.style.backgroundColor = `hsl(${hue} 55% 50%)`; img.replaceWith(replacement);
}

const foldingObservers = new WeakMap();

function observeFolding(el, prefix, height) {
  if (foldingObservers.has(el)) return;
  const foldedClass = `${prefix}-folded`, controlClass = `show-full-${prefix}`;
  const observed = new Set();
  let scheduled = false, lastWidth = -1, widthChangedAt = 0, settleTimer;
  const update = () => {
    scheduled = false;
    if (!el.isConnected) {
      clearTimeout(settleTimer); resize.disconnect(); mutations.disconnect(); foldingObservers.delete(el); return;
    }
    // A clipped parent can remain 200/400px tall while its content shrinks.
    // Observe its natural children as well, excluding our own overlay control.
    const targets = new Set([el, ...el.children].filter(node => !node.classList.contains(controlClass)));
    for (const node of observed) if (!targets.has(node)) { resize.unobserve(node); observed.delete(node); }
    for (const node of targets) if (!observed.has(node)) { resize.observe(node); observed.add(node); }
    if (el.classList.contains(`${prefix}-unfolded`) || !el.getClientRects().length || !el.clientWidth) return;
    // Responsive width transitions briefly squeeze even two lines into a tall
    // column. Wait for that width to settle before deciding to clip content.
    if (el.clientWidth !== lastWidth) {
      lastWidth = el.clientWidth; widthChangedAt = performance.now();
      clearTimeout(settleTimer); settleTimer = setTimeout(schedule, 120); return;
    }
    if (performance.now() - widthChangedAt < 120) return;
    const wasFolded = el.classList.contains(foldedClass);
    const maxHeight = el.style.getPropertyValue('max-height'), priority = el.style.getPropertyPriority('max-height');
    if (wasFolded) el.style.setProperty('max-height', 'none', 'important');
    const naturalHeight = el.getBoundingClientRect().height;
    if (wasFolded) {
      if (maxHeight) el.style.setProperty('max-height', maxHeight, priority);
      else el.style.removeProperty('max-height');
    }
    const shouldFold = naturalHeight > height + 1;
    el.classList.toggle(foldedClass, shouldFold);
    const control = $(`:scope > .${controlClass}`, el);
    if (!shouldFold) { control?.remove(); return; }
    if (!control) {
      const wrapper = document.createElement('div'); wrapper.className = controlClass;
      const toggle = button(__('展开'), 'btn btn-outline-primary argon-fold-toggle', () => {});
      const icon = document.createElement('i'); icon.className = 'fa fa-angle-down'; icon.setAttribute('aria-hidden', 'true');
      toggle.prepend(icon); toggle.setAttribute('aria-expanded', 'false');
      wrapper.append(toggle); el.append(wrapper);
    }
  };
  const schedule = () => { if (!scheduled) { scheduled = true; requestAnimationFrame(update); } };
  const resize = new ResizeObserver(schedule);
  const mutations = new MutationObserver(schedule);
  foldingObservers.set(el, resize);
  mutations.observe(el, { childList: true, characterData: true, subtree: true });
  update();
}

export function enhanceComments() {
  spaceContent();
  // Legacy stored stickers keep working without a jQuery lazyload runtime.
  $$('img[data-original]').forEach(img => {
    if (!img.hasAttribute('loading') && img.getAttribute('fetchpriority') !== 'high') img.loading = 'lazy';
    if (!img.hasAttribute('decoding')) img.decoding = 'async';
    // Old theme markup hid thumbnails inline. A temporary fade animation must
    // not reveal the old opacity:0 again when its effect finishes.
    if (img.matches('.post-thumbnail') && img.style.opacity === '0') img.style.removeProperty('opacity');
    img.addEventListener('load', () => fadeIn(img), { once: true });
    img.src = img.dataset.original; img.removeAttribute('data-original'); img.classList.remove('lazyload');
  });
  $$('img.avatar').forEach(img => { if (img.complete && !img.naturalWidth) textAvatar(img); });
  for (const [enabled, selector, prefix, height] of [
    [config.fold_long_comments, '.comment-item-inner', 'comment', 800],
    [config.fold_long_shuoshuo, '.shuoshuo-foldable > .shuoshuo-content', 'shuoshuo', 400]
  ]) {
    if (!enabled) continue;
    $$(selector).forEach(el => observeFolding(el, prefix, height));
  }
  refreshTimes();
}

function refreshTimes() {
  $$('.human-time').forEach(el => {
    const text = humanTime(Number(el.dataset.time), { locale: (config.language || 'zh-CN').replace('_', '-'), dateFormat: config.dateFormat, justNow: __('刚刚') });
    if (text) el.textContent = text;
  });
}
