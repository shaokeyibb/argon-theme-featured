import '../styles/theme-foundation.scss';
import '../style.scss';
import './modern.css';
import '../styles/design.css';
import { initUI } from './ui.js';
import { initComments, enhanceComments } from './comments.js';
import { enhanceContent } from './content.js';

function start() {
  initUI();
  initComments();
  enhanceComments();
  enhanceContent();
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
else start();
