import { $$, config } from './dom.js';

let library;
export async function spaceContent(root = document) {
  const options = String(config.pangu || '').split('|');
  const selectors = [['article', '.post-content'], ['comment', '.comment-item-text'], ['shuoshuo', '.shuoshuo-content']]
    .filter(([option]) => options.includes(option)).map(([, selector]) => selector);
  if (!selectors.length) return;
  const elements = $$(selectors.join(','), root);
  if (!elements.length) return;
  try {
    // Explicit browser export: the package's default export is a Node text API.
    library ??= import('pangu/browser');
    const { pangu } = await library;
    elements.forEach(element => pangu.spaceNode(element));
  } catch { /* The article remains readable when an optional chunk cannot load. */ }
}
