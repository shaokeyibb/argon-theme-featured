global.$ = global.jQuery = window.$ = window.jQuery = $ = require('jquery');
window['$'] = $;

require('./argon-design-system/js/argon.js');

require('./libs/jquery-pjax-plus/jquery.pjax.plus.js');
require('jquery.easing/jquery.easing.js');

window['$'].fn.headIndex = require("./libs/headindex/headindex.js").default;

require('./js/main.js');

import './argon-design-system/css/argon.css';
// Font Awesome - 使用优化后的版本，包含 font-display: swap
import './styles/font-awesome-optimize.css';

require('./style.scss');

export default {};