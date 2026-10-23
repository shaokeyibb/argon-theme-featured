import { $, $$, on, config, __, notify, copyText, button, storage, request, reducedMotion, randomID } from './dom.js';
import { spaceContent } from './typography.js';

export function enhanceContent() {
  $$('.clamp').forEach(el => { el.style.setProperty('--clamp-lines', Number(el.getAttribute('clamp-line')) || 3); });
  const list = $('#main.article-list');
  if (list && config.waterflow_columns !== '1') {
    list.classList.add('argon-masonry');
    new ResizeObserver(entries => {
      const width = entries[0].contentRect.width;
      let count = config.waterflow_columns === '2and3' ? (width > 1000 ? 3 : 2) : Number(config.waterflow_columns) || 1;
      if (width < (count === 3 ? 800 : 650)) count = 1;
      list.style.setProperty('--columns', Math.min(3, Math.max(1, count)));
    }).observe(list);
  }
  const outdated = $('#post_outdate_toast');
  if (outdated) { notify(outdated.dataset.text); outdated.remove(); }
  if ($('.hitokoto')) fetch('https://v1.hitokoto.cn', { signal: AbortSignal.timeout(8000) }).then(response => response.json()).then(result => {
    $$('.hitokoto').forEach(el => { el.textContent = result.hitokoto; });
  }).catch(() => {});
  initGithubCards();
  initShare();
  initExternalClicks();
  if (config.lightbox) initLightbox();
  if (config.code_highlight?.enable && $('article pre')) import('./highlight.js').then(module => module.highlight()).catch(() => {});
  if (config.math && /\$|\\\(|\\\[/.test($('#post_content')?.textContent || '')) import('./math.js').then(module => module.renderMath($('#post_content'))).catch(() => {});
  spaceContent();
}

function initGithubCards() {
  $$('.github-info-card').forEach(async card => {
    const description = $('.github-info-card-description', card);
    try {
      let data = card.dataset;
      if (data.getdata !== 'backend') {
        const response = await fetch(`https://api.github.com/repos/${encodeURIComponent(data.author)}/${encodeURIComponent(data.project)}`, { signal: AbortSignal.timeout(10000) });
        if (!response.ok) throw new Error(__('获取 Repo 信息失败'));
        data = await response.json();
      }
      description.textContent = data.description || '';
      if (typeof data.homepage === 'string' && /^https?:\/\//i.test(data.homepage)) {
        const link = document.createElement('a'); link.href = data.homepage; link.textContent = data.homepage; link.target = '_blank'; link.rel = 'noopener noreferrer'; description.append(' ', link);
      }
      $('.github-info-card-stars', card).textContent = data.stars ?? data.stargazers_count ?? '-';
      $('.github-info-card-forks', card).textContent = data.forks ?? data.forks_count ?? '-';
    } catch (error) { if (description) description.textContent = error.message; }
  });
}

function initLightbox() {
  const excluded = '.wp-lightbox-container, [data-wp-interactive], .no-fancybox, [data-no-fancybox="true"], .friend-link-avatar, .comment-sticker';
  const imageLink = link => /\.(?:avif|webp|png|jpe?g|gif|svg)(?:[?#]|$)/i.test(link?.href || '');
  const eligible = img => !img.closest(excluded) && (!img.closest('a') || img.closest('a').matches('.comment-image') || imageLink(img.closest('a')));
  // WordPress Image blocks with core's lightbox retain their own Interactivity API.
  on('click', '#post_content img, .shuoshuo-content img, a.comment-image', async (event, el) => {
    if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    if (el.closest(excluded)) return;
    const link = el.closest('a'), image = el.matches('img') ? el : $('img', el);
    if (link && !link.matches('.comment-image') && !imageLink(link)) return;
    const src = link?.href || image?.currentSrc || image?.src;
    if (!src || !/^https?:/i.test(new URL(src, location.href).protocol)) return;
    event.preventDefault();
    try {
      const [{ default: PhotoSwipe }] = await Promise.all([import('photoswipe'), import('photoswipe/style.css')]);
      const gallery = image?.closest('#post_content, .shuoshuo-content') || el.closest('.comment-item-text');
      const images = gallery ? $$('img', gallery).filter(eligible) : [];
      const data = images.map(img => ({ src: imageLink(img.closest('a')) ? img.closest('a').href : img.currentSrc || img.src, width: img.naturalWidth, height: img.naturalHeight, alt: img.alt }));
      let index = images.indexOf(image);
      if (index < 0) { index = 0; data.unshift({ src, width: image?.naturalWidth || 0, height: image?.naturalHeight || 0, alt: image?.alt || '' }); }
      // Measure full-size media before opening, including comment-image links.
      const item = data[index];
      const full = new Image(); full.src = item.src; await full.decode();
      item.width = full.naturalWidth; item.height = full.naturalHeight; item.measured = true;
      const lightbox = new PhotoSwipe({ dataSource: data, index, bgOpacity: .88,
        showAnimationDuration: reducedMotion() ? 0 : 333, hideAnimationDuration: reducedMotion() ? 0 : 333 });
      lightbox.on('contentLoad', event => {
        const content = event.content;
        if (content.data.measured || content.data.measuring) return;
        content.data.measuring = true;
        const probe = new Image(); probe.src = content.data.src;
        probe.decode().then(() => {
          content.data.width = probe.naturalWidth; content.data.height = probe.naturalHeight; content.data.measured = true;
          if (!lightbox.isDestroying) lightbox.refreshSlideContent(content.index);
        }).catch(() => {}).finally(() => { content.data.measuring = false; });
      });
      lightbox.init();
    } catch { if (link) location.assign(link.href); else notify(__('加载失败'), true); }
  });
}

function initShare() {
  const info = window.shareInfo;
  if (!info || !$('#share')) return;
  on('click', '#share_show', () => $('#share_container')?.classList.add('opened'));
  on('click', '#share_copy_link', event => { event.preventDefault(); copyText(info.url); });
  on('click', '#share_qrcode', async event => {
    event.preventDefault();
    try {
      const { default: QRCode } = await import('qrcode');
      const dialog = document.createElement('dialog'); dialog.className = 'argon-qr-dialog';
      const img = new Image(); img.alt = __('二维码'); img.width = 320; img.height = 320;
      img.src = await QRCode.toDataURL(info.url, { width: 320 });
      dialog.append(img, button(__('关闭'), 'btn btn-primary', () => dialog.close()));
      dialog.addEventListener('close', () => dialog.remove(), { once: true }); document.body.append(dialog); dialog.showModal();
    } catch { notify(__('加载失败'), true); }
  });
  const image = $('meta[property="og:image"]')?.content || '';
  const destinations = {
    telegram: ['https://t.me/share/url', { url: info.url, text: info.title }],
    twitter: ['https://twitter.com/intent/tweet', { url: info.url, text: info.title }],
    facebook: ['https://www.facebook.com/sharer/sharer.php', { u: info.url }],
    weibo: ['https://service.weibo.com/share/share.php', { url: info.url, title: info.title }],
    qq: ['https://connect.qq.com/widget/shareqq/index.html', { url: info.url, title: info.title, source: info.source, desc: info.description, pics: image }],
    qzone: ['https://sns.qzone.qq.com/cgi-bin/qzshare/cgi_qzshare_onekey', { url: info.url, title: info.title, desc: info.description, summary: info.description, site: info.source, pics: image }],
    douban: ['https://www.douban.com/sharebutton', { href: info.url, name: info.title, text: info.description, image }]
  };
  for (const [name, [base, params]] of Object.entries(destinations)) {
    const link = $(`#share .icon-${name}`); if (!link) continue;
    link.href = `${base}?${new URLSearchParams(params)}`; link.rel = 'noopener noreferrer';
  }
}

function initExternalClicks() {
  if (!config.external_link_clicks || !config.post_id) return;
  const clicked = new Set(), pageID = randomID();
  let clientID = storage.get('argon_external_link_clicks_client_id');
  if (!clientID) { clientID = randomID(); storage.set('argon_external_link_clicks_client_id', clientID); }
  on('click', 'a[data-argon-click], .external-link-click-wrapper a', async (_, link) => {
    if (clicked.has(link.href)) return;
    clicked.add(link.href);
    try {
      const result = await request('argon_click_external_link', {
        post_id: config.post_id, url: link.getAttribute('href'), link_nonce: link.dataset.clickNonce || '',
        client_id: clientID, page_id: pageID, _wpnonce: config.external_link_clicks_nonce
      }, { keepalive: true });
      if (result.status !== 'success') return;
      const count = Number(result.count); if (!Number.isFinite(count)) return;
      const formatted = new Intl.NumberFormat(config.language?.replace('_', '-'), { notation: 'compact' }).format(count);
      link.dataset.argonClickCount = formatted;
      const wrapper = link.closest('.external-link-click-wrapper');
      if (!wrapper) return;
      let badge = $('.external-link-click-badge', wrapper);
      if (!badge) { badge = document.createElement('span'); badge.className = 'external-link-click-badge'; wrapper.append(badge); }
      badge.dataset.count = String(count); badge.textContent = formatted;
    } catch { clicked.delete(link.href); }
  });
}
