import hljs from 'highlight.js/lib/common';
import { $, $$, config, __, button, copyText } from './dom.js';

function addGutter(pre, code, source) {
  const gutter = document.createElement('span'); gutter.className = 'argon-code-gutter'; gutter.setAttribute('aria-hidden', 'true');
  pre.append(gutter);
  // Ranges measure actual highlighted text, including wrapped lines and tabs.
  // They neither rewrite token spans nor insert line numbers into copied code.
  const walker = document.createTreeWalker(code, NodeFilter.SHOW_TEXT), nodes = [];
  let node, offset = 0;
  while ((node = walker.nextNode())) { nodes.push({ node, offset }); offset += node.length; }
  let start = 0, cursor = 0;
  const lines = source.replace(/\n$/, '').split('\n').map((line, index) => {
    while (cursor + 1 < nodes.length && nodes[cursor].offset + nodes[cursor].node.length <= start) cursor++;
    const current = nodes[cursor], range = document.createRange(), number = document.createElement('span');
    if (current) {
      const position = Math.min(start - current.offset, current.node.length);
      range.setStart(current.node, position); range.setEnd(current.node, Math.min(position + 1, current.node.length));
    }
    start += line.length + 1; number.textContent = index + 1; gutter.append(number);
    return { range, number };
  });
  let frame;
  const update = () => {
    cancelAnimationFrame(frame);
    frame = requestAnimationFrame(() => {
      const top = gutter.getBoundingClientRect().top;
      for (const {range, number} of lines) number.style.top = `${range.getBoundingClientRect().top - top}px`;
    });
  };
  new ResizeObserver(update).observe(code);
  code.addEventListener('scroll', update, { passive: true });
  document.fonts.ready.then(update);
  return update;
}

let fallbackFullscreen;
function exitFallback() {
  fallbackFullscreen?.classList.remove('hljs-codeblock-fullscreen');
  fallbackFullscreen = null;
  document.documentElement.classList.remove('argon-code-fullscreen');
}
document.addEventListener('keydown', event => { if (event.key === 'Escape') exitFallback(); });

export function highlight() {
  $$('article pre.code:not(:has(code))').forEach(pre => { const code = document.createElement('code'); code.textContent = pre.textContent; pre.replaceChildren(code); });
  $$('article pre > code:not([data-highlighted]):not(.no-hljs)').forEach(code => {
    const source = code.textContent, pre = code.parentElement;
    if (pre.classList.contains('no-hljs')) return;
    const language = [...code.classList].find(name => name.startsWith('language-'))?.slice(9);
    if (language && !hljs.getLanguage(language)) { code.classList.remove(`language-${language}`); code.classList.add('language-plaintext'); }
    hljs.highlightElement(code);
    pre.classList.add('hljs-codeblock'); code.setAttribute('hljs-codeblock-inner', '');
    const options = config.code_highlight || {};
    for (const [key, name] of [['hide_linenumber', 'hljs-hide-linenumber'], ['transparent_linenumber', 'hljs-transparent-linenumber'], ['break_line', 'hljs-break-line']]) pre.classList.toggle(name, !!options[key]);
    const updateGutter = addGutter(pre, code, source);
    const controls = document.createElement('div'); controls.className = 'hljs-control hljs hljs-title';
    for (const [label, className, iconName, action] of [
      [__('显示行号'), 'hljs-control-toggle-linenumber', 'list-ol', () => { pre.classList.toggle('hljs-hide-linenumber'); updateGutter(); }],
      [__('开启折行'), 'hljs-control-toggle-break-line', 'align-left', () => { pre.classList.toggle('hljs-break-line'); updateGutter(); }],
      [__('复制'), 'hljs-control-copy', 'copy', () => copyText(source)],
      [__('全屏'), 'hljs-control-fullscreen', 'expand', async () => {
        if (fallbackFullscreen) { exitFallback(); return; }
        try { if (document.fullscreenElement) await document.exitFullscreen(); else await pre.requestFullscreen(); }
        catch { fallbackFullscreen = pre; pre.classList.add('hljs-codeblock-fullscreen'); document.documentElement.classList.add('argon-code-fullscreen'); }
      }]
    ]) {
      const control = button('', `hljs-control-btn ${className}`, action);
      control.title = label; control.setAttribute('aria-label', label);
      const icon = document.createElement('i'); icon.className = `fa fa-${iconName}`; icon.setAttribute('aria-hidden', 'true');
      control.append(icon); controls.append(control);
    }
    pre.append(controls);
    pre.style.setProperty('--hljs-background-color', getComputedStyle(code).backgroundColor);
  });
}
