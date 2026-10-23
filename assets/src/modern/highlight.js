import hljs from 'highlight.js/lib/common';
import { $, $$, config, __, button, copyText } from './dom.js';

export function highlight() {
  $$('article pre.code:not(:has(code))').forEach(pre => { const code = document.createElement('code'); code.textContent = pre.textContent; pre.replaceChildren(code); });
  $$('article pre > code:not([data-highlighted]):not(.no-hljs)').forEach(code => {
    const source = code.textContent, pre = code.parentElement;
    if (pre.classList.contains('no-hljs')) return;
    const language = [...code.classList].find(name => name.startsWith('language-'))?.slice(9);
    if (language && !hljs.getLanguage(language)) { code.classList.remove(`language-${language}`); code.classList.add('language-plaintext'); }
    hljs.highlightElement(code);
    pre.classList.add('hljs-codeblock'); code.setAttribute('hljs-codeblock-inner', '');
    const options = config.code_highlight;
    for (const [key, name] of [['hide_linenumber', 'hljs-hide-linenumber'], ['transparent_linenumber', 'hljs-transparent-linenumber'], ['break_line', 'hljs-break-line']]) pre.classList.toggle(name, !!options[key]);
    // Independent gutter preserves highlighted spans, selectable text, and exact copy source.
    const gutter = document.createElement('span'); gutter.className = 'argon-code-gutter'; gutter.setAttribute('aria-hidden', 'true');
    gutter.textContent = source.replace(/\n$/, '').split('\n').map((_, index) => index + 1).join('\n'); pre.append(gutter);
    const controls = document.createElement('div'); controls.className = 'hljs-control hljs hljs-title';
    for (const [label, className, iconName, action] of [
      [__('显示行号'), 'hljs-control-toggle-linenumber', 'list-ol', () => pre.classList.toggle('hljs-hide-linenumber')],
      [__('开启折行'), 'hljs-control-toggle-break-line', 'align-left', () => pre.classList.toggle('hljs-break-line')],
      [__('复制'), 'hljs-control-copy', 'copy', () => copyText(source)],
      [__('全屏'), 'hljs-control-fullscreen', 'expand', async () => {
        try { if (document.fullscreenElement) await document.exitFullscreen(); else await pre.requestFullscreen(); } catch { pre.classList.toggle('hljs-codeblock-fullscreen'); }
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
