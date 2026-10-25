import { $$, __, button, config, randomID, scrollToElement, reducedMotion } from './dom.js';

const imageLink = link => /\.(?:avif|webp|png|jpe?g|gif|svg)(?:[?#]|$)/i.test(link?.href || '');
const excluded = '.wp-block-image, .wp-block-gallery, .no-fancybox, [data-no-fancybox="true"], [data-wp-interactive], .alignleft, .alignright';
const excludedContainers = `${excluded}, .argon-image-layout, figure, .wp-caption, pre, code, table, a, button, summary, [contenteditable="true"]`;

// Accept the original media wrapper without cloning picture sources or links.
function mediaNode(node, block) {
  if (!(node instanceof Element)) return null;
  const image = node.matches('img') ? node : node.querySelector('img');
  if (!image || image.closest(excluded) || ((image.hasAttribute('width') || image.hasAttribute('height')) && !image.classList.contains('argon-image-inset'))) return null;
  // Accept only img, picture > img, a > img and a > picture > img.
  let parent = image.parentElement;
  if (parent.tagName === 'PICTURE') {
    if (parent.textContent.trim() || [...parent.children].some(child => !['SOURCE', 'IMG'].includes(child.tagName)) || parent.querySelectorAll('img').length !== 1) return null;
    parent = parent.parentElement;
  }
  if (parent.tagName === 'A') {
    if (parent.children.length !== 1 || parent.textContent.trim()) return null;
    parent = parent.parentElement;
  }
  if (parent !== block) return null;
  for (let element = image; element; element = element.parentElement) {
    if (/width|height/i.test(element.getAttribute('style') || '')) return null;
    if (element === block) break;
  }
  return { node, image };
}

// Text mixed with an image remains prose. A semantic figure/caption pair is
// one item; native blocks, custom containers and authored geometry stay intact.
function blockMedia(block) {
  if (block.matches(excluded)) return null;
  if (block.matches('figure, .wp-caption')) {
    const children = [...block.childNodes].filter(node => node.nodeType !== Node.COMMENT_NODE && !(node.nodeType === Node.TEXT_NODE && !node.textContent.trim()));
    const caption = children.find(node => node instanceof Element && node.matches('figcaption, .wp-caption-text'));
    if (children.length !== 2 || !caption) return null;
    const media = mediaNode(children.find(node => node !== caption), block);
    return media ? [{ ...media, caption, figure: block }] : null;
  }
  if (block.nodeName !== 'P') return null;
  const media = [];
  for (const node of block.childNodes) {
    if (node.nodeType === Node.TEXT_NODE && !node.textContent.trim()) continue;
    if (node.nodeType === Node.COMMENT_NODE || node.nodeName === 'BR') continue;
    const item = mediaNode(node, block);
    if (!item) return null;
    media.push(item);
  }
  return media.length ? media : null;
}

export function enhanceImageLayout() {
  const updates = new Map();
  const finishers = [];
  const resize = new ResizeObserver(entries => entries.forEach(entry => updates.get(entry.target)?.()));
  const groups = [];
  const mediaBlocks = new Map(), containers = new Set();
  for (const root of $$('#post_content, .shuoshuo-content')) {
    // Markdown can live inside disclosures, admonitions, lists or other prose
    // containers. Group only direct siblings of their actual parent; never
    // move media across a container, a text node or a shortcode boundary.
    for (const block of $$('p, figure, .wp-caption', root)) {
      if (block.parentElement.closest(excludedContainers)) continue;
      const media = blockMedia(block);
      if (!media) continue;
      mediaBlocks.set(block, media); containers.add(block.parentElement);
    }
  }
  for (const container of containers) {
    let run = [];
    const finish = () => { if (run.length) groups.push(run); run = []; };
    // Non-whitespace text and comments (e.g. block/shortcode boundaries) also
    // interrupt a run. Never join images across an intervening content node.
    for (const node of container.childNodes) {
      if (node.nodeType === Node.TEXT_NODE && !node.textContent.trim()) continue;
      // HTML parsers create empty <p> nodes around Markdown's <p><figure>…
      // output. They carry no content and must not split adjacent figures.
      if (node instanceof Element && node.matches('p') && [...node.childNodes].every(child => child.nodeType === Node.TEXT_NODE && !child.textContent.trim())) {
        if (run.length) run.push({ paragraph: node, media: [] });
        continue;
      }
      const media = mediaBlocks.get(node);
      if (media) run.push({ paragraph: node, media });
      else finish();
    }
    finish();
  }
  for (const run of groups) {
    const layout = document.createElement('div'); layout.className = 'argon-image-layout';
    const items = document.createElement('div');
    const count = run.reduce((total, entry) => total + entry.media.length, 0), isGrid = count > 1;
    items.className = isGrid ? 'argon-image-grid' : 'argon-image-single';
    layout.append(items); run[0].paragraph.before(layout);
    const cells = [];
    let scheduled = false, afterLayout, motion, transitionFrom, releaseAnchor;
    const complete = () => {
      const done = afterLayout; afterLayout = undefined; done?.();
      // Keep anchoring disabled until the final geometry has been rendered.
      cancelAnimationFrame(releaseAnchor);
      releaseAnchor = requestAnimationFrame(() => layout.classList.remove('is-changing'));
    };
    const stopMotion = () => {
      const previous = motion; motion = undefined;
      previous?.animations.forEach(animation => animation.cancel());
      layout.classList.remove('is-animating');
    };
    const measure = () => {
      const outer = items.getBoundingClientRect();
      return { height: outer.height, cells: cells.map(({ cell, frame }) => {
        const box = cell.getBoundingClientRect();
        return { x: box.x - outer.x, y: box.y - outer.y, width: box.width, height: frame.getBoundingClientRect().height };
      }) };
    };
    const animateChange = before => {
      const after = measure(), animations = [];
      const options = { duration: 300, easing: 'cubic-bezier(.22,.61,.36,1)', fill: 'both' };
      const changed = (a, b) => Math.abs(a - b) > .5;
      cells.forEach(({ cell, frame }, index) => {
        const from = before.cells[index], to = after.cells[index];
        if (changed(from.height, to.height)) animations.push(frame.animate([
          { height: `${from.height}px`, maxHeight: 'none' }, { height: `${to.height}px`, maxHeight: 'none' }
        ], options));
        if (changed(from.x, to.x) || changed(from.y, to.y) || changed(from.width, to.width)) animations.push(cell.animate([
          { translate: `${from.x - to.x}px ${from.y - to.y}px`, width: `${from.width}px` },
          { translate: '0px 0px', width: `${to.width}px` }
        ], options));
      });
      // Changing real container height also moves the article below smoothly.
      if (changed(before.height, after.height)) animations.push(items.animate([
        { height: `${before.height}px` }, { height: `${after.height}px` }
      ], options));
      if (!animations.length) { complete(); return; }
      const state = motion = { animations, width: items.clientWidth, viewportHeight: innerHeight };
      layout.classList.add('is-animating');
      Promise.all(animations.map(animation => animation.finished)).then(() => {
        if (motion !== state) return;
        stopMotion(); arrange(); complete();
      }, () => {});
    };
    const update = () => {
      if (scheduled) return;
      scheduled = true;
      requestAnimationFrame(() => {
        scheduled = false;
        if (!layout.isConnected || !items.clientWidth) return;
        // Animated height and caption wrapping trigger ResizeObserver too.
        // Only a viewport change should interrupt the current transition.
        if (motion) {
          if (items.clientWidth === motion.width && innerHeight === motion.viewportHeight && !reducedMotion()) return;
          stopMotion();
        }
        const before = transitionFrom; transitionFrom = undefined;
        arrange();
        if (before && !reducedMotion() && items.animate && !matchMedia('print').matches) animateChange(before);
        else complete();
      });
    };
    for (const { paragraph, media } of run) {
      items.append(paragraph);
      if (paragraph.tagName === 'P') paragraph.classList.add('argon-image-paragraph');
      for (const { node, image, caption, figure } of media) {
        const cell = figure || document.createElement('span'); cell.classList.add('argon-image-cell');
        const frame = document.createElement('span'); frame.className = 'argon-image-frame'; frame.id = `argon-image-${randomID()}`;
        if (figure) {
          cell.classList.add('argon-image-figure'); caption.classList.add('argon-image-caption');
          node.before(frame); frame.append(node);
          updates.set(caption, update); resize.observe(caption);
        } else { node.before(cell); frame.append(node); cell.append(frame); }
        if (config.lightbox) {
          if (node.tagName === 'A') {
            if (imageLink(node)) node.classList.add('argon-image-view');
          } else if (/^https?:/i.test(image.currentSrc || image.src)) {
            const link = document.createElement('a'); link.href = image.currentSrc || image.src;
            link.className = 'argon-image-view'; frame.append(link); link.append(node);
            // An empty alt should still leave a named, keyboard-operable link.
            if (!image.alt) link.setAttribute('aria-label', __('查看大图'));
            image.addEventListener('load', () => { link.href = image.currentSrc || image.src; });
          }
        }
        let expanded = false;
        const toggle = button(__('展开完整图片'), 'argon-image-toggle', () => {
          // When the image top is offscreen, the browser may anchor content
          // below it and scroll by the growing height, making it unfold upward.
          cancelAnimationFrame(releaseAnchor);
          layout.classList.add('is-changing');
          // Read the currently visible frame before cancelling, so rapid
          // reversals continue from that frame instead of snapping to an end.
          transitionFrom = reducedMotion() ? undefined : measure();
          stopMotion();
          expanded = !expanded;
          const above = cell.getBoundingClientRect().top < 90;
          cell.classList.toggle('is-expanded', expanded);
          toggle.setAttribute('aria-expanded', String(expanded));
          toggle.textContent = expanded ? __('收起图片') : __('展开完整图片');
          afterLayout = !expanded && above ? () => scrollToElement(cell) : undefined;
          update();
        });
        toggle.hidden = true; toggle.setAttribute('aria-expanded', 'false'); toggle.setAttribute('aria-controls', frame.id);
        frame.after(toggle);
        image.addEventListener('load', update); image.addEventListener('error', update);
        cells.push({ cell, frame, image, toggle });
      }
    }
    function arrange() {
      const style = getComputedStyle(items);
      const columns = isGrid ? Number(style.getPropertyValue('--argon-image-columns')) || 1 : 1;
      const gap = parseFloat(style.getPropertyValue('--argon-image-gap')) || 12;
      const columnWidth = (items.clientWidth - (columns - 1) * gap) / columns;
      const heights = Array(columns).fill(0), previewLimit = innerHeight * .6, longLimit = innerHeight * .75;
      if (isGrid) items.classList.add('is-masonry');
      cells.forEach(({ cell, frame, image, toggle }, index) => {
        const failed = image.complete && !image.naturalWidth;
        const width = image.naturalWidth || (!failed && Number(image.getAttribute('width'))) || 0;
        const height = image.naturalHeight || (!failed && Number(image.getAttribute('height'))) || 0;
        if (width) frame.style.setProperty('--argon-image-natural-width', `${width}px`);
        else frame.style.removeProperty('--argon-image-natural-width');
        // Test the uncropped width after the outer container has been bounded.
        // Aspect ratio alone misses ordinary portraits on short screens.
        const mediaWidth = Math.min(isGrid ? columnWidth : frame.getBoundingClientRect().width, width);
        // Both the threshold and preview follow the viewport. Narrowing the
        // centered container should keep ordinary portraits below this limit.
        const long = width > 0 && mediaWidth * height / width > longLimit;
        cell.classList.toggle('is-long', long); cell.classList.toggle('is-unavailable', failed);
        toggle.hidden = !long;
        // Mobile CSS vh can differ from innerHeight while browser chrome is
        // visible. Use the same measured viewport for singles and grid items.
        cell.style.setProperty('--argon-image-preview-size', `${previewLimit}px`);
        if (!isGrid) return;
        const expanded = long && cell.classList.contains('is-expanded');
        const balanced = columns === 2 && Math.abs(heights[0] - heights[1]) <= columnWidth * .3;
        // A full-width preview is useful when it gains readable width without
        // leaving a large hole above it or an odd remainder below it.
        const wide = columns === 2 && long && (expanded || (
          count >= 3 && balanced && width >= columnWidth * 1.5 && (count - index - 1) % 2 === 0
        ));
        const column = wide || columns === 1 || heights[0] <= heights[1] ? 0 : 1;
        const top = wide ? Math.max(...heights) : heights[column];
        cell.style.gridColumn = wide ? '1 / -1' : String(column + 1);
        cell.style.gridRow = `${top + 1} / span 1`;
        const size = Math.max(1, Math.ceil(cell.getBoundingClientRect().height));
        cell.style.gridRow = `${top + 1} / span ${size}`;
        if (wide) heights.fill(top + size + gap);
        else heights[column] = top + size + gap;
      });
    }
    finishers.push(() => { if (motion) { stopMotion(); arrange(); complete(); } });
    updates.set(layout, update); resize.observe(layout); update();
  }
  // Viewport height changes can change the preview limit without changing width.
  if (groups.length) {
    window.addEventListener('resize', () => updates.forEach(update => update()), { passive: true });
    const finish = () => finishers.forEach(done => done());
    window.addEventListener('beforeprint', finish);
    matchMedia('print').addEventListener('change', finish);
    matchMedia('(prefers-reduced-motion: reduce)').addEventListener('change', finish);
  }
  else resize.disconnect();
}
